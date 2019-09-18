<?php namespace EugeneErg\SQLPreprocessor\Record;

/**
 * Class TableRecord
 */
class Table extends AbstractRecord
{
    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->getObject()->table;
    }

    /**
     * @return string|null
     */
    public function getBaseName()
    {
        return $this->getObject()->base;
    }
}