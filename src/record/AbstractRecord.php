<?php namespace EugeneErg\SQLPreprocessor\Record;

/**
 * Class AbstractRecord
 * @method static Container create(mixed ...$params)
 */
abstract class AbstractRecord
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var self
     */
    private $parent;

    /**
     * @var \Closure[]
     */
    private static $containers = [];

    /**
     * AbstractRecord constructor.
     * @param AbstractRecord|null $parent
     * @param \Closure|null $callback
     */
    protected function __construct(self $parent = null, \Closure $callback = null)
    {
        $this->container = new Container($this, self::$containers, $callback);
        $this->parent = $parent;
    }

    /**
     * @param $hash
     * @return self
     * @throws \Exception
     */
    public static function find($hash)
    {
        if (!isset(self::$containers["$hash"])) {
            throw new \Exception('invalid hash');
        }

        $callback = self::$containers["$hash"];

        return $callback();
    }

    /**
     * @return AbstractRecord|null
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @return Container
     */
    public function getContainer()
    {
        return $this->container;
    }
}
