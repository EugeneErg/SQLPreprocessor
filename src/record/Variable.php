<?php namespace EugeneErg\SQLPreprocessor\Record;

class Variable extends AbstractRecord
{
    /**
     * @param mixed $object
     * @return Container
     */
    public static function create($object)
    {
        return self::createContainer($object);
    }
}