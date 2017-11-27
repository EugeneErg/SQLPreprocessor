<?php namespace EugeneErg\SQLPreprocessor\Translaters;

use EugeneErg\SQLPreprocessor\Translater;
use EugeneErg\SQLPreprocessor\Query;
use EugeneErg\SQLPreprocessor\Field;
use EugeneErg\SQLPreprocessor\Variable;

class MySql extends Translater {
    const SELECT = 'select';
    const PARENTFIELD = 'parentfield';

    protected $query;

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
                return $this->quoteTable($var->getValue());
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
                    $result[] = ($first ? '' : mb_strtoupper($child->join) . ' JOIN ') . $join;
                    $first = false;
                }
            }
        }
        return $result;
    }
    final private function getInclude($field) {
        $include = $field->getValue();

        if ($this->type == Self::PARENTFIELD
            && !$this->query->select($include->field)
        ) {
            return $include->level == 0 ? '' : '`' . $include->query->index . '`.`' .$include->field . '`';
        }
        $alias = $this->type == self::SELECT && !$this->query->isCorrelate() ? ' `' . $include->field . '`' : '';

        if (!isset($include->level)) {
            throw new \Exception('Переменная ' . print_by_level($include->field, 2) . ' не содержит уровень принадлежности в запросе ' . $this->query->index);
        }

        if ($include->level == 0
            || ($include->level == -1 && !$include->query->isSubQuery)
        ) {
            $prevType = $this->type;
            if ($this->type == self::SELECT) {
                $this->type = self::CONDITION;
            }
            if (!is_object($include->field)) {
                print_r($include);
                die();
            }
            switch ($include->field->type) {
                case 'Field':
                    $result = '`' . $include->query->index . '`.`' . $include->field->function . '`';
                    break;
                case 'Function':
                    $result = $this->getFunction($include->field->function);
                    break;
                case 'Variable':
                    $result = $this->getVariable($include->field->function);
                    break;
                default:
                    print_r($include->field);
                    die();
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
                return '`' . $include->query->index . '`.`' .$include->field . '`';
        }
    }
    protected function getQuery(Query $query, array $fields = null) {
        $parentQuery = $this->query;
        $parentType = $this->type;
        $this->query = $query;

        if ($query->isSubQuery()) {
            if (count($select = $query->getSelect())) {
                $this->type = Self::SELECT;

                if (!is_null($fields)) {
                    $select = $fields;
                }
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
                $result = $this->makePartial('query', [
                    'action' => $action,
                    'needScob' => !is_null($query->getParent()),
                    'where' => $query->getWhere(),
                    'orders' => $query->getOrders(),
                    'groups' => $query->getGroups(),
                    'having' => $query->getHaving(),
                    'limit' => $query->getLimit(),
                    'ofset' => $query->getOffset(),
                    'alias' => $query->isCorrelate() || is_null($query->getParent()) ? null : $query->getIndex(),
                    'on' => $query->getOn(),
                ]);
            }
            else {
                $result = null;
            }
        }
        else {
            $result = $this->getTable();
            if ($on = $this->query->getOn()) {
                $result .= ' ON ' . $this->getParentCondition($on);
            }
        }
        $this->query = $parentQuery;
        $this->type = $parentType;
        return $result;
    }
}