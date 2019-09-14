<?php namespace EugeneErg\SQLPreprocessor\Parsers;

use EugeneErg\SQLPreprocessor\Link;
use EugeneErg\SQLPreprocessor\Raw;

/**
 * Interface ParserInterface
 * @package EugeneErg\SQLPreprocessor\Parsers
 */
abstract class ParserAbstract
{
    const TYPE_QUERY = 'query';
    const TYPE_LEFT_JOIN = 'query';
    const TYPE_RIGHT_JOIN = 'query';
    const TYPE_FROM = 'query';
    const TYPE_JOIN = 'query';
    const TYPE_CORRELATE = 'query';
    const TYPE_UNION = 'query';
    const TYPE_INNER_JOIN = 'query';
    const TYPE_OUTER_JOIN = 'query';
    const TYPE_SELECT = 'select';
    const TYPE_DELETE = 'delete';
    const TYPE_UPDATE = 'select';
    const TYPE_INSERT = 'select';
    const TYPE_ORDER_BY = 'order by';
    const TYPE_GROUP_BY = 'group by';
    const TYPE_ARGUMENT = 'argument';
    const TYPE_SET = 'set';
    const TYPE_WHERE = 'where';
    const TYPE_ON = 'on';
    const TYPE_USING = 'using';
    const TYPE_HAVING = 'having';
    const TYPE_SWITCH = 'default';
    const TYPE_DEFAULT = 'default';
    const TYPE_IF = 'default';
    const TYPE_CASE = 'default';
    const TYPE_ELSEIF = 'default';

    /**
     * @param Raw\Items $items
     * @return Link[]
     */
    abstract public static function getQuerySequence(Raw\Items $items);

    /**
     * @param Raw\Items $items
     * @return Link[]
     */
    abstract public static function getSelectSequence(Raw\Items $items);

    /**
     * @param Raw\Items $items
     * @return Link[]
     */
    abstract public static function getDeleteSequence(Raw\Items $items);

    /**
     * @param Raw\Items $items
     * @return Link[]
     */
    abstract public static function getOrderBySequence(Raw\Items $items);

    /**
     * @param Raw\Items $items
     * @return Link[]
     */
    abstract public static function getGroupBySequence(Raw\Items $items);

    /**
     * @param Raw\Items $items
     * @return Link[]
     */
    abstract public static function getWhereSequence(Raw\Items $items);

    /**
     * @param Raw\Items $items
     * @return Link[]
     */
    abstract public static function getOnSequence(Raw\Items $items);

    /**
     * @param Raw\Items $items
     * @return Link[]
     */
    abstract public static function getUsingSequence(Raw\Items $items);

    /**
     * @param Raw\Items $items
     * @return Link[]
     */
    abstract public static function getUnionSequence(Raw\Items $items);

    /**
     * @param Raw\Items $items
     * @return Link[]
     */
    abstract public static function getHavingSequence(Raw\Items $items);

    /**
     * @param Raw\Items $items
     * @return Link[]
     */
    abstract public static function getSetSequence(Raw\Items $items);

    /**
     * @param Raw\Items $items
     * @return Link[]
     */
    abstract public static function getArgumentSequence(Raw\Items $items);

    /**
     * @param Raw\Items $items
     * @return Link[]
     */
    abstract public static function getDefaultSequence(Raw\Items $items);

    /**
     * @param string $string
     * @return string
     */
    private static function camelCase($string)
    {
        $words = explode(' ', $string);
        array_walk($words, 'ucfirst');
        return implode('', $words);
    }

    /**
     * @param Raw\Items $items
     * @param string $type
     * @return Link[]
     */
    public static function getSequence($items, $type = self::TYPE_QUERY)
    {
        return call_user_func([static::class, 'get' . self::camelCase($type) . 'Sequence'], $items);
    }
}