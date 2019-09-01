<?php namespace EugeneErg\SQLPreprocessor;

/**
 * Class Hasher
 * @package EugeneErg\SQLPreprocessor
 */
final class Hasher
{
    private function __construct() {}
    private function __wakeup() {}

    /**
     * @var object[]
     */
    private static $hashes = [];

    /**
     * @param object $object
     * @return string
     */
    public static function getHash($object)
    {
        $hash = '$' . spl_object_hash($object) . '$';
        if (!isset(self::$hashes[$hash])) {
            self::$hashes[$hash] = $object;
        }
        return $hash;
    }

    /**
     * @param string $hash
     * @return object|null
     */
    public static function getObject($hash)
    {
        if (isset(self::$hashes[$hash])) {
            return self::$hashes[$hash];
        }
    }
}