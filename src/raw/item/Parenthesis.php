<?php namespace EugeneErg\SQLPreprocessor\Raw\Item;

class Parenthesis extends StructureItem
{
    const TEMPLATE = "\\([^\\)\\(]*\\)";
    const INCLUDE_TEMPLATE = '(?<=.).*(?=.)';

    /**
     * @inheritDoc
     */
    public function __toString()
    {
        return "({$this->getValue()})";
    }
}