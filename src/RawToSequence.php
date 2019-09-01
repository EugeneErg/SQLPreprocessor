<?php namespace EugeneErg\SQLPreprocessor;

/**
 * Class RawToSequence
 * @package EugeneErg\SQLPreprocessor
 */
class RawToSequence
{
    const CONTEXT_QUERY = 'query';
    const CONTEXT_DELETE = 'delete';
    const CONTEXT_ARGUMENT = 'argument';
    const CONTEXT_SELECT = 'select';
    const CONTEXT_UPDATE = 'update';
    const CONTEXT_INSERT = 'insert';

    /**
     * RawToSequence constructor.
     * @param Raw $raw
     */
    private function __construct(Raw $raw)
    {

    }

    /**
     * @param string $context
     * @param Raw $raw
     */
    public static function convertFromContext(Raw $raw, $context = self::CONTEXT_QUERY)
    {
        switch ($context) {
            case self::CONTEXT_DELETE:
            case self::CONTEXT_ARGUMENT:
            case self::CONTEXT_SELECT:
            case self::CONTEXT_UPDATE:
            case self::CONTEXT_INSERT:


            case self::CONTEXT_QUERY:
            default:
                return new class
        }
    }
}