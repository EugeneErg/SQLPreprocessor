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
    private $where = [];
    private $on = [];
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
    public function selected(Field $field) {
        return isset($this->select[$this->getFieldObjectHash($field->getObject())]);
    }
    public function getInclude(Field $field) {
        if (is_array($object = $field->getObject())) {
            $object = $field;
        }
        if (isset($this->include[$hash = $this->getFieldObjectHash($object)])) {
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
    private function getFieldObjectHash($object) {
        if (is_object($object)) {
            return spl_object_hash($object);
        }
        throw new \Exception('недопустимый тип объекта ' . getType($object));
    }
    public function getField($object, $createIfNotExist = true) {
        if (isset($this->include[$hash = $this->getFieldObjectHash($object)])) {
            return $this->include[$hash]->field;
        }
        if ($createIfNotExist) {
            return Field::create($this, $object);
        }
        return $object;
    }
    public function addNeed(Field $field, Query $query = null) {
        if (is_null($query)) {
            $query = $this;
        }
        if (!isset($this->include[$hash = $this->getFieldObjectHash($field->getObject())])) {
            $this->include[$hash] = (object) [
                'field' => $field,
            ];
            $prevQuery = $field->getContext();
            if ($field->getAggregateLevel() > 0) {
                $this->isSubQuery = true;
            }
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
                if ($this->include[$hash]->level == 0) {
                    $index = count($this->fields);
                    $this->fields[$index] = $field;
                    $field->setAlias($this->index . '.' . $index);
                }
            }
        }
        else {
            $query->output[$this->context][$hash] = $field;
            if ($this->parent !== $query) {
                $query->select[$hash] = $field;
            }
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
                if ($query->include[$hash]->level == 0) {
                    $index = count($query->fields);
                    $query->fields[$index] = $field;
                    $field->setAlias($query->index . '.' . $index);
                }
            }
        }
        $this->include[$hash]->query = $query;
    }
    public function addField($object = null, array $functions = []) {
        return Field::create($this, $object, $functions);
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
    public function addSelect($object) {
        if ($object instanceof SQLFunction) {
            $args = $object->getArgs();
            if (!count($args)) {
                return null;
            }
            switch ($args[0]->getType()) {
                case Argument::IS_NULL:
                    return '';
                case Argument::IS_SCALAR:
                    return $args[0]->getValue();
                case Argument::IS_FIELD:
                    $object = $args[0]->getValue();
                    break;
                default:
                    throw new \Exception('Данный тип аргумента не может быть правильным ключем массива');
            }
        }
        $field = $this->addField($object);
        return $this->select[$this->getFieldObjectHash($field->getObject())] = $field;
    }
    public function addOrder(array $childs, $asc = true) {
        $this->orders[] = (object) [
            'field' => $field = $this->addField($childs),
            'asc' => $asc,
        ];
        $this->isSubQuery = true;
        return $field;
    }
    public function addGroup(array $childs) {
        $this->isSubQuery = true;
        return $this->groups[] = $this->addField($childs);
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
        if ($this->getInclude($field)->level == -1
            || !in_array($field->getType(), [Field::TYPE_FIELD, Field::TYPE_NULL])
        ) {
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
    private function mergeResult($result) {
        switch (count($result)) {
            case 0:
                return [];
            case 1:
                return reset($result);
            default:
                return call_user_func_array('array_merge', $result);
        }
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
                    if ((in_array($value, ['and', '&&'])) === $not) {
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
            if ($num % 2 === 0 || !in_array($arg->getValue(), ['and', '&&', 'or', '||'])) {
                $res[] = $arg;
                continue;
            }
            if (count($res) == 1) {
                $result[] = $this->conditionArgumentAnalyze($res[0], $not);
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
        return $this->mergeResult($result);

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
                        break;
                    default:
                        if ($num > 0) {
                            $functions = array_slice($functions, 0, -$num);//функции объекта
                        }
                        break(2);
                }
            }
            $count = count($reverseFunctions);
            if ($count > 0 && $count - 1 == $num) {
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
            if ($object instanceof SQLFunction) {
                array_unshift($functions, $object);
                $object = null;
            }
            $result[] = [(object)[
                'not' => $not,
                'type' => 'field',
                'field' => $this->addField($object, $functions),
            ]];
        }
        return $this->mergeResult($result);
    }
    private function conditionTypeBlockAnalyze(array $blocks, \StdClass $result) {
        $return = 0;
        foreach ($blocks as $block) {
            if (isset($blocks->next)) {
                $return = $this->conditionTypeBlockAnalyze([$block->next], $result) || $return;
            }
            $return = $this->conditionTypeFunctionsAnalyze($block->union, null, $result) || $return;
            foreach ($block->childs as $child) {
                $return = $this->conditionTypeBlockAnalyze($child, $result) || $return;
            }
        }
        return $return;
    }
    private function conditionTypeFieldFunctionsAnalyze(Field $field, array $functions, \StdClass $result, $pos = 0) {
        /*
         * необходимо анализировать функции рекурсивно,
         * и в последнюю очередь анализировать объект
         * в некоторых случаях создавать новые фиелды из урезанных функций и ссылаться на них из текущего фиелда
         * */
        if ($pos >= count($functions)) {
            return ($field->getType() !== Field::TYPE_NULL) && $this->conditionTypeFieldAnalyze($field, $result);
        }
        $function = $functions[$pos];
        //первыя функция по сути является нулевым аргументом
        $move = $result->move;
        $result->move = [];
        $return = $this->conditionTypeFieldFunctionsAnalyze($field, $functions, $result, $pos + 1);
        $return = $this->conditionTypeArgsAnalyze($function->getArgs(), $result) || $return;
        if ($return) {
            $result->move = array_merge($move, $result->move);
            return 1;
        }
        $result->move = $move;
        return 0;
    }
    private function conditionTypeFieldAnalyze(Field $field, \StdClass $result) {
        /*
         * В каждой функции предпологаем что перемещение текущих необходимо,
         * но преремещание дочерних зависит от возвращаемого значения
         * */
        $hash = spl_object_hash($field);
        if (isset($result->levels[$hash])) {
            return $result->levels[$hash];
        }
        $include = $this->getInclude($field);
        if ($include->level != 0) {
            return $result->levels[$hash] = $include->level == 1;
        }
        if ($field->isAggregate()) {
            $result->move[$hash] = $field;
            return $result->levels[$hash] = 1;
        }
        if (in_array($type = $field->getType(), [Field::TYPE_FIELD, Field::TYPE_NULL])) {
            $move = $result->move;
            $result->move = [];
            if ($this->conditionTypeFieldFunctionsAnalyze($field, array_reverse($field->getFunctions()), $result)) {
                $result->move = array_merge($move, $result->move);
                return $result->levels[$hash] = 1;
            }
            $result->move = array_merge($move, [$hash => $field]);
            return $result->levels[$hash] = 0;
        }
        if ($field->getType() == Field::TYPE_VARIABLE) {
            $result->move[$hash] = $field;
            return $result->levels[$hash] = 0;
        }
        elseif ($field->getType() == Field::TYPE_BLOCK) {
            $move = $result->move;
            $result->move = [];
            if ($this->conditionTypeBlockAnalyze((array) $field->getObject(), $result)) {
                $result->move = array_merge($move, $result->move);
                return $result->levels[$hash] = 1;
            }
            $result->move = array_merge($move, [$hash => $field]);
            return $result->levels[$hash] = 0;
        }
    }
    private function conditionTypeArgsAnalyze(array $args, \StdClass $result) {
        $return = 0;
        foreach ($args as $arg) {
            if ($arg->getType() == Argument::IS_FIELD) {
                $return = $this->conditionTypeFieldAnalyze($arg->getValue(), $result) || $return;
            }
        }
        return $return;
    }
    private function conditionTypeFunctionsAnalyze(array $functions, $object = null, \StdClass $result) {
        if ($object instanceof SQLFunction) {
            array_unshift($functions[], $object);
            $object = null;
        }
        $return = ($object instanceof Field) && $this->conditionTypeFieldAnalyze($object, $result);
        foreach ($functions as $function) {
            $return = $this->conditionTypeArgsAnalyze($function->getArgs(), $result) || $return;
        }
        return $return;
    }
    private function conditionTypeAnalyze(\StdClass $condition, \StdClass $result) {
        switch ($condition->type) {
            case 'args':
                return $this->conditionTypeArgsAnalyze($condition->args, $result);
            case 'field':
                return $this->conditionTypeFieldAnalyze($condition->field, $result);
        }
    }
    private function conditionTypesAnalyze(array $conditions = []) {
        $result = (object) [
            'where' => [],
            //'having' => [],
            'on' => [],
            'move' => [],
            'levels' => [],
        ];
        foreach ($conditions as $condition) {
            if ($this->conditionTypeAnalyze($condition, $result)) {
                $result->on[] = $condition;
            }
            else {
                $result->where[] = $condition;
            }
        }
        return $result;
    }
    private function addInclude(Field $field, Query $query = null) {
        if (is_null($query)) {
            $query = $this;
            $level = 0;
        }
        else {
            $level = ($query != $this) * (1 - 2 * isset($this->childs[$query->context]));
        }
        return $this->include[$this->getFieldObjectHash($field->getObject())] = (object) [
            'field' => $field,
            'query' => $query,
            'level' => $level,
        ];
    }
    private function conditionAnalyze() {
        foreach ($this->childs as $child) {
            $child->conditionAnalyze();
        }
        $object = $this->condition->getObject();
        if (!isset($object->return[0]->function)) {
            return;
        }
        if ($this->isCorrelate()) {
            //все условия внутри
            $conditions = (object) [
                'where' => [
                    (object)[
                        'not' => false,
                        'type' => 'field',
                        'field' => $this->condition,
                    ]
                ],
                'on' => [],
                'move' => [],
            ];
        }
        elseif (!$this->isSubQuery()) {
            //все условия снаружи
            $conditions = (object) [
                'on' => [
                    (object)[
                        'not' => false,
                        'type' => 'field',
                        'field' => $this->condition,
                    ]
                ],
                'where' => [],
                'move' => [],
            ];
        }
        else {
            //получаем массив разделенных условий
            $conditions = $this->conditionFunctionsAnalyze($object->return[0]->function, $object->return[0]->union);
            //необходим дополнительный анализ каждого элемента массива на принадлежность к текущему запросу, агрегатным функциям или родительскому запросу
            $conditions = $this->conditionTypesAnalyze($conditions);
        }
        if (!is_null($this->parent)) {
            foreach ($conditions->move as $field) {
                $this->parent->addInclude($field, $this);
                $this->select[$this->getFieldObjectHash($field->getObject())] = $field;
            }
        }
        $this->where = $conditions->where;
        $this->on = $conditions->on;
    }
    private function getLevelByField(Field $field, &$levels, $level) {
        if ($field->isAggregate()) {
            $fields = [$field];
        }
        else {
            $fields = $field->getAggregates();
        }
        foreach ($fields as $field) {
            $newLevel = $field->getAggregateLevel();
            $levels[$level][$newLevel][spl_object_hash($field)] = $field;
            if ($level > 1) {
                $this->getLevelByField($field, $levels, $level);
            }
        }
    }
    private function getAggregateLevels() {
        $result = [];
        foreach ($this->select as $select) {
            if (0 === $level = $select->getAggregateLevel()) {
                $result[0][0][spl_object_hash($select)] = $select;
                continue;
            }
            if ($this->getInclude($select)->level != 0) {
                $result[0][0][spl_object_hash($select)] = $select;
                continue;
            }
            $this->getLevelByField($select, $result, $level);
        }
        return $result;
    }
    private function aggregateLevelNormalization() {
        foreach ($this->childs as $child) {
            $child->aggregateLevelNormalization();
        }
        if (!$this->isSubQuery() || $this->isCorrelate() || !count($this->select)) {
            return;
        }
        $validLevel = 1 + !!count($this->groups);
        $maxLevel = 0;
        $minLevel = $validLevel;
        $levels = $this->getAggregateLevels();
        foreach ($levels as $level => $fields) {
            if ($minLevel > $level) {
                $minLevel = $level;
            }
            if ($maxLevel < $level) {
                $maxLevel = $level;
            }
            if ($level > $validLevel) {
                throw new \Exception('Недопустимый уровень агрегатности');
            }
        }
        if ($minLevel == $maxLevel && $maxLevel < 2) {
            return;
        }
        if (!count($this->groups)) {
            $cloneQuery = $this->addClone($this);
            $cloneQuery->where = $this->where;

            foreach ($levels[1][1] as $field) {
                $cloneQuery->select[$this->getFieldObjectHash($field->getObject())] = $field;
                $cloneInclude = $cloneQuery->getInclude($field);
                $cloneInclude->query = $cloneQuery;

                $include = $this->getInclude($field);
                $include->query = $cloneQuery;
                $include->level = -1;
            }
            $this->where = array_merge($this->where, $this->on);
            $this->on = [];
        }
        else {
            /*if (isset($levels[2])) {//стоит выполнять вначале
                //оптимизировать при отсутсвии 0 уровня
                if (count($levels) == 1) {
                    $cloneQuery1 = $this;
                    $cloneQuery2 = $this->addClone($this);
                }
                else {
                    $newQuery = new Query();
                    $cloneQuery1 = $newQuery->addClone($this);
                    $cloneQuery1->groupbyClear();
                    $cloneQuery2 = $cloneQuery1->addClone($this);
                    $cloneQuery2->where = $this->where;
                    $cloneQuery2->on = $this->on;
                    foreach ($levels[2][2] as $field) {
                        $cloneQuery1->select[spl_object_hash($field)] = $field;
                        $cloneQuery1->addInclude($field);
                    }
                }
                foreach ($levels[2][1] as $field) {
                    $cloneQuery2->addSelect($field);
                    $cloneQuery1->addInclude($field, $cloneQuery2);
                }
            }
            if (isset($levels[1])) {
                $cloneQuery = $this->addClone($this);
                $cloneQuery->on = [];
                foreach ($levels[1][1] as $field) {
                    $cloneQuery->select[spl_object_hash($field)] = $field;
                    $cloneInclude = $cloneQuery->getInclude($field);
                    $cloneInclude->query = $cloneQuery;

                    $include = $this->getInclude($field);
                    $include->query = $cloneQuery;
                    $include->level = -1;
                }
                foreach ($this->groups as $field) {//группировка должна быть не перенесена а продублированна {on t2.group=table.group}
                    $cloneQuery->addSelect($field);
                    $hash = 'clone #' . count($field::fields($query));
                    $cloneField = $query->addField($hash, $field->type, $field->function);
                    $query->addInclude($cloneField);
                    $query->addInclude($field, $cloneQuery);

                    $cloneQuery->on[] = (object) array (
                        'not' => false,
                        'args' => array (
                            (object) array (
                                'type' => 'uses',
                                'value' => $field,
                            ),
                            (object) array (
                                'type' => 'string',
                                'value' => '=',
                            ),
                            (object) array (
                                'type' => 'uses',
                                'value' => $cloneField,
                            )
                        )
                    );
                }
            }
            if (isset($moveLevels[2]) && count($moveLevels) != 1) {
                $query->moveToChild($cloneQuery1);
                foreach ($moveLevels[2][0] as $field) {
                    $query->addInclude($field, $cloneQuery1);
                }
            }
            //$newQuery
            /*
                1) создаем новый запрос
                2) копируем в него текущий запрос
                3) переносим в селект скопированного запроса агрегаты второго уровня
                4) переносим новый запрос в качестве дочернего запроса, в текущий запрос
            */
            /*
                join(
                    select
                        avg(`t2`.count)
                    from(
                        select
                            count(*) `count`
                        from table
                        group by
                            group
                        limit 1,2
                    )t2
                )t3
            */
      /*      $query->groupbyClear();
            $query->distinct(true);
            /*if (is_null($query->parent)) {
                //die('необходимо проверить работоспособность инструкции');
                if ($query->where) {
                    $query->where += $query->on;
                }
                else {
                    $query->where = $query->on;
                }
                $query->on = null;
            }*/
        /*    if ($query->having) {
                if ($query->where) {
                    $query->where += $query->having;
                }
                else {
                    $query->where = $query->having;
                }
                $query->having = null;
            }*/
        }
    }
    public function analyze() {
        $this->calculatePathsVariables();
        $this->conditionAnalyze();
 //       $this->aggregateLevelNormalization();
    }
}