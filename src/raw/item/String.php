<?php namespace EugeneErg\SQLPreprocessor\Raw\Item;

use EugeneErg\SQLPreprocessor\Raw\Item;

/**
 * Class String
 * @method string getValue()
 */
class String extends Item
{
    const TEMPLATE = "'(?:[^']*(?:'')*)+'|" . '"(?:[^"]*(?:"")*)+"';

    /**
     * String constructor.
     * @param string $value
     */
    public function __construct($value)
    {
        parent::__construct(
            str_replace($value[0] . $value[0], $value[0], substr($value, 1, -1))
        );
    }

    /**
     * @inheritDoc
     */
    public function __toString()
    {
        return "'" . str_replace("'", "''", $this->getValue()) . "'";
    }
}