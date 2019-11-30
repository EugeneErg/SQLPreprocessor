<?php namespace EugeneErg\SQLPreprocessor\Record;

/**
 * Class Variable
 * @package EugeneErg\SQLPreprocessor\mutator
 */
class Property extends AbstractRecord
{
    /**
     * @var string
     */
    private $name;

    /**
     * @param string $name
     * @param AbstractRecord $parent
     * @return Container
     */
    public static function create($name, AbstractRecord $parent = null)
    {
        $new = new self($parent);
        $new->name = $name;

        return $new->getContainer();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}
