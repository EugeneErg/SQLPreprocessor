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
        $this->structure[] = (object) [
            'name' => $name,
            'options' => $args,
            'is_method' => $is_method,
        ];
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
        if (!is_null($child = self::getByHash($name))) {
            $this->structure[] = &$child->structure;
            return $this;
        }
        if (!isset($this->structure[$name]) && isset($this->otherSequenceName)) {
            $this->addBlock($this->otherSequenceName, [$name], false);
            return $this;
        }
        return $this->__call($name, []);
    }

    /**
     * @return array
     */
    private function getStructure()
    {
        $topology = new Topology($this->structure);
        return $topology->getStructure($this->sequence);
    }
}