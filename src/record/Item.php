<?php namespace EugeneErg\SQLPreprocessor\Record;

use EugeneErg\SQLPreprocessor\Raw\Item as OtherItem;

class Item extends AbstractRecord
{
    public static function create(OtherItem $item)
    {
        return self::createContainer($item);
    }
}