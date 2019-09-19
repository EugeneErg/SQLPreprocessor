<?php namespace EugeneErg\SQLPreprocessor\Record;

class ArrayTable extends AbstractRecord
{
    private $columnNames = [];

    public static function create(array $array)
    {
        return self::createContainer(array_values($array));
    }

    public function getColumn($columnName)
    {
        if (!isset($this->getColumnNames()[$columnName])) {
            throw new \Exception('column not exists');
        }
        $result = [];
        foreach ($this->getObject() as $row) {
            $result[] = isset($row[$columnName]) ? $row[$columnName] : null;
        }
        return $result;
    }

    public function getColumnNames()
    {
        if ($this->getRoot() !== $this) {
            return $this->getRoot()->getColumnNames();
        }
        if (isset($this->columnNames)) {
            return $this->columnNames;
        }
        $array = $this->getObject();
        return $this->columnNames = array_keys(
            count($array) > 1
                ? call_user_func_array('array_replace', $array)
                : $array
        );
    }

    public function getRowCount()
    {
        return count($this->getObject());
    }

    public function getColumnTypes($columnName)
    {
        //todo
    }

    public function getCurrentColumn()
    {
        $sequence = $this->getSequence();
        if (count($sequence) < 1
            || $sequence[0]->isMethod()
            || !isset($this->getColumnNames()[$sequence[0]->getName()])
        ) {
            return null;
        }
        return $this->getColumn($sequence[0]->getName());
    }

    public function getCurrentType()
    {
        //todo
    }
}