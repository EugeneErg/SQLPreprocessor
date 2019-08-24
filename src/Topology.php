<?php namespace EugeneErg\SQLPreprocessor;

class Topology
{
    const SEQUENCE_TYPE = 'sequence';
    const PARENT_TYPE = 'parent';
    const WORD_TYPE = 'word';

    private $blocks = [];

    public function __construct(array $blocks = [])
    {
        $this->addBlocks($blocks);
    }

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
     * @param string[]|null $filter
     * @return \stdClass[]
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
            $right = $this->blocks[$block->name];
            if ($right->type !== self::WORD_TYPE) {
                $pos++;
                if (is_array($blocks[$pos]) && $right->type === self::PARENT_TYPE) {
                    $block->children = $this->getChildren(
                        array_values($blocks[$pos]), $block->name
                    );
                }
                elseif ($right->type === self::SEQUENCE_TYPE) {
                    $block->children = $this->getSequenceChildren(
                        $blocks, $block->name, $pos, $ends + ["end$block->name"]
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

    /**
     * @param \stdClass[] $blocks
     * @param int $pos
     * @param string|null $parentName
     * @param string[] $ends
     * @return array
     * @throws \Exception
     */
    private function getSequenceChildren(array $blocks, $parentName = null, &$pos = 0, array $ends = [])
    {
        $result = [];
        $parent = $this->blocks[$parentName];
        $steps = $parent->next;
        $step = 0;
        if ($parent->type === self::SEQUENCE_TYPE) {
            array_unshift($steps, $parentName);
        }
        for (; $pos < count($blocks); $pos++) {
            $block = $blocks[$pos];
            if (!is_array($block)) {
                $block->name = strtolower($block->name);
            }
            switch ($parent->type) {
                case self::SEQUENCE_TYPE:
                    if (is_array($block)) {

                    }

                    break;
                case self::PARENT_TYPE:


            }
        }
        return $result;
    }

    /**
     * @param \stdClass[] $blocks
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
                $pos = array_search($block->name, $parent->next);
                if ($pos === false || $pos < $step) {
                    throw new \Exception('invalid structure');
                }
                if ($step != $pos) {
                    $step = $pos;
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
                    //"'fghgfhbf' && 12 || 123 * 5 + {$var([1,2,3,4])->test()->qwerty}"

                    $table = (object) [];

                    $newQuery = new Raw(['table1', 'table1', 'table2', 'table3'], [
                        'test' => 12,
                        'field' => 3
                    ], function($table1, $table1_1, $table2, $table3, $values) {
                        return "
                            SELECT
                                {$table1->id},
                                {$table1->name}
                                {$table1->old}
                            FROM {$table1}
                            JOIN {$table2}
                              ON {$table2->id}={$table1_1->parent_id}
                            WHERE {$table1->id}={$values->test}
                        
                        ";
                    });
                    $table2 = [];
                    "
                        (
                            FROM $table
                            JOIN (
                                WHERE $table2
                            )$table2
                            WHERE({$table->parent_id} = {$table::string(\"rgrthbtg\")})
                            ORDER BY {$table->parent_id}
                            GROUP BY {$table->parent_id}
                        )({$table->test("rgrthyt")->qwery}
                            
                        
                        )
                        SELECT
                        
                        
                    
                    
                    ";

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