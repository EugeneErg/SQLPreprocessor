<?php namespace EugeneErg\SQLPreprocessor;

/**
 * Trait HashesTrait
 * @package EugeneErg\SQLPreprocessor
 */
trait HashesTrait
{
    /**
     * @var string
     */
    private $hash;

    /**
     * SequenceTrait constructor.
     */
    private function __construct()
    {
        $this->hash = Hasher::getHash($this);
    }

    public function __clone()
    {
        $this->hash = Hasher::getHash($this);
    }

    public function __wakeup()
    {
        $this->hash = Hasher::getHash($this);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return Hasher::getHash($this);
    }
}