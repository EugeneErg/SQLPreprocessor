<?php namespace EugeneErg\SQLPreprocessor;

/**
 * Class Raw
 * @package EugeneErg\SQLPreprocessor
 */
class Raw
{
    use HashesTrait;

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

    /**
     * @var string[]
     */
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

    /**
     * @var string[]
     */
    private static $patterns = [
        self::PARENTHESIS_TYPE => "\\[[^'\"\]\\[]*\\]",
        self::RECTANGULAR_TYPE => "\\([^'\"\)\\(]*\\)",
    ];

    /**
     * @var string
     */
    private $string;

    /**
     * Query constructor.
     * @param string $string
     */
    public function __construct($string)
    {
        $this->string = $string;
        $this->hash = Hasher::getHash($this);
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
                return Hasher::getObject($value);
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
     * @return object[]
     */
    public function parse()
    {
        $string = $this->string;
        $result = $this->getIteration(self::$firstPatterns, $string);
        $results[] = $result;
        while (count($result)) {
            $result = $this->getIteration(self::$patterns, $string);
            $results[] = $result;
        }

        return $this->getStructure(call_user_func_array('array_replace', $results), strlen($string) + 2);
    }
}