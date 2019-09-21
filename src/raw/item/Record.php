<?php namespace EugeneErg\SQLPreprocessor\Raw\Item;

use EugeneErg\SQLPreprocessor\Hasher;
use EugeneErg\SQLPreprocessor\Record\Container;

/**
 * Class Record
 * @method Container getValue()
 */
class Record extends ValueItem
{
    const TEMPLATE = '\\$[0-9a-f]{32}\\$';

    public function __construct($value)
    {
        parent::__construct(Hasher::getObject($value));
    }
}