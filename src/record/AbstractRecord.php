<?php namespace EugeneErg\SQLPreprocessor\Record;

use EugeneErg\SQLPreprocessor\Link;

/**
 * Class AbstractRecord
 * @method static Container create(mixed ...$params)
 */
abstract class AbstractRecord
{
    private $object;

    /**
     * @var self
     */
    private $root;

    /**
     * @var Link[]
     */
    private $sequence;

    /**
     * @var object[]
     */
    private static $records = [];

    /**
     * @var Container
     */
    private $container;

    /**
     * @var Container
     */
    private $parentContainer;

    /**
     * AbstractRecord constructor.
     * @param mixed $object
     * @param Item[] $sequence
     * @param self|null $parent
     * @param self|null $root
     * @param Container $container
     */
    private function __construct($object, Container $container, $sequence = [], self $parent = null, self $root = null)
    {
        $this->root = is_null($root) ? $this : $root;
        $this->object = $object;
        $this->sequence = $sequence;
        $this->container = $container;
        $this->parentContainer = $parent;

        if (is_null($root)) {
            if (method_exists($this, 'initRoot')) {
                $this->initRoot();
            }
        }
        elseif (method_exists($this, 'initBranch')) {
            $this->initBranch();
        }
    }

    /**
     * @return mixed
     */
    public function getObject()
    {
        return $this->root->object;
    }

    /**
     * @return Link[]
     */
    public function getSequence()
    {
        return $this->sequence;
    }

    /**
     * @return static|null
     */
    public function getParent()
    {
        return $this->parentContainer ? self::getRecord($this->parentContainer) : null;
    }

    /**
     * @return $this
     */
    protected function getRoot()
    {
        return $this->root;
    }

    private static function getTree(array $branches)
    {
        //todo
        return [];
    }

    /**
     * @param Container[] $containers
     * @return array
     */
    public static function getTrees(array $containers)
    {
        $trees = [];
        foreach ($containers as $container) {
            $trees[self::$records[$container]->root][] = $container;
        }
        $result = [];
        foreach ($trees as $branches) {
            $result[] = self::getTree($branches);
        }
        return $result;
    }

    /**
     * @param Container[] $path
     * @param Link[] $sequence
     * @param mixed $object
     */
    protected static function addAssociate(array $path, array $sequence, $object = null)
    {
        self::$records[end($path)->__toString()] = (object) [
            'class' => static::class,
            'parent' => count($path) > 1 ? $path[count($path) - 2] : null,
            'root' => reset($path),
            'self' => null,
            'object' => $object,
            'sequence' => $sequence,
        ];
    }

    /**
     * @param mixed $object
     * @return Container
     */
    protected static function createContainer($object = null)
    {
        $root = new Container(
            function(array $path, array $sequence) {
                self::addAssociate($path, $sequence);
            }
        );
        self::addAssociate([$root], [], $object);
        return $root;
    }

    /**
     * @param Container $container
     * @return static
     */
    public static function getRecord(Container $container)
    {
        if (isset(self::$records["$container"]->self)) {
            return self::$records["$container"]->self;
        }
        $class = self::$records["$container"]->class;
        if (self::$records["$container"]->root !== $container) {
            $root = self::getRecord(self::$records["$container"]->root);
        }
        else {
            $root = null;
        }
        self::$records["$container"]->self = new $class(
            self::$records["$container"]->object,
            $container,
            self::$records["$container"]->sequence,
            self::$records["$container"]->parent,
            $root
        );
        return self::$records["$container"]->self;
    }

    public function getContainer()
    {
        return $this->container;
    }
}