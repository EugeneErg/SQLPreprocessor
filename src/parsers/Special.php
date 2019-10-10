<?php namespace EugeneErg\SQLPreprocessor\Parsers;

use EugeneErg\SQLPreprocessor\Builder;
use EugeneErg\SQLPreprocessor\Link;
use EugeneErg\SQLPreprocessor\ParseException;
use EugeneErg\SQLPreprocessor\Raw;
use EugeneErg\SQLPreprocessor\Record\Container;
use EugeneErg\SQLPreprocessor\record\FieldTable;
use EugeneErg\SQLPreprocessor\Record\Item;
use EugeneErg\SQLPreprocessor\Record\Query;
use EugeneErg\SQLPreprocessor\Record\Table;
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

    private function getWordAttribute(Raw\Item\Word $item, &$activeItem, &$num)
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

    private function getVariableAttribute(Raw\Item $item, &$activeItem)//sql variable
    {
        $result = $activeItem ? [$activeItem] : [];
        $activeItem = Item::create($item);

        return $result;
    }

    private function getParenthesisAttribute(Raw\Item\Parenthesis $item, &$activeItem)
    {
        return $this->getVariableAttribute($item, $activeItem);
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
            $shortName = substr(strrchr(get_class($item), '\\'), 1);
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
                $result[] = new Link('return', $this->getSequence($part, self::TYPE_ARGUMENT, true));
            }

            return $result;
        });
    }

    /**
     * @return int|null
     */
    private function geNextBlock()
    {
        return $this->items->pos([Raw\Item\Word::class => [
            'ORDER', 'GROUP',
            'FROM', 'LEFT', 'JOIN', 'RIGHT', 'INNER', 'OUTER', 'CORRELATE', 'UNION',
            'LIMIT','OFFSET', 'DISTINCT',
        ]], 0, Raw\Items::POS_FLAG_UPPER_CASE);
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

    private function getReturns(Raw\Items $items)
    {
        $parts = $items->explode(',|;');
        $results = [];

        foreach ($parts as $part) {
            if (count($part)) {
                $results[] = new Link('return',
                    $this->getSequence($part, self::TYPE_ARGUMENT), true
                );
            }
        }

        return $results;
    }

    public function getDefaultSequence()
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
                            $result = array_merge($result, $this->getReturns(new Raw\Items($return)));
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
                            $result = array_merge($result, $this->getReturns(new Raw\Items($return)));
                            $return = [];
                        }

                        $result[] = new Link($item->getValue(), [
                            $this->getSequence(
                                $this->items->splice($num + 1, $pos - $num - 1),
                                self::TYPE_ARGUMENT
                            )
                        ], true);
                        $num++;
                        break;
                    case 'else':
                    case 'default':
                    case 'endif':
                    case 'endswitch':
                        if (count($return)) {
                            $result = array_merge($result, $this->getReturns(new Raw\Items($return)));
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
            $result = array_merge($result, $this->getReturns(new Raw\Items($return)));
        }

        return $result;
    }

    public function getUpdateSequence()
    {
        return self::getSelectSequence();
    }

    public function getQuerySequence()
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
        $pos = $this->geNextBlock();

        //всё что до, это селект/апдейт/инсерт/делит
        if ($pos) {
            $part = $this->items->splice(0, $pos);
            $result->sequence = $this->getSequence($part, 'default');
        }
        else {
            $result->sequence = [];
        }

        while (count($this->items)) {
            $blockName = strtolower($this->items[0]->getValue());
            unset($this->items[0]);

            if (null === $pos = $this->geNextBlock()) {
                $pos = count($this->items);
            }

            $part = $this->items->splice(0, $pos);

            switch ($blockName) {
                case 'left':
                case 'right':
                case 'cross':
                case 'inner'://intersect
                case 'outer'://except
                    if (isset($part[0])
                        && $part[0] instanceof Raw\Item\Word
                        && strtolower($part[0]->getValue()) === 'join'
                    ) {
                        unset($part[0]);
                    }
                case 'from':
                case 'join'://inner
                case 'union'://from , , , ,
                case 'correlate':
                    if ($blockName === 'union') {
                        $blockName = 'from';
                        $is_union = true;
                    }
                    elseif (isset($part[0])
                        && $part[0] instanceof Raw\Item\Word
                        && strtolower($part[0]->getValue()) === 'union'
                    ) {
                        if ($blockName === 'correlate') {
                            throw ParseException::incorrectLink($part[0]);
                        }

                        unset($part[0]);
                        $is_union = true;
                    }
                    else {
                        $is_union = false;
                    }
                    if ($blockName === 'join') {
                        $blockName = 'inner';
                    }

                    $parts = $part->explode(',');

                    foreach ($parts as $part) {
                        switch (count($part)) {
                            case 0: continue(2);
                            case 2:
                                $parenthesis = reset($part);
                                $variable = end($part);
                                break;
                            case 1:
                                if ($part[0] instanceof Raw\Item\Parenthesis) {
                                    $parenthesis = $part[0];
                                    $variable = null;
                                }
                                else {
                                    $variable = $part[0];
                                    $parenthesis = null;
                                }
                                break;
                            default:
                                throw ParseException::incorrectCountArguments(count($part), 1, 2);
                        }

                        if (!is_null($parenthesis)) {
                            if (!$parenthesis instanceof Raw\Item\Parenthesis) {
                                throw ParseException::incorrectLink($parenthesis);
                            }

                            $query = $this->getSequence($parenthesis->getValue(), self::TYPE_QUERY);
                        }
                        else {
                            $query = null;
                        }
                        if (!is_nuLL($variable)) {
                            if ($variable instanceof Raw\Item\Field) {
                                $record = FieldTable::getRecord($variable->getValue());
                                if ($parenthesis && (count($record->getTableName()) != 1
                                    || $record->getTableName()[0] !== $record->getAlias())
                                ) {
                                    throw ParseException::incorrectLink($variable);
                                }
                            }
                            elseif ($variable instanceof Raw\Item\Record) {
                                $record = Table::getRecord($variable->getValue());
                            }
                            else {
                                throw ParseException::incorrectLink($variable);
                            }
                            if (count($record->getSequence())) {
                                throw ParseException::incorrectLink($variable);
                            }
                        }
                        else {
                            $variable = Query::create();
                        }

                        $stringType = strtolower($blockName);
                        $flags = Builder::QUERY_FLAGS;
                        $type = isset($flags[$stringType]) ? $flags[$stringType] : 0;

                        if (is_null($query)) {
                            $result->sequence[] = new Link('from', [$variable, $type], true);
                        }
                        else {
                            if (count($query->limit)) {
                                $limit = end($query->limit);
                                $offset = count($query->limit) > 1 ? reset($query->limit) : 0;
                            }
                            else {
                                $limit = null;
                                $offset = 0;
                            }
                            $result->sequence[] = new Link('query', [
                                $variable,
                                ($query->distinct ? Builder::QUERY_FLAG_DISTINCT : 0)
                                | ($is_union ? Builder::QUERY_FLAG_UNION : 0)
                                | $type,
                                $limit,
                                $offset,
                            ], true);
                            $result->sequence[] = $query->sequence;
                        }
                    }
                    break;
                case 'order':
                case 'group':
                    if (isset($part[0])
                        && $part[0] instanceof Raw\Item\Word
                        && strtoupper($part[0]->getValue()) === 'BY'
                    ) {
                        unset($part[0]);
                    }

                    $blockName = $blockName . 'By';
                    $result->sequence[] = new Link($blockName);
                    $result->sequence[] = $this->getSequence($part, $blockName);
                    break;
                case 'limit':
                case 'distinct':
                    $result->{$blockName} = $this->getSequence($part, $blockName);
            }
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    protected function getLimitSequence()
    {
        $parts = $this->items->explode(',');
        $result = [];

        switch (count($parts)) {
            case 1:
            case 2:
                foreach ($parts as $part) {
                    if (count($part) !== 1) {
                        throw ParseException::incorrectCountArguments(count($part), 1, 1);
                    }
                    if (!$part[0] instanceof Raw\Item\Number) {
                        throw ParseException::incorrectLink($part[0]);
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
    protected function getDistinctSequence()
    {
        if (count($this->items)) {
            throw ParseException::incorrectCountArguments(count($this->items), 0, 0);
        }
        return true;
    }
}