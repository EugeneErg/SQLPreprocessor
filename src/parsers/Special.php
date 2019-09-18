<?php namespace EugeneErg\SQLPreprocessor\Parsers;

use EugeneErg\SQLPreprocessor\Link;
use EugeneErg\SQLPreprocessor\ParseException;
use EugeneErg\SQLPreprocessor\Raw;
use EugeneErg\SQLPreprocessor\Variable;

/**
 * Class Special
 * @package EugeneErg\SQLPreprocessor\Parsers
 */
class Special extends ParserAbstract
{
    /**
     * @param Raw\Items $items
     * @param int $pos
     * @return string[]
     */
    private static function getFields(Raw\Items $items, $pos = 0)
    {
        $results = [];
        while (!is_null($newPos = $items->pos([Raw\Item::TYPE_FIELD => null], $pos))) {
            if ($items[$newPos]->getValue()[0] === '.') {
                $results[] = str_replace(
                    '``', '`', substr($items[$newPos]->getValue(), 2, -1)
                );
            }
        }
        return $results;
    }

    /**
     * @inheritDoc
     */
    public static function getArgumentSequence(Raw\Items $items)
    {
        $arguments = [];
        $activeItem = null;
        for ($num = 0; $num < count($items); $num++) {
            $item = $items[$num];
            switch ($item->getType()) {
                case Raw\Item::TYPE_WORD:
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
                    elseif (isset($items[$num + 1]) && $items[$num + 1]->is(Raw\Item::TYPE_PARENTHESIS)) {
                        $activeItem = call_user_func_array(
                            [new Variable(), $item->getValue()],
                            self::getArgumentSequence($items[$num + 1]->getValue())
                        );
                        $num++;
                    }
                    else {
                        $activeItem = (new Variable())->{$item->getValue()};
                    }
                    break;
                case Raw\Item::TYPE_PARENTHESIS://могут иметь методы
                case Raw\Item::TYPE_SQL_VAR:
                    if ($activeItem) {
                        $arguments[] = $activeItem;
                    }
                    $activeItem = new Variable($item);
                    break;
                case Raw\Item::TYPE_CONTEXT:
                    if ($activeItem) {
                        $arguments[] = $activeItem;
                        $activeItem = null;
                    }
                    $arguments[] = $item;
                    break;
                case Raw\Item::TYPE_FIELD:
                    if ($activeItem && $item->getValue()[0] === '.') {
                        if (isset($items[$num + 1]) && $items[$num + 1]->is(Raw\Item::TYPE_PARENTHESIS)) {
                            call_user_func_array([
                                    $activeItem,
                                    str_replace('``', '`', substr($item->getValue()[0], 1))
                                ],
                                self::getArgumentSequence($items[$num + 1]->getValue()));
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
                        $fields = self::getFields($items, $num + 1);
                        if (count($fields) > 1) {
                            $activeItem = new Variable((object) [
                                'base' => str_replace('``', '`',
                                    substr($item->getValue(), 1, -1)
                                ),
                                'table' => $fields[0]
                            ]);
                            $num++;
                        }
                        else {
                            $activeItem = new Variable(
                                str_replace('``', '`',
                                    substr($item->getValue(), 1, -1)
                                )
                            );
                        }
                    }
                    break;
                case Raw\Item::TYPE_RECTANGULAR:
                    if (!$activeItem) {
                        $arguments[] = new Variable($item->getValue());
                    }
                    elseif (isset($items[$num + 1]) && $items[$num + 1]->is(Raw\Item::TYPE_PARENTHESIS)) {
                        $activeItem = call_user_func_array(
                            [$activeItem, $item->getValue()],
                            self::getArgumentSequence($items[$num + 1]->getValue())
                        );
                    }
                    else {
                        $activeItem = $activeItem[$item->getValue()];
                    }
                    break;
                case Raw\Item::TYPE_METHOD:
                    if (!$activeItem) {
                        throw ParseException::incorrectLink($item);
                    }
                    if (isset($items[$num + 1]) && $items[$num + 1]->is(Raw\Item::TYPE_PARENTHESIS)) {
                        $activeItem = call_user_func_array(
                            [$activeItem, $item->getValue()],
                            self::getArgumentSequence($items[$num + 1]->getValue())
                        );
                    }
                    else {
                        $activeItem = $activeItem->{$item->getValue()};
                    }
                    break;
                case Raw\Item::TYPE_STRING:
                case Raw\Item::TYPE_NUMBER:
                    if ($activeItem) {
                        $arguments[] = $activeItem;
                        $activeItem = null;
                    }
                    $arguments[] = $item->getValue();
                    break;
                case Raw\Item::TYPE_VARIABLE:
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
    public static function getDeleteSequence(Raw\Items $items)//
    {
        /**
         * DELETE
         *  $var, $var2, $var3...
         */
        $parts = $items->explode(',');
        $result = [];
        foreach ($parts as $part) {
            switch (count($part)) {
                case 0: continue(2);
                case 1: break;
                default: throw ParseException::incorrectCountArguments(count($part), 0, 1);
            }
            $firstItem = reset($chain);
            if (!$firstItem->is(Raw\Item::TYPE_VARIABLE)
                || count(Variable::getSequence($firstItem->getValue()))
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
    public static function getGroupBySequence(Raw\Items $items)
    {
        // TODO: Implement getGroupBySequence() method.
        return self::getDefaultWithCallback($items, function(Raw\Items $items) {
            $parts = $items->explode([',', ';']);
            $result = [];
            foreach ($parts as $part) {
                $result[] = new Link('return', [$part->getRawValue()], true);
            }
            return $result;
        });
    }

    /**
     * @inheritDoc
     */
    public static function getHavingSequence(Raw\Items $items)
    {
        // TODO: Implement getHavingSequence() method.


    }

    /**
     * @inheritDoc
     */
    public static function getOnSequence(Raw\Items $items)
    {
        // TODO: Implement getOnSequence() method.
    }

    /**
     * @inheritDoc
     */
    public static function getOrderBySequence(Raw\Items $items)
    {
        // TODO: Implement getOrderBySequence() method.
        /**
         * ->orderBy->{
         *   Query::if(condition)->{
         *      Query::return($var->field, asc)
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
                        if (!$order[0]->is(Raw\Item::TYPE_WORD)
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
        return $items->pos([Raw\Item::TYPE_WORD => [
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
         *     Query::return($var->field_1, 'value')->
         *     if (new \Raw("condition"))->{
         *         Query::return($var->field_2, 'value_2')
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
                if ($field->is(Raw\Item::TYPE_VARIABLE)) {
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

    private static function getDefaultWithCallback(Raw\Items $items, \Closure $callback)
    {
        $result = [];
        $return = [];
        for ($num = 0; $num < count($items); $num++) {
            $item = $items[$num];
            if ($item->is(Raw\Item::TYPE_WORD)) {
                switch ($item->getValue()) {
                    case 'if':
                    case 'elseif':
                    case 'switch'://WORD()
                        if (!isset($items[$num + 1])
                            || !$items[$num + 1]->is(Raw\Item::TYPE_PARENTHESIS)
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
        if ($items[0]->is(Raw\Item::TYPE_VARIABLE)) {
            if (count($items) !== 1) {
                throw ParseException::incorrectCountArguments(count($items), 1, 1);
            }
            $variable = $items[0]->getValue();
            if (Variable::getObject($variable))
        }
        foreach ()
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
                        && $items[1]->is(Raw\Item::TYPE_WORD)
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
                        && $items[1]->is(Raw\Item::TYPE_WORD)
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
                        if ($part[0]->is(Raw\Item::TYPE_PARENTHESIS)) {
                            $query = self::getQuery($part[0]->getValue());
                            unset($part[0]);
                        }
                        else {
                            $query = null;
                        }
                        $variable = self::getTableVariable($part);





                        $result->sequence[] = new Link('query', [$variable, [
                            'type' => $blockName,
                            'is_union' => $is_union,
                            'limit' =>

                        ]], true);


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
                        && $items[1]->is(Raw\Item::TYPE_WORD)
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