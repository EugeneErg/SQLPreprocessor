<?php namespace EugeneErg\SQLPreprocessor\Record;

class Variable extends AbstractRecord
{
    public static function create($object)
    {
        return self::createContainer($object);
    }
}