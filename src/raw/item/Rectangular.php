<?php namespace EugeneErg\SQLPreprocessor\Raw\Item;

class Rectangular extends StructureItem
{
    const TEMPLATE = "\\[[^'\"\]\\[]*\\]";

    /**
     * @inheritDoc
     */
    public function __toString()
    {
        return "[{$this->getValue()}]";
    }
}