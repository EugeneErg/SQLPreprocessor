<?php namespace EugeneErg\SQLPreprocessor;

final class Field {
    private $context;
    private $object;
    private $functions = [];
    private $aggregateLevel;
    
    private function getObjectInfo() {
        if ('object' === $type = getType($this->object)) {
            if (!($this->object instanceof Variable)) {
                return get_class($this->object);
            }
            if ($this->object->getType() === Variable::IS_TABLE_FIELD) {
                return $this->object->getTableVar()->getValue() . '.' . $this->object->getValue();
            }
            return $this->object->getValue();
        }
        if (is_scalar($this->object)) {
            return '"' . $this->object . '"';
        }
        return $type = getType($this->object);
    }
    public function __debugInfo() {
        return [
            'query index' => $this->context ? $this->context->getIndex() : null,
            'object' => $this->object,
            'functionCount' => count($this->functions),
            'aggregateLevel' => $this->aggregateLevel,
        ];
    }
    public function __construct(Query $context, $object = null, array $functions = []) {
        if ($object instanceof Variable) {
            $sContext = $context->find($object);
        }
        elseif ($object instanceof Self) {
            $sContext = $object->getContext();
            if ($sContext === $context) {
                $this->aggregateLevel = $object->aggregateLevel;
                if (!count($functions) || !count($object->functions)) {
                    $functions = array_merge($object->functions, $functions);
                    $object = $object->object;
                }
            }
        }
        else {
            $sContext = $context;
        }
        $this->context = $context;
        foreach ($functions as $function) {
            $this->aggregateLevel += $this->getFunctionAggregateLevel($function);
        }
        $this->object = $object;
        $this->functions = $functions;
        if (!is_array($object) && !is_null($object)) {
            $context->addNeed($this, $sContext);
        }
    }
    public function getObject() {
        return $this->object;
    }
    public function getFunctions() {
        return $this->functions;
    }
    public function getValue() {
        if (!is_null($this->object)) {
            return $this->context->getFieldValue($this->object);
        }
    }
    public function getAggregateLevel() {
        return $this->aggregateLevel;
    }
    private function getFunctionAggregateLevel($function) {
        $level = 0;
        foreach ($function->getArgs() as $arg) {
            if ($arg->getType() == Argument::IS_FIELD
                && $arg->getValue()->context === $this->context
            ) {
                /*
                    если $arg->getValue()->context !== $context
                    то это либо дочерние либо родительские запросы
                    агрегатные функции дочерних запросов передаются в неагрегатном состоянии
                    агрегатные функции родительских запросов не доступны
                */
                $level = max($level, $arg->getValue()->aggregateLevel);
            }
        }
        return $level + (integer) $function->isAggregate();
    }
    private function getStructureAggregateLevel($structure) {
        $level = $this->getChildsAggregateLevel($structure->childs);
        if (isset($structure->next)) {
            $level = max($level, $this->getStructureAggregateLevel($structure->next));
        }
        foreach ($structure->union as $function) {
            $level = max($level, $this->getFunctionAggregateLevel($function));
        }
        return $level;
    }
    private function getChildsAggregateLevel($childs) {
        $level = 0;
        foreach ($childs as $key => $values) {
            foreach ($values as $value) {
                $level = max($level, $this->getStructureAggregateLevel($value));
            }
        }
        return $level;
    }
    public function setAggregateLevel() {
        if (is_null($this->aggregateLevel) && is_array($this->object)) {
            return $this->aggregateLevel = $this->getChildsAggregateLevel($this->object);
        }
    }
    public function getContext() {
        return $this->context;
    }
}