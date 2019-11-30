<?php namespace EugeneErg\SQLPreprocessor;

use EugeneErg\SQLPreprocessor\Parsers\ParserAbstract;
use EugeneErg\SQLPreprocessor\Record\AbstractRecord;
use EugeneErg\SQLPreprocessor\Record\Container;
use EugeneErg\SQLPreprocessor\Record\Query;

/**
 * Class Builder
 * @package EugeneErg\SQLPreprocessor
 *
 * @property $this $else
 * @property $this $endif
 * @property $this $groupBy
 * @property $this $endGroupBy
 * @property $this $orderBy
 * @property $this $endOrderBy
 * @property $this $endswitch
 * @property $this $endQuery
 *
 * @method $this if(mixed $argument)
 * @method $this elseif(mixed $argument)
 * @method $this from(Container $container, int $flags = 0, int $limit = null, int $offset = 0)
 * @method $this query(Container $container, int $flags = 0, int $limit = null, int $offset = 0)
 *
 * @method $this switch(mixed $argument)
 * @method $this case(mixed $argument)
 */
class Builder
{
    /**
     * self::delete
     * ->if (table1->old)-
     *      ->return(table1)
     * ->endif
     * from 1
     *
     * self::deleteRaw("
     *      IF (old)
     *          table1
     *      endif
     *      from
     *
     * ");
     *
     */

    use SequenceTrait;

    const UPDATE_TYPE = 'update';
    const INSERT_TYPE = 'insert';
    const DELETE_TYPE = 'delete';
    const SELECT_TYPE = 'select';

    const JOIN_TYPE_INNER = 0;
    const JOIN_TYPE_LEFT = 1;
    const JOIN_TYPE_RIGHT = 2;
    const JOIN_TYPE_OUTER = 3;
    const JOIN_TYPE_CORRELATE = 4;
    const QUERY_FLAG_UNION = 12;
    const QUERY_FLAG_DISTINCT = 16;

    const QUERY_FLAGS = [
        'from' => self::JOIN_TYPE_INNER,
        'join' => self::JOIN_TYPE_INNER,
        'leftjoin' => self::JOIN_TYPE_LEFT,
        'rightjoin' => self::JOIN_TYPE_RIGHT,
        'innerjoin' => self::JOIN_TYPE_INNER,
        'crossjoin' => self::JOIN_TYPE_INNER,
        'outerjoin' => self::JOIN_TYPE_OUTER,
        'left' => self::JOIN_TYPE_LEFT,
        'right' => self::JOIN_TYPE_RIGHT,
        'inner' => self::JOIN_TYPE_INNER,
        'cross' => self::JOIN_TYPE_INNER,
        'outer' => self::JOIN_TYPE_OUTER,
        'correlate' => self::JOIN_TYPE_CORRELATE,
        'union' => self::QUERY_FLAG_UNION,
        'distinct' => self::QUERY_FLAG_DISTINCT,
    ];

    /**
     * @var string
     */
    private $type = self::SELECT_TYPE;

    protected $structure = [
        'if' => ['type' => Topology::SEQUENCE_TYPE, 'next' => ['elseif', 'else']],
        'query' => Topology::PARENT_TYPE,
        'orderby' => Topology::PARENT_TYPE,
        'groupby' => Topology::PARENT_TYPE,
        'from',
        'set',
        'where',
        'orwhere',
        'switch' => ['case', 'default'],
    ];

    /**
     * Builder constructor
     * .
     * @param string $type
     */
    private function __construct($type = self::SELECT_TYPE)
    {
        $this->type = $type;
    }

    private function getStructureBlock($value)
    {
        $objectFromHash = Hasher::getObject($value);

        if ($objectFromHash instanceof Variable) {
            return new Link('set', [$objectFromHash], true);
        }
        if ($objectFromHash instanceof Raw) {
            return $objectFromHash;
        }

        return new Link('set', [$value], true);
    }

    /**
     * @param string $query
     * @param ParserAbstract|null|string $parser
     *
     * @return Builder
     */
    public static function raw($query, $parser = null)
    {
        $new = new self();

        if (is_null($parser) || $parser instanceof ParserAbstract) {
            $new->sequence = new Raw($query, $parser);
        }
        else {
            $new->sequence = call_user_func_array([Raw::class, $parser], [$query]);
        }

        return $new;
    }

