<?php namespace EugeneErg\SQLPreprocessor;

/**
 * Class Chain
 * @package EugeneErg\SQLPreprocessor
 */
class Link
{
    const TYPE_NORMAL = null;
    const TYPE_LOWER = false;
    const TYPE_UPPER = true;

    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $arguments = [];

    /**
     * @var bool
     */
    private $isMethod = false;

    /**
     * @var self[]
     */
    private $children = [];

    /**
     * Chain constructor.
     * @param string $name
     * @param array $arguments
     * @param bool $isMethod
     */
    public function __construct($name, array $arguments = [], $isMethod = false)
    {
        $this->name = $name;
        $this->arguments = $arguments;
        $this->isMethod = $isMethod;
    }

    /**
     * @param null|bool $type
     * @return string
     */
    public function getName($type = self::TYPE_NORMAL)
    {
        if (!is_string($this->name)) {
            return $this->name;
        }

        switch ($type) {
            case self::TYPE_LOWER: return strtolower($this->name);
            case self::TYPE_UPPER: return strtoupper($this->name);
            default: return $this->name;
        }
    }

    /**
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @return bool
     */
    public function isMethod()
    {
        return $this->isMethod;
    }

    /**
     * @param self $child
     */
    private function addChild(self $child)
    {
        $this->children[] = $child;
    }

    /**
     * @param self[]|\Closure $children
     */
    public function setChildren(array $children)
    {
        if ($children instanceof \Closure) {
            $this->children = $children;
            return;
        }

        $this->children = [];

        foreach ($children as $child) {
            $this->addChild($child);
        }
    }

    public function getChildren()
    {
        return $this->children;
    }
}