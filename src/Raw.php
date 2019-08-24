<?php namespace EugeneErg\SQLPreprocessor;

/**
 * Class Raw
 * @package EugeneErg\SQLPreprocessor
 */
class Raw
{
    const CONTEXT_TYPE_QUERY = 'query';
    const CONTEXT_TYPE_SUBQUERY = 'subQuery';
    const CONTEXT_TYPE_ARGUMENT = 'argument';

    const STRING_TYPE = 'string';
    const PARENTHESIS_TYPE = 'parenthesis';
    const RECTANGULAR_TYPE = 'rectangular';
    const VARIABLE_TYPE = 'variable';
    const CONTEXT_TYPE = 'context';
    const SQLVAR_TYPE = 'sql_var';
    const NUMBER_TYPE = 'number';
    const WORD_TYPE = 'word';
    const METHOD_TYPE = 'method';
    const FIELD_TYPE = 'field';

    private static $firstPatterns = [
        self::VARIABLE_TYPE => '\\$[0-9a-f]{32}\\$',
        self::SQLVAR_TYPE => '@[\\w\\.]*',
        self::NUMBER_TYPE => '\\b(?:\\d*\\.\\d+|\\d+\\.?\\d*)(?:[Ee][+-]?\\d+)?\\b',
        self::METHOD_TYPE => '\\.\\s*[a-zA-Z_]\\w*\\b',
        self::WORD_TYPE => '\\b\\w+\\b',
        self::STRING_TYPE => "'(?:[^']*(?:'')*)+'|" . '"(?:[^"]*(?:"")*)+"',
        self::FIELD_TYPE => '(?:\\.\\s*)?`(?:[^`]*(?:``)*)+`',
        self::CONTEXT_TYPE => ',|:|[+-]*|[^\\[\\]\\(\\)\\w\\s\'",:+-]+'
    ];
    private static $patterns = [
        self::PARENTHESIS_TYPE => "\\[[^'\"\]\\[]*\\]",
        self::RECTANGULAR_TYPE => "\\([^'\"\)\\(]*\\)",
    ];

    private $string;

    /**
     * Query constructor.
     * @param string $string
     */
    public function __construct($string)
    {
        $this->string = $string;
    }

    /**
     * @param string $type
     * @param string $value
     *
     * @return mixed
     */
    private function getValue($type, $value)
    {
        switch ($type) {
            case self::STRING_TYPE:
                return str_replace($value[0] . $value[0], $value[0], substr($value, 1, -1));
            case self::PARENTHESIS_TYPE:
            case self::RECTANGULAR_TYPE:
                return [];
            case self::VARIABLE_TYPE:
                return Variable::find($value);
            case self::METHOD_TYPE:
                return trim(substr($value, 1));
            case self::CONTEXT_TYPE:
                return preg_split('/\\s+/', strtolower(trim($value)));
            case self::WORD_TYPE:
                return strtolower($value);
            case self::FIELD_TYPE:
                if ($value[0] === '.') {
                    return '.' . trim(substr($value, 1));
                }
            default:
                return $value;
        }
    }

    /**
     * @param array $patterns
     * @param string $string
     * @return object[]
     */
    private function getIteration(array $patterns, &$string)
    {
        preg_match_all(
            '/(' . implode(')|(', $patterns) . ')/',
            $string, $matches, PREG_OFFSET_CAPTURE
        );
        unset($matches[0]);
        $results = [];
        $types = array_keys($patterns);
        foreach ($matches as $typeNumber => $variants) {
            foreach ($variants as $variant) {
                if (!empty($variant) && $variant[0] !== '') {
                    $results[$variant[1]] = (object) [
                        'type' => $types[$typeNumber - 1],
                        'size' => strlen($variant[0]),
                        'value' => $this->getValue($types[$typeNumber - 1], $variant[0]),
                    ];
                    $string = substr_replace(
                        $string, str_repeat(' ', $results[$variant[1]]->size),
                        $variant[1], $results[$variant[1]]->size
                    );
                }
            }
        }
        return $results;
    }

    /**
     * @param array $blocks
     * @param int|null $size
     * @param int $pos
     * @return object[]
     */
    private function getStructure(array $blocks, $size, $pos = -1)
    {
        $result = [];
        for ($i = $pos + 1; $i < $pos + $size - 1; ) {
            if (!isset($blocks[$i])) {
                $i++;
                continue;
            }
            $block = $blocks[$i];
            if ($block->type === self::RECTANGULAR_TYPE || $block->type === self::PARENTHESIS_TYPE) {
                $block->value = $this->getStructure($blocks, $block->size, $i);
            }
            $result[] = $block;
            unset($block->size);
            $i += $block->size;
        }
        return $result;
    }

    /**
     * @param string $context
     * @return object[]
     * @throws \Exception
     */
    public function parse($context = self::CONTEXT_TYPE_QUERY)
    {
        if (!in_array($context, [
            self::CONTEXT_TYPE_QUERY,
            self::CONTEXT_TYPE_ARGUMENT,
            self::CONTEXT_TYPE_SUBQUERY])
        ) {
            throw new \Exception('');
        }
        $string = $this->string;
        $result = $this->getIteration(self::$firstPatterns, $string);
        $results[] = $result;
        while (count($result)) {
            $result = $this->getIteration(self::$patterns, $string);
            $results[] = $result;
        }

        return call_user_func([self::class, 'analyze' . ucfirst($context)],
            $this->getStructure(call_user_func_array('array_replace', $results), strlen($string) + 2)
        );
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
                    
            }
        }
        return $result;
    }

    /**
     * @param array $structure
     * @return object[]
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