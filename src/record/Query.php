<?php namespace EugeneErg\SQLPreprocessor\Record;

use EugeneErg\SQLPreprocessor\Hasher;

class Query extends AbstractRecord
{
    /**
     * @var AbstractRecord[]
     */
    private $useRecords = [];

    /**
     * @param  $record
     */
    public function useRecord(AbstractRecord $record)
    {
        $this->useRecords[Hasher::getHash($record)] = $record;
    }

    /**
     * @return AbstractRecord[]
     */
    public function getUsedRecords()
    {
        return $this->useRecords;
    }
}