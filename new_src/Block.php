<?php namespace EugeneErg\SQLPreprocessor;

class Block
{
    private $name;
    private $num;
    private $children = [];

    public function __construct($name, array $children = [], $num = 0)
    {
        $this->name = $name;
        $this->num = $num;
        $this->children = $children;
    }

    public function addChild(self $child)
    {
        $this->children[] = $child;
    }

    public function getNum()
    {
        return $this->num;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getChildren()
    {
        return $this->children;
    }
}