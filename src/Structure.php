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
    public function addChild($key, Self $block, $levels = [], $max = null, $min = null) {
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
                'max' => $max,
                'min' => $min,
            ];
        }
        elseif (count($this->childs[$key]->levels)) {
            $this->childs[$key]->levels = Self::array_union($this->childs[$key]->levels, $levels);
            if (!is_null($max)) {
                $this->childs[$key]->max = $max;
            }
            if (!is_null($min)) {
                $this->childs[$key]->min = $min;
            }
        }
    }
    public function __debugInfo() {
        return [
            'union' => $this->union,
            'min_count' => $this->min_count,
            'max_count' => $this->max_count,
            'levels' => $this->levels,
            'next' => $this->next,
            'childs' => $this->childs,
        ];
    }
    private function isValidChildLevels(array $childs, array $levels = null) {
        foreach ($this->childs as $key => $child) {
            $min = isset($child->min) ? $child->min : $child->value->min_count;
            if ((is_null($levels)
                || !count($child->levels)
                || count(array_intersect($child->levels, $levels)))
                    && $min > 0
                    && (!isset($childs[$key])
                        || $min > count($childs[$key]))
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
                if (!count($this->childs)) {
                    $result->next = $this->validation($function);
                    break;
                }
                $result->childs = $this->validation($function, $childLevels);
                $result->childsLevels = $childLevels;
                $canBeUnion = false;
                $canBeChild = false;
                $canBeClose = false;
                continue;
            }
            $name = $function->getName();
            if ($canBeUnion && isset($this->union[$name])) {
                $result->union[] = $function;
                continue;
            }
            if ($canBeChild && isset($this->childs[$name])) {
                $child = $this->childs[$name];
                $max = isset($child->max) ? $child->max : $child->value->max_count;
                if ($max > 0
                    && isset($result->childs[$name])
                        && ($count = count($result->childs[$name]) + 1) > $max
                ) {
                    throw new \Exception("Для блока {$name} превышено допустимое максимальное количество использований: {$count} > {$max}");
                }
                if (!is_null($levels)
                    && !count($child->levels == [] ? $levels : $levels = array_intersect($levels, $child->levels))
                ) {
                    throw new \Exception('Структура ни соответсвует ни нодному указанному шаблону');
                }
                $result->childs[$name][] = $child->value->inlineValidation($functions, $i);
                $canBeUnion = false;
                continue;
            }
            if (isset($this->next[$name])) {
                $result = $result->next = $this->next[$name]->inlineValidation($functions, $i);
                break;
            }
            if ($canBeClose && (array_key_exists($name, $this->next) || $name === $endName)) {
                $levels = $this->isValidChildLevels($result->childs, $levels);
                $result->end = $function;
                break;
            }
            $i--;
            break;
        }
        return $return;
    }
    private function validationNext(array $functions) {
        if (isset($functions[0])) {
            $name = $functions[0]->getName();
            if (!isset($this->next[$name])) {
                throw new \Exception('Структура ни соответсвует ни нодному указанному шаблону');
            }
            $i = 0;
            $result = $this->next[$name]->inlineValidation($functions, $i);
            if (isset($functions[$i + 1])) {
                throw new \Exception('Структура ни соответсвует ни нодному указанному шаблону');
            }
            return $result;
        }
    }
    public function validation(array $functions, &$levels = null) {
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
                throw new \Exception('Структура ни соответсвует ни нодному указанному шаблону');//не нашлось подходящих дочерних блоков - структура невалидная
            }
            $child = $this->childs[$name];
            if (!is_null($levels) && !count($child->levels == [] ? $levels : $levels = array_intersect($levels, $child->levels))) {
                throw new \Exception('Структура ни соответсвует ни нодному указанному шаблону');//не осталось подходяших уровней - структура невалидная
            }
            $results[$name][] = $child->value->inlineValidation($functions, $i);
        }
        $this->isValidChildLevels($results, $levels);
        return $results;
    }
}