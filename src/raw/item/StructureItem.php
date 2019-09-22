<?php namespace EugeneErg\SQLPreprocessor\Raw\Item;

use EugeneErg\SQLPreprocessor\Raw\Items;

/**
 * Class StructureItem
 * @method Items getValue()
 */
abstract class StructureItem extends ValueItem
{
    public function __construct(Items $value)
    {
        parent::__construct($value);
    }
}