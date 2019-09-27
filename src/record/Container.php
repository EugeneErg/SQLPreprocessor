<?php namespace EugeneErg\SQLPreprocessor\Record;

use EugeneErg\SQLPreprocessor\Hasher;
use EugeneErg\SQLPreprocessor\HashesTrait;
use EugeneErg\SQLPreprocessor\Link;

/**
 * Class Record
 */
class Container
{
    use HashesTrait;

    /**
     * @var Link[]
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
     * @var self[]
     */
    private $path = [];

    /**
     * @var \Closure
     */
    private $callback;

    /**
     * self constructor.
     * @param \Closure $callback
     */
    public function __construct(\Closure $callback)
    {
        $this->hash = Hasher::getHash($this);
        $this->path = [$this];
        $this->callback = $callback;
    }

    /**
     * @param string $name
     * @return self
     */
    public function __get($name)
    {
        if (!isset($this->children[$name])) {
            $new = clone $this;
            $new->path[] = $new;
            $new->sequence[] = new Link($name);
            $callback = $this->callback;
            $callback($new->path, $new->sequence);
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
     * @param string|[] $name
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
            $new->path[] = $new;
            $new->sequence[$name] = new Link(
                $name,
                $arguments,
                true
            );
            $callback = $this->callback;
            $callback($new->path, $new->sequence);
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
     * __invoke block function is context
     * @param mixed ...$params
     * @return self
     */
    public function __invoke(...$params)
    {
        return $this->__call('__invoke', $params);
    }
}