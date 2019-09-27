<?php namespace EugeneErg\SQLPreprocessor\Raw;

/**
 * Class ItemAbstract
 */
abstract class Item
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
        return in_array(get_class($this), $classes);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return "{$this->getValue()}";
    }
}