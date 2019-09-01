<?php namespace EugeneErg\SQLPreprocessor;

/**
 * Trait SequenceTrait
 * @package EugeneErg\SQLPreprocessor
 */
trait SequenceTrait
{
    use HashesTrait;

    private $sequence = [];
    private $structure = [];
    private $otherSequenceName = null;

    /**
     * @param string $name
     * @param array $args
     * @param bool $is_method
     * @throws \Exception
     */
    private function addBlock($name, array $args, $is_method = true)
    {
        if (!isset($this->sequence[$name])) {
            throw new \Exception('invalid sequence');
        }
        $this->structure[] = new Chain(
            $name,
            $args,
            $is_method
        );
    }

    /**
     * @param string $name
     * @param array $args
     * @return $this
     * @throws \Exception
     */
    public function __call($name, array $args)
    {
        $this->addBlock($name, $args);
        return $this;
    }

    /**
     * @param string $name
     * @return $this
     * @throws \Exception
     */
    public function __get($name)
    {
        if (!is_null($child = Hasher::getObject($name))) {
            $this->structure[] = &$child->structure;
            return $this;
        }
        if (!isset($this->structure[$name])) {
            if (isset($this->otherSequenceName)) {
                $this->addBlock($this->otherSequenceName, [$name], false);
                return $this;
            }
            if (method_exists($this, 'getStructureBlock')) {
                $this->structure[] = $callback = $this->getStructureBlock($name);
                return $this;
            }
        }
        return $this->__call($name, []);
    }

    /**
     * @return array
     * @throws \Exception
     */
    private function getStructure()
    {
        $topology = new Topology($this->structure);
        if (method_exists($this, 'chainToArray')) {
            $callback = function($object, Chain $parent) {
                return $this->chainToArray($object, $parent);
            };
        }
        else {
            $callback = null;
        }
        return $topology->getStructure($this->sequence, $callback);
    }
}