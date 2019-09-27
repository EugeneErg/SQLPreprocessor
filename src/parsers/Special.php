<?php namespace EugeneErg\SQLPreprocessor\Parsers;

use EugeneErg\SQLPreprocessor\Link;
use EugeneErg\SQLPreprocessor\ParseException;
use EugeneErg\SQLPreprocessor\Raw;
use EugeneErg\SQLPreprocessor\Record\AbstractRecord;
use EugeneErg\SQLPreprocessor\Record\Container;
use EugeneErg\SQLPreprocessor\record\FieldTable;
use EugeneErg\SQLPreprocessor\Record\Item;
use EugeneErg\SQLPreprocessor\Record\Query;
use EugeneErg\SQLPreprocessor\Record\Variable;

/**
 * Class Special
 */
class Special extends ParserAbstract
{
    /**
     * @var string[]
     */
    const ITEMS = [
        Raw\Item\Field::class,
        Raw\Item\Method::class,
        Raw\Item\Number::class,
        Raw\Item\Record::class,
        Raw\Item\String::class,
        Raw\Item\Variable::class,
        Raw\Item\Word::class,
        Raw\Item\Parenthesis::class,
        Raw\Item\Rectangular::class,
    ];

    /**
     * @inheritDoc
     */
    public function getInsertSequence()
    {
        return $this->getSelectSequence();
    }

    private function getWordArgument(Raw\Item\Word $item, &$activeItem, &$num)
    {
        $result = $activeItem ? [$activeItem] : [];

        if (in_array(strtolower($item->getValue()), ['or', 'and', 'not'])) {
            $result[] = $item;
            $activeItem = null;
        }
        elseif (isset($this->items[$num + 1])//with null
            && $this->items[$num + 1] instanceof Raw\Item\Parenthesis
        ) {
            $activeItem = call_user_func_array(
                [Query::create(), $item->getValue()],
                $this->getSequence($this->items[$num + 1]->getValue(), self::TYPE_ARGUMENT)
            );
            $num++;
        }
        else {
            $activeItem = Query::create()->{$item->getValue()};
        }

        return $result;
    }

    private function getVariableArgument(Raw\Item $item, &$activeItem)//sql variable
    {
        $result = $activeItem ? [$activeItem] : [];
        $activeItem = Item::create($item);

        return $result;
    }

    private function getParenthesisArgument(Raw\Item\Parenthesis $item, &$activeItem)
    {
        return $this->getVariableArgument($item, $activeItem);
    }

    private function getStringAttribute(Raw\Item $item, &$activeItem)
    {
        $result = $activeItem ? [$activeItem] : [];
        $activeItem = Variable::create($item->getValue());//php variable

        return $result;
    }

    private function getNumberAttribute(Raw\Item\Number $item, &$activeItem)
    {
        return $this->getStringAttribute($item, $activeItem);
    }

    private function getContextAttribute(Raw\Item\Context $item, &$activeItem)
    {
        if (!$activeItem) {
            return [$item];
        }

        $result = [$activeItem, $item];
        $activeItem = null;

        return $result;
    }

    private function getFieldAttribute(Raw\Item $item, &$activeItem)
    {
        $result = $activeItem ? [$activeItem] : [];
        $activeItem = $item->getValue();

        return $result;
    }

    private function getRecordAttribute(Raw\Item\Record $item, &$activeItem)
    {
        return $this->getFieldAttribute($item, $activeItem);
    }

    private function getRectangularAttribute(Raw\Item\Rectangular $item, Container &$activeItem, &$num)
    {
        if (!$activeItem) {
            $activeItem = Item::create($item);

            return [];
        }
        if (isset($this->items[$num + 1])
            && $this->items[$num + 1] instanceof Raw\Item\Parenthesis
        ) {
            $activeItem = $activeItem->__call($item,
                $this->getSequence($this->items[$num + 1]->getValue(), self::TYPE_ARGUMENT)
            );
            $num++;
        }
        else {
            $activeItem = $activeItem->__get($item);
        }

        return [];
    }

    private function getMethodAttribute(Raw\Item\Method $item, &$activeItem, &$num)
    {
        if (!$activeItem) {
            throw ParseException::incorrectLink($item);
        }
        if (isset($this->items[$num + 1])
            && $this->items[$num + 1] instanceof Raw\Item\Parenthesis
        ) {
            $activeItem = call_user_func_array(
                [$activeItem, $item->getValue()],
                $this->getSequence($this->items[$num + 1]->getValue(), self::TYPE_ARGUMENT)
            );
        }
        else {
            $activeItem = $activeItem->{$item->getValue()};
        }

        return [];
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
            $shortName = strrchr(get_class($item), '\\');
            $arguments[] = $this->{"get{$shortName}Attribute"}($item, $activeItem, $num);
        }

        if ($activeItem) {
            $arguments[] = [$activeItem];
        }

