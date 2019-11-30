<?php namespace EugeneErg\SQLPreprocessor\Record;

/**
 * Class TableRecord
 */
class Table extends AbstractRecord
{
    /**
     * @var string
     */
    private $tableName;

    /**
     * @var string|null
     */
    private $baseName;

    /**
     * @param string $tableName
     * @param string|null $baseName
     * @return Container
     */
    public static function create($tableName, $baseName = null)
    {
        $new = new self();
        $new->tableName = $tableName;
        $new->baseName = $baseName;

        return $new->getContainer();
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * @return string|null
     */
    public function getBaseName()
    {
        return $this->baseName;
    }
}