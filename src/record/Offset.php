<?php namespace EugeneErg\SQLPreprocessor\Record;

/**
 * Class Offset
 * @package EugeneErg\SQLPreprocessor\Record
 */
class Offset extends AbstractRecord
{
    /**
     * @var array
     */
    private $keys;

    /**
     * @var array|null
     */
    private $arguments;

    /**
     * @param AbstractRecord $parent
     * @param array $keys
     * @param array|null $arguments
     * @return Container|void
     */
    public static function create(AbstractRecord $parent, array $keys, array $arguments = null)
    {
        $new = new self($parent);
        $new->keys = $keys;
        $new->arguments = $arguments;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->keys);
    }

    /**
     * @return array|null
     */
    public function getKeys()
    {
        return $this->keys;
    }

    /**
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @param int $pos
     * @return mixed[]|null
     */
    public function getKey($pos = 0)
    {
        if (isset($this->keys[$pos])) {
            return $this->keys[$pos];
        }

        return null;
    }
}
