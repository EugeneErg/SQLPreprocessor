<?php namespace EugeneErg\SQLPreprocessor;

final class Field {
    private $context;
    private $object;
    private $functions = [];
    
    public function __construct(Query $context, $object = null, array $functions = []) {
        $this->context = $context;
        $this->object = $object;
        $this->functions = $functions;
    }
    public function getFunctions() {
        return $this->functions;
    }
    private function getValue() {
        if (!is_null($object)) {
            return $this->context->getFieldValue($this->object);
        }
    }
}