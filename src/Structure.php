<?php namespace EugeneErg\SQLPreprocessor;

class Structure
{
    private $validate;
    private $children;
    private $blocks = [];
    private $block_lists = [];

    public function __construct(\Closure $callback = null, array $children = [])
    {
        $this->validate = $callback;
        $this->children = $children;
    }

    private function setBlockPositions(array $blocks)
    {
        $this->blocks = [];
        foreach ($blocks as $pos => $block) {
            $name = $block->getName();
            if (!isset($result[$name])) {
                $this->blocks[$name] = [];
            }
            $this->blocks[$name][$pos] = $block;
        }
    }

    public function __invoke(array $blocks)
    {
        $this->block_lists = $blocks;
        $validate = $this->validate;
        $this->setBlockPositions($blocks);
        if (!is_null($validate) && !$validate($this)) {
            throw new \Exception('invalid structure');
        }
        foreach ($this->block_lists as $block) {
            $name = $block->getName();
            if (empty($this->children[$name])) {
                throw new \Exception("invalid block name '{$name}'");
            }
            $child = $this->children[$name];
            $children = $block->getChildren();
            if ($child instanceof \Closure) {
                $child($children);
            }
            elseif (count($children)) {
                throw new \Exception("block '{$name}' can't have children blocks");
            }
        }
        $this->blocks = [];
    }
    
    public function counts(...$params)
    {
        $result = [];
        foreach ($params as $name) {
            if (isset($this->blocks[$name])) {
                $result[$name] = count($this->blocks[$name]);
            }
            else {
                $result[$name] = 0;
            }
        }
        return (object) $result;
    }

    public function count(...$params)
    {
        return array_sum((array) $this->counts($params));
    }

    /**
     * @param array[] ...$sets
     * @return int
     * @throws \Exception
     */
    public function getVariant(...$sets)
    {
        $result = null;
        foreach ($sets as $num => $params) {
            foreach ($params as $name => $count) {
                $real_count = $this->count($count);
                if (is_int($name)) {
                    $min_count = 1;
                    $max_count = $real_count;
                }
                elseif (is_array($count)) {
                    $min_count = reset($count);
                    $max_count = count($count) > 1 ? end($count) : $real_count;
                }
            else {
                    $min_count = $count;
                    $max_count = $count;
                }
                if ($real_count < $min_count || $real_count > $max_count) {
                    continue(2);
                }
            }
            if ($result !== null) {
                throw new \Exception('incorrect structure');
            }
            $result = $num;
        }
        if ($result === null) {
            throw new \Exception('incorrect structure');
        }
        return $result;
    }

    public function topology(...$params)
    {
        foreach ($params as $pos => $name) {
            if (!isset($this->blocks[$name])) {
                unset($params[$pos]);
            }
        }
        $params = array_values($params);
        $current = array_keys($this->blocks[$params[0]]);
        for ($i = 1; $i < count($params); $i++) {
            $next = array_keys($this->blocks[$params[$i]]);
            if (max($current) > min($next)) {
                return false;
            }
            $current = $next;
        }
        return true;
    }

    private function getPartials($from, $to, array &$path)
    {
        $result = [];
        $step = false;
        $start = 0;
        foreach ($path as $pos => $block) {
            if (!$step && $block->getName() === $from) {
                $start = $pos;
            }
            elseif ($step && $block->getName() === $to) {
                $result[] = array_slice($path, $start, $pos - $start);
                array_splice($path, $start, $pos - $start);
            }
        }
        $path = array_values($path);
        return $result;
    }

    public function partials(array $parts, \Closure $callback)
    {
        foreach ($parts as $from => $to) {
            $partials = $this->getPartials($from, $to, $this->block_lists);
            foreach ($partials as $partial) {
                new Structure(function ($part) use ($callback, $from, $to) {
                    return $callback($part, $from, $to);
                }, $partial);
            }
        }
    }

    public function addChildren(array $children)
    {
        $this->children = array_replace($this->children, $children);
    }
}