<?php namespace EugeneErg\SQLPreprocessor\Raw\Item;

use EugeneErg\SQLPreprocessor\Raw\Item;

class Number extends Item
{
    const TEMPLATE = '\\b(?:\\d*\\.\\d+|\\d+\\.?\\d*)(?:[Ee][+-]?\\d+)?\\b';

    public function getRawValue()
    {
        return $this->getValue();
    }
}