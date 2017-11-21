<?php namespace EugeneErg\SQLPreprocessor;

final class SQL {

    private $functions = [];
    private $hash;
    private $fields = [];
    
    private function __clone() {}
    private function __wakeup() {}

    private function hashes($hash = null) {
        static $hashes = [];
        if (is_null($hash)) {
            $hashes[$hash = spl_object_hash($this)] = $this;
            return $hash;
        }
        if (isset($hashes[$hash])) {
            return $hashes[$hash];
        }
    }
    private function __construct() {
        $this->hash = $this->hashes();
    }
    private static function structure() {
        static $structure;
        if (isset($structure)) {
            return $structure;
        }
        $structure = new Structure();
        $if = new Structure(1, 1, ['or', 'and']);
            $else = new Structure();
            $endif = new Structure();
        $switch = new Structure(1, 1);
            $case = new Structure(0, 0, ['or', 'and']);
            $default = new Structure();
        $select = new Structure(1);
        $var = new Structure();
        $from = new Structure();
        $orderby = new Structure();
        $groupby = new Structure();
        $into = new Structure(1, 1);
            $insert = new Structure();
        $update = new Structure(1);
        $delete = new Structure(1);
        $return = new Structure(0, 1);//ретерн не имеет дочерних блоков, поэтому не будет нуждаться в закрывающей функции
                
        
        //блок if
        $if->addBlock('else', $else);
        $if->addBlock('elseif', $if);
        $if->addBlock('endif');
        $else->addBlock('endif');
        
        
        //блок switch
        $switch->addBlock('case', $case);
        $switch->addBlock('default', $default);
        $case->addBlock('case', $case);
        $case->addBlock('default', $default);
        $case->addBlock('endswitch');
        $default->addBlock('endswitch');
        
        
        // блок from
        foreach (['from', 'delete'] as $name) {
            $$name->addChild('from', $from);
            $$name->addChild('return', $return, 'return');
            $$name->addChild('if', $if, 'if');
            $$name->addChild('switch', $switch, 'switch');
            $$name->addChild('var', $var);
            $$name->addChild('groupby', $groupby);
            $$name->addChild('orderby', $orderby);
        }
        
        
        // блок delete
        $delete->addChild('delete', $delete);
        
        
        //блок var if else case default orderby groupby update insert
        foreach (['var', 'if', 'else', 'case', 'default', 'orderby', 'groupby', 'insert', 'update', 'select'] as $name) {
            $$name->addChild('switch', $switch, 'switch');
            $$name->addChild('if', $if, 'if');
            $$name->addChild('return', $return, 'return');
        }
        
        $var->addBlock('var', $var);
        $var->addBlock('endvar');
        
        
        $orderby->addBlock('orderby', $orderby);
        $orderby->addBlock('endorderby');
        
        
        $groupby->addBlock('groupby', $groupby);
        $groupby->addBlock('endgroupby');
        
        
        //блок select
        $select->addChild('select', $select, 'select');
        
        
        //блок into
        $into->addBlock('insert', $insert);
        $insert->addBlock('insert', $insert);
        $insert->addBlock('endinto', $insert);
        
        $structure->addChild('from', $from, [], null, 1);
        
        $structure->addChild('select', $select, 'select');
        $structure->addChild('into', $into, 'insert');
        $structure->addChild('update', $update, 'update');
        $structure->addChild('delete', $delete, 'delete', null, 1);
        
        return $structure;
    }
    public static function create(\Closure $function = null) {
        $new = new Self();
        if (is_null($function)
            || $new === $result = $function($new)
        ) {
            return $new;
        }
        return $new->return($result);
    }
    public function __debugInfo() {
        return [
            'hash' => $this->hash,
        ];
    }
    public function __call($name, $args) {
        $this->functions[] = new SQLFunction($name, $args);
        return $this;
    }
    public static function __callStatic($name, $args) {
        $sql = new Self();
        return $sql->__call($name, $args);
    }
    public function __get($name) {
        if (!is_null($sql = $this->hashes($name))) {
            $this->functions[] = &$sql->functions;
            return $this;
        }
        return $this->__call($name, []);
    }
    public function __toString() {
        return $this->hash;
    }
    private static function getContext($context) {
        switch(getType($context)) {
            case 'object':
                return spl_object_hash($context);
            case 'NULL':
                return 'NULL';
            default:
                if (is_scalar($context)) {
                    return 'SCALAR ' . $context;
                }
                throw new \Exception('неизвестный тип переменной ' . print_r($context,true));
        }
    }
    private function fromStructureValidation(\StdClass $value, Query $query = null) {
        $value->query = call_user_func_array(is_null($query) ? [Query::class, 'create'] : [$query, 'addChild'], $value->function->getValues());
    }
    private function deleteStructureValidation(\StdClass $value, Query $query = null) {
        $value->query = call_user_func_array(is_null($query) ? [Query::class, 'createDeleted'] : [$query, 'addDeleted'], $value->function->getValues());
    }
    private function selectStructureParentValidation(\StdClass $value, \StdClass $structure, Query $query) {
        $args = $value->function->getArgs();
        if (!count($args)) {
            $val = null;
        }
        elseif ($args[0]->getType() == 'NULL') {
            $val = '';
        }
        else {
            $val = $args[0]->getValue();
        }
        $hash = Self::getContext($val);
        if (isset($structure->select->childs[$hash])) {
            throw new \Exception('нельзя использовать одинаковые ключи для двух соседних селектов');
        }
        $structure->select->childs[$hash] = $value->select = new \StdClass();
        $value->select->key = $val;
        $value->select->childs = [];
        if (!isset($value->childs['select'])) {
            $value->select->result = $query->addSelect($value->childs);
        }
    }
    private function varStructureValidation(\StdClass $value, Query $query) {
        $args = $value->function->getArgs();
        if (!count($args) || $args[0]->getType() != 'scalar') {
            throw new \Exception('неправильный тип аргумента');
        }
        $query->addVariable($value->childs, $args[0]->getValue());
        if (isset($value->next)) {
            $this->varStructureValidation($value->next, $query);
        }
    }
    private function varStructureParentValidation(\StdClass $value, \StdClass $structure, Query $query) {}
    private function fromStructureParentValidation(\StdClass $value, \StdClass $structure = null, Query $query) {
        $moveChilds = $value->childs;
        unset($moveChilds['from']);
        unset($moveChilds['delete']);
        $value->query->addConditions($moveChilds);
    }
    private function deleteStructureParentValidation(\StdClass $value, \StdClass $structure = null, Query $query) {
        $this->fromStructureParentValidation($value, $structure, $query);
    }
    private function orderbyStructureParentValidation(\StdClass $value, \StdClass $structure, Query $query) {
        $args = $value->function->getArgs();
        $query->addOrderby($value->childs, !count($args) || !empty($args[0]->getValue()));
        if (isset($value->next)) {
            $this->orderbyStructureParentValidation($value->next, $structure, $query);
        }
    }
    private function groupbyStructureParentValidation(\StdClass $value, \StdClass $structure, Query $query) {
        $query->addGroupby($value->childs);
        if (isset($value->next)) {
            $this->groupbyStructureParentValidation($value->next, $structure, $query);
        }
    }
    private function updateStructureParentValidation(\StdClass $value, \StdClass $structure, Query $query) {
        if (!count($args)) {
            throw new \Exception('неправильный тип аргумента');
        }
        $query->addUpdate($value->childs, $args[0]->getValue());
        if (isset($value->next)) {
            $this->updateStructureParentValidation($value->next, $structure, $query);
        }
    }
    private function intoStructureParentValidation(\StdClass $value, \StdClass $structure, Query $query) {
        $args = $value->function->getArgs();
        if (!count($args) || $args[0]->getType() != 'scalar') {
            throw new \Exception('неправильный тип аргумента');
        }
        $value->insertTable = $args[0]->getValue();
    }
    private function insertStructureParentValidation(\StdClass $value, \StdClass $structure, Query $query) {
        $args = $value->function->getArgs();
        if (!count($args) || $args[0]->getType() != 'scalar') {
            throw new \Exception('неправильный тип аргумента');
        }
        $query->addInsert($value->childs, $structure->insertTable, $args[0]->getValue());
        if (isset($value->next)) {
            $this->insertStructureParentValidation($value->next, $structure, $query);
        }
    }
    private function getQueryTree(\StdClass $structure, array $keys) {
        $query = null;
        $result = null;
        foreach ($keys as $key) {
            if (isset($structure->childs[$key])) {
                foreach ($structure->childs[$key] as $value) {
                    if (!isset($query) && isset($structure->query)) {
                        $result = $query = $structure->query;
                    }
                    $this->{"{$key}StructureValidation"}($value, $query);
                    $subQuery = $this->getQueryTree($value, $keys);
                    if (is_null($result)) {
                        $result = $subQuery;
                    }
                }
            }
        }
        if (!isset($result) && isset($structure->query)) {
            $result = $structure->query;
        }
        return $result;
    }
    final private function getQueryInfo(Self $fQuery, Query $query) {
        /*
            $var->id->function() => SQL::from($var, $var->id)->function()
            $var('id')->function() => SQL::from($var, 'id')->function()
            $var($var2->id)->function() => SQL::from($var, $var2->id)->function()
            $var($var2('id'))->function => SQL::from($var, SQL::from($var2, 'id'))->function()
            SQL::from('id')
            SQL::from($var->id)
        */
        if (!count($fQuery->functions)) {
            throw new \Exception('аргументом не может быть пустой запрос');
        }
        if ($fQuery->functions[0]->getName() != 'from') {
            return $query->addField(null, $fQuery->functions);
        }
        $from = array_shift($fQuery->functions);
        $args = $from->getArgs();
        $context = $query;
        switch (count($args)) {
            case 0:
                return $this->getQueryInfo($fQuery, $query);
            case 2:
            default:
                if ($args[0]->getType() != Argument::IS_VARIABLE) {
                    throw new \Exception('Первым параметром ожидается объект переменной');
                }
                $context = $query->find($args[0]->getValue());
                $args[0] = $args[1];
            case 1:
                $value = $args[0]->getValue();
                switch ($args[0]->getType()) {
                    case Argument::IS_FUNCTION:
                        $this->getUseArgumentArgs($args[0], $context);
                        $value = $args[0]->getValue();
                        break;
                    case Argument::IS_SCALAR:
                        $value = $context->getVariable($value);
                        if (is_null($value->getAggregateLevel())) {
                            $this->getUseChilds($value->getObject(), $context);
                            $value->setAggregateLevel();
                        }
                        break;
                    case Argument::IS_VARIABLE:
                        $vContext = $query->find($value);
                        if ($vContext !== $context) {
                            $value = $context->addField($value);
                        }
                    default:
                        throw new \Exception('Неправильный тип аргумента');
                }
                $field = $context->addField($value, $fQuery->functions);
                $this->getUseFunctions($fQuery->functions, $context);//необходимо вычислять агрегатные уровни функций в объявленном контектсе
                if ($context !== $query) {
                    return $query->addField($field);
                }
                return $field;
        }
    }
    private function getUseArgumentArgs(Argument $argument, Query $query) {
        $level = 0;
        if (Argument::IS_ARRAY == $type = $argument->getType()) {
            foreach ($argument->getValue() as $arg) {
                $level = max($level, $this->getUseArgumentArgs($arg, $query));
            }
        }
        elseif ($type == Argument::IS_VARIABLE) {
            $argument->setValue($query->addField($argument->getValue()));
        }
        elseif ($type == Argument::IS_FUNCTION) {
            $field = $this->getQueryInfo($argument->getValue(), $query);
            $argument->setValue($field);
            $level = max($level, $field->getAggregateLevel());
        }
        return $level;
    }
    private function getUseFunctionArgs(SQLFunction $function, Query $query) {
        foreach ($function->getArgs() as $arg) {
            $this->getUseArgumentArgs($arg, $query);
        }
    }
    private function getUseFunctions(array $functions, Query $query) {
        foreach ($functions as $function) {
            $this->getUseFunctionArgs($function, $query);
        }
    }
    private function getUseChilds(array $childs, Query $query) {
        foreach ($childs as $key => $values) {
            foreach ($values as $value) {
                $this->getFields($value, $query);
            }
        }
    }
    private function getFields(\StdClass $structure, Query $query) {
        if (isset($structure->query)) {
            $query = $structure->query;
        }
        elseif (isset($structure->function)) {
            $this->getUseFunctionArgs($structure->function, $query);
        }
        $this->getUseChilds($structure->childs, $query);
        $this->getUseFunctions($structure->union, $query);
        if (isset($structure->next)) {
            $this->getFields($structure->next, $query);
        }
    }
    private function tryMoveFirstChildQueryToRoot(\StdClass $structure) {
        if ($structure->query === $newFirstQuery = $structure->query->setOneChildAsRoot()) {
            return $structure;
        }
        foreach ($structure->childs['from'] as $num => $child) {
            if (isset($child->query) && $child->query === $newFirstQuery) {
                unset($structure->childs['from'][$num]);
                $child->childs = array_merge_recursive($structure->childs, $child->childs);
                return $child;
            }
        }
        return $structure;
    }
    private function parseTreeFunctions(\StdClass $structure, array $keys, Query $query) {
        if (isset($structure->query)) {
            $query = $structure->query;
        }
        foreach ($structure->childs as $key => $values) {
            $is_inarray = in_array($key, $keys);
            foreach ($values as $value) {
                $this->parseTreeFunctions($value, $keys, $query);
                if ($is_inarray) {
                    $this->{"{$key}StructureParentValidation"}($value, $structure, $query);
                }
            }
            if ($is_inarray) {
                unset($structure->childs[$key]);
            }
        }
    }
    public function __invoke($sql, \Closure $function) {
        $structure = new \StdClass();
        $structure->childs = Self::structure()->validation($this->functions, $levels);
        $structure->union = [];
        $type = reset($levels);
        $query = $this->getQueryTree($structure, ['from', 'delete', 'var']);//содержит основной запрос
        $select = $structure->select = new \StdClass();//формирует структуру результата
        $structure->select->childs = [];
        $this->getFields($structure, $query);
        $this->parseTreeFunctions($structure, ['orderby', 'groupby', 'insert', 'into', 'select', 'var', 'from', 'delete'], $query);
        unset($structure);
        $query->calculatePathsVariables();
    }
}