    /**
     * @param $query
     * @param null $parser
     *
     * @return Builder
     */
    public static function deleteRaw($query, $parser = null)
    {
        $result = self::raw($query, $parser);
        $result->type = self::DELETE_TYPE;

        return $result;
    }

    /**
     * @param $query
     * @param null $parser
     *
     * @return Builder
     */
    public static function selectRaw($query, $parser = null)
    {
        return self::raw($query, $parser);
    }

    /**
     * @param $query
     * @param null $parser
     *
     * @return Builder
     */
    public static function updateRaw($query, $parser = null)
    {
        $result = self::raw($query, $parser);
        $result->type = self::UPDATE_TYPE;

        return $result;
    }

    /**
     * @param $query
     * @param null $parser
     *
     * @return Builder
     */
    public static function insertRaw($query, $parser = null)
    {
        $result = self::raw($query, $parser);
        $result->type = self::INSERT_TYPE;

        return $result;
    }

    /**
     * @return Builder
     */
    public static function insert()
    {
        return new self(self::INSERT_TYPE);
    }

    /**
     * @return Builder
     */
    public static function update()
    {
        return new self(self::UPDATE_TYPE);
    }

    /**
     * @return Builder
     */
    public static function delete()
    {
        return new self(self::DELETE_TYPE);
    }

    /**
     * @return Builder
     */
    public static function select()
    {
        return new self();
    }

    /*
     * типы блоков
     * 1) слово (не содержит дочерних блоков)
     * 2) включающая последовательность (
     *        текущий блок становится дочерним к себе,
     *        там же дочерними становятся блоки, не соотвествующие шаблону,
     *        а соотвествующие становятся соседними
     *        пока последовательность не завершится
     *    )
     * 4) родитель (все следующие блоки становятся дочерними)
     *
     * все блоки кроме (1) и кроме дочерних последовательностей могут завершаться ключевыми блоками {name}end
     * все блоки кроме (1) могут включать дочерние через фигурные скобки
     *
     */



    /**
     * @param array $structure
     *
     * @throws \Exception
     */
    private function validateStructure(array $structure)
    {
        static $query;

        if (!$query) {
            $default = new Structure(function (Structure $default) {
                return true;
            });
            $if = new Structure(function (Structure $if) {
                $if->getVariant(
                    ['elseif', 'else' => [0, 1]],
                    ['elseif' => 0, 'else' => 1]
                );
                return $if->topology('elseif', 'else');
            }, [
                'if' => $default,
                'elseif' => $default,
                'else' => $default
            ]);
            $switch = new Structure(function (Structure $switch) {
                $switch->getVariant(
                    ['case', 'default' => [0, 1]]
                );
                return $switch->topology('case', 'default');
            }, [
                'switch' => true,
                'case' => $default,
                'default' => $default,
            ]);
            $default->addChildren([
                'if' => $if,
                'switch' => $switch,
                'set' => true
            ]);
            $query = new Structure();
            $query->addChildren([
                'query' => $query,
                'from' => true,
                'orderby' => $default,
                'groupby' => $default,
                'if' => $if,
                'switch' => $switch,
                'set' => true,
                'where' => true,//...raw|container|builder
                'orwhere' => true,//...raw|container|builder
            ]);
        }

        $query($structure);
    }

