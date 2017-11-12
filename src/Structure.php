<?php namespace EugeneErg\SQLPreprocessor;

class Structure {
	private $next = [];
	private $union = [];
	private $childs = [];
	private $levels = [];
	private $max_count = 0;
	private $min_count = 0;

	public function __construct($min_count = 0, $max_count = 0, array $unions = []) {
		$this->min_count = $min_count;
		$this->max_count = $max_count;
		foreach ($unions as $key => $union) {
			if (is_numeric($key)) {
				$this->union[$union] = [0, 0];
			}
			elseif (!is_array($union)) {
				$this->union[$key] = [0, $union];
			}
			else {
				$this->union[$key] = $union;
			}
		}
	}
	public function addBlock($key, Self $block = null) {
		if (isset($this->next[$key])) {
			throw new \Exception('Блок с данным ключем уже был выбран');
		}
		$this->next[$key] = $block;
	}
	private static function array_union(array $a, array $b) {
		return array_merge(array_diff($a, $b), array_intersect($a, $b), array_diff($b, $a));
	}
	public function addChild($key, Self $block, $levels = []) {
		$key = (string) $key;
		if (isset($this->childs[$key]) && $this->childs[$key]->value !== $block) {
			throw new \Exception('Блок с данным ключем уже был выбран');
		}
		$levels = (array) $levels;
		$this->levels = array_unique(array_merge($this->levels, $levels));
		if (!isset($this->childs[$key]) || !count($this->childs[$key]->levels)) {
			$this->childs['key'] = (object) [
				'levels' => $levels,
				'value' => $block,
			];
		}
		elseif (count($this->childs['key']->levels)) {
			$this->childs['key']->levels = Self::array_union($this->childs['key']->levels, $levels);
		}
	}
	public function __debugInfo() {
		return [
			'union' => $this->union,
			'next' => $this->next,
			'childs' => $this->childs,
			'levels' => $this->levels,
		];
	}
	private function isValidChildLevels(array $childs, array $levels = null) {
		foreach ($this->childs as $child) {
			if ((is_null($levels)
				|| !count($child->levels)
				|| count(array_intersect($child->levels, $levels)))
					&& $child->value->min_count > 0
					&& (!isset($childs[$child->key])
						|| $child->value->min_count < count($childs[$child->key]))
			) {
				if (is_null($levels)
					|| !count($child->levels)
						|| !count($levels = array_diff($levels, $child->levels))
				) {
					throw new \Exception('невалидная структура');
				}
			}
		}
		return $levels;
	}
	
	private function inlineValidation(array $functions, &$pos = 0) {
		$return = $result = (object) [
			'union' => [],
			'childs' => [],
		];
		$structure = clone $this;
		$levels = !count($this->levels) ? null : $this->levels;

		if (!count($structure->next)
			&& count($structure->childs)
				&& $pos > 0
					&& !is_array($functions[$pos - 1])
		) {
			//обявляем существование завершающей функции для текущего блока
			$name = $functions[$pos - 1]->getName();
			$structure->addBlock("end{$name}");
		}
		
		$canBeUnion = true;
		$canBeChild = true;
		$canBeClose = true;
		
		for ($i = $pos; $i < count($functions); $i++) {
			if ($canBeChild && is_array($functions[$i])) {
				//структура является вложенной и закрывает текущий блок
				$result->childs = $this->validation($functions[$i]);
				//не могут быть дочерними и закрывающими, только следующий блок
				$canBeUnion = false;
				$canBeChild = false;
				$canBeClose = false;
				continue;
			}
			$name = $functions[$i]->getName();
			if ($canBeUnion && isset($this->union[$name])) {
				$result->union[] = $functions[$i];
				continue;
			}
			if ($canBeChild && isset($this->childs[])) {
				$child = $this->childs[$name];
				if ($child->value->max_count > 0
					&& isset($result[$name])
						&& ($count = count($result[$name]) + 1) > $child->value->max_count
				) {
					throw new \Exception("Для блока {$name} превышено допустимое максимальное количество использований: {$count} > {$child->value->max_count}");
				}
				if (!is_null($levels)
					&& !count($child->levels == [] ? $levels : $levels = array_intersect($levels, $child->levels))
				) {
					throw new \Exception('Структура ни соответсвует ни нодному указанному шаблону');
				}
				$newChild = $child->value->inlineValidation($functions, ++$i);
				$newChild->function = $functions[$i];
				$result->childs[$name][] = $newChild;
				$i--;//после дочерних юнион не бывает, либо другие дочерние, либо следующий, либо закрывающий блок
				$canBeUnion = false;
				continue;
			}
			if (isset($structure->next[$name])) {
				$result = $result->next = (object) [
					'union' => [],
					'childs' => [],
					'function' => $functions[$i],
				];
				$canBeUnion = true;
				$canBeChild = true;
				$canBeClose = true;
				continue;
			}
			if ($canBeClose && array_key_exists($name, $structure->next)) {
				$levels = $this->isValidChildLevels($result->childs, $levels);
				$result->end = $functions[$i];
			}
			break;
		}
		return $return;
	}
	public function validation(array $functions) {
		$results = [];

		if (!count($this->childs)) {
			if (count($functions)) {
				throw new \Exception('Структура невалидна - блок не должен иметь дочерних элементов');
			}
			return $results;
		}
		$levels = !count($this->levels) ? null : $this->levels;

		if (!count($functions)) {
			$levels = $this->isValidChildLevels($results, $levels);
			return $results;
		}
		for ($i = 0; $i < count($functions); $i++) {
			if (!isset($this->childs[$functions[$i]->getName()])) {
				throw new \Exception('Структура ни соответсвует ни нодному указанному шаблону');//не нашлось подходящих дочерних блоков - структура невалидная
			}
			$child = $this->childs[$functions[$i]->getName()];
			if (!is_null($levels) && !count($child->levels == [] ? $levels : $levels = array_intersect($levels, $child->levels))) {
				throw new \Exception('Структура ни соответсвует ни нодному указанному шаблону');//не осталось подходяших уровней - структура невалидная
			}
			$newChild = $child->value->inlineValidation($functions, ++$i);
			$newChild->function = $functions[$i];
			$results[$functions[$i]->getName()][] = $newChild;
			$i--;
		}
		$this->isValidChildLevels($results, $levels);
		return $results;
	}
}