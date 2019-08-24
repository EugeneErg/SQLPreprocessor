<?php namespace EugeneErg\SQLPreprocessor;

class SQL
{
    use SequenceTrait;

    const UPDATE_TYPE = 'update';
    const INSERT_TYPE = 'insert';
    const DELETE_TYPE = 'delete';
    const SELECT_TYPE = 'select';

    private $hash;
    private $topology;

    protected $structure = [
        'if' => ['type' => Topology::SEQUENCE_TYPE, 'next' => ['elseif', 'else']],
        'from' => Topology::PARENT_TYPE,
        'delete' => Topology::PARENT_TYPE,
        'orderby' => Topology::PARENT_TYPE,
        'groupby' => Topology::PARENT_TYPE,
        'var' => Topology::PARENT_TYPE,
        'select' => Topology::PARENT_TYPE,
        'insert',
        'update',
        'switch' => ['case', 'default'],
    ];
    protected $otherSequenceName = 'return';

    public function __clone()
    {

    }

    public function __wakeup()
    {

    }

    public static function __callStatic($name, $args)
    {
        $sql = new self();
        return $sql->__call($name, $args);
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

    private function getQuestionType()
    {
        static $root;
        $result = null;
        if (!$root) {
            $default = new Structure(function(Structure $default) {
                $default->getVariant(
                    ['if' => 1, 'switch' => 0, 'return' => 0],
                    ['if' => 0, 'switch' => 1, 'return' => 0],
                    ['if' => 0, 'switch' => 0, 'return' => 1]
                );
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
                'return' => true
            ]);
            $from = new Structure();
            $from->addChildren([
                'from' => $from,
                'switch' => $switch,
                'orderby' => $default,
                'groupby' => $default,
                'return' => true,
                'var' => $default,
            ]);
            $delete = new Structure();
            $delete->addChildren([
                'from' => $from,
                'delete' => $delete,
                'switch' => $switch,
                'orderby' => $default,
                'groupby' => $default,
                'return' => true,
                'var' => $default,
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
             *
             * update($table, [
             *      'field1' => $table->field1,
             *      'field2' => $table->field2
             * ])
             * ->update($table [
             *      'field1' => $table->field1,
             *      'field2' => $table->field2
             * ])
             *
             *
             *
             * */

            $select = new Structure(function(Structure $select) {
                $select->getVariant(
                    ['return' => 1, 'select' => 0, 'if' => 0, 'switch' => 0],
                    ['return' => 0, 'select',      'if' => 0, 'switch' => 0],
                    ['return' => 0, 'select' => 0, 'if' => 1, 'switch' => 0],
                    ['return' => 0, 'select' => 0, 'if' => 0, 'switch' => 1]
                );
                return true;
            });
            $select->addChildren([
                'select' => $select,
                'if' => $if,
                'switch' => $switch,
                'return' => true,
            ]);
            $root = new Structure(function(Structure $root) use(&$result) {
                $type = $root->getVariant(
                    ['update',      'from' => 1,      'insert' => 0, 'delete' => 0, 'select' => 0],
                    ['insert' => 1, 'from' => 1,      'update' => 0, 'delete' => 0, 'select' => 0],
                    ['delete' => 1, 'from' => 0,      'insert' => 0, 'update' => 0, 'select' => 0],
                    ['select' => 1, 'from' => [0, 1], 'insert' => 0, 'delete' => 0, 'update' => 0]
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
                'insert' => true,
                'update' => true,
                'delete' => $delete,
                'select' => $select,
            ]);
        };
        $root($this->getStructure());
        return $result;
    }

    public function __invoke(Translater $sqlClass, \Closure $function = null)
    {
        $topology = $this->getTopology();
        $structure = $topology->getStructure($this->structure);



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
        return $this->createResult($function($request), $select);
    }



    /**
     * @param string $context
     * @param array $structure
     * @return object[][]
     */
    private function explode($context, array $structure)
    {
        $result = [];
        $value = [];
        foreach ($structure as $item) {
            if ($item->type !== self::CONTEXT_TYPE
                || $item->value !== $context
            ) {
                $value[] = $item;
            }
            else {
                $result[] = $value;
                $value = [];
            }
        }
        if (count($value)) {
            $result[] = $value;
        }
        return $result;
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

    /**
     * @param array $structure
     * @return object[]
     * @throws \Exception
     */
    private function getSelectBlock(array $structure)
    {
        /*
         * string, context, sql_var, sql_method, number, variable, field, method
         * con
         * */
        // селект это набор аргументов, переданных через запятую
        /*
         * SELECT
         * [//инкрементный ключ, а значит правила для каждой строки
             * field_1,//название поля - ключ, значение пол - значение
             * field2: field4, //значение одного поля - ключ, значение второго - значение
             * "string": field5,//строка - ключ, значение поля - значение
             * 'string' field_6,//ключ значение можно без двоеточия
             * `string` field7,//в данном случае ключ-строка string
             * field3 [//ключ - значение поля, а значение - массив с теми же правилами
             *   value1, `q`: value2, `q2` value3
             * ],
             * [//инкрементный ключ у массива можно без двоеточия
             *   value1, `q`: value2, `q2` value3
             * ],
         *   * : field8//инкрементный ключ, значение поля 8
         *]
         *
         * что делать с этим
         * [
         *  (q ? 1 : 0): field1
         *]
         * */
        /*
         * 1) разделяем на части, запятыми
         *    2.1) если часть - массив, то ключ инкремент, а значение - массив, анализируем
         *    2.2) если в части есть двоеточие, всё, что после двоеточия - значение, если есть до - ключ, иначе инкремент
         *    2.3) если нет двоеточия
         *       2.3.1) если после(круглых скобок, метода, поля, переменной, квадратных скобок, строки) нет (метода, котекста, квадратных скобок)
         *          то считаем, между ними разделитель и левая часть - ключ, правая - значение
         *
         * */
        $structure = $this->explode(',', $structure);
        $result = [];
        foreach ($structure as $value) {
            $keyValue = $this->explode(':', $value);
            switch (count($keyValue)) {
                case 0:
                    break;
                case 1:
                    if (count($keyValue[0])) {
                        if ($keyValue[0][0]->type === self::RECTANGULAR_TYPE) {
                            if (count($keyValue[0]) > 1) {
                                throw new \Exception('');
                            }
                            $value = $this->getSelectBlock($keyValue[1]);
                            $key = null;
                        }
                        else {
                            $value = $keyValue[0];
                            $key = [
                                (object) [
                                    'type' => self::STRING_TYPE,
                                    'value' => $this->getStringKey($value)
                                ]
                            ];
                        }
                        $result[] = (object) [
                            'key' => $key,
                            'value' => $value
                        ];
                    }
                    break;
                case 2:
                    if (!count($keyValue[1])) {
                        $value = null;
                    }
                    elseif ($keyValue[1][0]->type === self::RECTANGULAR_TYPE) {
                        if (count($keyValue[1]) > 1) {
                            throw new \Exception('');
                        }
                        $value = $this->getSelectBlock($keyValue[1]);
                    }
                    else {
                        $value = $keyValue[1];
                    }
                    if ($value !== null || count($keyValue[0])) {
                        $result[] = (object) [
                            'key' => count($keyValue[0]) ? $keyValue[0] : null,
                            'value' => $value
                        ];
                    }
                    break;
                default:
                    throw new \Exception('');
            }
        }
        return $result;
    }

    /**
     * @param string $type
     * @param array $structure
     *
     * @return object[]
     */
    private function getBlock($type, array $structure)
    {
        return call_user_func([$this, 'get' . ucfirst($type) . 'Block'], $structure);
    }

    /**
     * @param array $structure
     * @param int $pos
     * @return object|null
     * @throws \Exception
     */
    private function getNextBlock(array $structure, &$pos = 0)
    {
        if (!isset($structure[$pos])) {
            return null;
        }
        $first = $structure[$pos];
        if ($first->type !== self::WORD_TYPE) {
            throw new \Exception('');
        }
        $types = self::getPartsBlockTypes();
        if (!isset($types[$first->value])) {
            throw new \Exception('');
        }
        $type = $first->value . implode('', $types[$first->value]);
        $pos++;
        foreach ($types[$first->value] as $num => $part) {
            if (!isset($structure[$pos])) {
                return null;
            }
            if ($structure[$pos]->type !== self::WORD_TYPE
                || $structure[$pos]->value !== $part
            ) {
                break;
            }
            $pos++;
        }
        $subStructure = [];
        for (; $pos < count($structure); $pos++) {
            if ($structure[$pos]->type === self::WORD_TYPE
                && isset($types[$structure[$pos]->value])
            ) {
                return (object)[
                    'type' => $type,
                    'value' => $this->getBlock($type, $subStructure)
                ];
            }
            $subStructure[] = $structure[$pos];
        }
        return (object)[
            'type' => $type,
            'value' => $this->getBlock($type, $subStructure)
        ];
    }

    private static function getPartsBlockTypes()
    {
        static $result;
        if (!$result) {
            $blockTypes = [
                'from', 'join', 'left join', 'right join', 'correlate',
                'select', 'update', 'delete', 'insert',
                'where', 'having', 'on',
                'order by', 'group by',
            ];
            $result = [];
            foreach ($blockTypes as $type) {
                $parts = explode(' ', $type);
                $key = array_shift($parts);
                $partsBlockTypes[$key] = $parts;
            }
        }
        return $result;
    }

    /**
     * @param array $structure
     * @return object[]
     * @throws \Exception
     */
    private function analyzeQuery(array $structure)
    {
        $blocks = [];
        while ($block = $this->getNextBlock($structure, $pos)) {
            $blocks[] = $block;
        }
        return $blocks;
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

}