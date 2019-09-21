<?php namespace EugeneErg\SQLPreprocessor;

use EugeneErg\SQLPreprocessor\Parsers\ParserAbstract;
use EugeneErg\SQLPreprocessor\Parsers\Special;
use EugeneErg\SQLPreprocessor\Raw\Item\String;
use EugeneErg\SQLPreprocessor\Raw\Item\StructureItem;
use EugeneErg\SQLPreprocessor\Raw\Item\ValueItem;
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
    private static $parsers = [
        'default' => Special::class
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
     * Builder constructor.
     * @param string $string
     * @param ParserAbstract $parser
     */
    public function __construct($string, ParserAbstract $parser = null)
    {
        $this->parser = is_null($parser) ? new self::$parsers['default']() : $parser;
        $this->string = $string;
        $this->hash = Hasher::getHash($this);
    }

    /**
     * @param $parser
     * @param array $arguments
     * @return Raw
     */
    public static function __callStatic($parser, array $arguments)
    {
        return new self($arguments[0], new self::$parsers[$parser]());
    }

    /**
     * @param $class
     * @param $alias
     */
    public static function registerParser($class, $alias)
    {
        if (!is_subclass_of($class, ParserAbstract::class)) {
            throw ParseException::incorrectParserClass($class);
        }
        self::$parsers[$alias] = $class;
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
                    $class = $types[$typeNumber - 1];
                    if (is_subclass_of($class, StructureItem::class)) {
                        $results[$variant[1]] = (object) [
                            'class' => $class,
                            'size' => strlen($variant[0]),
                        ];
                    }
                    else {
                        $results[$variant[1]] = (object) [
                            'object' => new $class($variant[0]),
                            'size' => strlen($variant[0]),
                        ];
                    }
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
     * @param object[] $items
     * @param int|null $size
     * @param int $pos
     * @return ValueItem[]
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
            if (!isset($block->object)) {
                $block->object = new $block->class(new Items($this->getStructure($items, $block->size, $i)));
            }
            $result[] = $block->object;
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
        $valuePatterns = [];
        $structurePatterns = [];
        $parser = $this->parser;
        foreach ($parser::ITEMS as $itemClass) {
            if (is_subclass_of($itemClass, StructureItem::class)) {
                $structurePatterns[$itemClass] = $itemClass::TEMPLATE;
            }
            else {
                $valuePatterns[$itemClass] = $itemClass::TEMPLATE;
            }
        }
        $result = $this->getIteration($valuePatterns, $string);
        $results[] = $result;
        while (count($result)) {
            $result = $this->getIteration($structurePatterns, $string);
            $results[] = $result;
        }
        return $this->parser->getSequence(
            new Items($this->getStructure(
                call_user_func_array('array_replace', $results), strlen($string) + 2)
            ), $type
        );
    }
}