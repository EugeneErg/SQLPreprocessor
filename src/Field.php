<?php namespace EugeneErg\SQLPreprocessor;

final class Field {
    const TYPE_FIELD = 'field';
    const TYPE_VARIABLE = 'variable';
    const TYPE_BLOCK = 'block';
    const TYPE_NULL = null;
    private $context;
    private $object;
    private $functions = [];
    private $aggregateLevel;
    private $type;

/*    public function __debugInfo() {
        return [
            'query index' => $this->context ? $this->context->getIndex() : null,
            'object' => $this->object,
            'functionCount' => count($this->functions),
            'aggregateLevel' => $this->aggregateLevel,
        ];
    }*/
    public function __construct(Query $context, $object = null, array $functions = []) {
        $contextFromObject = !count($functions);
        if (!$contextFromObject
            && !is_null($object)
            && !$object instanceof Self
        ) {
            $object = $context->getField($object);
        }
        if ($object instanceof Self) {
            $oContext = $object->getContext();
            $this->type = Self::TYPE_FIELD;
            if ($oContext === $context || $contextFromObject) {
                $this->aggregateLevel = $object->aggregateLevel;
            }
        }
        elseif ($object instanceof Variable) {
            $oContext = $context->find($object);
            $this->type = Self::TYPE_VARIABLE;
        }
        elseif (is_array($object)) {
            $oContext = $context;
            $this->type = Self::TYPE_BLOCK;
        }
        else {
            $oContext = $context;
            $this->type = Self::TYPE_NULL;
        }
        if (!isset($this->aggregateLevel)) {
            $this->aggregateLevel = 0;
        }
        $this->context = $contextFromObject ? $oContext : $context;

        foreach ($functions as $function) {
            $this->aggregateLevel += $this->getFunctionAggregateLevel($function);
        }
        $this->object = $object;
        $this->functions = $functions;
        if (!is_array($object) && !is_null($object)) {
            $context->addNeed($this, $oContext);
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
    public function getType() {
        return $this->type;
    }
}