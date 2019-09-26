<?php namespace EugeneErg\SQLPreprocessor\Raw\Item;

use EugeneErg\SQLPreprocessor\Raw\Item;
use EugeneErg\SQLPreprocessor\Record\Container;
use EugeneErg\SQLPreprocessor\record\FieldTable;

/**
 * Class Field
 * @method Container getValue()
 */
class Field extends Item
{
    const TEMPLATE = '`(?:[^`]*(?:``)*)+`'
        . '(?:\\s*\\.\\s*`(?:[^`]*(?:``)*)+`)*'
        . '(?:\\s*(?:\\.\\s*[a-zA-Z_]\\w*\\(?!\\s*\\()|`(?:[^`]*(?:``)*)+`))?';

    /**
     * Field constructor.
     * @param string $value
     * @throws \Exception
     */
    public function __construct($value)
    {
        preg_match_all('/(?:\\.|\\s)\\s*(?:`(?:[^`]*(?:``)*)+`|[a-zA-Z_]\\w*)/', $value, $matches);
        $tableName = [];
        $alias = null;
        $field = null;
        foreach ($matches as $match) {
            switch ($match[0]) {
                case '`':
                    if (count($tableName)) {
                        $alias = $this->fieldToString($match);
                    }
                    else {
                        $tableName[] = $this->fieldToString($match);
                    }
                    break;
                case '.':
                    if ($match[strlen($match) - 1] === '`') {
                        $tableName[] = $this->fieldToString(trim(substr($match, 1)));
                    }
                    else {
                        $field = trim(substr($match, 1));
                    }
                    break;
                default:
                    $alias = $this->fieldToString($match);
            }
        }
        if (is_null($alias)) {
            $value = FieldTable::create(end($tableName), $tableName);
            if (!is_null($field)) {
                $value = $value->$field;
            }
        }
        else {
            $value = FieldTable::create($alias, $tableName);
        }
        parent::__construct($value);
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function __toString()
    {
        $record = FieldTable::getRecord($this->getValue());
        $tableName = $record->getTableName();
        $result = [];

        foreach ($tableName as $field) {
            $result[] = $this->stringToField($field);
        }

        $sequence = $record->getSequence();

        if (count($sequence) > 1
            || (count($sequence) === 1
                && $sequence[0]->isMethod()
            )
        ) {
            throw new \Exception('invalid logic');
        }

        $alias = $record->getAlias();

        return implode('`.`', $result)
            . (count($sequence) ? "." . $sequence[0]->getName() : '')
            . ($alias !== end($tableName) ? ' ' . $this->stringToField($alias) : '');
    }

    /**
     * @param string $value
     * @return string
     */
    private function stringToField($value)
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }

    /**
     * @param string $value
     * @return string
     */
    private function fieldToString($value)
    {
        return str_replace('``', '`', substr($value, 1, -1));
    }
}