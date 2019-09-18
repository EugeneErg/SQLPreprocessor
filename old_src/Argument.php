<?php namespace EugeneErg\SQLPreprocessor;

final class Argument {

    const IS_ARRAY = 'array';
    const IS_SCALAR = 'scalar';
    const IS_VARIABLE = 'variable';
    const IS_FUNCTION = 'function';
    const IS_FIELD = 'field';
    const IS_NULL = 'null';

    private $type;
    private $value;
    
    public static function byArray($array) {
        foreach ($array as $key => $arg) {
            $array[$key] = new Argument($arg);
        }
        return $array;
    }
    public function getType() {
        if (isset($this->type)) {
            return $this->type;
        }
        switch ($type = getType($this->value)) {
            case 'array': return $this->type = Self::IS_ARRAY;
            case 'NULL': return $this->type = Self::IS_NULL;
            case 'object':
                if ($this->value instanceof Variable) {
                    return $this->type = Self::IS_VARIABLE;
                }
                elseif ($this->value instanceof Query) {
                    return $this->type = Self::IS_FUNCTION;
                }
                elseif ($this->value instanceof Field) {
                    return $this->type = Self::IS_FIELD;
                }
                break;
            default:
                if (is_scalar($this->value)) {
                    return $this->type = Self::IS_SCALAR;
                }
        }
        throw new \Exception("Invalid function argument type '{$type}'");
    }
    public function getValue() {
        return $this->value;
    }
    public function setValue($arg) {
        $this->value = $arg;
        $this->type = null;
        if (Self::IS_ARRAY == $this->type = $this->getType()) {
            $this->value = Self::byArray($arg);
        }
        return $this->value;
    }
    public function __construct($arg) {
        $this->setValue($arg);
    }
}