        return call_user_func_array('array_merge', $arguments);
    }

    /**
     * @inheritDoc
     */
    public function getDeleteSequence()
    {
        return $this->getArgumentSequence();//?
        /**
         * DELETE
         *  $var, $var2, `base`.`table` `alias`, $var3...
         */
        /*
         * move to builder
         *
        $parts = $this->items->explode(',');
        $result = [];
        foreach ($parts as $part) {
            switch (count($part)) {
                case 0: continue(2);
                case 1: break;
                default:
                    throw ParseException::incorrectCountArguments(count($part), 1, 1);
            }

            $item = reset($part);
            $container = $item->getValue();

            if (!$container instanceof Container) {
                throw ParseException::incorrectLink($item);
            }

            $record = AbstractRecord::getRecord($container);

            if (count($record->getSequence())) {
                throw ParseException::incorrectLink($item);
            }

            $result[] = $record;
        }
        return $result;*/
    }

    /**
     * @inheritDoc
     */
    public function getGroupBySequence()
    {
        // TODO: Implement getGroupBySequence() method.
        return self::getDefaultWithCallback(function(Raw\Items $items) {
            $parts = $items->explode(',|;');
            $result = [];
            foreach ($parts as $part) {
                $result[] = new Link('return', $this->getSequence($part, self::TYPE_ARGUMENT), true);
            }
            return $result;
        });
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
        return self::getDefaultWithCallback(function(Raw\Items $items) {
            $parts = $items->explode(',|;');
            $result = [];

            foreach ($parts as $part) {
                if (!count($part)) {
                    continue;
                }
                /*
                 * move to builder
                 *
                 * $endPart = end($part);
                if ($endPart instanceof Raw\Item\Word
                    && in_array(strtolower($endPart->getValue()), ['asc', 'desc'])
                ) {
                    if (count($part) == 1) {
                        throw ParseException::incorrectCountArguments(count($part), 2);
                    }
                    $order = strtolower($endPart->getValue());
                    unset($part[count($part) - 1]);
                }
                else {
                    $order = 'asc';
                }*/
                $result[] = new Link('return', $this->getSequence($part, self::TYPE_ARGUMENT, true);
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
        return $items->pos([Raw\Item\Word::class => [
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
    public function getQuerySequence()
    {
        return self::getQuery($this->items)->sequence;
    }

    /**
     * @inheritDoc
     */
    public function getSelectSequence()
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
        return self::getDefaultWithCallback(function(Raw\Items $items) {
            $parts = $items->explode(',|;');
            $results = [];

            foreach ($parts as $part) {
                if (!count($part)) {
                    continue;
                }
                /*
                 * move to builder
                 *
                $arguments = $part->explode('=', 2);

                if (count($arguments[0]) !== 1) {
                    throw ParseException::incorrectCountArguments(count($arguments[0]), 1, 1);
                }

                $field = $arguments[0][0];

                if (!$field instanceof Raw\Item\Field) {
                    throw ParseException::incorrectLink($field);
                }

                $container = $field->getValue();
                $record = FieldTable::getRecord($container);

                if (count($record->getTableName()) !== 1
                    || count($record->getSequence())
                    || $record->getTableName()[0] !== $record->getAlias()
                ) {
                    throw ParseException::incorrectLink($field);
                }
                */
                $results[] = new Link('return',
                    $this->getSequence($part, self::TYPE_ARGUMENT), true
                );
            }

            return $results;
        });
    }

    /**
     * @inheritDoc
     */
    public function getUsingSequence()
    {
        // TODO: Implement getUsingSequence() method.
        return $this->getArgumentSequence();
    }

    /**
     * @inheritDoc
     */
    public function getWhereSequence()
    {
        // TODO: Implement getWhereSequence() method.
        return $this->getArgumentSequence();
    }

    private function getDefaultWithCallback(\Closure $callback)
    {
        $result = [];
        $return = [];

        for ($num = 0; $num < count($this->items); $num++) {
            $item = $this->items[$num];

            if ($item instanceof Raw\Item\Word) {
                $word = strtolower($item->getValue());
                switch ($word) {
                    case 'break':
                        if (!isset($this->items[$num + 1])
                            || !$this->items[$num + 1] instanceof Raw\Item\Parenthesis
                        ) {
                            $result[] = new Link($word);
                            break;
                        }

                        $breakValue = $this->items[$num + 1]->getValue();

                        if (count($breakValue) !== 1
                            || !$breakValue[0] instanceof Raw\Item\Number
                            || $breakValue[0]->getValue() < 0
                        ) {
                            throw ParseException::incorrectLink($item);
                        }

                        $result[] = new Link($word, [$breakValue[0]->getValue()], true);

                        break;
                    case 'if':
                    case 'elseif':
                    case 'switch':
                        if (!isset($this->items[$num + 1])
                            || !$this->items[$num + 1] instanceof Raw\Item\Parenthesis
                            || !count($this->items[$num + 1]->getValue())
                        ) {
                            throw ParseException::incorrectLink($item);
                        }
                        if (count($return)) {
                            $result = array_merge($result, $callback(new Raw\Items($return)));
                            $return = [];
                        }

                        $result[] = new Link(
                            $item->getValue(), $this->getSequence(
                                $this->items[$num + 1]->getValue(), self::TYPE_ARGUMENT
                            ), true
                        );

                        $num++;
                        break;
                    case 'case'://WORD ... :
                        //substr('','','')
                        $pos = $this->items->pos(':', $num);

                        if (is_null($pos)) {
                            throw ParseException::ewfer(':');
                        }
                        if (count($return)) {
                            $result = array_merge($result, $callback(new Raw\Items($return)));
                            $return = [];
                        }

                        $result[] = new Link($item->getValue(), [
                            $this->items->splice($num + 1, $pos - $num - 1)->getRawValue()
                        ], true);
                        $num++;
                        break;
                    case 'else':
                    case 'default':
                    case 'endif':
                    case 'endswitch':
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

    public function getDefaultSequence()
    {
        return self::getDefaultWithCallback(function(Raw\Items $items) {
            return [new Link('return',
                $this->getSequence($items, self::TYPE_ARGUMENT),
                true
            )];
        });
    }

    public function getUpdateSequence()
    {
        return self::getSelectSequence();
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