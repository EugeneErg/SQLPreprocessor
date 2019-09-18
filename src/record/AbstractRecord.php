<?php namespace EugeneErg\SQLPreprocessor\Record;

use EugeneErg\SQLPreprocessor\Link;

abstract class AbstractRecord
{
    private $object;

    /**
     * @var self
     */
    private $root;

    /**
     * @var Link[]
     */
    private $sequence;

    /**
     * AbstractRecord constructor.
     * @param mixed $object
     * @param Link[] $sequence
     * @param self|null $root
     */
    public function __construct($object, $sequence = [], self $root = null)
    {
        $this->root = is_null($root) ? $this : $root;
        $this->object = $object;
        $this->sequence = $sequence;
        if (is_null($root) && method_exists($this,'init')) {
            $this->init();
        }
    }

    /**
     * @return mixed
     */
    public function getObject()
    {
        return $this->root->object;
    }

    /**
     * @return Link[]
     */
    public function getSequence()
    {
        return $this->sequence;
    }

    /**
     * @return self
     */
    protected function getRoot()
    {
        return $this->root;
    }

    public static function getTrees(array $records)
    {
        //$root
    }
}