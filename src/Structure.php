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
			$this->childs[$key] = (object) [
				'levels' => $levels,
				'value' => $block,
			];
		}
		elseif (count($this->childs[$key]->levels)) {
			$this->childs[$key]->levels = Self::array_union($this->childs[$key]->levels, $levels);
		}
	}
	public function __debugInfo() {
		return [
			'union' => $this->union,
			'min_count' => $this->min_count,
			'levels' => $this->levels,
			'next' => $this->next,
			'childs' => $this->childs,
		];
	}
	private function isValidChildLevels(array $childs, array $levels = null) {
		foreach ($this->childs as $key => $child) {
			if ((is_null($levels)
				|| !count($child->levels)
				|| count(array_intersect($child->levels, $levels)))
					&& $child->value->min_count > 0
					&& (!isset($childs[$key])
						|| $child->value->min_count > count($childs[$key]))
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
	private function inlineValidation(array $functions, &$i) {
		//_log("Ищем дочерние и соседние блоки для функции '{$functions[$i]->getName()}'");
		$return = $result = (object) [
			'function' => $functions[$i],
			'union' => [],
			'childs' => [],
		];
		$levels = !count($this->levels) ? null : $this->levels;

		if (!count($this->next)
			&& count($this->childs)
				&& !is_array($functions[$i])
		) {
			//обявляем существование завершающей функции для текущего блока
			$name = $functions[$i]->getName();
			$endName = "end{$name}";
		}
		else {
			$endName = null;
		}
		
		$canBeUnion = true;
		$canBeChild = true;
		$canBeClose = true;
		
		for ($i++; $i < count($functions); $i++) {
			$function = $functions[$i];
			if ($canBeChild && is_array($function)) {
				//структура является вложенной и закрывает текущий блок
				//_log("дочерние блоки функции вложены в массив");
				if (!count($structure->childs)) {
					$result->next = $this->validation($function);
					break;
				}
				$result->childs = $this->validation($function);
				//не могут быть дочерними и закрывающими, только следующий блок
				$canBeUnion = false;
				$canBeChild = false;
				$canBeClose = false;
				continue;
			}
			$name = $function->getName();
			if ($canBeUnion && isset($this->union[$name])) {
				//_log("находим юнион функцию '{$name}'");
				$result->union[] = $function;
				continue;
			}
			if ($canBeChild && isset($this->childs[$name])) {
				$child = $this->childs[$name];
				if ($child->value->max_count > 0
					&& isset($result->childs[$name])
						&& ($count = count($result->childs[$name]) + 1) > $child->value->max_count
				) {
					throw new \Exception("Для блока {$name} превышено допустимое максимальное количество использований: {$count} > {$child->value->max_count}");
				}
				if (!is_null($levels)
					&& !count($child->levels == [] ? $levels : $levels = array_intersect($levels, $child->levels))
				) {
					throw new \Exception('Структура ни соответсвует ни нодному указанному шаблону');
				}
				//_log("находим дочерний блок");
				$result->childs[$name][] = $child->value->inlineValidation($functions, $i);
				$canBeUnion = false;
				continue;
			}
			if (isset($this->next[$name])) {
				//_log("находим следующий блок");
				$result = $result->next = $this->next[$name]->inlineValidation($functions, $i);
				$i++;
			}
			elseif ($canBeClose && (array_key_exists($name, $this->next) || $name === $endName)) {
				//_log("находим закрывающий блок {$name}");
				$levels = $this->isValidChildLevels($result->childs, $levels);
				$result->end = $function;
				$i++;
			}
			$i--;
			break;
		}
		if (isset($functions[$i + 1])) {
			//_log("возвращаемся к родительскому блоку, не найдя связи с функцией '{$functions[$i + 1]->getName()}'");
		}
		else {
			//_log("возвращаемся к родительскому блоку");
		}
		return $return;
	}
	private function validationNext(array $functions) {
		if (isset($functions[0])) {
			$name = $functions[0]->getName();
			if (!isset($this->next[$name])) {
				throw new \Exception('Структура ни соответсвует ни нодному указанному шаблону');
			}
			$result = $this->next[$name]->inlineValidation($functions, $i);
			if (isset($functions[$i + 1])) {
				throw new \Exception('Структура ни соответсвует ни нодному указанному шаблону');
			}
			return $result;
		}
	}
	public function validation(array $functions) {
		//_log('анализируем новый структурный массив --------------------------------');
		if (!count($this->childs) && count($this->next)) {
			return $this->validationNext($functions);
		}
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
			$function = $functions[$i];
			$name = $functions[$i]->getName();
			if (!isset($this->childs[$name])) {
				//dd(171, array_keys($this->childs), $name, $functions, $results);
				throw new \Exception('Структура ни соответсвует ни нодному указанному шаблону');//не нашлось подходящих дочерних блоков - структура невалидная
			}
			$child = $this->childs[$name];
			if (!is_null($levels) && !count($child->levels == [] ? $levels : $levels = array_intersect($levels, $child->levels))) {
				//dd(176, $name, $results);
				throw new \Exception('Структура ни соответсвует ни нодному указанному шаблону');//не осталось подходяших уровней - структура невалидная
			}
			$results[$name][] = $child->value->inlineValidation($functions, $i);
			if (isset($functions[$i + 1]) && is_array($functions[$i + 1])) {
				//dd($functions[$i], $i, $functions, $results);
			}
		}
		$this->isValidChildLevels($results, $levels);
		return $results;
	}
}