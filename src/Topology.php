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

    private $breakLevel = 0;

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
     * @param array $blocks
     * @param int $pos
     * @param string[] $ends
     * @return object[]
     * @throws \Exception
     */
    private function getArrayChildren(array $blocks, &$pos = 0, array $ends = [])
    {
        $result = [];
        for (; $pos < count($blocks); $pos++) {
            $block = $blocks[$pos];
            if (is_array($block)) {
                throw new \Exception('invalid sequence');
            }
            if ($this->breakLevel = $this->getBreakLevel($block->name, $ends)) {
                return $result;
            }
            $regulation = $this->blocks[$block->name];
            $result[] = $block;
            if ($regulation->type !== self::WORD_TYPE) {
                $pos++;
                if ($regulation->type === self::SEQUENCE_TYPE) {
                    $block->children = $this->getSequenceChildren(
                        $blocks, $block->name, $pos, $ends
                    );
                    if ($this->breakLevel) {
                        return $result;
                    }
                    $pos--;
                }
                elseif (is_array($blocks[$pos])) {
                    $block->children = $this->getChildren(
                        $blocks[$pos], $block->name
                    );
                }
                else {
                    $block->children = $this->getChildren(
                        $blocks, $block->name, $pos, array_merge($ends, ["end$block->name"])
                    );
                    if ($this->breakLevel > 1) {
                        $this->breakLevel--;
                        return $result;
                    }
                    $pos--;
                }
            }
        }
        $this->breakLevel = 0;
        return $result;
    }

    private function getPartSequence()
    {

    }

    /**
     * @param object[] $blocks
     * @param int $pos
     * @param string|null $parentName
     * @param string[] $ends
     * @return array
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
        $current = (object) [
            'name' => $parentName,
            'children' => [],
        ];
        $result = [$current];
        for (; $pos < count($blocks); $pos++) {
            if (is_array($blocks[$pos])) {
                $current->children = $this->getArrayChildren($blocks[$pos]);
                $pos++;
            } else {
                $current->children = $this->getArrayChildren(
                    $blocks, $pos, array_merge($ends, $next, ["end$parentName"])
                );
            }
            if (!isset($blocks[$pos])) {
                return $result;
            }
            $current = $blocks[$pos];
            if (is_array($current)) {
                throw new \Exception('incorrect structure');
            }
            if ($current->name === "end$parentName") {
                $this->breakLevel = 0;
                return $result;
            }
            if (false === $step = array_search($current->name, $next)) {
                $this->breakLevel = $this->getBreakLevel($current->name, $ends);
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
     * @return array
     * @throws \Exception
     */
    private function getChildren(array $blocks, $parentName, &$pos = 0, array $ends = [])
    {
        $result = [];
        $parent = $this->blocks[$parentName];
        $next = $parent->next;
        for (; $pos < count($blocks); $pos++) {
            $block = $blocks[$pos];
            if (is_array($block)) {
                throw new \Exception('invalid structure');
            }
            if ($this->breakLevel = $this->getBreakLevel($block->name, $ends)) {
                return $result;
            }
            if (count($parent->next)) {
                $nextPos = array_search($block->name, $next);
                if ($nextPos === false) {
                    throw new \Exception('invalid structure');
                }
                array_splice($next, 0, $nextPos);
            }
            $result[] = $block;
            $pos++;
            if (isset($blocks[$pos]) && is_array($blocks[$pos])) {
                $block->children = $this->getArrayChildren($blocks[$pos]);
            } else {
                $block->children = $this->getArrayChildren($blocks, $pos, $ends + $next);
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

    private function blockNameToLowerCase(array $blocks)
    {
        $result = [];
        foreach ($blocks as $block) {
            if (is_array($block)) {
                $result[] = $this->blockNameToLowerCase($block);
            }
            else {
                $block->name = strtolower($block->name);
                $result[] = $block;
            }
        }
        return $result;
    }

    /**
     * @param array $blocks
     * @return array
     * @throws \Exception
     */
    public function getStructure(array $blocks)
    {
        return $this->getArrayChildren(
            $this->blockNameToLowerCase($blocks)
        );
    }
}