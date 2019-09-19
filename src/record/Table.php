<?php namespace EugeneErg\SQLPreprocessor\Record;

/**
 * Class TableRecord
 */
class Table extends AbstractRecord
{
    /**
     * @param string $tableName
     * @param string|null $baseName
     * @return Container
     */
    public static function create($tableName, $baseName = null)
    {
        return self::createContainer((object) [
            'tableName' => $tableName,
            'baseName' => $baseName,
        ]);
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->getObject()->tableName;
    }

    /**
     * @return string|null
     */
    public function getBaseName()
    {
        return $this->getObject()->baseName;
    }
}