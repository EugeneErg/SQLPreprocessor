<?php namespace EugeneErg\SQLPreprocessor\mutator;

use EugeneErg\SQLPreprocessor\Record\AbstractRecord;
use EugeneErg\SQLPreprocessor\Record\Container;

/**
 * Class Operator
 * @package EugeneErg\SQLPreprocessor\mutator
 */
class Operator extends AbstractRecord
{
    /**
     * @var string
     */
    private $name;

    /**
     * Operator constructor.
     * @param string $name
     * @return Container
     */
    public static function create($name)
    {
        $new = new self();
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
