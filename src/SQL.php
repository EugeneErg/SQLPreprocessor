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
            $$name->addChild('return', $return);
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
        
        $structure->addChild('from', $from);
        $structure->addChild('var', $var);
        
        $structure->addChild('select', $select, 'select');
        $structure->addChild('into', $into, 'insert');
        $structure->addChild('update', $update, 'update');
        $structure->addChild('delete', $delete, 'delete');
        
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
    private function fromStructureValidation($value, $structure) {
        $value->query = call_user_func_array([$structure->query, 'addChild'], $value->function->getValues());
    }
    private function deleteStructureValidation($value, $structure) {
        $value->query = call_user_func_array([$structure->query, 'addDeleted'], $value->function->getValues());
    }
    private function switchStructureValidation($value, $structure) {
        
    }
    private function ifStructureValidation($value, $structure) {
        
    }
    private function intoStructureValidation($value, $structure) {
        
    }
    private function returnStructureValidation($value, $structure) {
        
    }
    private function selectStructureValidation($value, $structure) {
        
    }
    private function varStructureValidation($value, $structure) {
        
    }
    private function orderbyStructureValidation($value, $structure) {
        
    }
    private function groupbyStructureValidation($value, $structure) {
        
    }
    private function getQueryTree(\StdClass $structure, array $keys) {
        foreach ($keys as $key) {
            if (isset($structure->childs[$key])) {
                foreach ($structure->childs[$key] as $value) {
                    $this->{"{$key}StructureValidation"}($value, $structure);
                    $this->getQueryTree($value, $keys);
                }
            }
        }
    }
    final private function getQueryInfo(Query $fQuery, Query $query) {
        /*
            $var->count() - SQL::from($var)->count() - посчитать количество * в контексте $var
            SQL::from($var, $var)->count() - посчитать количество * в контексте $var
            $var->id->count() - SQL::from($var->id)->count() - посчитать количество $var->id в текущем контектсе
            SQL::from($var, $var->id)->count() - посчитать количество $var->id в контексте $var
            SQL::from('var')->count() - посчитать количество переменых в текущем контектсе
            SQL::from($var, 'var')->count() - посчитать количество переменных в контексте запроса $var
            SQL::count() - SQL::from($context)->count()посчитать количество * в текущем контексте
            SQL::from('var') - получить значение переменной
        */
        if (!count($fQuery->functions)) {
            throw new \Exception('аргументом не может быть пустой запрос');
        }
        if ($fQuery->functions[0]->getName() == 'from') {
            $from = $fQuery->functions[0];
            unset($fQuery->functions[0]);
            $args = $from->getArgs();
            switch (count($args)) {
                case 0:
                    throw new \Exception('Неправильное количество аргументов');
                case 1:
                    switch ($args[0]->getType()) {
                        case Argument::IS_VARIABLE:
                            $variable = $args[0]->getValue();
                            if ($variable->getType() != Variable::IS_TABLE_FIELD) {
                                $context = $query;
                                $object = $variable;
                            }
                            else {
                                $context = $variable;
                                $object = null;
                            }
                            break;
                        case Argument::IS_SCALAR:
                            $object = $args[0]->getValue();//название переменной, объявленной в структуре
                            $context = $query;
                        default:
                            throw new \Exception('Неправильное значение аргумента');
                    }
                    break;
                case 2:
                default:
                    
            }
        }
        else {
            $context = $query;
            $object = null;
        }
        
        if (count($args) > 1) {
            if ($args[1] instanceof Variable) {
                return $this->getQeuryInfoByVariable($args[0], $args[1]);
            }
            return $this->getQeuryInfoByString($args[0], $args[1]);
        }
        if (count($functions) == 1 && $functions[0]->getName() == 'from') {
            switch (count($args = $functions[0]->getArgs())) {
                case 1:
                    if ($functions[0]->args[0]->type != 'string') {
                        throw new \Exception('Переменная должна быть строкой');
                    }
                    return (object)array (
                        'type' => 'Variable',
                        'name' => $functions[0]->args[0]->value,
                        'index' => null,
                        'function' => null,
                        'query' => $query,
                    );
                case 2:
                    if ($functions[0]->args[1]->type != 'string') {
                        throw new \Exception('Переменная должна быть строкой');
                    }
                    switch ($functions[0]->args[0]->type) {
                        case 'string':
                            $query = self::getContext($functions[0]->args[0]->value);
                            break;
                        case 'object ' . Variable::class:
                            $query = 'Object #' . $functions[0]->args[0]->value;
                            break;
                        default:
                        throw new \Exception('Переменная должна быть строкой');
                    }
                    
                    return (object)array (
                        'type' => 'Variable',
                        'name' => $functions[0]->args[1]->value,
                        'index' => null,
                        'function' => null,
                        'query' => $this->query->find($query),
                    );
                default:
                    
            }
        }
        
        //это агрегатная функция с названием контекста, и аргументами
        if ($functions[0]->name == 'from') {
            if (self::cnt($functions[0]->args) != 1) {
                throw new \Exception('Неправильное количество аргументов');
            }
            switch ($functions[0]->args[0]->type) {
                case 'string':
                    $query = self::getContext($functions[0]->args[0]->value);
                    break;
                case 'object ' . Variable::class:
                    $query = 'Object #' . $functions[0]->args[0]->value;
                    break;
                default:
                throw new \Exception('Переменная должна быть строкой');
            }
            
            unset($functions[0]);
            
            return (object)array(
                'type' => 'Function',
                'index' => null,
                'function' => &$functions,
                'query' => $this->query->find($query),
            );
        }
        else {
            return (object)array(
                'type' => 'Function',
                'index' => null,
                'function' => &$functions,
                'query' => $query,
            );
        }
    }
    private function getFieldByArg(Argument $argument, Query $query) {
        $varHash = spl_object_hash($value = $arg->getValue());
        if (isset($this->fields[$varHash])) {
            return $this->fields[$varHash];
        }
        switch ($arg->getType()) {
            case Argument::IS_VARIABLE:
                if ($value->getType != Variable::IS_TABLE_FIELD) {
                    $fieldHash = '*';
                }
                else {
                    $fieldHash = 'Field ' . $value->getValue()[1];
                }
                $outputQuery = $this->query->find($tableVar = $value->getTableVar());
                if (is_null($this->fields[$varHash] = $outputQuery->field($fieldHash))) {
                    $this->fields[$varHash] = $outputQuery->addField($varHash, 'Field', $value);
                }
            case Argument::IS_FUNCTION:
                $var = $this->getQueryInfo($value->functions, $query);

                $outputQuery = $var->query;
                if ($var->type == 'Function') {
                    $fieldHash = $varHash;
                    
                    $this->getUseFunctionArgs(SQLFunction $function, Query $query)
                    $this->getVarLink($var->function, $var->query);
                    $this::validationAggregates($var->function);
                    
                    $outputQuery->isSubQuery(!empty($var->function[0]->is_aggregates));
                }
                else {
                    $fieldHash = 'Variable ' . $var->name;
                    unset($var->name);
                }
                if (is_null($this->fields[$varHash] = $outputQuery->field($fieldHash))) {
                    $this->fields[$varHash] = $outputQuery->addField($fieldHash, $var->type, $var->function);
                }
                break;
        }
        return $this->fields[$varHash];
    }
    private function getUseArgumentArgs(Argument $argument, Query $query) {
        switch ($argument->getType()) {
            case Argument::IS_ARRAY:
                foreach ($argument->getValue() as $arg) {
                    $this->getUseArgumentArgs($arg, $query);
                }
                break;
            case Argument::IS_VARIABLE:
            case Argument::IS_FUNCTION:
            
            $query->addNeed($field, $field->query);
            $arg = (object)array (
                'type' => 'uses',
                'value' => $field,
            );
        }
    }
    private function getUseFunctionArgs(SQLFunction $function, Query $query) {
        foreach ($function->getArgs() as $arg) {
            $this->getUseArgumentArgs($arg, $query);
        }
    }
    private function getUseArgs(\StdClass $structure, Query $query = null) {
        if (isset($structure->query)) {
            $query = $structure->query;
        }
        else {
            $this->getUseFunctionArgs($structure->function, $query);
        }
        foreach ($structure->childs as $key => $values) {
            foreach ($values as $value) {
                $this->getUseArgs($value, $query);
            }
        }
        foreach ($structure->union as $function) {
            $this->getUseFunctionArgs($function, $query);
        }
        if (isset($structure->next)) {
            $this->getUseArgs($structure->next, $query);
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
    public function __invoke() {
        $structure = new \StdClass();
        $structure->childs = Self::structure()->validation($this->functions, $levels);
        $structure->query = new Query();
        $type = reset($levels);
        $this->getQueryTree($structure, ['from', 'delete']);
        $structure = $this->tryMoveFirstChildQueryToRoot($structure);
        $this->getUseArgs($structure);
    }
}