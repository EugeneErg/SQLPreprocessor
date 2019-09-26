<?php namespace EugeneErg\SQLPreprocessor\Raw;

use EugeneErg\SQLPreprocessor\Parsers\ParserAbstract;
use EugeneErg\SQLPreprocessor\Raw;

/**
 * Class Items
 * @package EugeneErg\SQLPreprocessor\Raw
 */
class Items implements \ArrayAccess, \Countable
{
    const POS_FLAG_NOT_MATCH = 1;
    const POS_FLAG_LOWER_CASE = 2;
    const POS_FLAG_UPPER_CASE = 6;
    /**
     * @var Item[]
     */
    private $items = [];

    /**
     * RawItems constructor.
     * @param Item[] $items
     */
    public function __construct(array $items = [])
    {
        foreach ($items as $item) {
            $this->set($item);
        }
    }

    /**
     * @param int $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->items[$offset]);
    }

    /**
     * @param int $offset
     * @return Item
     */
    public function offsetGet($offset)
    {
        return $this->items[$offset];
    }

    /**
     * @param Item $value
     * @param int|null $offset
     */
    private function set(Item $value, $offset = null)
    {
        if (!isset($offset, $this->items[$offset])) {
            $this->items[] = $value;
        }
        else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * @param int $offset
     * @param Item $value
     */
    public function offsetSet($offset, $value)
    {
        $this->set($value, $offset);
    }

    /**
     * @param mixed int
     */
    public function offsetUnset($offset)
    {
        array_splice($this->items, $offset, 1);
    }

    /**
     * @param string $delimiter
     * @param int $maxCount
     * @return self[]
     */
    public function explode($delimiter, $maxCount = 0)
    {
        $result = [];
        $partOfChain = [];
        foreach ($this->items as $item) {
            if ($item instanceof Raw\Item\Context
                && count($result) !== $maxCount - 1
            ) {
                $strings = preg_split(
                    "/{$delimiter}/", $item->getValue(),
                    $maxCount - count($result) > 1 ? $maxCount - count($result) : -1
                );
                if (count($strings) === 1) {
                    $partOfChain[] = $item;
                    continue;
                }
                foreach ($strings as $string) {
                    if (count($partOfChain)) {
                        $result[] = new self($partOfChain);
                    }
                    $partOfChain = [new Raw\Item\Context($string)];
                }
                $partOfChain = [];
            }
            else {
                $partOfChain[] = $item;
            }
        }
        if (count($partOfChain)) {
            $result[] = new self($partOfChain);
        }
        return $result;
    }

    public function count()
    {
        return count($this->items);
    }

    private function transformCase($value, $type)
    {
        if (is_null($type) || !is_string($value)) {
            return $value;
        }
        if ($type) {
            return strtoupper($value);
        }
        return strtolower($value);
    }

    private function getCaseTransform($flags)
    {
        switch ($flags & self::POS_FLAG_UPPER_CASE) {
            case self::POS_FLAG_UPPER_CASE: return true;
            case self::POS_FLAG_LOWER_CASE: return false;
            default: return null;
        }
    }

    /**
     * @param string|array|\Closure $context
     * @param int $offset
     * @param int $flags
     * @return int|null
     */
    public function pos($context, $offset = 0, $flags = 0)
    {
        $caseTransform = $this->getCaseTransform($flags);
        $notMatch = $flags & self::POS_FLAG_NOT_MATCH === self::POS_FLAG_NOT_MATCH;
        if (!$context instanceof \Closure) {
            $search = [];
            foreach ((array) $context as $type => $subjects) {
                if (is_int($type)) {
                    $type = Raw\Item\Context::class;
                }
                if (is_null($subjects)) {
                    $search[$type] = null;
                }
                else {
                    foreach ((array)$subjects as $subject) {
                        $search[$type][] = $this->transformCase($subject, $caseTransform);
                    }
                }
            }
            $context = function($value, Item $item) use($search) {
                return array_key_exists(get_class($item), $search)
                    && ($search[get_class($item)] === null || in_array($value, $search[get_class($item)]));
            };
        }
        for ($i = $offset; $i < $this->count(); $i++) {
            if ($context(
                    $this->transformCase($this[$i]->getValue(), $caseTransform),
                    $this[$i]
                ) !== $notMatch
            ) {
                return $i;
            }
        }
        return null;
    }

    /**
     * @param int $offset
     * @param int $length
     * @param Items|null $replacement
     * @return Items
     */
    public function splice($offset = 0, $length = 0, self $replacement = null)
    {
        return new self(array_splice(
            $this->items, $offset, $length, $replacement ? $replacement->items : []
        ));
    }

    /**
     * @param int $offset
     * @param int|null $length
     * @return Items
     */
    public function slice($offset = 0, $length = null)
    {
        return new self(array_slice($this->items, $offset, $length));
    }

    /**
     * @param ParserAbstract|null $parser
     * @return Raw|mixed
     */
    public function getRawValue(ParserAbstract $parser = null)
    {
        $result = [];
        foreach ($this->items as $item) {
            $result[] = $item->getRawValue($parser);
        }
        return $this->count() === 1 ? reset($result) : new Raw(implode(' ', $result), $parser);
    }

    /**
     * @return array
     */
    public function getRawValues()
    {
        $arrayItems = $this->explode(',|;');
        $result = [];
        foreach ($arrayItems as $items) {
            $result[] = $items->getRawValue();
        }
        return $result;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return "{$this->getRawValue()}";
    }
}