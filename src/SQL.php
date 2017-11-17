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
                $context = $query
                    ->find($args[0]
                    ->getValue()
                    ->getTableVar());
                $args[0] = $args[1];
            case 1:
                $value = $args[0]->getValue();
                switch ($args[0]->getType()) {
                    case Argument::IS_FUNCTION:
                        $this->getUseArgumentArgs($args[0], $context);
                        $value = $args[0]->getValue();
                        break;
                    case Argument::IS_SCALAR:
                        break;
                    case Argument::IS_VARIABLE:
                        $vContext = $query
                            ->find($value
                            ->getTableVar());
                        if ($vContext !== $context) {
                            $value = $context->addField($value);
                        }
                    default:
                        throw new \Exception('Неправильный тип аргумента');
                }
                $field = $context->addField($value, $fQuery->functions);
                foreach ($fQuery->functions as $function) {
                    $this->getUseFunctionArgs($function, $context);
                }
                if ($context !== $query) {
                    return $query->addField($field);
                }
                return $field;
        }
    }
    private function getUseArgumentArgs(Argument $argument, Query $query) {
        if (Argument::IS_ARRAY == $type = $argument->getType()) {
            foreach ($argument->getValue() as $arg) {
                $this->getUseArgumentArgs($arg, $query);
            }
            return;
        }
        if ($type == Argument::IS_VARIABLE) {
            $field = $query->addField($argument->getValue());
        }
        elseif ($type == Argument::IS_FUNCTION) {
            $field = $this->getQueryInfo($argument->getValue(), $query);
            
        }
        else {
            return;
        }
        $argument->setValue($field);
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
    public function getFunctions() {
        return $this->functions;
    }
}