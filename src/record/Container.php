<?php namespace EugeneErg\SQLPreprocessor\Record;

use EugeneErg\SQLPreprocessor\Hasher;

/**
 * Class Container
 * @package EugeneErg\SQLPreprocessor\Mutator
 */
class Container
{
    const TYPE_METHOD = 'method';
    const TYPE_PROPERTY = 'property';
    const TYPE_INITIATOR = null;

    /**
     * @var ?string
     */
    private $type;

    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $arguments;

    /**
     * @var string
     */
    private $hash;

    /**
     * @var array
     */
    private $containers;

    /**
     * @var self[]
     */
    private $path = [];

    /**
     * @var self[]
     */
    private $properties = [];

    /**
     * @var self[]
     */
    private $methods = [];

    /**
     * @var array
     */
    private $values = [];

    /**
     * @var AbstractRecord
     */
    private $record;

    /**
     * @var \Closure
     */
    private $callback;

    /**
     * Container constructor.
     * @param AbstractRecord $record
     * @param array $containers
     * @param \Closure|null $callback
     */
    public function __construct(AbstractRecord $record, array &$containers, \Closure $callback = null)
    {
        $this->record = $record;
        $this->containers = &$containers;
        $this->hash = Hasher::getHash($this);
        $this->callback = $callback;
        $this->addContainer();
    }

    private function addContainer()
    {
        $this->containers[$this->hash] = function() {
            $this->getChain();
        };
    }

    /**
     * @param string $name
     * @return self mixed
     */
    public function __get($name)
    {
        if (!isset($this->properties[$name])) {
            $callback = $this->callback;
            $new = $callback ? $callback(self::TYPE_PROPERTY, $name) : null;

            if (is_null($new)) {
                $new = clone $this;
                $new->path[] = $this;
                $new->name = $name;
                $new->type = self::TYPE_PROPERTY;
            }

            $this->properties[$name] = $new;
        }

        return $this->properties[$name];
    }

    /**
     * @param array $arguments
     * @return string
     */
    private function getKey(array $arguments)
    {
        $keys = [];

        foreach ($arguments as $argument) {
            $pos = array_search($argument, $this->values, true);

            if ($pos === false) {
                $pos = count($this->values);
                $this->values[] = $argument;
            }

            $keys[] = $pos;
        }

        return implode('-', $keys);
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return self
     */
    public function __call($name, array $arguments)
    {
        $key = $this->getKey($arguments);

        if (!isset($this->methods[$name][$key])) {
            $callback = $this->callback;
            $new = $callback ? $callback(self::TYPE_METHOD, $name, $arguments) : null;

            if (is_null($new)) {
                $new = clone $this;
                $new->path[] = $this;
                $new->name = $name;
                $new->arguments = $arguments;
                $new->type = self::TYPE_METHOD;
            }

            $this->methods[$name][$key] = $new;
        }

        return $this->methods[$name][$key];
    }

    private function __clone()
    {
        $this->hash = Hasher::getHash($this);
        $this->methods = [];
        $this->properties = [];
        $this->arguments = [];
        $this->callback = null;
        $this->addContainer();
    }

    /**
     * @param mixed ...$arguments
     * @return self
     */
    public function __invoke(...$arguments)
    {
        return $this->__call('__invoke', $arguments);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->hash;
    }

    /**
     * @return AbstractRecord
     * @throws \Exception
     */
    private function getChain()
    {
        if (is_null($this->record) && count($this->path)) {
            $parent = AbstractRecord::find(end($this->path));
            $container = $this->type === self::TYPE_METHOD
                ? Method::create($this->name, $this->arguments, $parent)
                : Property::create($this->name, $parent);
            $this->record = AbstractRecord::find($container);
        }

        return $this->record;
    }
}