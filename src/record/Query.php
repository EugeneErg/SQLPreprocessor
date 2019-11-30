<?php namespace EugeneErg\SQLPreprocessor\Record;

use EugeneErg\SQLPreprocessor\Builder;

/**
 * Class Query
 * @package EugeneErg\SQLPreprocessor\Record
 */
class Query extends AbstractRecord
{
    /**
     * @var Builder
     */
    private $builder;

    /**
     * @param Builder $builder
     * @return Container
     */
    public static function create(Builder $builder)
    {
        $new= new self();
        $new->builder = $builder;

        return $new->getContainer();
    }
}
