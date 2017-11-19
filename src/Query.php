<?php namespace EugeneErg\SQLPreprocessor;

final class Query {
    const JOIN_LEFT = 'left';
    const JOIN_RIGHT = 'right';
    const JOIN_CORRELATE = 'correlate';
    //const JOIN_UNION = 'union';
    
    private $index;
    private $alias;
    private $context;
    private $var;
    private $join;
    private $limit;
    private $offset;
    private $distinct;
    private $isSubQuery;
    private $branch;
    private $parent;
    private static $queries = [];
    private $deleted = [];
    private $childs = [];
    private $fields = [];
    
    private function __clone() {}
    private function __wakeup() {}
    
    final public function isCorrelate() {
        return $this->join === Self::JOIN_CORRELATE;
    }
    public function __construct(Variable $var = null, $join = null, $offset = 0, $limit = null, $distinct = false) {
        if (!is_null($var) && $var->getType() == Variable::IS_TABLE_FIELD) {
            throw new \Exception('Переменная содердит поле таблицы, а не саму таблицу');
        }
        if (!in_array($join, [null, Self::JOIN_LEFT, Self::JOIN_RIGHT, Self::JOIN_CORRELATE])) {
            throw new \Exception('join должен иметь одно из следующих значений: "' . implode(',', [Self::JOIN_LEFT, Self::JOIN_RIGHT, Self::JOIN_CORRELATE, 'NULL']) . '"');
        }
        if (!is_numeric($offset) || $offset < 0) {
            throw new \Exception('Offset должен быть целым неотрицательным числом');
        }
        if ((!is_null($limit) && !is_numeric($limit)) || $limit < 0) {
            throw new \Exception('Limit должен быть целым неотрицательным числом или NULL');
        }
        if (!is_bool($distinct)) {
            throw new \Exception('Distinct должен быть true или false');
        }
        $this->context = is_null($var) ? 'NULL' : spl_object_hash($var);
        $this->branch = count(self::$queries);
        self::$queries[$this->branch][$this->context] = $this;
        $this->var = $var;
        $this->join = $join;
        $this->limit = $limit;
        $this->offset = $offset;
        $this->distinct = $distinct;
        $this->isSubQuery
            =  is_null($var)
            || $var->getType() == Variable::IS_QUERY
            || $this->limit
            || $this->offset
            || $this->distinct
            || $this->isCorrelate();
        $this->index = 0;
        $this->alias = 0;
    }
    public function addChild(Variable $var = null, $join = null, $offset = 0, $limit = null, $distinct = false) {
        $query = new Self($var, $join, $offset, $limit, $distinct);
        if (isset(self::$queries[$this->branch][$query->context])) {
            throw new \Exception("В данной ветке уже существует запрос с контекстом '{$query->context}'");
        }
        $query->index = count(self::$queries[$this->branch]);
        $query->alias = $query->index;
        self::$queries[$this->branch][$query->context] = $query;
        unset(self::$queries[$query->branch]);
        $query->branch = $this->branch;
        $query->parent = $this;
        $this->isSubQuery = true;
        return $this->childs[$query->context] = $query;
    }
    public function addDeleted(Variable $var = null, $join = null, $offset = 0, $limit = null, $distinct = false) {
        $query = $this->addChild($var, $join, $offset, $limit, $distinct);
        if ($query->isCorrelate()) {
            throw new \Exception('Невозможно удаление из коррелированного запроса');
        }
        $this->deleted[$query->context] = $query;
    }
    final public function setOneChildAsRoot() {
        if (!count($this->childs)) {
            return $this;
        }
        $nextParent = null;
        foreach ($this->childs as $child) {
            if ($child->isCorrelate()) {
                continue;
            }
            if (isset($nextParent)) {
                return $this;
            }
            $nextParent = $child;
        }
        if (is_null($nextParent)) {
            return $this;
        }
        foreach ($this->childs as $context => $child) {
            if ($child != $nextParent) {
                $child->parent = $nextParent;
                $nextParent->childs[$context] = $child;
            }
        }
        $nextParent->deleted = $this->deleted;
        //unset(self::$queries[$this->branch][$this->context]);нельзя удалять т.к. сбой индексов
        $nextParent->parent = null;
        $nextParent->isSubQuery = true;
        return $nextParent;
    }
    public function find($context, $branch = null) {
        if (is_null($branch)) {
            $branch = $this->branch;
        }
        if ($context instanceof Variable) {
            $context = spl_object_hash($context->getTableVar());
        }
        if (!isset(self::$queries[$branch][$context])) {
            throw new \Exception('не существует запроса ' . $context);
        }
        return self::$queries[$branch][$context];
    }
    /*public function field($field = null) {
        if (is_null($field)) {
            return $this->fields[];
        }
        if (isset($this->fields[$field])) {
            return $this->fields[$field];
        }
    }*/
    /*private function setFieldIndex($object) {
        if ($object instanceof Variable) {
            if (!isset($this->fields['Field ' . $object->getValue()])) {
                $this->fields['Field ' . $object->getValue()] = count($this->fields);
            }
        }
        elseif (!isset($this->fields['Variable ' . $object])) {
            $this->fields['Variable ' . $object] = count($this->fields);
        }
    }*/
    public function addNeed(Field $field) {
        $object = $field->getObject();
        $context = $field->getContext();
        if (is_scalar($object)) {
            $hash = 'SCALAR ' . $object;
        }
        elseif (is_object($object)) {
            $hash = spl_object_hash($object);
        }
        else {
            throw new \Exception('недопустимый тип объекта');
        }
        if (!isset($this->fields[$hash])) {
            $this->fields[$hash] = (object) [
                'object' => $object,
                'index' => count($this->fields)
            ];
            if ($field->getAggregateLevel() != 0) {
                $this->isSubQuery = true;
            }
        }
        if (!isset($this->output[$context->context][$hash])) {
            $this->output[$context->context][$hash] = 1;
            $this->include[$hash] = $this;
        }
    }
    public function addField($object = null, array $functions = []) {
        return new Field($this, $object, $functions);
    }
    public function getVariable($name) {
        return $this->variable[$name];
    }
    public function addVariable(array $childs, $name) {
        return $this->variable[$name] = $this->addField($childs);
    }
    public function addSelect(array $childs) {
        $field = $this->addField($childs);
        /*
            необходимо также добавить в извлекаемые поля
        */
        return $field;
    }
    public function addOrderby(array $childs, $asc = true) {
        $this->orderby[] = (object) [
            'field' => $field = $this->addField($childs),
            'asc' => $asc,
        ];
        return $field;
    }
    public function addGroupby(array $childs, $asc = true) {
        return $this->orderby[] = $this->addField($childs);
    }
    public function addInsert(array $childs, $table_name, $field_name) {
        if (isset($this->insert[$table_name][$field_name])) {
            throw new \Exception('нельзя установить два значения для одного поля');
        }
        return $this->insert[$table_name][$field_name] = $this->addField($childs);
    }
    public function addUpdate(array $childs, Variable $variable) {
        if ($variable->getType() != Variable::IS_TABLE_FIELD) {
            throw new \Exception('Неправильный тип аргумента');
        }
        $hash = spl_object_hash($variable);
        $this->update[$hash] = (object) [
            'key' => $variable,
            'value' => $field = $this->addField($childs),
        ];
        return $field;
    }
}