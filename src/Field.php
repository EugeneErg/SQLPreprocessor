<?php namespace EugeneErg\SQLPreprocessor;

final class Field {
    private $context;
    private $object;
    private $functions = [];
    private $aggregateLevel = 0;
    
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
        foreach ($functions as $function) {
            $maxLevel = 0;
            foreach ($function->getArgs() as $arg) {
                if ($arg->getType() == Argument::IS_FIELD
                    && $arg->getValue()->context === $context
                ) {
                    /*
                        если $arg->getValue()->context !== $context
                        то это либо дочерние либо родительские запросы
                        агрегатные функции дочерних запросов передаются в неагрегатном состоянии
                        агрегатные функции родительских запросов не доступны
                    */
                    $maxLevel = max($arg->getValue()->aggregateLevel, $maxLevel);
                }
            }
            $this->aggregateLevel += $maxLevel + (integer) $function->isAggregate();
        }
        $this->context = $context;
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
    public function getContext() {
        return $this->context();
    }
}