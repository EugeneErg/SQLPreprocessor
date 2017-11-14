<?php
	error_reporting(E_ALL);
	include_once 'src/Argument.php';
	include_once 'src/Variable.php';
	include_once 'src/SQLFunction.php';
	include_once 'src/Structure.php';
	include_once 'src/SQL.php';
	include_once 'src/Query.php';
	
	use EugeneErg\SQLPreprocessor\Argument;
	use EugeneErg\SQLPreprocessor\Variable;
	use EugeneErg\SQLPreprocessor\SQLFunction;
	use EugeneErg\SQLPreprocessor\Structure;
	use EugeneErg\SQLPreprocessor\SQL;
	use EugeneErg\SQLPreprocessor\Query;
	
	function dd() {
		$res = '<? ';
		foreach (func_get_args() as $item) {
			ob_start();
			call_user_func('var_dump', $item);
			$res .= str_replace('<?', '< ?', ob_get_clean());
		}
		die(highlight_string($res, true));
	}

	
	function sql() {
		return call_user_func_array([SQL::class, 'create'], func_get_args());
	}
	
	function select() {
		return call_user_func_array([SQL::class, 'select'], func_get_args());
	}
	
	function SQLSwitch() {
		return call_user_func_array([SQL::class, 'switch'], func_get_args());
	}
	
	function SQLReturn() {
		return call_user_func_array([SQL::class, 'return'], func_get_args());
	}
	function _log($text) {
		echo $text . '<br>';
	}
	
	$query = sql()->from($var = new Variable('tabel_name', ['id', 'name', 'description']))
		->endfrom
		->select()->{
			select('id')->{
				SQL(function($sql) use($var) {
					return $var->id;
				})
			}
			->select('name')->{
				SQL()->return($var->name)
			}
			->select('description')->{
				SQL()->
				switch($var->description)->{
					SQL()->case('')->
						return('description is not clear')->
					case(null)->{
						SQL()->return('description is not set')
					}->
					default->{
						SQL()->return($var->description)
					}
				}
			}
		};
	
	$query();