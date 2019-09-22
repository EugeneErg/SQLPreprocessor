<?php namespace EugeneErg\SQLPreprocessor\Parsers;

use EugeneErg\SQLPreprocessor\Link;
use EugeneErg\SQLPreprocessor\ParseException;
use EugeneErg\SQLPreprocessor\Raw;
use EugeneErg\SQLPreprocessor\Raw\Item\Context;
use EugeneErg\SQLPreprocessor\Raw\Item\Field;
use EugeneErg\SQLPreprocessor\Raw\Item\Method;
use EugeneErg\SQLPreprocessor\Raw\Item\Number;
use EugeneErg\SQLPreprocessor\Raw\Item\Parenthesis;
use EugeneErg\SQLPreprocessor\Raw\Item\Record;
use EugeneErg\SQLPreprocessor\Raw\Item\Rectangular;
use EugeneErg\SQLPreprocessor\Raw\Item\String;
use EugeneErg\SQLPreprocessor\Raw\Item\Variable;
use EugeneErg\SQLPreprocessor\Raw\Item\Word;
use EugeneErg\SQLPreprocessor\Raw\Items;
use EugeneErg\SQLPreprocessor\Record\AbstractRecord;
use EugeneErg\SQLPreprocessor\record\FieldTable;
use EugeneErg\SQLPreprocessor\Record\Item;
use EugeneErg\SQLPreprocessor\Record\Query;
use EugeneErg\SQLPreprocessor\Record\Table;

/**
 * Class Special
 * @package EugeneErg\SQLPreprocessor\Parsers
 */
class Special extends ParserAbstract
{
    /**
     * @var string[]
     */
    const ITEMS = [
        Field::class,
        Method::class,
        Number::class,
        Record::class,
        String::class,
        Variable::class,
        Word::class,
        Parenthesis::class,
        Rectangular::class,
    ];

    /**
     * @param Raw\Items $items
     * @param int $pos
     * @return string[]
     */
    private static function getFields(Raw\Items $items, $pos = 0)
    {
        $results = [];
        $items->pos(function($value, $type) use(&$results) {
            try {
                $result[] = self
            }
            catch () {

            }
            if ($type !== Raw\ItemAbstract::TYPE_FIELD
                || $value[0] !== '.'
            ) {
                return false;
            }
            $results[] = str_replace('``', '`', substr($value, 2, -1));
            return true;
        }, $pos, Raw\Items::POS_FLAG_NOT_MATCH);
        return $results;
    }

    /**
     * @inheritDoc
     */
    public function getInsertSequence()
    {
        // TODO: Implement getInsertSequence() method.
    }

