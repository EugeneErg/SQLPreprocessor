<?php namespace EugeneErg\SQLPreprocessor;

final class SQLFunction {

	private $name;
	private $args;
    private $is_aggregate;

	function __construct($name, array $args = []) {
		$this->name = mb_strtolower($name);
        $this->is_aggregate = in_array($name, ['count', 'min', 'max', 'avg']);
		$this->args = Argument::byArray($args);
	}
	function getName() {
		return $this->name;
	}
	function getArgs() {
		return $this->args;
	}
	function getValues() {
		$result = [];
		foreach ($this->args as $arg) {
			$result[] = $arg->getValue();
		}
		return $result;
	}
	public function __debugInfo() {
		return [
			'name' => $this->name,
			'argCount' => count($this->args),
		];
	}
    public function isAggregate() {
        return $this->is_aggregate;
    }
}