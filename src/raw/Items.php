<?php namespace EugeneErg\SQLPreprocessor\Raw;

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

    private function isConnected(Item $current, Item $next)
    {
        if (!$current->is(Item::TYPE_CONTEXT)) {
            if ($next->is(
                ITEM::TYPE_WORD, Item::TYPE_NUMBER, Item::TYPE_STRING, Item::TYPE_VARIABLE, Item::TYPE_SQL_VAR)) {
                return true;
            }
            if ($next->is(Item::TYPE_FIELD) && $next->getValue()[0] !== '.') {
                return true;
            }
        }
        if ($next->is(Item::TYPE_PARENTHESIS)
            && !$current->is(Item::TYPE_WORD, Item::TYPE_METHOD)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return self[]
     */
    public function getUnconnectedParts()
    {
        $result = [];
        $items = [];
        foreach ($this->items as $pos => $item) {
            if (!isset($this[$pos + 1]) || self::isConnected($item, $this[$pos + 1])) {
                $result[] = new self($items);
                $items = [];
            }
        }
        return $result;
    }

    /**
     * @param string|string[] $delimiter
     * @param int $maxCount
     * @return self[]
     */
    public function explode($delimiter, $maxCount = 0)
    {
        $result = [];
        $partOfChain = [];
        foreach ($this->items as $link) {
            if ($link->is(Item::TYPE_CONTEXT)
                && count($result) !== $maxCount - 1
                && in_array($link->getValue(), (array)$delimiter)
            ) {
                if (count($partOfChain)) {
                    $result[] = new self($partOfChain);
                }
                $partOfChain = [];
            }
            else {
                $partOfChain[] = $link;
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
                    $type = Item::TYPE_CONTEXT;
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
            $context = function($value, $type) use($search) {
                return array_key_exists($type, $search)
                    && ($search[$type] === null || in_array($value, $search[$type]));
            };
        }
        for ($i = $offset; $i < $this->count(); $i++) {
            if ($context(
                    $this->transformCase($this[$i]->getValue(), $caseTransform),
                    $this[$i]->getType()
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
     * @return mixed
     */
    public function getRawValue()
    {
        $result = [];
        foreach ($this->items as $item) {
            $result[] = $item->getRawValue();
        }
        return $this->count() === 1 ? $result[0] : new Raw(implode(' ', $result));
    }

    /**
     * @return array
     */
    public function getRawValues()
    {
        $arrayItems = $this->explode([',',';']);
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