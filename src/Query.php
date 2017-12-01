<?php namespace EugeneErg\SQLPreprocessor;

final class Query {
    const JOIN_LEFT = 'left';
    const JOIN_RIGHT = 'right';
    const JOIN_CORRELATE = 'correlate';

    const CONDITION_PARENT = 'parent';
    const CONDITION_AGGREGATE = 'agregeate';
    const CONDITION_CHILD = 'child';
    //const JOIN_UNION = 'union';
    
    private $index;
    private $level = 0;
    private $alias;
    private $context;
    private $var;
    private $join;
    private $limit;
    private $offset;
    private $isDistinct;
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
    private $orders = [];
    private $groups = [];
    private $select = [];
    private $output = [];
    private $include = [];
    private $condition;
    private $intoTable;

    private function __wakeup() {}
    private function __construct() {}
    
    /*public function __debugInfo() {
        return [
            'index' => $this->index,
            'childs' => $this->childs,
            'include' => $this->include,
            'output' => $this->output,
            'select' => $this->select,
        ];
    }*/
    public function getInclude(Field $field) {
        if (isset($this->include[$hash = spl_object_hash($field)])) {
            return $this->include[$hash];
        }
    }
    public function isCorrelate() {
        return $this->join === Self::JOIN_CORRELATE;
    }
    public function isMultiCorrelate() {
        return $this->isCorrelate() && $this->limit != 1;
    }
    public static function create(Variable $var, $join = null, $offset = 0, $limit = null, $isDistinct = false) {
        if ($var->getType() == Variable::IS_TABLE_FIELD) {
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
        if (!is_bool($isDistinct)) {
            throw new \Exception('Distinct должен быть true или false');
        }
        $query = new Self();
        $query->context = spl_object_hash($var);
        $query->branch = count(self::$queries);
        self::$queries[$query->branch][$query->context] = $query;
        $query->var = $var;
        $query->join = $join;
        $query->limit = $limit;
        $query->offset = $offset;
        $query->isDistinct = $isDistinct;
        $query->isSubQuery
            =  $var->getType() == Variable::IS_QUERY
            || $query->limit
            || $query->offset
            || $query->isDistinct
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
        if (isset(Self::$queries[$this->branch][$query->context])) {
            throw new \Exception("В данной ветке уже существует запрос с контекстом '{$query->context}'");
        }
        $query->index = count(Self::$queries[$this->branch]);
        $query->alias = $query->index;
        Self::$queries[$this->branch][$query->context] = $query;
        unset(Self::$queries[$query->branch]);
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
        $object = $field->getObject();//что нужно
        if (is_null($query)) {
            $query = $this;
        }
        if (is_scalar($object)) {
            $hash = 'SCALAR ' . $object;
        }
        elseif (is_object($object)) {
            $hash = spl_object_hash($object);
        }
        else {
            throw new \Exception('недопустимый тип объекта ' . getType($object));
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
        if ($query === $this) {
            if (isset($this->include[$hash]->query)) {
                $query = $this->include[$hash]->query;
            }
            if (!isset($this->include[$hash]->level)) {
                $this->include[$hash]->level = ($query !== $this) * (1 - 2 * isset($this->childs[$query->context]));//0 1 -1
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
                $query->include[$hash]->level = ($query !== $prevQuery) * (1 - 2 * ($prevQuery->parent === $query));
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
        if (isset($this->variable[$name])) {
            throw new \Exception('Запрещено переопределять переменные');
        }
        return $this->variable[$name] = $this->addField($childs);
    }
    public function addSelect(array $childs) {
        $field = $this->addField($childs);
        return $this->select[spl_object_hash($field)] = $field;
    }
    public function addOrder(array $childs, $asc = true) {
        $this->orders[] = (object) [
            'field' => $field = $this->addField($childs),
            'asc' => $asc,
        ];
        return $field;
    }
    public function addGroup(array $childs, $asc = true) {
        return $this->orders[] = $this->addField($childs);
    }
    public function addIntoTable($table_name) {
        if (!is_scalar($table_name)) {
            throw new \Exception('неправильный тип аргумента');
        }
        return $this->intoTable = $table_name;
    }
    public function addInsert(array $childs, $field_name) {
        if (!is_scalar($field_name)) {
            throw new \Exception('неправильный тип аргумента');
        }
        if (isset($this->insert[$field_name])) {
            throw new \Exception('нельзя установить два значения для одного поля');
        }
        return $this->insert[$field_name] = $this->addField($childs);
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
    private function checkContext(Query $dest, $isMultiCorrelate = false) {
        if ($dest === $src = $this) {
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
            if ($src === $dest) {
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
        if (count($this->groups)) {
            return [1 => $fields];
        }
        $result = [];
        foreach ($fields as $hash => $field) {
            $result[$field->getContext() !== $this || !$field->getAggregateLevel()][$hash] = $field;
        }
        return $result;
    }
    private function __clone() {
        $this->childs = [];
        $this->include = [];
    }
    final public function addClone(Query $srcQuery) {
        $query = clone $srcQuery;
        $query->alias = $query->index = count(Self::$queries[$this->branch]);
        $query->context = 'clone #' . $query->index;
        Self::$queries[$this->branch][$query->context] = $query;
        $query->branch = $this->branch;
        $query->parent = $this;
        $this->isSubQuery = true;
        $childs = [];
        foreach ($srcQuery->childs as $child) {
            $childs[$child->context] = $query->addClone($child);
        }
        foreach ($srcQuery->include as $context => $include) {
            if (!isset($include->level)) {
                $query->addNeed($include->field, $include->query);
            }
            else {
                switch ($include->level) {
                    case 0:
                        $includeQuery = $query;
                        break;
                    case 1:
                        if ($this->isCorrelate()) {
                            $includeQuery = $include->query;
                        }
                        else {
                            $includeQuery = $this;
                        }
                        break;
                    case -1:
                        $includeQuery = $childs[$include->query->context];
                }
                $query->include[$context] = (object) array (
                    'field' => $include->field,
                    'query' => $includeQuery,
                    'level' => $include->level
                );
            }
        }
        return $this->childs[$query->context] = $query;
    }
    private function calculatePathsVariables() {
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
        $multiCorrelate = [];
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
                    $multiCorrelate[$nQuery->context][$destContext] = $newFields[$isMultiCorrelate];
                    continue;
                }
                if (in_array($nQuery, [$this, $dest], true)) {
                    $nQuery = null;
                }
                foreach ($newFields[$isMultiCorrelate] as $field) {
                    $dest->addNeed($field, $nQuery);
                }
            }
        }
        foreach ($multiCorrelate as $intoContext => $needs) {
            $nQuery = $this->find($intoContext)->addClone($this);
            foreach ($needs as $needContext => $fields) {
                $need = $this->find($needContext);
                foreach ($fields as $field) {
                    $need->addNeed($field, $nQuery);
                    $need->addNeed($field);
                }
            }
        }
    }
    public function __call($name, $args) {
        if (!count($args)) {
            switch (true) {
                case mb_strpos($name, 'get') === 0:
                    $name = lcfirst(mb_substr($name, 3));
                case mb_strpos($name, 'is') === 0:
                    if (isset($this->$name)) {
                        return $this->$name;
                    }
            }
        }
    }
    private function conditionFieldAnalyze(Field $field, $not = false) {
        if ($this->getInclude($field)->level == -1) {
            return [(object)[
                'not' => $not,
                'type' => 'field',
                'field' => $field,
            ]];
        }
        return $this->conditionFunctionsAnalyze($field->getObject(), $field->getFunctions(), $not);
    }
    private function conditionArgumentAnalyze(Argument $arg, $not = false) {
        if ($arg->getType() != Argument::IS_FIELD) {
            return [(object)[
                'not' => $not,
                'type' => 'args',
                'args' => [$arg],
            ]];
        }
        return $this->conditionFieldAnalyze($arg->getValue(), $not);
    }
    private function conditionArgumentsAnalyze(array $args, $not = false) {
        if (2 == $count = count($args)) {
            $args[2] = $args[1];
            static $specArg;
            if (!isset($specArg)) {
                $specArg = new Argument('=');
            }
            $args[1] = $specArg;
        }
        elseif ($count % 2 != 1) {
            throw new \Exception('неверное количество аргументов ' . $count);
        }
        //return($var->id->or()->and()->or(), 'or', 2)->and(3)//помним, что вызываемые функции аргументов могут содержать булеановские функции
        for ($i = 1; $i < count($args); $i+=2) {
            switch ($value = $args[$i]->getValue()) {
                case 'and':
                case '&&':
                case 'or':
                case '||':
                    if ((in_array($value, ['and', '&&'])) !== $not) {
                        return [(object)[
                            'not' => $not,
                            'type' => 'args',
                            'args' => $args,
                        ]];
                    }
                default:
                    continue;
            }
        }
        $result = [];
        $res = [];
        foreach ($args as $num => $arg) {
            if ($num % 2 !== 0 || !in_array($arg->getValue(), ['and', '&&', 'or', '||'])) {
                $res[] = $arg;
                continue;
            }
            if (count($res) == 1) {
                $result[] = $this->conditionArgumentAnalyze($res, $not);
            }
            else {
                $result[] = [(object)[
                    'not' => $not,
                    'type' => 'args',
                    'args' => $res,
                ]];
            }
            $res = [];
        }
        if (count($res)) {
            $result[] = [(object)[
                'not' => $not,
                'type' => 'args',
                'args' => $res,
            ]];
        }
        return call_user_func_array('array_merge', $result);
    }
    private function conditionFunctionsAnalyze($object, $functions, $not = false) {
        $reverseFunctions = array_reverse($functions);
        $oneCondition = false;
        foreach ($reverseFunctions as $function) {
            switch ($name = $function->getName()) {
                case 'not':
                    $not = !$not;
                    continue(2);
                case 'and':
                case 'or':
                    if (($name == 'and') !== $not) {
                        continue(2);
                    }
                    $oneCondition = true;
                default:
                    break(2);
            }
        }
        $result = [];
        if (!$oneCondition) {
            foreach ($reverseFunctions as $num => $function) {
                switch ($name = $function->getName()) {
                    case 'not':
                        $not = !$not;
                        continue(2);
                    case 'and':
                    case 'or':
                        $result[] = $this->conditionArgumentsAnalyze($function->getArgs(), $not);
                    default:
                        $functions = array_slice($functions, 0, -$num);//функции объекта
                        break(2);
                }
            }
            if (count($reverseFunctions) - 1 == $num) {
                $functions = [];
            }
            if (!count($functions) && is_object($object)) {
                if ($object instanceof SQLFunction) {
                    $result[] = $this->conditionArgumentsAnalyze($object->getArgs(), $not);
                    $object = null;
                }
                elseif ($object instanceof Field) {
                    $result[] = $this->conditionFieldAnalyze($object, $not);
                    $object = null;
                }
            }
        }
        if (count($functions) || !is_null($object)) {
            $result[] = [(object)[
                'not' => $not,
                'type' => 'functions',
                'functions' => $functions,
                'object' => $object,
            ]];
        }
        array_reverse($result);
        return call_user_func_array('array_merge', $result);
    }
    private function conditionAnalyze() {
        /*
         * определяем тип условия:
         * where
         * having
         * on
         * */

        /*
            result:

            [
                not => false,
                [
                    arg1,
                    cond1,
                    [
                        not => true,
                        [
                            arg1,
                            cond1,
                            arg2,
                        ]
                    ],
                    cond2
                ]
            ]



        */
        foreach ($this->childs as $child) {
            $child->conditionAnalyze();
        }
        $object = $this->condition->getObject();
        if (!isset($object['return'][0]->function)) {
            return;
        }


        if ($this->isCorrelate()) {
            //все условия внутри

        }
        elseif (!$this->isSubQuery()) {
            //все условия снаружи

        }
        else {
            //получаем массив разделенных условий
            $result = $this->conditionFunctionsAnalyze($object['return'][0]->function, $object['return'][0]->union);
            //необходим дополнительный анализ каждого элемента массива на принадлежность к текущему запросу, агрегатным функциям или родительскому запросу
            if ($result) {
                die();
            }
        }
    }
    public function analyze() {
        $this->calculatePathsVariables();
        $this->conditionAnalyze();
        //$this->aggregateLevelNormalization();
    }
}