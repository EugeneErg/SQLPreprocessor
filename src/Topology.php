<?php namespace EugeneErg\SQLPreprocessor;

/**
 * Class Topology
 * @package EugeneErg\SQLPreprocessor
 */
class Topology
{
    const SEQUENCE_TYPE = 'sequence';
    const PARENT_TYPE = 'parent';
    const WORD_TYPE = 'word';

    /**
     * @var object[]
     */
    private $blocks = [];

    /**
     * @var int
     */
    private $breakLevel = 0;

    /**
     * @var \Closure|null
     */
    private $callback;

    /**
     * Topology constructor.
     * @param array $blocks
     */
    public function __construct(array $blocks = [])
    {
        $this->addBlocks($blocks);
    }

    /**
     * @param string $name
     * @param string $type
     * @param string[] $next
     */
    public function addBlock($name, $type = self::PARENT_TYPE, array $next = [])
    {
        $lowerNext = [];
        foreach ($next as $value) {
            $lowerNext[] = strtolower($value);
        }
        $this->blocks[strtolower($name)] = (object)[
            'type' => $type,
            'next' => $lowerNext,
        ];
    }

    /**
     * @param array $blocks
     */
    public function addBlocks(array $blocks)
    {
        foreach ($blocks as $name => $option) {
            if (is_int($name)) {
                $name = $option;
                $option = [
                    'type' => self::WORD_TYPE,
                    'next' => []
                ];
            } elseif ($option === self::PARENT_TYPE) {
                $option = [
                    'type' => self::PARENT_TYPE,
                    'next' => []
                ];
            } elseif (is_array($option)) {
                if (!isset($option['type'])) {
                    $option = [
                        'type' => self::PARENT_TYPE,
                        'next' => isset($option['next']) ? $option['next'] : $option
                    ];
                } elseif (!isset($option['next'])) {
                    $option['next'] = [];
                }
            }
            $this->addBlock($name, $option['type'], $option['next']);
        }
    }

    /**
     * @param string $name
     * @param array $ends
     *
     * @return int
     */
    private function getBreakLevel($name, array $ends)
    {
        for ($i = count($ends) - 1; $i >= 0; $i--) {
            if ($ends[$i] === $name) {
                return count($ends) - $i;
            }
        }
        return 0;
    }

    /**
     * @param mixed $block
     * @param Raw|array $prev
     * @return array
     * @throws \Exception
     */
    private function getBlock($block, $prev)
    {
        if (is_array($block)) {
            return $block;
        }
        if (!isset($this->callback)) {
            throw new \Exception('Invalid chain type');
        }
        $callback = $this->callback;
        $result = $callback($block, $prev);
        if (!is_array($result)) {
            throw new \Exception('Invalid chain type');
        }
        return $result;
    }

    /**
     * @param array $blocks
     * @param int $pos
     * @param string[] $ends
     * @return Link[]
     * @throws \Exception
     */
    private function getArrayChildren(array $blocks, &$pos = 0, array $ends = [])
    {
        $result = [];
        for (; $pos < count($blocks); $pos++) {
            $block = $blocks[$pos];
            if (!$block instanceof  Link) {
                throw new \Exception('invalid sequence');
            }
            if ($this->breakLevel = $this->getBreakLevel($block->getName(Link::TYPE_LOWER), $ends)) {
                return $result;
            }
            $regulation = $this->blocks[$block->getName(Link::TYPE_LOWER)];
            $result[] = $block;
            if ($regulation->type !== self::WORD_TYPE) {
                $pos++;
                if (!isset($blocks[$pos])) {
                    $this->breakLevel = 0;
                    return $result;
                }
                if ($regulation->type === self::SEQUENCE_TYPE) {
                    $block->setChildren($this->getSequenceChildren(
                        $blocks, $block->getName(Link::TYPE_LOWER), $pos, $ends
                    ));
                    if ($this->breakLevel) {
                        return $result;
                    }
                    $pos--;
                }
                elseif (is_array($blocks[$pos])) {
                    $block->setChildren($this->getChildren(
                        $blocks[$pos], $block->getName(Link::TYPE_LOWER)
                    ));
                }
                elseif ($blocks[$pos] instanceof Raw) {
                    $block->setChildren(function($type) use ($blocks, $block, $pos) {
                        return $this->getChildren(
                            $blocks[$pos]->parse($type), $block->getName(Link::TYPE_LOWER)
                        );
                    });
                }
                else {
                    $block->setChildren($this->getChildren(
                        $blocks, $block->getName(Link::TYPE_LOWER), $pos,
                        array_merge($ends, ["end{$block->getName(Link::TYPE_LOWER)}"])
                    ));
                    if ($this->breakLevel > 1) {
                        $this->breakLevel--;
                        return $result;
                    }
                    if ($this->breakLevel !== 1) {
                        $pos--;
                    }
                }
            }
        }
        $this->breakLevel = 0;
        return $result;
    }

