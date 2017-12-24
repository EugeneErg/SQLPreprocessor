<?php namespace EugeneErg\SQLPreprocessor\Translaters;

use EugeneErg\SQLPreprocessor\SQLFunction;
use EugeneErg\SQLPreprocessor\Translater;
use EugeneErg\SQLPreprocessor\Query;
use EugeneErg\SQLPreprocessor\Field;
use EugeneErg\SQLPreprocessor\Variable;
use EugeneErg\SQLPreprocessor\Argument;

class MySql extends Translater {
    const SELECT = 'select';
    const PARENTFIELD = 'parentfield';
    const CONDITION = 'condition';

    protected $query;
    protected $type = Self::PARENTFIELD;

    protected function quoteTable($name) {
        return '`' . str_replace('`','``', $name) . '`';
    }
    protected function quoteStrring($string) {
        return "'" . str_replace(array('\\',"'"), array('\\\\',"\\'"), $string) . "'";
    }
    protected function getTable() {
        $var = $this->query->getVar();
        switch ($var->getType()) {
            case Variable::IS_TABLE_NAME:
                return $this->quoteTable($var->getValue()) . ' `' . $this->query->getIndex() . '`';
            case Variable::IS_SUBQUERY:
                $sql = $var->getValue();
                return $sql(new $this());
            case Variable::IS_TABLE_CONTENT:
                if (!count($values = $var->getValue())) {
                    break;
                }
                if (!count($firstValues = array_shift($values))) {
                    break;
                }
                $subSelects = [];
                $subSelect = [];
                foreach ($firstValues as $key => $value) {
                    $subSelect[] = $this->quoteStrring($value) . ' ' . $this->quoteTable($value);
                }
                $subSelects[] = implode(',', $subSelect);
                foreach ($values as $value) {
                    $subSelect = [];
                    foreach ($value as $val) {
                        $subSelect[] = $this->quoteStrring($val);
                    }
                    $subSelects[] = implode(',', $subSelect);
                }
                return '((SELECT ' . implode(')UNION ALL(SELECT ', $subSelects) . '))' . $this->quoteTable($this->query->getIndex());
        }
    }
    protected function getChilds() {
        $result = [];
        if (!$first = is_null($table = $this->getTable())) {
            $result[] = $table;
        }
        $delete = $this->query->getDelete();
        foreach ($this->query->getChilds() as $child) {
            if (!$child->isCorrelate()
                && (count($child->getSelect())
                    || isset($delete[$child]))
            ) {
                if ('' != $join = $this->getQuery($child)) {
                    $result[] = ($first ? '' : mb_strtoupper($child->getJoin()) . ' JOIN ') . $join;
                    $first = false;
                }
            }
        }
        return $result;
    }
    protected function getArray(array $array) {
        $result = [];
        foreach ($array as $arg) {
            $result[] = $this->getArg($arg);
        }
        return $result;
    }
    protected function getArg(Argument $arg) {
        switch ($arg->getType()) {
            case Argument::IS_FIELD:
                return $this->getInclude($arg->getValue());
            case Argument::IS_ARRAY:
                return explode(',', $this->getArray($arg->getValue()));
            case Argument::IS_NULL:
                return 'NULL';
            case Argument::IS_SCALAR:
                if (is_string($value = $arg->getValue())) {
                    return $this->quoteStrring($value);
                }
                return "$value";
        }
    }
    protected function getArgs(array $args) {
        /*
         * аргументы подготавливаются для функции типа IF RETURN
         * количество аргументов должно быть нечетным или равным 2-м
         * каждый второй аргумент ожидается оператором
         * */
        if (2 == $count = count($args)) {
            return "{$this->getArg($args[0])}={$this->getArg($args[1])}";
        }
        if ($count % 2 != 1) {
            throw new \Exception('Неправильное количество аргументов');
        }
        $result = '';
        foreach ($args as $num => $arg) {
            if ($num % 2 == 0) {
                $result .= $this->getArg($arg);
            }
            elseif (in_array($oper = mb_strtolower($arg->getValue()),
                ['=', '<', '>', '-', '+', '*', '/', '>=', '<=', '!=', '<>', '||', 'or', '&&', 'and'])
            ) {
                $result .= ")$oper(";
            }
            else {
                throw new \Exception('неожиданный оператор');
            }
        }
        return $result;
    }
    protected function getFunctionOr(SQLFunction $function, $objectValue = null) {
        if (is_null($objectValue)) {
            return $this->getArgs($function->getArgs());
        }
        return "($objectValue))OR(({$this->getArgs($function->getArgs())})";
    }
    protected function getFunctionAnd(SQLFunction $function, $objectValue = null) {
        if (is_null($objectValue)) {
            return $this->getArgs($function->getArgs());
        }
        return "($objectValue))AND(({$this->getArgs($function->getArgs())})";
    }
    protected function getFunctionReturn(SQLFunction$function, $objectValue = null) {
        if (!is_null($objectValue)) {
            throw new \Exception('Функция return должна вызываться от переменной таблицы');
        }
        return $this->getArgs($function->getArgs());
    }
    protected function getFunctionCount($function, $objectValue = null) {
        if (is_null($objectValue)) {
            return 'COUNT(*)';
        }
        return "COUNT({$objectValue})";
    }
    protected function getFunctionMax($function, $objectValue = null) {
        return "MAX({$objectValue})";
    }
    protected function getFunctionResult($function, $objectValue = null) {

    }
    protected function getFunctions(array $functions, $object) {
        if ($object instanceof SQLFunction) {
            $objectValue = $this->getArgs($object->getArgs());
        }
        elseif ($object instanceof Field) {
            if ($object->getType() == Field::TYPE_VARIABLE
                && $object->getObject()->getType() == Variable::IS_TABLE_NAME
            ) {
                $objectValue = null;
            }
            else {
                $objectValue = $this->getInclude($object);
            }
        }
        else {
            $objectValue = null;
        }
        foreach ($functions as $function) {
            $name = $function->getName();
            if (!method_exists($this, "getFunction{$name}")) {
                throw new \Exception('Данный метод неопределен');
            }
            $objectValue = $this->{"getFunction{$name}"}($function, $objectValue);//если функция вызывается от корня переменной, тут должен быть нулл
        }
        return $objectValue;
    }
    protected function getBlock(array $block) {
        foreach ($block as $name => $childs) {
            foreach ($childs as $child) {
                if ($name == 'return') {
                    return $this->getFunctions($child->union, $child->function);
                }
                if ($name == 'if') {
                    $result = "CASE WHEN({$this->getFunctions($child->union, $child->function)})THEN({$this->getBlock($child->childs)})";
                }
                else {
                    $result = "CASE({$this->getFunctions($child->union, $child->function)})";
                }
                while (isset($child->next)) {
                    $child = $child->next;
                    if (in_array($child->function->getName(), ['elseif', 'case'])) {
                        $result .= "WHEN({$this->getFunctions($child->union, $child->function)})THEN({$this->getBlock($child->childs)})";
                    }
                    else {
                        $result .= "ELSE({$this->getBlock($child->childs)})";
                    }
                }
                return "{$result}END CASE";
            }
        }
    }
    protected function getParentInclude(\StdClass $include) {
        $childQuery = $this->query;
        $this->query = $include->query;
        $result = $this->getInclude($include->field);
        $this->query = $childQuery;
        return $result;
    }
    private function getInclude($field) {
        $include = $this->query->getInclude($field);

        if ($this->type == Self::PARENTFIELD
            && $this->query->selected($include->field)
                && $include->level !== 0
        ) {
            return '`' . $include->query->getIndex() . '`.`' . $include->field . '`';
        }
        $alias = $this->type == Self::SELECT && !$this->query->isCorrelate() ? ' `' . $include->field . '`' : '';

        if ($include->level == 0
            || ($include->level == -1 && !$include->query->isSubQuery())
        ) {
            $prevType = $this->type;
            if ($this->type == self::SELECT) {
                $this->type = self::CONDITION;
            }
            if (!is_object($include->field)) {
                print_r($include);
                die();
            }
            switch ($include->field->getType()) {
                case Field::TYPE_VARIABLE:
                    $result = '`' . $include->query->getIndex() . '`.`' . $include->field->getObject()->getValue() . '`';
                    break;
                case Field::TYPE_FIELD:
                case Field::TYPE_NULL:
                    $result = $this->getFunctions($include->field->getFunctions(), $include->field->getObject());
                    break;
                case Field::TYPE_BLOCK:
                    $result = $this->getBlock((array)$include->field->getObject());
                    break;
            }
            $this->type = $prevType;
            return $result . $alias;
        }
        switch ($include->level) {
            case 1://переменная родительского запроса, значит мы - коррелированный запрос
                if ($include->query->isCorrelate()) {
                    return $this->getQuery($include->query, $include->field) . $alias;
                }
                return $this->getParentInclude($include);
            //return '`' . $include->query->index . '`.`' . $include->field . '`';
            case -1://переменная дочернего запроса
                if ($include->query->isCorrelate()) {
                    return $this->getQuery($include->query, $include->field) . $alias;
                }
                return '`' . $include->query->getIndex() . '`.`' .$include->field . '`';
        }
    }
    protected function getQuery(Query $query, array $fields = null) {
        $parentQuery = $this->query;
        $parentType = $this->type;
        $this->query = $query;

        if ($query->isSubQuery() || is_null($query->getParent())) {
            if (count($select = $query->getSelect())) {
                $this->type = Self::SELECT;

                if (!is_null($fields)) {
                    $select = $fields;
                }
                $selects = [];
                foreach ($select as $field) {
                    $selects[] = $this->getInclude($field);
                }
                $action = $this->makePartial('select', [
                    'distinct' => $query->isDistinct(),
                    'selects' => $selects,
                    'childs' => $this->getChilds(),
                ]);
            }
            elseif (count($insert = $query->getInsert())) {
                $this->type = Self::CONDITION;
                $selects = [];
                $keys = [];
                foreach ($insert as $key => $field) {
                    $keys[] = $key;
                    $selects[$field->context] = $this->getInclude($field);
                }
                $action = $this->makePartial('insert', [
                    'table' => $this->quoteTable($query->getIntoTable()),
                    'keys' => $keys,
                    'distinct' => $query->isDistinct(),
                    'selects' => $selects,
                    'childs' => $this->getChilds(),
                ]);
            }
            elseif (count($update = $query->getUpdate())) {
                $this->type = Self::CONDITION;
                $updates = [];
                foreach ($update as  $hash => $update) {
                    $updates[$hash] = $this->getInclude($update->key) . '=' . $this->getInclude($update->value);
                }
                $action = $this->makePartial('update', [
                    'childs' => $this->getChilds(),
                    'updates' => $updates,
                ]);
            }
            elseif (count($delete = $query->getDelete())) {
                if (count($delete) > 1
                    && (!is_null($query->getLimit())
                        || count($query->getOrders())
                        || count($query->getGroups()))
                ) {
                    throw new \Exception('При удалении из нескольких таблиц, нельзя использовать лимит, orderby, groupby');
                }
                /*if (count($delete) == 1) {
                    $delQuery = $this->query->deleted(0);
                    $delQuery->setDefaultName();
                    $alias = array();
                }
                else {

                }*/
                $deleted = [];
                foreach ($delete as $delQuery) {
                    if (in_array($query, [$delQuery->getParent(), $delQuery], true)) {
                        $deleted[] = $this->quoteTable($delQuery->getIndex());
                    }
                }
                $action = $this->makePartial('delete', [
                    'delete' => $deleted,
                    'childs' => $this->getChilds(),
                ]);
            }
            if (isset($action)) {
                $orders = [];
                $this->type = self::PARENTFIELD;
                foreach ($this->query->getOrders() as $order) {
                    $orders[] = $this->getInclude($order->field) . ($order->asc ? '' : ' DESC');
                }
                $groups = array();
                foreach ($this->query->getGroups() as $group) {
                    $groups[] = $this->getInclude($group);
                }
                $this->type = Self::CONDITION;

                $result = $this->makePartial('query', [
                    'action' => $action,
                    'needScob' => !is_null($query->getParent()),
                    'where' => $query->getWhere(),
                    'orders' => $orders,
                    'groups' => $groups,
                    'having' => $query->getParent() ? [] : $query->getOn(),
                    'limit' => $query->getLimit(),
                    'ofset' => $query->getOffset(),
                    'alias' => $query->isCorrelate() || is_null($query->getParent()) ? null : $query->getIndex(),
                    'on' => $query->getParent() ? $query->getOn() : [],
                ]);
            }
            else {
                $result = null;
            }
        }
        else {
            $result = $this->getTable();
            if (count($on = $this->query->getOn())) {
                $result .= ' ON ' . $this->getParentCondition($on);
            }
        }
        $this->query = $parentQuery;
        $this->type = $parentType;
        return $result;
    }
    protected function getCurentCondition(array $conditions) {
        $result = [];
        foreach ($conditions as $condition) {
            switch ($condition->type) {
                case 'args':
                    $value = $this->getArgs($condition->args);
                    break;
                case 'field':
                    $value = $this->getInclude($condition->field);
            }
            if ($condition->not) {
                $result[] = "!({$value})";
            }
            else {
                $result[] = "({$value})";
            }
        }
        return implode(')&&(', $result);
    }
    protected function getParentCondition(array $conditions) {
        $this->type = self::PARENTFIELD;
        $childQuery = $this->query;
        $this->query = $this->query->getParent();
        $result = $this->getCurentCondition($conditions);
        $this->query = $childQuery;
        return $result;
    }
}