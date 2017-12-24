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
    private $aggregates = [];
    private $isAggregate = false;
    private $type;

/*    public function __debugInfo() {
        return [
            'query index' => $this->context ? $this->context->getIndex() : null,
            'object' => $this->object,
            'functionCount' => count($this->functions),
            'aggregateLevel' => $this->aggregateLevel,
        ];
    }*/
    private function __construct() {}
    public static function create(Query $context, $object = null, array $functions = []) {
        $field = new Self();

        $contextFromObject = !count($functions);
        if (!is_array($object) && !is_null($object) && !$object instanceof Self) {
            $object = $context->getField($object, !$contextFromObject);
            if ($contextFromObject && $object instanceof Self) {
                return $object;
            }
        }
        if ($object instanceof Self) {
            $oContext = $object->getContext();
            $field->type = Self::TYPE_FIELD;
            if ($oContext === $context || $contextFromObject) {
                $field->aggregateLevel = $object->aggregateLevel;
                if ($object->isAggregate) {
                    $field->aggregates = [$object];
                }
                else {
                    $field->aggregates = $object->aggregates;
                }
            }
        }
        elseif ($object instanceof Variable) {
            $oContext = $context->find($object);
            $field->type = Self::TYPE_VARIABLE;
        }
        elseif (is_array($object)) {
            $object = (object) $object;
            $oContext = $context;
            $field->type = Self::TYPE_BLOCK;
        }
        else {
            $oContext = $context;
            $field->type = Self::TYPE_NULL;
        }
        if (!isset($field->aggregateLevel) && $field->type != Self::TYPE_BLOCK) {
            $field->aggregateLevel = 0;
        }
        $field->context = $contextFromObject ? $oContext : $context;
        $field->object = $object;
        $field->functions = $functions;
        foreach ($functions as $pos => $function) {
            $field->aggregateLevel += $field->getFunctionAggregateLevel($function);
            if ($function->isAggregate()) {
                $field->aggregateLevel++;
                $field->isAggregate = true;
                if (count($functions) !== $pos + 1) {
                    $field->functions = array_splice($functions, 0, $pos + 1);
                    if (!is_array($object) && !is_null($object)) {
                        $context->addNeed($field, $oContext);
                    }
                    return Self::create($context, $field, array_slice($functions, $pos));
                }
            }
        }
        if (!is_array($object) && !is_null($object)) {
            $context->addNeed($field, $oContext);
        }
        return $field;
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
                $field = $arg->getValue();
                if ($field->isAggregate) {
                    $this->aggregates[spl_object_hash($field)] = $field;
                }
                else {
                    $this->aggregates = array_merge($this->aggregates, $field->aggregates);
                }
                $level = max($level, $field->aggregateLevel);
            }
        }
        return $level;
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
        if (is_null($this->aggregateLevel) && $this->type == Self::TYPE_BLOCK) {
            return $this->aggregateLevel = $this->getChildsAggregateLevel($this->object);
        }
    }
    public function getContext() {
        return $this->context;
    }
    public function getType() {
        return $this->type;
    }
    public function isAggregate() {
        return $this->isAggregate;
    }
    public function getAggregates() {
        return $this->aggregates;
    }
    public function __toString() {
        return (string) $this->context->getAlias($this);
    }
}