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
    const TYPE_UPDATE = 'update';
    const TYPE_INSERT = 'insert';

    const TYPE_ORDER_BY = 'order by';
    const TYPE_GROUP_BY = 'group by';
    const TYPE_ARGUMENT = 'argument';

    const TYPE_WHERE = 'argument';
    const TYPE_ON = 'argument';
    const TYPE_HAVING = 'argument';
    const TYPE_USING = 'argument';

    const TYPE_SWITCH = 'default';
    const TYPE_DEFAULT = 'default';
    const TYPE_IF = 'default';
    const TYPE_CASE = 'default';
    const TYPE_ELSEIF = 'default';

    /**
     * @var Raw\Items
     */
    protected $items;

    /**
     * @return object
     */
    abstract public function getQuerySequence();

    /**
     * @return Link[]
     */
    abstract public function getSelectSequence();

    /**
     * @return Link[]
     */
    abstract public function getDeleteSequence();

    /**
     * @return Link[]
     */
    abstract public function getOrderBySequence();

    /**
     * @return Link[]
     */
    abstract public function getGroupBySequence();

    /**
     * @return Link[]
     */
    abstract public function getArgumentSequence();

    /**
     * @return Link[]
     */
    abstract public function getDefaultSequence();

    /**
     * @return Link[]
     */
    abstract public function getUpdateSequence();

    /**
     * @return Link[]
     */
    abstract public function getInsertSequence();

    /**
     * @return Link[]
     */
    abstract protected function getLimitSequence();

    /**
     * @return Link[]
     */
    abstract protected function getDistinctSequence();

    /**
     * @param string $string
     * @return string
     */
    private static function camelCase($string)
    {
        $words = explode(' ', $string);
        array_walk($words, function($value) {
            return ucfirst($value);
        });

        return implode('', $words);
    }

    /**
     * @param Raw\Items $items
     * @param string $type
     * @return Link[]
     */
    public function getSequence(Raw\Items $items, $type = self::TYPE_QUERY)
    {
        $prevItems = $this->items;
        $this->items = $items;
        $result = $this->{'get' . self::camelCase($type) . 'Sequence'}();
        $this->items = $prevItems;

        return $result;
    }
}