    /**
     * @param object[] $blocks
     * @param int $pos
     * @param string|null $parentName
     * @param string[] $ends
     * @return Link[]
     * @throws \Exception
     */
    private function getSequenceChildren(array $blocks, $parentName = null, &$pos = 0, array $ends = [])
    {
        /*
         * рассчет найденных end-s
         *
         * нужно отличать родительские end-s от дочерних
         *
         * 1) в дочерний поиск переадется цепь end-s
         * 2) если одно из цепи дочернего поиска нашло end,
         *
         * пример:
         *
         * end-s: endif endif endfrom endswitch else endif
         *
         * дочерний поиск находит
         *
         *
         * */


        /*
         * 1) if {...} else {...}
         * 2) if {...}
         * 3) if ... else {...}
         * 4) if ... else ... endif
         * 5) if {...} else ... endif
         * 6) if ... endif
         *
         * после скобок ждем следующий шаг или другой блок но не endif
         * без скобок ждем следующий шаг или endif
         *
         *
         *
         * */


        $parent = $this->blocks[$parentName];
        $next = $parent->next;
        $current = new Link($parentName);
        $result = [$current];
        for (; $pos < count($blocks); $pos++) {
            if (is_array($blocks[$pos])) {
                $current->setChildren($this->getArrayChildren($blocks[$pos]));
                $pos++;
            }
            elseif ($blocks[$pos] instanceof Raw) {
                $current->setChildren(function($type) use($blocks, $pos) {
                    return $this->getArrayChildren($blocks[$pos]->parse($type));
                });
                $pos++;
            }
            else {
                $current->setChildren($this->getArrayChildren(
                    $blocks, $pos, array_merge($ends, $next, ["end$parentName"])
                ));
            }
            if (!isset($blocks[$pos])) {
                return $result;
            }
            $current = $blocks[$pos];
            if (!$current instanceof Link) {
                throw new \Exception('incorrect structure');
            }
            if ($current->getName(Link::TYPE_LOWER) === "end$parentName") {
                $this->breakLevel = 0;
                return $result;
            }
            if (false === $step = array_search($current->getName(Link::TYPE_LOWER), $next)) {
                $this->breakLevel = $this->getBreakLevel($current->getName(Link::TYPE_LOWER), $ends);
                return $result;
            }
            $result[] = $current;
            array_splice($next, 0, $step);
        }
        $this->breakLevel = 0;
        return $result;
    }

    /**
     * @param object[] $blocks
     * @param int $pos
     * @param string|object $parentName
     * @param string[] $ends
     * @return Link[]
     * @throws \Exception
     */
    private function getChildren(array $blocks, $parentName, &$pos = 0, array $ends = [])
    {
        $result = [];
        $parent = $this->blocks[$parentName];
        $next = $parent->next;
        for (; $pos < count($blocks); $pos++) {
            $block = $blocks[$pos];
            if (!$block instanceof Link) {
                throw new \Exception('invalid structure');
            }
            if ($this->breakLevel = $this->getBreakLevel($block->getName(Link::TYPE_LOWER), $ends)) {
                return $result;
            }
            if (count($parent->next)) {
                $nextPos = array_search($block->getName(Link::TYPE_LOWER), $next);
                if ($nextPos === false) {
                    throw new \Exception('invalid structure');
                }
                array_splice($next, 0, $nextPos);
            }
            $result[] = $block;
            $pos++;
            if (isset($blocks[$pos]) && is_array($blocks[$pos])) {
                $block->setChildren($this->getArrayChildren($blocks[$pos]));
            }
            elseif (isset($blocks[$pos]) && $blocks[$pos] instanceof Raw) {
                $block->setChildren(function($type) use($blocks, $pos) {
                    return $this->getArrayChildren($blocks[$pos]->parse($type));
                });
            }
            else {
                $block->setChildren($this->getArrayChildren($blocks, $pos, array_merge($ends, $next)));
                if ($this->breakLevel > count($next)) {
                    $this->breakLevel -= count($next);
                    return $result;
                }
                $pos--;
            }
        }
        $this->breakLevel = 0;
        return $result;
    }

    /**
     * @param array|Raw $blocks
     * @return array|\Closure
     * @throws \Exception
     */
    public function getStructure($blocks)
    {
        if (is_array($blocks)) {
            return $this->getArrayChildren($blocks);
        }
        if ($blocks instanceof Raw) {
            return function($type) use($blocks) {
                return $this->getArrayChildren($blocks->parse($type));
            };
        }

        throw new \Exception('Invalid Argument type');
    }
}