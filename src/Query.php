<?php namespace EugeneErg\SQLPreprocessor;

final class Query {
    const JOIN_LEFT = 'left';
    const JOIN_RIGHT = 'right';
    const JOIN_CORRELATE = 'correlate';
    //const JOIN_UNION = 'union';
    
    private $index;
    private $level = 0;
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
    private $isDeleted = false;
    private $childs = [];
    private $fields = [];
    private $variable = [];
    private $update = [];
    private $insert = [];
    private $orderby = [];
    private $groupby = [];
    private $select = [];
    private $output = [];
    private $include = [];
    private $conditions;
    
    private function __clone() {}
    private function __wakeup() {}
    private function __construct() {}
    
    public function __debugInfo() {
        return [
            'index' => $this->index,
            'childs' => $this->childs,
            'include' => $this->include,
            'output' => $this->output,
        ];
    }
    public function getIndex() {
        return $this->index;
    }
    public function isCorrelate() {
        return $this->join === Self::JOIN_CORRELATE;
    }
    public function isMultiCorrelate() {
        return $this->isCorrelate() && $this->limit != 1;
    }
    public static function create(Variable $var = null, $join = null, $offset = 0, $limit = null, $distinct = false) {
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
        $query = new Self();
        $query->context = is_null($var) ? 'NULL' : spl_object_hash($var);
        $query->branch = count(self::$queries);
        self::$queries[$query->branch][$query->context] = $query;
        $query->var = $var;
        $query->join = $join;
        $query->limit = $limit;
        $query->offset = $offset;
        $query->distinct = $distinct;
        $query->isSubQuery
            =  is_null($var)
            || $var->getType() == Variable::IS_QUERY
            || $query->limit
            || $query->offset
            || $query->distinct
            || $query->isCorrelate();
        $query->index = 0;
        $query->alias = 0;
        return $query;
    }
    public static function createDeleted(Variable $var = null, $join = null, $offset = 0, $limit = null, $distinct = false) {
        $result = Self::create($var, $join, $offset, $limit, $distinct);
        $result->isDeleted = true;
        return $result;
    }
    public function addChild(Variable $var = null, $join = null, $offset = 0, $limit = null, $distinct = false) {
        $query = Self::create($var, $join, $offset, $limit, $distinct);
        if (isset(self::$queries[$this->branch][$query->context])) {
            throw new \Exception("В данной ветке уже существует запрос с контекстом '{$query->context}'");
        }
        $query->index = count(self::$queries[$this->branch]);
        $query->alias = $query->index;
        self::$queries[$this->branch][$query->context] = $query;
        unset(self::$queries[$query->branch]);
        $query->branch = $this->branch;
        $query->parent = $this;
        $query->level = $this->level + 1;
        $this->isSubQuery = true;
        return $this->childs[$query->context] = $query;
    }
    public function addDeleted(Variable $var = null, $join = null, $offset = 0, $limit = null, $distinct = false) {
        $query = $this->addChild($var, $join, $offset, $limit, $distinct);
        $query->isDeleted = true;
        if ($query->isCorrelate()) {
            throw new \Exception('Невозможно удаление из коррелированного запроса');
        }
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
    public function addNeed(Field $field, Query $query = null) {
        $object = $field->getObject();
        if (is_null($query)) {
            $query = $field->getContext();
        }
        if (is_scalar($object)) {
            $hash = 'SCALAR ' . $object;
        }
        elseif (is_object($object)) {
            $hash = spl_object_hash($object);
        }
        else {
            throw new \Exception('недопустимый тип объекта');
        }
        if (!isset($this->include[$hash])) {
            $this->include[$hash] = (object) [
                'field' => $field,
            ];
            $prevQuery = $field->getContext();
        }
        else {
            $prevQuery = $this->include[$hash]->query;
            if (isset($prevQuery->output[$this->context][$hash])) {
                unset($prevQuery->output[$this->context][$hash]);
                if (!count($prevQuery->output[$this->context])) {
                    unset($prevQuery->output[$this->context]);
                }
            }
        }
        if ($query == $this) {
            if (isset($this->include[$hash]->query)) {
                $query = $this->include[$hash]->query;
            }
            if (!isset($this->include[$hash]->level)) {
                $this->include[$hash]->level = ($query != $this) * (1 - 2 * isset($this->childs[$query->context]));//0 1 -1
            }
        }
        else {
            $query->output[$this->context][$hash] = $field;
            $query->select[$hash] = $field;
            if (!isset($query->include[$hash])) {//если его вообще ещё нет
                $query->include[$hash] = (object) array (
                    'field' => $field,
                );
            }
            if (!isset($query->include[$hash]->query) || $query->include[$hash]->query != $query) {
                $query->include[$hash]->query = $prevQuery;
            }
            if (!isset($query->include[$hash]->level)) {
                $query->include[$hash]->level = ($query != $prevQuery) * (1 - 2 * ($prevQuery->parent == $query));
            }
        }
        $this->include[$hash]->query = $query;
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
        return $this->select[] = $this->addField($childs);
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
        if (isset($this->update[$hash])) {
            throw new \Exception('нельзя установить два значения для одного поля');
        }
        $this->update[$hash] = (object) [
            'key' => $variable,
            'value' => $field = $this->addField($childs),
        ];
        return $field;
    }
    public function addConditions($childs) {
        if (isset($this->condition)) {
            throw new \Exception('Условия извлечения из таблицы можно указать лишь один раз');
        }
        return $this->condition = $this->addField($childs);
    }
    private function levelUp($count = 1) {
        $query = $this;
        for ($i = 0; $i < $count; $i++) {
            $query = $query->parent;
        }
        return $query;
    }
    private function checkContext($dest, $isMultiCorrelate = false) {
        if ($dest === $this) {
            return null;
        }
        $destLevel = $dest->level;
        $srcLevel = $src->level;
        
        if ($srcLevel <= $destLevel) {
            if ($destLevel > $srcLevel) {
                for (; $destLevel > $srcLevel + 1; $destLevel--) {
                    if(!$dest->isCorrelate()) {
                        throw new \Exception('использовать прородительские поля могут только коррелированные запросы');
                    }
                    $dest = $dest->levelUp();
                }
                $dest = $dest->levelUp();
            }
            if ($src == $dest) {
                return null;//src является предком dest, данные не извлекаются
            }
            $dest = $dest->levelUp();//делаем уровень dest, выше, уровня src
            $destLevel = $srcLevel - 1;
        }
        elseif ($isMultiCorrelate) {
            $src = $src->levelUp($srcLevel - $destLevel - 1);//понижаем уровень src до уровня дочернего к dest
            $srcLevel = $destLevel + 1;
        }
        else {//если запрос не является коррелированным мультизапросом, то извлечь необходимо в родительский запрос
            return $src->levelUp();
        }
        //ищем общего предка для обоих запросов
        while ($src->parent != $dest) {//уровень dest должен быть меньше уровня src
            $src = $src->levelUp();
            $srcLevel--;
            $dest = $dest->levelUp();
            $destLevel--;
            if (!$dest->isCorrelate() && $src->parent != $dest) {
                throw new \Exception('использовать прородительские поля могут только коррелированные запросы');
            }
        }
        if ($isMultiCorrelate) {
            return $dest;
        }
        return $src;//контекст, который будет отмечен для извлечения данных, будет ближайшим дочерним подзапросом, общего предка, по ветке SRC
    }
    private function getAgregateStatuse(array $fields = []) {
        if (!$this->isMultiCorrelate()) {
            return [$fields];
        }
        if (!count($this->groupby)) {
            return [1 => $fields];
        }
        /*
            лимит не равен 1 и отсутствует группировка.
            в этом случае множественные значения будут для не агрегатных полей, агрегатные, такие как count() max() min() .. будут возвращать единственное значение, так же как (count() + 5) и т д
            
        */
        dd($fields);
        $result = [];
        foreach ($fields as $hash => $field) {
            if ($field->query != $this || $field->type == 'Field') {
                $result[1][$hash] = $field;
                continue;
            }
            //можем пробежать либо по дочерним фиелдам, либо по функциям
            //задача: определить какие фиелды являются агрегатными или содержат в себе агрегаты
            
            if (!isset($field->isContainsAggregate)) {
                $field->isContainsAggregate = $this->findAggregatesFunction($field->function);
            }
            $result[!$field->isContainsAggregate][$hash] = $field;
        }
        return $result;
    }
    public function calculatePathsVariables() {
        foreach (array_reverse($this->childs) as $child) {
            if ($child->isMultiCorrelate()) {
                $child->calculatePathsVariables();
            }
        }
        foreach ($this->childs as $child) {
            if (!$child->isMultiCorrelate()) {
                $child->calculatePathsVariables($child);
            }
        }
        if (count($this->output)) {
            dd($this->output);
        }
        
        foreach ($this->output as $destContext => $fields) {
            $dest = $this->find($destContext);
            if ($dest == $this) {
                throw new \Exception("{$destContext} == {$this->context}");
            }
            $nextQuery = [];
            $newFields = $this->getAgregateStatuse($fields);
            foreach ($newFields as $isMultiCorrelate => $nFields) {
                $nextQuery[$isMultiCorrelate] = $this->checkContext($dest, $isMultiCorrelate);
            }
            foreach ($nextQuery as $isMultiCorrelate => $nQuery) {
                if ($isMultiCorrelate) {
                    $nQuery = $nQuery->addClone($this, 'correlate');
                    foreach ($newFields[$isMultiCorrelate] as $field) {
                        $dest->addNeed($field, $nQuery);
                    }
                    $nQuery = null;
                }
                elseif (in_array($nQuery, array($this, $dest))) {
                    $nQuery = null;
                }
                foreach ($newFields[$isMultiCorrelate] as $field) {
                    $dest->addNeed($field, $nQuery);
                }
            }
        }
    }
}