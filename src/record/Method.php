<?php namespace EugeneErg\SQLPreprocessor\Record;

class Method extends AbstractRecord
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
     * @param string $name
     * @param array $arguments
     * @param AbstractRecord|null $parent
     * @return Container
     */
    public static function create($name, array $arguments = [], AbstractRecord $parent = null)
    {
        $new = new self($parent);
        $new->name = $name;
        $new->arguments = $arguments;

        return $new->getContainer();
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
}
