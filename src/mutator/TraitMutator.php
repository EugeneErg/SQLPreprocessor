<?php namespace EugeneErg\SQLPreprocessor\Mutator;

trait TraitMutator
{
    /**
     * @var self
     */
    private $parent;

    /**
     * @param string $name
     * @param array $arguments
     * @return Method
     */
    public function newMethod($name, array $arguments = [])
    {
        $new = new Method($name, $arguments);
        $new->parent = $this;

        return $new;
    }

    /**
     * @param string $name
     * @return Property
     */
    public function newVariable($name)
    {
        $new = new Property($name);
        $new->parent = $this;

        return $new;
    }
}
