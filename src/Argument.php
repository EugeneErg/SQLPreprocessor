<?php namespace EugeneErg\SQLPreprocessor;

final class Argument {

	const IS_ARRAY = 'array';
	const IS_SCALAR = 'scalar';
	const IS_VARIABLE = 'variable';
	const IS_FUNCTION = 'function';

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
		switch ($type = getType($arg)) {
			case 'array': return $this->type = Self::IS_ARRAY;
			case 'object':
				if ($arg instanceof Variable) {
					return $this->type = Self::IS_VARIABLE;
				}
				elseif ($arg instance of SQL) {
					return $this->type = Self::IS_FUNCTION;
				}
				break;
			default:
				if (is_scalar($arg)) {
					return $this->type = Self::IS_SCALAR;
				}
		}
		throw new \Exception("Invalid function argument type '{$type}'");
	}
	function __construct($arg) {
		$this->value = $arg;
		if (Self::IS_ARRAY == $this->type = $this->getType()) {
			$this->value = Self::byArray($arg);
		}
	}
}