<?php namespace EugeneErg\SQLPreprocessor\Parsers;

use EugeneErg\SQLPreprocessor\Builder;
use EugeneErg\SQLPreprocessor\Link;
use EugeneErg\SQLPreprocessor\ParseException;
use EugeneErg\SQLPreprocessor\Raw;
use EugeneErg\SQLPreprocessor\Record\AbstractRecord;
use EugeneErg\SQLPreprocessor\Record\Container;
use EugeneErg\SQLPreprocessor\record\FieldTable;
use EugeneErg\SQLPreprocessor\Record\Item;
use EugeneErg\SQLPreprocessor\Record\Method;
use EugeneErg\SQLPreprocessor\Record\Offset;
use EugeneErg\SQLPreprocessor\Record\Query;
use EugeneErg\SQLPreprocessor\Record\Table;
use EugeneErg\SQLPreprocessor\Record\Variable;
use EugeneErg\SQLPreprocessor\Record\Property;

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
        return $this->getDefaultSequence();
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

    private function getRectangularAttribute(Raw\Item\Rectangular $item, Container &$activeItem = null, &$num)
    {
        if (!$activeItem) {
            $activeItem = Variable::create($this->getSequence($item->getValue(), self::TYPE_ARGUMENT));

            return [];
        }

        if (isset($this->items[$num + 1])
            && $this->items[$num + 1] instanceof Raw\Item\Parenthesis
        ) {
            $activeItem = Offset::create(
                AbstractRecord::find($activeItem),
                $this->getSequence($item->getValue(), self::TYPE_ARGUMENT),
                $this->getSequence($this->items[$num + 1]->getValue(), self::TYPE_ARGUMENT),
            );
            $num++;
        }
        else {
            $activeItem = Offset::create(
                AbstractRecord::find($activeItem),
                $this->getSequence($item->getValue(), self::TYPE_ARGUMENT)
            );
        }

        return [];
    }

    private function getMethodAttribute(Raw\Item\Method $item, Container &$activeItem = null, &$num = 0)
    {
        if (!$activeItem) {
            throw ParseException::incorrectLink($item);
        }
        if (isset($this->items[$num + 1])
            && $this->items[$num + 1] instanceof Raw\Item\Parenthesis
        ) {
            $activeItem = Method::create(
                $item->getValue(),
                $this->getSequence(
                    $this->items[$num + 1]->getValue(),
                    self::TYPE_ARGUMENT
                ),
                AbstractRecord::find($activeItem)
            );
        }
        else {
            $activeItem = Property::create($item->getValue(), $activeItem);
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
                $result[] = new Link('set', $this->getSequence($part, self::TYPE_ARGUMENT), true);
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
                $result[] = new Link('set', $this->getSequence($part, self::TYPE_ARGUMENT, true));
            }

            return $result;
        });
    }

    /**
     * @return int|null
     */
    private function getNextQueryBlock()
    {
        return $this->items->pos([Raw\Item\Word::class => [
            'ORDER', 'GROUP',
            'FROM', 'LEFT', 'JOIN', 'RIGHT', 'INNER', 'OUTER', 'CORRELATE', 'UNION',
            'LIMIT','OFFSET', 'DISTINCT',
            'WHERE', 'ON', 'HAVING',
        ]], 0, Raw\Items::POS_FLAG_UPPER_CASE);
    }

    /**
     * @return int|null
     */
    private function getNextConditionBlock()
    {
        return $this->items->pos([Raw\Item\Word::class => [
            'switch', 'case', 'default', 'endswitch',
            'if', 'elseif', 'else', 'endif',
        ]], 0, Raw\Items::POS_FLAG_LOWER_CASE);
    }

    /**
     * @inheritDoc
     */
    public function getSelectSequence()
    {
        /**
         * SELECT|UPDATE|INSERT
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
        return $this->getDefaultSequence();

        return $this->getDefaultSequence(function(Raw\Items $items) {
            $field = $items[0];
            
            if (isset($items[1])
                && !($items[1] instanceof Raw\Item\Context
                    && $items[1]->getValue()[0] === '=')
            ) {
                throw ParseException::incorrectLink($items[1]);
            }
            if ($field instanceof Raw\Item\String
                || $field instanceof Raw\Item\Word
            ) {
                if (count($items) < 3) {
                    throw ParseException::incorrectCountArguments(count($items), 3);
                }

                $fieldName = $field->getValue();
                unset($items[0]);
            }
            elseif ($field instanceof Raw\Item\Field) {
                $record = FieldTable::getRecord($field->getValue());
                $sequence = $record->getSequence();

                if (count($sequence)) {
                    throw ParseException::incorrectLink($field);
                }
                
                $tableName = $record->getTableName();
                
                if (count($tableName) > 1 && count($items) > 1) {
                    throw ParseException::incorrectCountArguments(count($items), 0, 1);
                }
                if (count($items) > 1) {
                    unset($items[0]);
                }
                
                $fieldName = end($tableName);
            }
            else {
                throw ParseException::incorrectLink($field);
            }

            if (count($items) > 1) {
                if (isset($items[0])
                    && !($items[0] instanceof Raw\Item\Context
                        && $items[0]->getValue()[0] === '='
                    )
                ) {
                    if (count($items) === 1) {
                        throw ParseException::incorrectCountArguments(1, 0, 0);
                    }
                    if ($items[0]->getValue() === '=') {
                        unset($items[0]);
                    }
                    else {
                        $items[0] = new Raw\Item\Context(substr($items[0]->getValue(), 1));
                    }
                }
            }

            return $items->splice(0, 0, new Raw\Items([new Raw\Item\Word($fieldName)]));
        });
        /*self::getDefaultWithCallback(function(Raw\Items $items) {
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
                *
                $results[] = new Link('set',
                    $this->getSequence($part, self::TYPE_ARGUMENT), true
                );
            }

            return $results;
        });*/
    }

    private function getReturns(Raw\Items $items)
    {
        $parts = $items->explode(',|;');
        $results = [];

        foreach ($parts as $part) {
            if (count($part)) {
                $results[] = new Link('set',
                    $this->getSequence($part, self::TYPE_ARGUMENT), true
                );
            }
        }

        return $results;
    }

    public function getDefaultSequence()
    {
        $result = [];

        while (count($this->items)) {
            $pos = $this->getNextConditionBlock();
            
            if (is_null($pos)) {
                $pos = count($this->items);
            }
            if ($pos) {
                $sets = $this->items->splice(0, $pos)->explode(',|;');

                foreach ($sets as $set) {
                    if (count($set)
                        && !(count($set) === 1
                            && $set[0] instanceof Raw\Item\Context
                            && $set[0]->getValue() === '')
                    ) {
                        $result[] = new Link('set',
                            $this->getSequence($set, self::TYPE_ARGUMENT)
                        );
                    }
                }
            }
            if (!count($this->items)) {
                return $result;
            }

            $item = $this->items[0];
            unset($this->items[0]);
            $word = strtolower($item->getValue());
            
            switch ($word) {
                case 'if':
                case 'elseif':
                case 'switch':
                    if (!isset($this->items[0])
                        || !$this->items[0] instanceof Raw\Item\Parenthesis
                        || !count($this->items[0]->getValue())
                    ) {
                        throw ParseException::incorrectLink($item);
                    }

                    $result[] = new Link(
                        $word, $this->getSequence(
                            $this->items[0]->getValue(), self::TYPE_ARGUMENT
                        ), true
                    );

                    unset($this->items[0]);
                    break;
                case 'case'://WORD ... :
                    $parts = $this->items->explode(':', 2);

                    if (count($parts) < 2) {
                        throw ParseException::ewfer(':');
                    }

                    $this->items = $parts[1];
                    $result[] = new Link($word, [
                        $this->getSequence($parts[0], self::TYPE_ARGUMENT)
                    ], true);
                    break;
                case 'default':
                    if (isset($this->items[0])
                        && $this->items[0] instanceof Raw\Item\Context
                        && substr($this->items[0]->getValue(), 0, 1) === ':'
                    ) {
                        if ($this->items[0]->getValue() === ':') {
                            unset($this->items[0]);
                        }
                        else {
                            $this->items[0] = new Raw\Item\Context(substr($this->items[0]->getValue(), 1));
                        }
                    }
                default:
                    $result[] = new Link($word);
            }
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
        $pos = $this->getNextQueryBlock();

        //всё что до, это set
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

            if (null === $pos = $this->getNextQueryBlock()) {
                $pos = count($this->items);
            }

            $part = $this->items->splice(0, $pos);

            switch ($blockName) {
                case 'where':
                case 'on':
                case 'having':
                    $result->sequence[] = new Link('where', self::getSequence($part, 'argument'));
                    break;
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