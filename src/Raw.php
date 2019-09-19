<?php namespace EugeneErg\SQLPreprocessor;

use EugeneErg\SQLPreprocessor\Parsers\ParserAbstract;
use EugeneErg\SQLPreprocessor\Parsers\Special;
use EugeneErg\SQLPreprocessor\Raw\Item;
use EugeneErg\SQLPreprocessor\Raw\Items;

/**
 * Class Raw
 * @package EugeneErg\SQLPreprocessor
 */
class Raw
{
    use HashesTrait;

    /**
     * @var string[]
     */
    private static $firstPatterns = [
        Item::TYPE_RECORD => '\\$[0-9a-f]{32}\\$',
        Item::TYPE_SQL_VAR => '@[\\w\\.]*',
        Item::TYPE_NUMBER => '\\b(?:\\d*\\.\\d+|\\d+\\.?\\d*)(?:[Ee][+-]?\\d+)?\\b',
        Item::TYPE_METHOD => '\\.\\s*[a-zA-Z_]\\w*\\b',
        Item::TYPE_WORD => '\\b\\w+\\b',
        Item::TYPE_STRING => "'(?:[^']*(?:'')*)+'|" . '"(?:[^"]*(?:"")*)+"',
        Item::TYPE_FIELD => '(?:\\.\\s*)?`(?:[^`]*(?:``)*)+`',
        Item::TYPE_CONTEXT => ',|;|:|[<>=]+|[+-]+|!+|[^\\[\\]\\(\\)\\w\\s\'"+-,;:<>=]+',//test it -= +=
    ];

    /**
     * @var string[]
     */
    private static $patterns = [
        Item::TYPE_PARENTHESIS => "\\[[^'\"\]\\[]*\\]",
        Item::TYPE_RECTANGULAR => "\\([^'\"\)\\(]*\\)",
    ];

    /**
     * @var string
     */
    private $string;

    /**
     * @var ParserAbstract
     */
    private $parser;

    /**
     * Query constructor.
     * @param string $string
     * @param string $parser
     */
    public function __construct($string, $parser = Special::class)
    {
        if (!is_subclass_of($parser, ParserAbstract::class)) {
            throw ParseException::incorrectParserClass($parser);
        }
        $this->parser = $parser;
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
            case Item::TYPE_STRING:
                return str_replace($value[0] . $value[0], $value[0], substr($value, 1, -1));
            case Item::TYPE_PARENTHESIS:
            case Item::TYPE_RECTANGULAR:
                return [];
            case Item::TYPE_RECORD:
                return Hasher::getObject($value);
            case Item::TYPE_METHOD:
                return trim(substr($value, 1));
            case Item::TYPE_CONTEXT:
                return preg_split('/\\s+/', strtolower(trim($value)));
            case Item::TYPE_WORD:
                return strtolower($value);
            case Item::TYPE_FIELD:
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
     * @return Item[]
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
                    $results[$variant[1]] = new Item(
                        $this->getValue($types[$typeNumber - 1], $variant[0]),
                        $types[$typeNumber - 1]
                    );
                    $results[$variant[1]]->size = strlen($variant[0]);
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
     * @param Item[] $items
     * @param int|null $size
     * @param int $pos
     * @return Item[]
     */
    private function getStructure(array $items, $size, $pos = -1)
    {
        $result = [];
        for ($i = $pos + 1; $i < $pos + $size - 1; ) {
            if (!isset($items[$i])) {
                $i++;
                continue;
            }
            $block = $items[$i];
            if ($block->is(Item::TYPE_RECTANGULAR, Item::TYPE_PARENTHESIS)) {
                $block->setChildren(new Items($this->getStructure($items, $block->size, $i)));
            }
            $result[] = $block;
            unset($block->size);
            $i += $block->size;
        }
        return $result;
    }

    /**
     * @param string $type
     * @return Link[]
     */
    public function parse($type = ParserAbstract::TYPE_QUERY)
    {
        $string = $this->string;
        $result = $this->getIteration(self::$firstPatterns, $string);
        $results[] = $result;
        while (count($result)) {
            $result = $this->getIteration(self::$patterns, $string);
            $results[] = $result;
        }

        $parser = $this->parser;
        return $parser::getSequence(
            new Items($this->getStructure(call_user_func_array('array_replace', $results), strlen($string) + 2)),
            $type
        );
    }
}