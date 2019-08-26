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
        $this->blocks[strtolower($name)] = (object) [
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
            }
            elseif ($option === self::PARENT_TYPE) {
                $option = [
                    'type' => self::PARENT_TYPE,
                    'next' => []
                ];
            }
            elseif (is_array($option)) {
                if (!isset($option['type'])) {
                    $option = [
                        'type' => self::PARENT_TYPE,
                        'next' => isset($option['next']) ? $option['next'] : $option
                    ];
                }
                elseif (!isset($option['next'])) {
                    $option['next'] = [];
                }
            }
            $this->addBlock(strtolower($name), $option['type'], $option['next']);
        }
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
            $block->name = strtolower($block->name);
            if (in_array($block->name, $ends)) {
                return $result;
            }
            $regulation = $this->blocks[$block->name];
            if ($regulation->type !== self::WORD_TYPE) {
                $pos++;
                if (is_array($blocks[$pos]) && $regulation->type === self::PARENT_TYPE) {
                    $block->children = $this->getChildren(
                        array_values($blocks[$pos]), $block->name
                    );
                }
                elseif ($regulation->type === self::SEQUENCE_TYPE) {
                    $block->children = $this->getSequenceChildren(
                        $blocks, $block->name, $pos, $ends
                    );
                }
                else {
                    $block->children = $this->getChildren(
                        $blocks, $block->name, $pos, $ends + ["end$block->name"]
                    );
                }
            }
            $result[] = $block;
        }
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
        $result = [];
        $parent = $this->blocks[$parentName];

        if (is_array($blocks[$pos])) {
            $result[] = (object) [
                'name' => $parentName,
                'children' => $this->getArrayChildren(
                    array_values($blocks[$pos])
                )
            ];
            if (!isset($blocks[$pos + 1]) || !in_array(strtolower($blocks[$pos + 1]->name), $parent->next)) {
                return $result;
            }
        }
        else {
            $result[] = ( ) [
                'name' => $parentName,
                'children' => $this->getArrayChildren(
                    array_values($blocks[$pos]), $pos, $ends + $parent->next + ["end$parentName"]
                )
            ];
            



        }


        $step = 1;



        for (; $pos < count($blocks); $pos++) {
            $block = $blocks[$pos];
            if (is_array($block)) {
                $block->name = strtolower($block->name);
            }



        }
        return $result;
    }

    /**
     * @param object[] $blocks
     * @param int $pos
     * @param string|null $parentName
     * @param string[] $ends
     * @return array
     * @throws \Exception
     */
    private function getChildren(array $blocks, $parentName, &$pos = 0, array $ends = [])
    {
        $result = [];
        $parent = $this->blocks[$parentName];
        $step = 0;
        for (; $pos < count($blocks); $pos++) {
            $block = $blocks[$pos];
            if (is_array($block)) {
                throw new \Exception('invalid structure');
            }
            $block->name = strtolower($block->name);
            if (in_array($block->name, $ends)) {
                return $result;
            }
            if (count($parent->next)) {
                $nextPos = array_search($block->name, $parent->next);
                if ($nextPos === false || $nextPos < $step) {
                    throw new \Exception('invalid structure');
                }
                if ($step != $nextPos) {
                    $step = $nextPos;
                }
            }
            $pos++;
            if (isset($blocks[$pos]) && is_array($blocks[$pos])) {
                $block->children = $this->getArrayChildren($blocks[$pos]);
            }
            else {
                $childEnd = $ends;
                for ($i = $step; $i < count($parent->next); $i++) {
                    $childEnd[] = $parent->next[$i];
                }
                $block->children = $this->getArrayChildren($blocks, $pos, $childEnd);
                if (in_array($blocks[$pos]->name, $ends)) {
                    $result[] = $block;
                    $pos++;
                    return $result;
                }
            }
            $result[] = $block;
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
        return $this->getArrayChildren(array_values($blocks));
    }
}