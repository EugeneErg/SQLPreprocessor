<?php namespace EugeneErg\SQLPreprocessor\Raw\Item;

use EugeneErg\SQLPreprocessor\Parsers\ParserAbstract;
use EugeneErg\SQLPreprocessor\Raw\Item;
use EugeneErg\SQLPreprocessor\Raw\Items;

/**
 * Class StructureItem
 * @method Items getValue()
 */
abstract class StructureItem extends Item
{
    public function __construct(Items $value)
    {
        parent::__construct($value);
    }

    /**
     * @param ParserAbstract|null $parser
     * @return \EugeneErg\SQLPreprocessor\Raw|Item|mixed
     */
    public function getRawValue(ParserAbstract $parser = null)
    {
        return $this->getValue()->getRawValue($parser);
    }

    /*public function getRawValues(ParserAbstract $parser = null)
    {
        $result = [];
        foreach ($this->getValue() as $item) {
            if ($item instanceof self) {
                $result[] = $item->getRawValues($parser);
            }
            else {
                $result[] = $item->getRawValue();
            }
        }
        return $result;
    }*/
}