    /**
     * @inheritDoc
     */
    public function getArgumentSequence()
    {
        $arguments = [];
        $activeItem = null;
        for ($num = 0; $num < count($this->items); $num++) {
            $item = $this->items[$num];
            switch ($item->getType()) {
                case Raw\ItemAbstract::TYPE_WORD:
                    if ($activeItem) {
                        $arguments[] = $activeItem;
                        $activeItem = null;
                    }
                    if (strtolower($item->getValue()) === 'null') {
                        $arguments[] = null;
                    }
                    elseif (in_array(strtolower($item->getValue()), ['or', 'and', 'not'])) {
                        $arguments[] = $item;
                    }
                    elseif (isset($this->items[$num + 1]) && $this->items[$num + 1]->is(Raw\ItemAbstract::TYPE_PARENTHESIS)) {
                        $activeItem = call_user_func_array(
                            [Query::create(), $item->getValue()],
                            $this->getSequence($this->items[$num + 1]->getValue(), self::TYPE_ARGUMENT)
                        );
                        $num++;
                    }
                    else {
                        $activeItem = Query::create()->{$item->getValue()};
                    }
                    break;
                case Raw\ItemAbstract::TYPE_PARENTHESIS://могут иметь методы
                case Raw\ItemAbstract::TYPE_SQL_VAR:
                    if ($activeItem) {
                        $arguments[] = $activeItem;
                    }
                    $activeItem = Item::create($item);
                    break;
                case Raw\ItemAbstract::TYPE_CONTEXT:
                    if ($activeItem) {
                        $arguments[] = $activeItem;
                        $activeItem = null;
                    }
                    $arguments[] = $item;
                    break;
                case Raw\ItemAbstract::TYPE_FIELD:
                    if ($activeItem && $item->getValue()[0] === '.') {
                        if (isset($this->items[$num + 1]) && $this->items[$num + 1]->is(Raw\ItemAbstract::TYPE_PARENTHESIS)) {
                            call_user_func_array([
                                    $activeItem,
                                    str_replace('``', '`', substr($item->getValue()[0], 1))
                                ],
                                self::getSequence($this->items[$num + 1]->getValue(), self::TYPE_ARGUMENT));
                            $num++;
                        }
                        else {
                            $activeItem->{
                                str_replace('``', '`', substr($item->getValue()[0], 1))
                            };
                        }
                    }
                    elseif ($item->getValue()[0] === '.') {
                        throw ParseException::incorrectLink($item);
                    }
                    else {
                        if ($activeItem) {
                            $arguments[] = $activeItem;
                        }
                        $fields = self::getFields($this->items, $num + 1);
                        if (count($fields) > 1) {
                            $activeItem = Table::create($fields[0],
                                str_replace('``', '`', substr($item->getValue(), 1, -1))
                            );
                            $num++;
                        }
                        else {
                            $activeItem = Table::create(str_replace('``', '`',
                                substr($item->getValue(), 1, -1)
                            ));
                        }
                    }
                    break;
                case Raw\ItemAbstract::TYPE_RECTANGULAR:
                    if (!$activeItem) {
                        $arguments[] = Item::create($item);
                    }
                    elseif (isset($this->items[$num + 1]) && $this->items[$num + 1]->is(Raw\ItemAbstract::TYPE_PARENTHESIS)) {
                        $activeItem = call_user_func_array(
                            [$activeItem, $item->getValue()],
                            self::getArgumentSequence($this->items[$num + 1]->getValue())
                        );
                    }
                    else {
                        $activeItem = $activeItem[$item->getValue()];
                    }
                    break;
                case Raw\ItemAbstract::TYPE_METHOD:
                    if (!$activeItem) {
                        throw ParseException::incorrectLink($item);
                    }
                    if (isset($this->items[$num + 1]) && $this->items[$num + 1]->is(Raw\ItemAbstract::TYPE_PARENTHESIS)) {
                        $activeItem = call_user_func_array(
                            [$activeItem, $item->getValue()],
                            self::getArgumentSequence($this->items[$num + 1]->getValue())
                        );
                    }
                    else {
                        $activeItem = $activeItem->{$item->getValue()};
                    }
                    break;
                case Raw\ItemAbstract::TYPE_STRING:
                case Raw\ItemAbstract::TYPE_NUMBER:
                    if ($activeItem) {
                        $arguments[] = $activeItem;
                        $activeItem = null;
                    }
                    $arguments[] = $item->getValue();
                    break;
                case Raw\ItemAbstract::TYPE_RECORD:
                    if ($activeItem) {
                        $arguments[] = $activeItem;
                    }
                    $activeItem = $item->getValue();
                    break;
            }
        }
        if ($activeItem) {
            $arguments[] = $activeItem;
        }
        return $arguments;
    }

