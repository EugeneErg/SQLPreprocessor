<?php namespace EugeneErg\SQLPreprocessor;

class Structure
{
    private $validate;
    private $children;
    private $blocks = [];

    public function __construct(\Closure $callback, array $children = [])
    {
        $this->validate = $callback;
        $this->children = $children;
    }

    public function __invoke(array $blocks)
    {
        $this->blocks = $blocks;
        $validate = $this->validate;
        if (!$validate($this)) {
            throw new \Exception('invalid structure');
        }
        foreach ($this->blocks as $block) {
            if (!isset($this->children[$block->getName()])) {
                $child = $this->children[$block->getName()];
                $child($block->getChildren());
            }
        }
        $this->blocks = [];
    }

    public function count(Block ...$params)
    {
        $result = 0;
        foreach ($this->blocks as $block) {
            $result += in_array($block->getName(), $params);
        }
        return $result;
    }
}