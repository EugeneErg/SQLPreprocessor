<?php namespace EugeneErg\SQLPreprocessor;

use EugeneErg\SQLPreprocessor\Record as PathRecord;

/**
 * Class Record
 * @method static self fromTable(string $tableName, string|null $baseName)
 * @method static self fromQuery()
 * @method static self fromArrayTable(array $array)
 * @method static self fromLink(Link $link)
 * @method static self fromVariable(mixed $object)
 * @method static PathRecord\AbstractRecord getController()
 */
class Record
{
    use HashesTrait;

    /**
     * @var mixed
     */
    private $object;

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
     * @var string
     */
    private $type;

    /**
     * @var string[]
     */
    private static $publicMethods = ['controller'];

    /**
     * @var array
     */
    private static $constructors = ['table', 'query', 'variable', 'arrayTable', 'link'];

    /**
     * @var
     */
    private $controllerName;

    /**
     * @var self
     */
    private $root;

    /**
     * @var PathRecord\AbstractRecord
     */
    private $controller;

    /**
     * self constructor.
     * @param mixed $object
     * @param string $controllerName
     */
    private function __construct($object, $controllerName)
    {
        $this->object = $object;
        $this->controllerName = $controllerName;
        $this->hash = Hasher::getHash($this);
        $this->root = $this;
    }

    /**
     * @param string $tableName
     * @param string|null $baseName
     * @return Record
     */
    private static function createTableRecord($tableName, $baseName= null)
    {
        return new self((object) [
            'table' => $tableName,
            'base' => $baseName,
        ], PathRecord\Table::class);
    }

    /**
     * @return Record
     */
    private static function createQueryRecord()
    {
        return new self(null, PathRecord\Query::class);
    }

    /**
     * @param mixed $object
     * @return Record
     */
    private static function createVariableRecord($object)
    {
        return new self($object, PathRecord\Variable::class);
    }

    /**
     * @param array $array
     * @return Record
     */
    private static function createArrayTableRecord(array $array)
    {
        return new self($array, PathRecord\ArrayTable::class);
    }

    /**
     * @param Link $link
     * @return Record
     */
    private static function createLinkRecord(Link $link)
    {
        return new self($link, PathRecord\Link::class);
    }

    /**
     * @return PathRecord\AbstractRecord
     */
    private function getControllerMethod()
    {
        if (!isset($this->controller)) {
            if ($this->root === $this) {
                $this->controller = new $this->controllerName($this->object);
            }
            else {
                $this->controller = new $this->controllerName(null, $this->sequence, $this->root->getController());
            }
        }

        return $this->controller;
    }

    /**
     * @param string $name
     * @return self
     */
    public function __get($name)
    {
        if (!isset($this->children[$name])) {
            $new = clone $this;
            $new->root = $this->root;
            $new->sequence[] = new Link($name);
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
            $new->root = $this->root;
            $new->sequence[$name] = new Link(
                $name,
                $arguments,
                true
            );
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
        if (substr($name, 0, '4') === 'get' && in_array(substr($name, 3), self::$publicMethods)) {
            $hash = array_shift($arguments);
            $variable = Hasher::getObject($hash);
            if (!$variable instanceof self) {
                throw new \Exception('invalid class name');
            }
            return call_user_func_array([$variable, "{$name}Method"], $arguments);
        }
        if (substr($name, 0, '4') === 'from' && in_array($name, self::$constructors)) {
            return call_user_func_array([self::class, 'create' . substr($name, 4) . 'Record'], $arguments);
        }
        throw new \Exception("Inaccessible static method $name");
    }
}