    /**
     * @inheritDoc
     */
    public static function getDeleteSequence()//
    {
        /**
         * DELETE
         *  $var, $var2, $var3...
         */
        $parts = $this->items->explode(',');
        $result = [];
        foreach ($parts as $part) {
            switch (count($part)) {
                case 0: continue(2);
                case 1: break;
                default: throw ParseException::incorrectCountArguments(count($part), 0, 1);
            }
            $firstItem = reset($chain);
            if (!$firstItem->is(Raw\ItemAbstract::TYPE_RECORD)
                || count(AbstractRecord::getRecord($firstItem->getValue())->getSequence())
            ) {
                throw ParseException::incorrectLink($firstItem);
            }
            $result[] = $firstItem->getValue();
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getGroupBySequence()
    {
        // TODO: Implement getGroupBySequence() method.
        return self::getDefaultWithCallback(function(Raw\Items $items) {
            $parts = $items->explode([',', ';']);
            $result = [];
            foreach ($parts as $part) {
                $result[] = new Link('return', [$part->getRawValue()], true);
            }
            return $result;
        });
    }

    private function isConnected(ItemAbstract $current, ItemAbstract $next)
    {
        if (!$current->is(ItemAbstract::TYPE_CONTEXT)) {
            if ($next->is(
                ItemAbstract::TYPE_WORD, ItemAbstract::TYPE_NUMBER, ItemAbstract::TYPE_STRING, ItemAbstract::TYPE_VARIABLE, ItemAbstract::TYPE_SQL_VAR)) {
                return true;
            }
            if ($next->is(ItemAbstract::TYPE_FIELD) && $next->getValue()[0] !== '.') {
                return true;
            }
        }
        if ($next->is(ItemAbstract::TYPE_PARENTHESIS)
            && !$current->is(ItemAbstract::TYPE_WORD, ItemAbstract::TYPE_METHOD)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return self[]
     */
    public function getUnconnectedParts()
    {
        $result = [];
        $items = [];
        foreach ($this->items as $pos => $item) {
            if (!isset($this[$pos + 1]) || self::isConnected($item, $this[$pos + 1])) {
                $result[] = new self($items);
                $items = [];
            }
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getOrderBySequence()
    {
        // TODO: Implement getOrderBySequence() method.
        /**
         * ->orderBy->{
         *   Builder::if(condition)->{
         *      Builder::return($var->field, asc)
         *   }
         *   ->return($var->field_2, desc)
         *
         *
         *
         * }
         *
         */
        return self::getDefaultWithCallback($items, function(Raw\Items $items) {
            $parts = $items->explode([',', ';']);
            $result = [];
            foreach ($parts as $part) {
                $arguments = $part->getUnconnectedParts();
                switch (count($arguments)) {
                    case 1:
                        $argument = reset($arguments);
                        $order = 'ASC';
                        break;
                    case 2:
                        $argument = reset($arguments);
                        $order = end($arguments);
                        if (count($order) !== 1) {
                            throw ParseException::incorrectCountArguments(count($order), 1);
                        }
                        if (!$order[0]->is(Raw\ItemAbstract::TYPE_WORD)
                            || !in_array(strtoupper($order[0]->getValue()), ['ASC', 'DESC'])//todo: need constants
                        ) {
                            throw ParseException::incorrectLink($order[0]);
                        }
                        $order = $order[0]->getValue();
                        break;
                    default:
                        throw ParseException::incorrectCountArguments(count($arguments), 1, 2);
                }
                $result[] = new Link('return',
                    [$argument->getRawValue(), $order],
                    true
                );
            }
            return $result;
        });
    }

    /**
     * @param Raw\Items $items
     * @param int $pos
     * @return int|null
     */
    private static function geNextBlock(Raw\Items $items, $pos = 0)
    {
        return $items->pos([Raw\ItemAbstract::TYPE_WORD => [
            'UPDATE', 'DELETE', 'INSERT', 'SELECT',
            'ORDER', 'GROUP',
            'HAVING', 'ON', 'WHERE', 'USING',
            'FROM', 'LEFT', 'JOIN', 'RIGHT', 'INNER', 'OUTER', 'CORRELATE', 'UNION',
            'LIMIT','OFFSET', 'DISTINCT',
        ]], $pos, Raw\Items::POS_FLAG_UPPER_CASE);
    }

    /**
     * @inheritDoc
     */
    public static function getQuerySequence(Raw\Items $items)
    {
        return self::getQuery($items)->sequence;
    }

    /**
     * @inheritDoc
     */
    public static function getSelectSequence(Raw\Items $items)
    {
        /**
         * SELECT|UPDATE
         *     field_name_one = 'string',
         *     if (condition_1)
         *          field_name_two = 'string_two'
         *     else
         *          field_name_two = number_1
         *     field_name_other
         *
         *
         * ->select->{
         *     Builder::return($var->field_1, 'value')->
         *     if (new \Raw("condition"))->{
         *         Builder::return($var->field_2, 'value_2')
         *     }
         * }
         */
        return self::getDefaultWithCallback($items, function(Raw\Items $items) {
            $parts = $items->explode([',', ';']);
            $results = [];
            foreach ($parts as $part) {
                if (!count($part)) {
                    continue;
                }
                $arguments = $part->explode('=', 2);
                if (count($arguments[0]) !== 1) {
                    throw ParseException::incorrectCountArguments(count($arguments[0]), 1, 1);
                }
                $field = $arguments[0][0];
                if ($field->is(Raw\ItemAbstract::TYPE_RECORD)) {
                    throw ParseException::incorrectLink($field);
                }
                unset($arguments[0]);
                if (count($arguments)) {
                    $results[] = new Link('return', [$field->getValue(), $arguments[0]->getRawValue()], true);
                }
                else {
                    $results[] = new Link('return', [$field->getValue()], true);
                }
            }
            return $results;
        });
    }

    /**
     * @inheritDoc
     */
    public static function getSetSequence(Raw\Items $items)
    {
        // TODO: Implement getSetSequence() method.
    }

    /**
     * @inheritDoc
     */
    public static function getUnionSequence(Raw\Items $items)
    {
        // TODO: Implement getUnionSequence() method.
    }

    /**
     * @inheritDoc
     */
    public static function getUsingSequence(Raw\Items $items)
    {
        // TODO: Implement getUsingSequence() method.
    }

    /**
     * @inheritDoc
     */
    public static function getWhereSequence(Raw\Items $items)
    {
        // TODO: Implement getWhereSequence() method.
        return $items->getRawValue();
    }

    private static function getDefaultWithCallback(\Closure $callback)
    {
        $result = [];
        $return = [];
        for ($num = 0; $num < count($items); $num++) {
            $item = $items[$num];
            if ($item->is(Raw\ItemAbstract::TYPE_WORD)) {
                switch ($item->getValue()) {
                    case 'if':
                    case 'elseif':
                    case 'switch'://WORD()
                        if (!isset($items[$num + 1])
                            || !$items[$num + 1]->is(Raw\ItemAbstract::TYPE_PARENTHESIS)
                            || !count($items[$num + 1]->getValue())
                        ) {
                            throw ParseException::incorrectLink($item);
                        }
                        if (count($return)) {
                            $result = array_merge($result, $callback(new Raw\Items($return)));
                            $return = [];
                        }
                        $result[] = new Link($item->getValue(), [
                            $items[$num + 1]->getRawValue()
                        ], true);
                        $num++;
                        break;
                    case 'case'://WORD ... :
                        //substr('','','')
                        $pos = $items->pos(':', $num);
                        if (is_null($pos)) {
                            throw ParseException::ewfer(':');
                        }
                        if (count($return)) {
                            $result = array_merge($result, $callback(new Raw\Items($return)));
                            $return = [];
                        }
                        $result[] = new Link($item->getValue(), [
                            $items->splice($num + 1, $pos - $num - 1)->getRawValue()
                        ], true);
                        $num++;
                        break;
                    case 'else':
                    case 'default':
                    case 'endif':
                    case 'endswitch'://WORD
                        if (count($return)) {
                            $result = array_merge($result, $callback(new Raw\Items($return)));
                            $return = [];
                        }
                        $result[] = new Link($item->getValue());
                        break;
                    default://other
                        $return[] = $item;
                }
            }
            else {
                $return[] = $item;
            }
        }
        if (count($return)) {
            $result = array_merge($result, $callback(new Raw\Items($return)));
        }
        return $result;
    }

    public static function getDefaultSequence(Raw\Items $items)
    {
        return self::getDefaultWithCallback($items, function(Raw\Items $items) {
            return [new Link('return',
                $items->getRawValues(),
                true
            )];
        });
    }

    public static function getUpdateSequence(Raw\Items $items)
    {
        return self::getSelectSequence($items);
    }

    private static function getTableVariable(Raw\Items $items)
    {
        if (!count($items)) {
            throw ParseException::incorrectCountArguments(0, 1);
        }
        /**
         * record|fields => array || string
         */
        if ($items[0]->is(Raw\ItemAbstract::TYPE_RECORD)) {
            if (count($items) !== 1) {
                throw ParseException::incorrectCountArguments(count($items), 1, 1);
            }
            $record = AbstractRecord::getRecord($items[0]->getValue());

            if (count($record->getSequence())) {
                throw ParseException::incorrectCountArguments(count($items), 1, 1);
            }

            return $items[0]->getValue();
        }
        if ($items[0]->is(Raw\ItemAbstract::TYPE_FIELD)) {
            if ($items[0]->getValue()[0] === '.') {
                throw ParseException::incorrectLink($items[0]);
            }
            $tableName = [substr($items[0]->getValue(), 1, -1)];
            unset($items[0]);
            foreach ($items as $num => $item) {
                if (!$item->is(Raw\ItemAbstract::TYPE_FIELD)) {
                    throw ParseException::incorrectLink($item);
                }
                if ($item->getValue()[0] !== '.') {
                    if ($num !== count($items) - 1) {
                        throw ParseException::incorrectLink($item);
                    }
                    $alias = substr($item->getValue(), 1, -1);
                }
            }
            if (!isset($alias)) {
                $alias = end($tableName);
            }


            if (isset($items[1])) {


            }
        }
        //foreach ()
    }

    private static function getQuery(Raw\Items $items)
    {
        //limit offset distinct...

        /**
         * Допустимо:
         *
         * select *
         * from union(
         *  select //правила объединения
         *  from `table`
         * on//условия выборки
         *  from union ()
         *  from `table
         * where//условия объединения
         * )
         *
         * join внутри Union не допустимо
         */

        $result = (object) [
            'limit' => [],
            'distinct' => false,
            'sequence' => []
        ];

        $pos = self::geNextBlock($items);
        if ($pos !== 0) {
            throw ParseException::qwe();
        }
        $item = $items[0];
        while (count($items)) {
            $blockName = strtolower($item->getValue());
            switch ($blockName) {
                case 'left':
                case 'right':
                case 'inner'://intersect
                case 'outer'://except
                    if (isset($items[1])
                        && $items[1]->is(Raw\ItemAbstract::TYPE_WORD)
                        && strtolower($items[1]->getValue()) === 'join'
                    ) {
                        unset($items[1]);
                    }
                case 'from':
                case 'join'://inner
                case 'correlate':
                case 'union'://from , , , ,
                    if ($blockName === 'union') {
                        $blockName = 'from';
                        $is_union = true;
                    }
                    elseif (isset($items[1])
                        && $items[1]->is(Raw\ItemAbstract::TYPE_WORD)
                        && strtolower($items[1]->getValue()) === 'union'
                    ) {
                        unset($items[1]);
                        $is_union = true;
                    }
                    else {
                        $is_union = false;
                    }
                    if ($blockName === 'join') {
                        $blockName = 'inner';
                    }

                    $pos = self::geNextBlock($items);
                    unset($items[0]);
                    $part = $items->splice(0, $pos - 1);
                    $parts = $part->explode(',');
                    foreach ($parts as $part) {
                        if (!count($part)) {
                            continue;
                        }
                        if ($part[0]->is(Raw\ItemAbstract::TYPE_PARENTHESIS)) {
                            $query = self::getQuery($part[0]->getValue());
                            unset($part[0]);
                        }
                        else {
                            $query = null;
                        }
                        $variable = self::getTableVariable($part);

                        if (is_null($query)) {
                            $result->sequence[] = new Link('table', [$variable, $blockName, $is_union], true);
                        }
                        else {
                            $result->sequence[] = new Link('query', [$variable, [
                                'type' => $blockName,
                                'is_union' => $is_union,
                                'limit' => $query->limit,
                                'distinct' => $query->distinct,
                            ]], true);
                            $result->sequence[] = $query->sequence;
                        }
                    }
                    break;
                case 'having':
                case 'on':
                case 'where':
                case 'using':
                    $pos = self::geNextBlock($items);
                    unset($items[0]);
                    $part = $items->splice(0, $pos - 1);
                    $result->sequence[] = new Link($blockName, self::getWhereSequence($part), true);
                    break;
                case 'order':
                case 'group':
                    if (isset($items[1])
                        && $items[1]->is(Raw\ItemAbstract::TYPE_WORD)
                        && strtoupper($items[1]->getValue()) === 'BY'
                    ) {
                        unset($items[1]);
                    }
                    $blockName = $blockName . 'By';
                case 'update':
                case 'select':
                case 'delete':
                case 'insert':
                case 'limit':
                case 'distinct':
                    $pos = self::geNextBlock($items);
                    unset($items[0]);
                    $part = $items->splice(0, $pos - 1);
                    $result->sequence[] = new Link($blockName);
                    $method = ucfirst($blockName);
                    if (in_array($blockName, ['limit', 'distinct'])) {
                        $result->{$blockName} = call_user_func([self::class, "get{$method}Sequence"], $part);
                    }
                    else {
                        $result->sequence[] = array_merge(
                            $result->sequence, call_user_func([self::class, "get{$method}Sequence"], $part)
                        );
                    }
            }
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    protected static function getLimitSequence(Raw\Items $items)
    {
        $parts = $items->explode(',');
        $result = [];
        switch (count($parts)) {
            case 1:
            case 2:
                foreach ($parts as $part) {
                    if (count($part) !== 1) {
                        throw ParseException::incorrectCountArguments(count($part), 1, 1);
                    }
                    $result[] = $part[0]->getValue();
                }
                break;
            default:
                throw ParseException::incorrectCountArguments(count($parts), 1, 1);
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    protected static function getDistinctSequence(Raw\Items $items)
    {
        if (count($items)) {
            throw ParseException::incorrectCountArguments(count($items), 0, 0);
        }
        return true;
    }
}