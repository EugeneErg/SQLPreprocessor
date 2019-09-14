<?php namespace EugeneErg\SQLPreprocessor\Parsers;

use EugeneErg\SQLPreprocessor\Link;
use EugeneErg\SQLPreprocessor\ParseException;
use EugeneErg\SQLPreprocessor\Query;
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
     * @inheritDoc
     */
    public static function getQuerySequence(Raw\Items $items)
    {
        // TODO: Implement getQuerySequence() method.
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
    }

    private static function getDefaultWithCallback(Raw\Items $items, \Closure $callback)
    {
        $result = [];
        $return = new Raw\Items();
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
                            $result = array_merge($result, $callback($return));
                            $return = new Raw\Items();
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
                            $result = array_merge($result, $callback($return));
                            $return = new Raw\Items();
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
                            $result = array_merge($result, $callback($return));
                            $return = new Raw\Items();
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
            $result = array_merge($result, $callback($return));
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
}