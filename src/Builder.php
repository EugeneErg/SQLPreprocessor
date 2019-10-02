<?php namespace EugeneErg\SQLPreprocessor;

use EugeneErg\SQLPreprocessor\Parsers\ParserAbstract;

/**
 * Class Builder
 * @package EugeneErg\SQLPreprocessor
 *
 * @property $this $else
 * @property $this $endif
 * @property $this $groupby
 * @property $this $endgroupby
 * @property $this $endorderby
 * @property $this $default
 * @property $this $endswitch
 * @property $this $endvar
 * @property $this $endfrom
 * @property $this $enddelete
 *
 * @method $this if(mixed $argument)
 * @method $this elseif(mixed $argument)
 * @method $this from(mixed ...$arguments)
 * @method $this delete(mixed ...$arguments)
 * @method $this orderby(mixed $argument)
 * @method $this select(mixed $argument)
 * @method $this insert(mixed $argument)
 * @method $this update(mixed $argument)
 * @method $this switch(mixed $argument)
 * @method $this case(mixed $argument)
 * @method $this var(mixed $argument)
 */
class Builder
{
    use SequenceTrait;

    const UPDATE_TYPE = 'update';
    const INSERT_TYPE = 'insert';
    const DELETE_TYPE = 'delete';
    const SELECT_TYPE = 'select';

    protected $structure = [
        'if' => ['type' => Topology::SEQUENCE_TYPE, 'next' => ['elseif', 'else']],
        'from' => Topology::PARENT_TYPE,
        'orderby' => Topology::PARENT_TYPE,
        'groupby' => Topology::PARENT_TYPE,
        'var' => Topology::PARENT_TYPE,
        'select' => Topology::PARENT_TYPE,
        'delete',
        'break',
        'table',
        'insert' => Topology::PARENT_TYPE,
        'update' => Topology::PARENT_TYPE,
        'switch' => ['case', 'default'],
    ];

    private function getStructureBlock($value)
    {
        $objectFromHash = Hasher::getObject($value);

        if ($objectFromHash instanceof Variable) {
            return new Link('return', [$objectFromHash], true);
        }
        if ($objectFromHash instanceof Raw) {
            return $objectFromHash;
        }

        return new Link('return', [$value], true);
    }

    /**
     * @param string $name
     * @param array $args
     * @return $this
     * @throws \Exception
     */
    public static function __callStatic($name, array $args)
    {
        return (new self())->__call($name, $args);
    }

    /**
     * @param string $query
     * @param ParserAbstract|null|string $parser
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

    public function function_Name()
    {
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


    }

    /**
     * @return null
     * @throws \Exception
     */
    private function getQuestionType()
    {
        static $root;
        $result = null;
        if (!$root) {
            $default = new Structure(function(Structure $default) {
                return true;
            });
            $if = new Structure(function(Structure $if) {
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

            $switch = new Structure(function(Structure $switch) {
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
                'return' => true,
                'break' => true
            ]);
            $from = new Structure();
            $from->addChildren([
                'from' => $from,
                'table' => true,
                'switch' => $switch,
                'orderby' => $default,
                'groupby' => $default,
                'return' => true,
                'var' => $default,
                'select' => $default,
            ]);

            /*
             * from()->{
             *
             * }
             * ->insert(`table`, [
             *      'field1' => $table->field1,
             *      'field2' => $table->field2
             * ])
             *
             * from('left', $table, 'distinct', new Raw("$table1->id = $tabke2->id"), [
             *      sql::from()
             * ])
             *
             *
             * update->
             *     if ()->{"
             *          $var->field = 1,
             *          $var->field2 = 3
             *     "}->else->{
             *
             *      }
             *     $table => [
             *          'field1' => $table->field1,
             *          'field2' => $table->field2
             *     ],
             *     $table => [
             *          'field1' => $table->field1,
             *          'field2' => $table->field2
             *     ]
             * ])
             *
             * UPDATE `table`
             * SET `field1`=1, `field2`=2...
             *
             *
             * select([
             *     $table->field,
             *     'alias' => $table->field,
             *     'string key' => Raw,
             *     Raw => 'string value'
             * ])
             *
             *
             * */

            $root = new Structure(function(Structure $root) use(&$result) {
                $type = $root->getVariant(
                    ['update', 'from' => 1,      'insert' => 0, 'delete' => 0, 'select' => 0],
                    ['insert', 'from' => 1,      'update' => 0, 'delete' => 0, 'select' => 0],
                    ['delete', 'from' => 0,      'insert' => 0, 'update' => 0, 'select' => 0],
                    ['select', 'from' => [0, 1], 'insert' => 0, 'delete' => 0, 'update' => 0]
                );
                $result = [
                    self::UPDATE_TYPE,
                    self::INSERT_TYPE,
                    self::DELETE_TYPE,
                    self::SELECT_TYPE,
                ][$type];
                return true;
            }, [
                'from' => $from,
                'insert' => $default,
                'update' => $default,
                'delete' => true,
                'select' => $default,
            ]);
        };
        $root($this->getStructure());
        return $result;
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
            $structure = $structure(ParserAbstract::TYPE_QUERY);
        }
        else {
            $rootAsRaw = false;
        }

        var_dump($structure);die;

        $questionType = $this->getQuestionType($structure);

        var_dump($structure);






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