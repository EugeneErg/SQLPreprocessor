<?php namespace EugeneErg\SQLPreprocessor\Raw\Item;

use EugeneErg\SQLPreprocessor\Raw\Item;

class Method extends Item
{
    const TEMPLATE = '\\.\\s*[a-zA-Z_]\\w*\\b';

    public function __construct($value)
    {
        parent::__construct(trim(substr($value, 1)));
    }

    public function __toString()
    {
        return '.' . $this->getValue();
    }
}