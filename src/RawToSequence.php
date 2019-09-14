<?php namespace EugeneErg\SQLPreprocessor;

/**
 * Class RawToSequence
 * @package EugeneErg\SQLPreprocessor
 */
class RawToSequence
{
    const CONTEXT_QUERY = 'query';
    const CONTEXT_DELETE = 'delete';
    const CONTEXT_ARGUMENT = 'argument';
    const CONTEXT_SELECT = 'select';
    const CONTEXT_UPDATE = 'update';
    const CONTEXT_INSERT = 'insert';

    /**
     * @return array
     */
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
     * @param string $type
     * @param array $structure
     *
     * @return object[]
     */
    private static function getBlock($type, array $structure)
    {
        $result = call_user_func([new self($structure), 'get' . ucfirst($type) . 'Block']);
        switch ($type) {
            case self::CONTEXT_SELECT:
            case self::CONTEXT_DELETE:
                return new Link($type, $result, true);
            default:
                return $result;
        }
    }

    /**
     * @param array $structure
     * @param int $pos
     * @return object|null
     * @throws \Exception
     */
    private static function getNextBlock(array $structure, &$pos = 0)
    {
        if (!isset($structure[$pos])) {
            return null;
        }
        $first = $structure[$pos];
        if ($first->type !== Raw::WORD_TYPE) {
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
            if ($structure[$pos]->type !== Raw::WORD_TYPE
                || $structure[$pos]->value !== $part
            ) {
                break;
            }
            $pos++;
        }
        for ($subStructure = []; $pos < count($structure); $pos++) {
            if ($structure[$pos]->type === Raw::WORD_TYPE
                && isset($types[$structure[$pos]->value])
            ) {
                return (object)[
                    'type' => $type,
                    'value' => self::getBlock($type, $subStructure)
                ];
            }
            $subStructure[] = $structure[$pos];
        }
        return (object)[
            'type' => $type,
            'value' => self::getBlock($type, $subStructure)
        ];
    }

