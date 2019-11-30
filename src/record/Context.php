<?php namespace EugeneErg\SQLPreprocessor\Record;

use EugeneErg\SQLPreprocessor\Hasher;

/**
 * Class Context
 * @package EugeneErg\SQLPreprocessor\Record
 */
class Context extends AbstractRecord
{
    /**
     * @var AbstractRecord[]
     */
    private $useRecords = [];

    /**
     * @return Container
     */
    public static function create()
    {
        return (new self())->getContainer();
    }

    /**
     * @param AbstractRecord $record
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
