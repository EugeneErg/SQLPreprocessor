<?php namespace EugeneErg\SQLPreprocessor\Raw;

use EugeneErg\SQLPreprocessor\ParseException;
use EugeneErg\SQLPreprocessor\Variable;

/**
 * Class Item
 * @package EugeneErg\SQLPreprocessor\Raw
 */
class Item
{
    const TYPE_STRING = 'string';
    const TYPE_PARENTHESIS = 'parenthesis';
    const TYPE_RECTANGULAR = 'rectangular';
    const TYPE_RECORD = 'record';
    const TYPE_CONTEXT = 'context';
    const TYPE_SQL_VAR = 'sql_var';
    const TYPE_NUMBER = 'number';
    const TYPE_WORD = 'word';
    const TYPE_METHOD = 'method';
    const TYPE_FIELD = 'field';

    /**
     * @var string
     */
    private $type;

    private $value;

    /**
     * RawPart constructor.
     * @param $value
     * @param string $type
     */
    public function __construct($value, $type = self::TYPE_CONTEXT)
    {
        if (!in_array($type, [
            self::TYPE_STRING,
            self::TYPE_PARENTHESIS,
            self::TYPE_RECTANGULAR,
            self::TYPE_VARIABLE,
            self::TYPE_CONTEXT,
            self::TYPE_SQL_VAR,
            self::TYPE_NUMBER,
            self::TYPE_WORD,
            self::TYPE_METHOD,
            self::TYPE_FIELD,
        ])) {
            $type = self::TYPE_CONTEXT;
        }
        $this->type = $type;
        $this->value = $value;
    }

    /**
     * @return int|string|float|Variable|Items
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string[] $types
     * @return bool
     */
    public function is(...$types)
    {
        return in_array($this->type, $types);
    }

    /**
     * @param Item $child
     */
    public function addChild(self $child)
    {
        if (!in_array($this->type, [self::TYPE_PARENTHESIS, self::TYPE_RECTANGULAR])) {
            throw ParseException::notAccessMethod($this, 'addChild');
        }
        $this->value[] = $child;
    }

    /**
     * @param Items $children
     */
    public function setChildren(Items $children)
    {
        if (!in_array($this->type, [self::TYPE_PARENTHESIS, self::TYPE_RECTANGULAR])) {
            throw ParseException::notAccessMethod($this, 'addChild');
        }
        $this->value = $children;
    }

    public function getRawValue()
    {
        switch ($this->type) {
            case self::TYPE_PARENTHESIS:
                return "({$this->value})";
            case self::TYPE_RECTANGULAR:
                return "[{$this->value}]";
            case self::TYPE_STRING:
                return "'" . str_replace("'", "''", $this->value) . "'";
            default:
                return $this->value;
        }
    }

    public function __toString()
    {
        return "{$this->getRawValue()}";
    }
}