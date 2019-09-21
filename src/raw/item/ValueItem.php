<?php namespace EugeneErg\SQLPreprocessor\Raw\Item;

/**
 * Class ItemAbstract
 */
abstract class ValueItem
{
    /**
     * @var string
     */
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param string[] $classes
     * @return bool
     */
    public function is(...$classes)
    {
        foreach ($classes as $class) {
            if (is_subclass_of($this, $class)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return "{$this->getValue()}";
    }
}