<?php namespace EugeneErg\SQLPreprocessor\Mutator;

/**
 * Class Value
 * @package EugeneErg\SQLPreprocessor\mutator
 */
class Value
{
    use TraitMutator;

    /**
     * @var mixed
     */
    private $value;

    /**
     * Value constructor.
     * @param mixed $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}