    /**
     * @param string $context
     * @param array $structure
     * @param int $maxCount
     * @return object[][]
     */
    private static function explode($context, array $structure, $maxCount = 0)
    {
        $result = [];
        $value = [];
        foreach ($structure as $item) {
            if ($item->type !== Raw::CONTEXT_TYPE
                || $item->value !== $context
                || count($result) === $maxCount - 1
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
     * @return array
     * @throws \Exception
     */
    public static function getQueryBlock(array $structure)
    {
        $blocks = [];
        while ($block = self::getNextBlock($structure, $pos)) {
            $blocks[] = $block;
        }
        return $blocks;
    }

    /**
     * @return Link[]
     */
    public static function getDeleteBlock(array $structure)
    {
        $structures = self::explode(',', $structure);
        $result = [];
        foreach ($structures as $structure) {
            if (count($structure) === 0) {
                continue;
            }
            if (count($structure) > 1) {
                throw new \Exception('invalid structure');
            }
            $block = reset($structure);
            if ($block->type !== Raw::VARIABLE_TYPE
                && ($block->type !== Raw::FIELD_TYPE || $block->value[0] === '.')
            ) {
                throw new \Exception('invalid structure');
            }
            $result[] = $block->type !== Raw::VARIABLE_TYPE
                ? $block->value
                : str_replace('``', '`', substr($block->value, 1, -1));
        }
        return $result;
    }

    /**
     * @return Link[]
     */
    private static function getArgumentBlock()
    {

    }

    private static function getArrayBlock($structure)
    {
        $result = [];
        $structures = self::explode(',', $structure);
        foreach ($structures as $structure) {
            $keys = self::explode(':', $structure, 2);
            switch (count($keys)) {
                case 0:
                    break;
                case 1:
                    $result[] = self::getAsArrayOrString($keys[0]);
                default:
                    $result[self::blockToRaw($keys[0])] = self::getAsArrayOrString($keys[1]);
            }
        }
        return $result;
    }

    private static function getAsArrayOrString($structure)
    {
        if (count($structure) === 1 && $structure[0]->type === Raw::RECTANGULAR_TYPE) {
            return self::getArrayBlock($structure[0]->value);
        }
        return self::blockToRaw($structure);
    }

    /**
     * @return object[]
     * @throws \Exception
     */
    private static function getSelectBlock(array $structure)
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
         *
         *
         * return new Chain('select', [SELECT_ARRAY], true)
         *
         * */


        /**
         * Поле,
         * Поле `Альяс`,
         * Выражение,
         *
         * => [
         * 'Поле' => Значение поля
         * 'Альяс' => Значение поля
         * 'Выражение' => Значение выражения
         * ]
         *
         *
         */

        $structures = self::explode(',', $structure);
        $result = [];
        foreach ($structures as $structure) {
            $strings = self::structureToStrings($structure);
            switch (count($strings)) {
                case 1:
                    $result[] = self::getAsArrayOrString($strings[0]);
                    break;
                case 2:
                    $result[self::blockToRaw($strings[1])] = self::getAsArrayOrString($strings[0]);
                    break;
                default:
                    throw new \Exception('invalid structure');
            }
        }
        return $result;
    }

    private static function isField($block, $method = false)
    {
        return $block->type === Raw::FIELD_TYPE
            && (is_null($method) || ($block->value[0] === '.') === $method);
    }

    private static function isFieldChain(array $structure)
    {
        if (!isset($structure[0])
        || !self::isField($structure[0])
        ) {
            return false;
        }
        for ($num = 1; $num < count($structure) - 1; $num++) {
            if (!self::isField($structure[$num], true)) {
                return false;
            }
        }
        return self::isField(end($structure), null);
    }

    /**
     * @return Link[]
     */
    private static function getUpdateBlock(array $structure)
    {
        $structures = self::explode(',', $structure);
        $result = [];
        foreach ($structures as $structure) {
            if (count($structure) === 0) {
                continue;
            }
            if (count($structure) > 1
                && !self::isFieldChain($structure)

            ) {
                throw new \Exception('invalid structure');
            }
            if (!self::isField($structure[0]) && $structure[0]->type !== Raw::VARIABLE_TYPE) {
                throw new \Exception('invalid structure');
            }
            if ($structure[0]->type !== Raw::VARIABLE_TYPE) {
                $result[] = $structure[0]->value;
            }
            else {
                $result[] = self::blockToRaw($structure);
            }
        }
        return $result;
    }

    /**
     * @return Link[]
     */
    private static function getInsertBlock()
    {

    }

    private static function blockToString($blocks)
    {
        //if (count($blocks) === 1)
    }

    /**
     * @param $blocks
     * @return string
     */
    private static function blocksToString($blocks)
    {
        $result = [];
        foreach ($blocks as $block) {
            switch ($block->type) {
                case Raw::STRING_TYPE:
                    $result[] = '"' . str_replace('`', '``', $block->value) . '"';
                    break;
                case Raw::PARENTHESIS_TYPE:
                    $result[] = '(' . self::blocksToString($block->value) . ')';
                    break;
                case Raw::RECTANGULAR_TYPE:
                    $result[] = '[' . self::blocksToString($block->value) . ']';
                    break;
                case Raw::METHOD_TYPE:
                    $result[] = '.' . $block->value;
                    break;
                default:
                    $result[] = $block->value;
            }
        }

        return implode(' ', $result);
    }

    /**
     * @param array $blocks
     * @return string
     */
    private static function blockToRaw($blocks)
    {
        if (count($blocks) === 1
            && in_array($blocks[0]->type, [Raw::STRING_TYPE, Raw::NUMBER_TYPE, Raw::VARIABLE_TYPE])
        ) {
            return $blocks[0]->value;
        }
        return new Raw(self::blocksToString($blocks));
    }

    /**
     * @param array $structure
     */
    private static function getValueBlock(array $structure)
    {
        /**
         * 1) определить количество аргументов и их связи
         * 2) связбю может быть контекст или ничего
         * 3) слово может быть либо константой, либо функцией
         * 4) у всего могут быть методы
         * 5) у всего могут быть параметры
         */
        /**
         * ([@,$,"",[],()]|W())((.f)*(.m())*([])*)*
         *
         */
        /**
         * 1) находим разделители - контекст, переменная, строка, поле без точки
         *
         * так же разделителями могут являться повторяющиеся скобочки (выражение) (выражение)
         */

        $structures = [];
        $blocks = [];
        foreach ($structure as $pos => $block) {

            if ($block->type === Raw::CONTEXT_TYPE) {
                if (count($blocks)) {
                    $structures[] = $blocks;
                    $blocks = [];
                }
                $structures[] = $block;
                continue;
            }
            if (count($blocks)
                && (in_array($block->type, [
                    Raw::STRING_TYPE, Raw::CONTEXT_TYPE, Raw::VARIABLE_TYPE, Raw::SQLVAR_TYPE, Raw::NUMBER_TYPE
                ])
                    || ($block->type === Raw::FIELD_TYPE && $block->value[0] !== '.')
                    || ($block->type === Raw::WORD_TYPE
                        && isset($structure[$pos])
                        && $structure[$pos]->type === Raw::WORD_TYPE)
                    || ($block->type === Raw::PARENTHESIS_TYPE
                        && isset($structure[$pos])
                        && in_array($structure[$pos]->type, [Raw::PARENTHESIS_TYPE, RAW::WORD_TYPE]))
                )
            ) {
                $structures[] = $blocks;
                $blocks = [];
            }
            $blocks[] = $block;
        }
        if (count($blocks)) {
            $structures[] = $blocks;
        }


        foreach ($structures as $blocks) {
            //if ()
        }

    }
}