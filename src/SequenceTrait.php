<?php namespace EugeneErg\SQLPreprocessor;

/**
 * Trait SequenceTrait
 * @package EugeneErg\SQLPreprocessor
 */
trait SequenceTrait
{
    use HashesTrait;

    private $sequence = [];
    private $otherSequenceName = null;

    /**
     * @param string $name
     * @param array $args
     * @param bool $is_method
     * @throws \Exception
     */
    private function addBlock($name, array $args, $is_method = true)
    {
        if (!isset($this->structure[$name])) {
            throw new \Exception('invalid sequence');
        }
        $this->sequence[] = new Link(
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
            $this->sequence[] = &$child->sequence;

            return $this;
        }
        if (!isset($this->structure[$name])) {
            if (isset($this->otherSequenceName)) {
                $this->addBlock($this->otherSequenceName, [$name], false);

                return $this;
            }
            if (method_exists($this, 'getStructureBlock')) {
                $this->sequence[] = $this->getStructureBlock($name);

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
        return (new Topology($this->structure))->getStructure($this->sequence);
    }
}