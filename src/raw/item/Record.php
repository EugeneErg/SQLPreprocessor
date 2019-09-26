<?php namespace EugeneErg\SQLPreprocessor\Raw\Item;

use EugeneErg\SQLPreprocessor\Hasher;
use EugeneErg\SQLPreprocessor\Raw\Item;
use EugeneErg\SQLPreprocessor\Record\AbstractRecord;
use EugeneErg\SQLPreprocessor\Record\Container;

/**
 * Class Record
 * @method Container getValue()
 */
class Record extends Item
{
    const TEMPLATE = '\\$[0-9a-f]{32}\\$(?:\\s*\\.\\s*(?:[a-zA-Z_]\\w*\\(?!\\s*\\())*';

    /**
     * @inheritDoc
     */
    public function __construct($value)
    {
        $hash = substr($value, 0, 34);

        if ($hash !== $value) {
            $fields = explode('.', substr($value, 34));
        }
        else {
            $fields = [];
        }

        $container = Hasher::getObject($value);

        if (!$container instanceof Container) {
            throw new \Exception('invalid record class');
        }

        $record = AbstractRecord::getRecord($container);

        foreach ($fields as $field) {
            $record = $record->$field;
        }

        parent::__construct($record->getContainer());
    }

    public function getRawValue()
    {
        return $this->getValue();
    }
}