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
     * @var self[]
     */
    private static $hashes = [];

    /**
     * SequenceTrait constructor.
     */
    private function __construct()
    {
        $this->getCurrentHash();
    }

    public function __clone()
    {
        $this->hash = null;
        $this->getCurrentHash();
    }

    public function __wakeup()
    {
        $this->hash = null;
        $this->getCurrentHash();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getCurrentHash();
    }

    /**
     * @param string $hash
     * @return self|null
     */
    private static function getByHash($hash)
    {
        if (isset(self::$hashes[$hash])) {
            return self::$hashes[$hash];
        }
    }

    /**
     * @return string
     */
    private function getCurrentHash()
    {
        if (!isset($this->hash)) {
            self::$hashes[$this->hash = '$' . spl_object_hash($this) . '$'] = $this;
        }
        return $this->hash;
    }
}