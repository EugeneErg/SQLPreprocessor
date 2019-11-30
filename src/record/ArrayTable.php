<?php namespace EugeneErg\SQLPreprocessor\Record;

/**
 * Class ArrayTable
 * @package EugeneErg\SQLPreprocessor\Record
 */
class ArrayTable extends AbstractRecord
{
    /**
     * @var array
     */
    private $columns;

    /**
     * @var array
     */
    private $columnNames = [];

    /**
     * @param array $array
     * @return Container
     */
    public static function create(array $array)
    {
        $new = new self();
        $new->columns = array_values($array);

        return $new->getContainer();
    }

    /**
     * @param string $columnName
     * @return array
     * @throws \Exception
     */
    public function getColumn($columnName)
    {
        if (!isset($this->getColumnNames()[$columnName])) {
            throw new \Exception('column not exists');
        }

        $result = [];

        foreach ($this->columns as $row) {
            $result[] = isset($row[$columnName]) ? $row[$columnName] : null;
        }

        return $result;
    }

    public function getColumnNames()
    {
        if (isset($this->columnNames)) {
            return $this->columnNames;
        }

        return $this->columnNames = array_keys(
            count($this->columns) > 1
                ? call_user_func_array('array_replace', $this->columns)
                : $this->columns
        );
    }

    public function getRowCount()
    {
        return count($this->columns);
    }

    public function getColumnTypes($columnName)
    {
        //todo
    }
}