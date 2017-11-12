<?php namespace EugeneErg\SQLPreprocessor;

final class SQLFunction {

	private $name;
	private $args;

	function __construct($name, array $args = []) {
		$this->name = mb_strtolower($name);
		$this->args = Argument::byArray($args);
	}
	function getName() {
		return $this->name;
	}
	function getArgs() {
		return $this->args;
	}
	public function __debugInfo() {
		return [
			'name' => $this->name,
			'argCount' => count($this->args),
		];
	}
}