    /**
     * @param array $arguments
     *
     * @throws \Exception
     *
     * @return object
     */
    private function getFromStructure(array $arguments)
    {
        if (!isset($arguments[0])) {
            throw new \Exception('incorrect type argument 1');
        }

        $container = $arguments[0];

        if (count(AbstractRecord::getRecord($container)->getSequence())) {
            throw new \Exception('invalid record field');
        }
        if (!isset($arguments[1])) {
            $type = 0;
        }
        elseif (!is_numeric($arguments[1])) {
            $type = $arguments[1];
        }
        else {
            $stringType = strtolower($arguments[1]);
            $flags = self::QUERY_FLAGS;

            if (isset($flags[$stringType])) {
                $type = $flags[$stringType];
            }
            else {
                $type = 0;
            }
        }
        if (isset($arguments[2])) {
            $limit = $arguments[2];
            $offset = isset($arguments[3]) ? $arguments[3] : 0;
        }
        else {
            $limit = null;
            $offset = 0;
        }

        return (object) [
            'container' => $container,
            'type' => $type,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * @param array $arguments
     * @param array $children
     * @param bool $rootAsRaw
     * @param string $type
     *
     * @return object
     *
     * @throws \Exception
     */
    private function getQueryStructure(array $arguments, array $children, $rootAsRaw = false, $type = self::SELECT_TYPE)
    {
        $result = $this->getFromStructure($arguments);
        $record = AbstractRecord::getRecord($arguments[0]);
        $conditions = [];

        foreach ($children as $child) {
            $name = $child->getName();

            if (in_array($name, ['if', 'switch', 'set'])) {
                $conditions[] = $child;
                continue;
            }

            $method = 'get' . ucfirst($name) . 'Structure';
            $record->add($name,
                $this->{$method}($child->getArguments(), $child->getChildren(), $rootAsRaw)
            );
        }

        $record->add('condition',
            $this->getConditionsStructure($conditions, $result->container, $rootAsRaw, $type)
        );

        return $result;
    }

    private function getWhereStructure(
        array $structure, Container $container, $rootAsRaw = false, $type = self::SELECT_TYPE
    ) {

    }

    /**
     * @param Link[] $structure
     * @param array $switches
     *
     * @return array
     */
    private function structureToList(array $structure, array $switches = [])
    {
        $result = [];

        foreach ($structure as $pos => $link) {
            $name = $link->getName(Link::TYPE_LOWER);

            if ($name === 'return') {
                $arguments = $link->getArguments();
                $result[] = (object) [
                    'type' => $name,
                    'field' => array_shift($arguments),
                    'value' => $arguments,
                ];
            }
            elseif ($name === 'break') {
                //use switches
                $arguments = $link->getArguments();
                $level = count($arguments) ? max(1, (int) $arguments[0]) : 1;

                
            }
            else {
                $subSwitches = $switches;

                if ($name === 'switch') {
                    $subSwitches[] = array_slice($structure, $pos + 1);
                }

                $children = $this->structureToList(
                    $link->getChildren(), $subSwitches
                );

                foreach ($children as $child) {

                }
            }
        }

        return $result;
    }

    /**
     * @param Link[] $structure
     * @param Container $container
     * @param bool $rootAsRaw
     * @param string $type
     *
     * @return object
     */
    private function getConditionsStructure(
        array $structure, Container $container, $rootAsRaw = false, $type = self::SELECT_TYPE
    ) {
        /**
         * select - word, string, field, record
         *
         * update, insert - field, record
         *
         * delete has't set conditions
         *
         */
        LogicStructureConverter::toList(
            $structure,
            function(Link $link) use($rootAsRaw, $container, $type) {
                $arguments = $link->getArguments();

                if (!count($arguments)) {
                    throw new \Exception('invalid setter');
                }

                $field = array_shift($arguments);

                if ($rootAsRaw) {

                }
                else {

                }

                return [$fieldName, new Link('set', $arguments)];
            },
            $type === 'update' ?
                function($fieldName) use($container) {
                    return $container->$fieldName;
                } : null
        );
    }

    /**
     * @param array $arguments
     * @param array $children
     * @param bool $rootAsRaw
     */
    private function getSelectStructure(array $arguments, array $children, $rootAsRaw = false)
    {

    }

    /**
     * @param array $arguments
     * @param array $children
     * @param bool $rootAsRaw
     */
    private function getUpdateStructure(array $arguments, array $children, $rootAsRaw = false)
    {

    }

    /**
     * @param array $arguments
     * @param array $children
     * @param bool $rootAsRaw
     */
    private function getInsertStructure(array $arguments, array $children, $rootAsRaw = false)
    {

    }

    /**
     * @param array $arguments
     * @param array $children
     * @param bool $rootAsRaw
     */
    private function getDeleteStructure(array $arguments, array $children, $rootAsRaw = false)
    {

    }

    /**
     * @param array $arguments
     * @param array $children
     * @param bool $rootAsRaw
     */
    private function getVarStructure(array $arguments, array $children, $rootAsRaw = false)
    {

    }

    /**
     * @param array $arguments
     * @param array $children
     * @param bool $rootAsRaw
     */
    private function getOrderByStructure(array $arguments, array $children, $rootAsRaw = false)
    {

    }

    /**
     * @param array $arguments
     * @param array $children
     * @param bool $rootAsRaw
     */
    private function getGroupByStructure(array $arguments, array $children, $rootAsRaw = false)
    {

    }

    /**
     * @param Translater $sqlClass
     * @param \Closure|null $function
     *
     * @throws \Exception
     */
    public function __invoke(Translater $sqlClass = null, \Closure $function = null)
    {
        $structure = $this->getStructure();

        if ($structure instanceof \Closure) {
            $rootAsRaw = true;
            $queryOption = $structure(ParserAbstract::TYPE_QUERY);
            $structure = $queryOption->topology;
        }
        else {
            $rootAsRaw = false;
            $queryOption = (object) [
                'limit' => [],
                'distinct' => false,
            ];
        }

        $this->validateStructure($structure);
        $result = $this->getQueryStructure([
                Query::create(),
                $queryOption->distinct ? self::QUERY_FLAG_DISTINCT : 0
            ], $structure, $rootAsRaw, $this->type
        );
        var_dump($this->type);die;






        /*



        $structure = new \StdClass();
        $structure->childs = Self::structure()->validation($this->functions, $levels);
        $structure->union = [];
        $type = reset($levels);
        $query = $this->getQueryTree($structure, ['from', 'delete', 'var']);//содержит основной запрос
        $select = $structure->select = new \StdClass();//формирует структуру результата
        $structure->select->childs = [];
        $this->getFields($structure, $query);
        $this->parseTreeFunctions($structure, ['orderby', 'groupby', 'insert', 'into', 'select', 'var', 'from', 'delete'], $query);
        unset($structure);
        $query->analyze();
        $request = $sqlClass->translate($query);
        if (is_null($function)) {
            return $request;
        }
        return $this->createResult($function($request), $select);*/
    }

    /**
     * @param array $structure
     * @return string
     */
    private function getStringKey(array $structure)
    {
        $result = '';
        foreach ($structure as $item) {
            switch ($item->type) {
                case self::STRING_TYPE:
                case self::NUMBER_TYPE:
                case self::WORD_TYPE:
                    $result .= " $item->value";
                    break;
                case self::PARENTHESIS_TYPE:
                    $result .= "({$this->getStringKey($item->value)})";
                    break;
                case self::RECTANGULAR_TYPE:
                    $result .= "[{$this->getStringKey($item->value)}]";
                    break;
                case self::CONTEXT_TYPE:
                case self::METHOD_TYPE:
                    $result .= $item->value;
                    break;
                case self::SQLVAR_TYPE:
                    $result .= "@$item->value";
                    break;
                case self::FIELD_TYPE:
                    if ($item->value[0] === '.') {
                        $result .= '.' . str_replace('``', '`', substr($item->value, 2, -1));
                    }
                    else {
                        $result .= ' ' . str_replace('``', '`', $item->value);
                    }
                    break;
                case self::VARIABLE_TYPE:
                    // TODO
            }
        }
        return $result;
    }

    private function getFromBlock(array $structure)
    {

    }

    private function getCorrelateBlock(array $structure)
    {
        return $this->getFromBlock($structure);
    }

    private function getRightjoinBlock(array $structure)
    {
        return $this->getFromBlock($structure);
    }

    private function getLeftjoinBlock(array $structure)
    {
        return $this->getFromBlock($structure);
    }

    private function getJoinBlock(array $structure)
    {
        return $this->getFromBlock($structure);
    }

    private function analyzeSubQuery(array $structure)
    {

    }

    private function analyzeArgument(array $structure)
    {
        /*
         * наличие:
         *  логические операторы
         *  алгебраические операторы
         *  методы и слова(методы sql)
         *  переменные и переменные sql
         *
         *
         *
         *
         *
         * */
    }

    /**
     * @param Raw $raw
     * @param Link $parent
     *
     * @return Link[]
     * @throws \Exception
     */
    private function chainToArray(Raw $raw, Link $parent)
    {
        $name = $parent->getName();

        if (!is_string($name)) {
            throw new \Exception('invalid link name');
        }

        $name = strtolower($name);
        $types = [
            'from' => ParserAbstract::TYPE_FROM,
            'orderby' => ParserAbstract::TYPE_ORDER_BY,
            'groupby' => ParserAbstract::TYPE_GROUP_BY,
            'select' => ParserAbstract::TYPE_SELECT,
            'insert' => ParserAbstract::TYPE_INSERT,
            'update' => ParserAbstract::TYPE_UPDATE,
            //'var' =>
        ];

        if (!isset($types[$name])) {
            throw new \Exception('invalid link name');
        }

        return $raw->parse($types[$name]);
    }
}