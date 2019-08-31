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
     * @var array
     */
    private $arguments = [];

    /**
     * @var self[][]
     */
    private $methods = [];

    /**
     * @var string[]
     */
    private static $publicStaticMethods = ['getObject', 'getSequence', 'getVariable'];

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
    private function getObject($variable)
    {
        return $this->object;
    }

    /**
     * @param string|self $variable
     * @return array
     */
    private function getSequence($variable)
    {
        return $this->sequence;
    }

    /**
     * @param string|self $variable
     * @return mixed
     */
    private function getVariable($variable)
    {
        return (object) [
            'object' => $this->object,
            'sequence' => $this->sequence,
        ];
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
    public function offsetGet($offset = null)
    {
        if (func_num_args() !== 1 || !(is_int($offset) || is_string($offset))) {
            return $this->__call('offsetGet', func_get_args());
        }
        return $this->__get($offset);
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return self
     */
    public function __call($name, array $arguments)
    {
        $keys = [];
        foreach ($arguments as $argument) {
            $key = array_search($argument, $this->arguments, true);
            if (is_null($key)) {
                $key = count($this->arguments);
                $this->arguments[] = $argument;
            }
            $keys[] = $key;
        }

        $key = implode('-', $keys);

        if (!isset($this->methods[$name][$key])) {
            $new = clone $this;
            $new->sequence[$name] = (object) [
                'name' => $name,
                'args' => $arguments,
                'is_method' => true,
            ];
            $this->methods[$name][$key] = $new;
        }
        return $this->methods[$name][$key];
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
        if (count($arguments) < 1) {
            throw new \Exception('invalid count arguments');
        }
        $hash = array_shift($arguments);
        $variable = self::getByHash($hash);
        if (in_array($name, self::$publicStaticMethods)) {
            return call_user_func_array([$variable, $name], $arguments);
        }
        throw new \Exception("Inaccessible static method $name");
    }

    /**
     * __invoke block function is context
     * @param mixed ...$params
     * @return Variable
     */
    public function __invoke(...$params)
    {
        return $this->__call('__invoke', $params);
    }
}