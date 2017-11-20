<?php namespace EugeneErg\SQLPreprocessor;

final class Field {
    private $context;
    private $object;
    private $functions = [];
    private $aggregateLevel;
    
    public function __construct(Query $context, $object = null, array $functions = []) {
        if ($object instanceof Variable) {
            $sContext = $context->find($object);
        }
        elseif ($object instanceof Self) {
            $sContext = $object->getContext();
            if ($sContext === $context) {
                $this->aggregateLevel = $object->aggregateLevel;
                if (!count($functions) || !count($object->functions)) {
                    $object = $object->object;
                    $functions = array_merge($object->functions, $functions);
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
        if ($sContext !== $context) {
            $sContext->addNeed($this);
        }
    }
    public function getObject() {
        return $this->object;
    }
    public function getFunctions() {
        return $this->functions;
    }
    public function getValue() {
        if (!is_null($object)) {
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