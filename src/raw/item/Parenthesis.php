<?php namespace EugeneErg\SQLPreprocessor\Raw\Item;

use EugeneErg\SQLPreprocessor\Raw\Items;

/**
 * Class Parenthesis
 * @method Items getValue()
 */
class Parenthesis extends StructureItem
{
    const TEMPLATE = "\\([^'\"\)\\(]*\\)";
    /**
     * @inheritDoc
     */
    public function __toString()
    {
        return "({$this->getValue()})";
    }
}