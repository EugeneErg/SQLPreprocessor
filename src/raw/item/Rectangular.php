<?php namespace EugeneErg\SQLPreprocessor\Raw\Item;

class Rectangular extends StructureItem
{
    const TEMPLATE = "\\[[^\\]\\[]*\\]";
    const INCLUDE_TEMPLATE = '(?<=.).*(?=.)';

    /**
     * @inheritDoc
     */
    public function __toString()
    {
        return "[{$this->getValue()}]";
    }
}