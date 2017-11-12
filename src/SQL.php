<?php namespace EugeneErg\SQLPreprocessor;

final class SQL {
	
	private $functions = [];
	private $hash;
	private $index;
	
	private function __clone() {}
	private function __wakeup() {}

	private function index() {
		static $i = 0;
		return $i++;
	}
	private function hashes($hash = null) {
		static $hashes = [];
		if (is_null($hash)) {
			$hashes[$hash = spl_object_hash($this)] = $this;
			return $hash;
		}
		if (isset($hashes[$hash])) {
			return $hashes[$hash];
		}
	}
	private function __construct() {
		$this->hash = $this->hashes();
		$this->index = $this->index();
	}
	private static function structure() {
		static $structure;
		if (isset($structure)) {
			return $structure;
		}
		$structure = new Structure();
		$if = new Structure(1, 1, ['or', 'and']);
			$else = new Structure();
			$endif = new Structure();
		$switch = new Structure(1, 1);
			$case = new Structure(0, 0, ['or', 'and']);
			$default = new Structure();
		$select = new Structure(1);
		$var = new Structure();
		$from = new Structure();
		$orderby = new Structure();
		$groupby = new Structure();
		$into = new Structure(1, 1);
			$insert = new Structure();
		$update = new Structure(1);
		$delete = new Structure(1);
		$return = new Structure(0, 1);//ретерн не имеет дочерних блоков, поэтому не будет нуждаться в закрывающей функции
				
		
		//блок if
		$if->addBlock('else', $else);
		$if->addBlock('elseif', $if);
		$if->addBlock('endif');
		$else->addBlock('endif');
		
		
		//блок switch
		$switch->addBlock('case', $case);
		$switch->addBlock('default', $default);
		$case->addBlock('case', $case);
		$case->addBlock('default', $default);
		$case->addBlock('endswitch');
		$default->addBlock('endswitch');
		
		
		// блок from
		foreach (['from', 'delete'] as $name) {
			$$name->addChild('from', $from);
			$$name->addChild('return', $return);
			$$name->addChild('var', $var);
			$$name->addChild('groupby', $groupby);
			$$name->addChild('orderby', $orderby);
		}
		
		
		// блок delete
		$delete->addChild('delete', $delete);
		
		
		//блок var if else case default orderby groupby update insert
		foreach (['var', 'if', 'else', 'case', 'default', 'orderby', 'groupby', 'insert', 'update', 'select'] as $name) {
			$$name->addChild('switch', $switch, 0);
			$$name->addChild('if', $if, 1);
			$$name->addChild('return', $return, 2);
		}
		
		
		$orderby->addBlock('orderby', $orderby);
		$orderby->addBlock('endorderby');
		
		
		$groupby->addBlock('groupby', $groupby);
		$groupby->addBlock('endgroupby');
		
		
		//блок select
		$select->addChild('select', $select, 3);
		
		
		//блок into
		$into->addBlock('insert', $insert);
		$insert->addBlock('insert', $insert);
		$insert->addBlock('endinto', $insert);
		
		$structure->addChild('from', $from);
		$structure->addChild('var', $var);
		
		$structure->addChild('select', $select, 0);
		$structure->addChild('into', $into, 1);
		$structure->addChild('update', $update, 2);
		$structure->addChild('delete', $delete, 3);
		
		return $structure;
	}
	public static function create() {
		$new = new Self();
		if (!count($args = func_get_args())) {
			return $new;
		}
		if ($args[0] instanceOf \Closure) {
			$function = $args[0];
			$args[0] = $new;
		}
		else {
			$function = [$this, 'from'];
		}
		if ($new !== $result = call_user_func_array($function, $args)) {
			$new->return($result);
		}
		return $new;
	}
	public function __debugInfo() {
		return [
			'hash' => $this->hash,
			'index' => $this->index,
		];
	}
	public function __call($name, $args) {
		$this->functions[] = new SQLFunction($name, $args);
		return $this;
	}
	public static function __callStatic($name, $args) {
		return call_user_func_array([new Self(), $name], $args);
	}
	public function __get($name) {
		if (!is_null($sql = $this->hashes($name))) {
			$this->functions[] = &$sql->functions;
			return $this;
		}
		return $this->__call($name, []);
	}
	public function __toString() {
		return $this->hash;
	}
	public function __invoke() {
		$functions = $this->structure()->validation($this->functions);
		dd($functions);
	}
}