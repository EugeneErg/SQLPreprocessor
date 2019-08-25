<?php namespace EugeneErg\SQLPreprocessor;

/**
 * Class Variable
 * @package EugeneErg\SQLPreprocessor
 */
class Variable implements \ArrayAccess
{
    use HashesTrait;

    /**
     * @var array|Raw|SQL|object|string|null
     */
    private $object;

    /**
     * @var object[]
     */
    private $sequence = [];

    /**
     * @var self[]
     */
    private $children = [];

    /**
     * @var string[]
     */
    private static $publicStaticMethods = ['getObject', 'getSequence'];

    /**
     * Variable constructor.
     * @param SQL|string|object|array|Raw|null $object
     */
    public function __construct($object = null)
    {
        $this->getCurrentHash();
        $this->object = $object;
    }

    /**
     * @param string|self $variable
     * @return mixed
     */
    private static function getObject($variable)
    {
        return self::getByHash($variable)->object;
    }

    /**
     * @param string|self $variable
     * @return array
     */
    private static function getSequence($variable)
    {
        return self::getByHash($variable)->sequence;
    }

    /**
     * @param string $name
     * @return self
     */
    public function __get($name)
    {
        if (!isset($this->children[$name])) {
            $new = clone $this;
            $new->sequence[] = (object) [
                'name' => $name,
                'args' => [],
                'is_method' => false,
            ];
            $this->children[$name] = $new;
        }
        return $this->children[$name];
    }

    /**
     * @param string|int $offset
     * @return self
     * @throws \Exception
     */
    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return self
     */
    public function __call($name, array $arguments)
    {
        $new = clone $this;
        $new->sequence[] = (object) [
            'name' => $name,
            'args' => $arguments,
            'is_method' => true,
        ];
        return $new;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @return self
     */
    public function offsetSet($offset = null, $value = null)
    {
        return $this->__call('offsetSet', func_get_args());
    }

    /**
     * @param mixed $offset
     * @return self
     */
    public function offsetUnset($offset = null)
    {
        return $this->__call('offsetUnset', func_get_args());
    }

    /**
     * @param mixed $offset
     * @return self
     */
    public function offsetExists($offset = null)
    {
        return $this->__call('offsetExists', func_get_args());
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     * @throws \Exception
     */
    public static function __callStatic($name, array $arguments)
    {
        if (in_array($name, self::$publicStaticMethods)) {
            return call_user_func_array([self::class, $name], $arguments);
        }
        throw new \Exception("Inaccessible static method $name");
    }
}