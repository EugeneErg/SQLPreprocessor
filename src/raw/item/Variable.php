<?php namespace EugeneErg\SQLPreprocessor\Raw\Item;

use EugeneErg\SQLPreprocessor\Raw\Item;

class Variable extends Item
{
    const TEMPLATE = '@[\\w\\.]*';

    public function __construct($value)
    {
        parent::__construct(substr($value, 1));
    }

    public function __toString()
    {
        return '@' . $this->getValue();
    }
}