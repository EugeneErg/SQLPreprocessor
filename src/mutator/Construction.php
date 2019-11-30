<?php namespace EugeneErg\SQLPreprocessor\Mutator;

/**
 * Class Construction
 * @package EugeneErg\SQLPreprocessor\mutator
 */
class Construction
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $arguments;

    /**
     * @var array
     */
    private $children;

    /**
     * Construction constructor.
     * @param string $name
     * @param array $arguments
     * @param array $children
     */
    public function __construct($name, array $arguments = [], array $children = [])
    {
        $this->name = $name;
        $this->arguments = $arguments;
        $this->children = $children;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @return array
     */
    public function getChildren()
    {
        return $this->children;
    }
}
