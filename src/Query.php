<?php namespace EugeneErg\SQLPreprocessor;

final class Query {
	const JOIN_LEFT = 'left';
	const JOIN_RIGHT = 'right';
	const JOIN_CORRELATE = 'correlate';
	//const JOIN_UNION = 'union';
	
	private $index;
	private $alias;
	private $context;
	private $var;
	private $join;
	private $limit;
	private $offset;
	private $distinct;
	private $isSubQuery;
	private $branch;
	private $parent;
	private static $queries = [];
	private $deleted = [];
	private $childs = [];
	
	private function __clone() {}
	private function __wakeup() {}
	
	final public function isCorrelate() {
		return $this->join === Self::JOIN_CORRELATE;
	}
	public function __construct(Variable $var = null, $join = null, $offset = 0, $limit = null, $distinct = false) {
		if (!is_null($var) && $var->getType() == Variable::IS_TABLE_FIELD) {
			throw new \Exception('Переменная содердит поле таблицы, а не саму таблицу');
		}
		if (!in_array($join, [null, Self::JOIN_LEFT, Self::JOIN_RIGHT, Self::JOIN_CORRELATE])) {
			throw new \Exception('join должен иметь одно из следующих значений: "' . implode(',', [Self::JOIN_LEFT, Self::JOIN_RIGHT, Self::JOIN_CORRELATE, 'NULL']) . '"');
		}
		if (!is_numeric($offset) || $offset < 0) {
			throw new \Exception('Offset должен быть целым неотрицательным числом');
		}
		if ((!is_null($limit) && !is_numeric($limit)) || $limit < 0) {
			throw new \Exception('Limit должен быть целым неотрицательным числом или NULL');
		}
		if (!is_bool($distinct)) {
			throw new \Exception('Distinct должен быть true или false');
		}
		$this->context = is_null($var) ? 'NULL' : spl_object_hash($var);
		$this->branch = count(self::$queries);
		self::$queries[$this->branch][$this->context] = $this;
		$this->var = $var;
		$this->join = $join;
		$this->limit = $limit;
		$this->offset = $offset;
		$this->distinct = $distinct;
		$this->isSubQuery = is_scalar($var)
			|| is_null($var)
			|| $this->limit
			|| $this->offset
			|| $this->distinct
			|| $this->isCorrelate();
		$this->index = 0;
		$this->alias = 0;
	}
	public function addChild(Variable $var = null, $join = null, $offset = 0, $limit = null, $distinct = false) {
		$query = new Self($var, $join, $offset, $limit, $distinct);
		if (isset(self::$queries[$this->branch][$query->context])) {
			throw new \Exception("В данной ветке уже существует запрос с контекстом '{$query->context}'");
		}
		$query->index = count(self::$queries[$this->branch]);
		$query->alias = $query->index;
		self::$queries[$this->branch][$query->context] = $query;
		unset(self::$queries[$query->branch]);
		$query->branch = $this->branch;
		$query->parent = $this;
		$this->isSubQuery = true;
		return $parent->childs[$query->context] = $query;
	}
	public function addDeleted(Variable $var = null, $join = null, $offset = 0, $limit = null, $distinct = false) {
		$query = $this->addChild($var, $join, $offset, $limit, $distinct);
		if ($query->isCorrelate()) {
			throw new \Exception('Невозможно удаление из коррелированного запроса');
		}
		$this->deleted[$query->context] = $query;
	}
}