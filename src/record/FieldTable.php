<?php namespace EugeneErg\SQLPreprocessor\record;

class FieldTable extends AbstractRecord
{
    /**
     * @var self[][]
     */
    private static $contexts = [];

    /**
     * @var Container
     */
    private $context;

    /**
     * @var int
     */
    private $number;

    /**
     * @param string $alias
     * @param string[]|string|null $tableName
     * @return Container
     * @throws \Exception
     */
    public static function create($alias, $tableName = null)
    {
        return self::createContainer((object) [
            'tableName' => (array) $tableName,
            'alias' => $alias
        ]);
    }

    /**
     * @return string[]
     */
    public function getTableName()
    {
        return $this->getObject()->tableName;
    }

    /**
     * @return string
     */
    public function getAlias()
    {
        return $this->getObject()->alias;
    }

    /**
     * @param Container $context
     */
    public function setContext(Container $context)
    {
        $alias = $this->getAlias();
        if ($this->context) {
            unset(self::$contexts[$this->context][$alias][$this->number]);
        }
        $this->context = $context;
        self::$contexts[$context][$alias][] = $this;
        end(self::$contexts[$context][$alias]);
        $this->number = key(self::$contexts[$context][$alias]);
    }

    /**
     * @return Container
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @param Container $context
     * @param string $alias
     * @return self[]
     */
    public static function getByContext(Container $context, $alias)
    {
        return isset(self::$contexts[$context][$alias]) ? self::$contexts[$context][$alias] : [];
    }
}