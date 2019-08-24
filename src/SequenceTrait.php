<?php namespace EugeneErg\SQLPreprocessor;

/**
 * Trait SequenceTrait
 * @package EugeneErg\SQLPreprocessor
 */
trait SequenceTrait
{
    private $hash;
    private $sequence = [];
    protected $structure = [];
    protected $otherSequenceName = null;
    protected $createdAsNew = false;
    protected $parameterAsFunction = true;

    /**
     * @param string $name
     * @param array $args
     * @return $this
     * @throws \Exception
     */
    public function __call($name, $args)
    {
        if (!isset($this->structure[$name])) {
            throw new \Exception('invalid sequence');
        }
        $this->structure[] = (object) [
            'name' => $name,
            'options' => $args,
            'is_method' =
        ];
        return $this;
    }

    /**
     * @param string $name
     * @return $this
     * @throws \Exception
     */
    public function __get($name)
    {
        if (!is_null($child = $this->hashes($name))) {
            $this->structure[] = &$child->structure;
            return $this;
        }
        if (!isset($this->structure[$name]) && isset($this->otherSequenceName)) {
            return $this->__call($this->otherSequenceName, [$name]);
        }
        return $this->__call($name, []);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->hash;
    }

    /**
     * @param string|null $hash
     * @return string|SequenceTrait|void
     */
    private function hashes($hash = null)
    {
        static $hashes = [];
        if (is_null($hash)) {
            $hashes[$hash = '$' . spl_object_hash($this) . '$'] = $this;
            return $hash;
        }
        if (isset($hashes[$hash])) {
            return $hashes[$hash];
        }
    }

    /**
     * SequenceTrait constructor.
     */
    private function __construct()
    {
        $this->hash = $this->hashes();
    }

    /**
     * @return array
     */
    protected function getStructure()
    {
        $topology = new Topology($this->structure);
        return $topology->getStructure($this->sequence);
    }
}