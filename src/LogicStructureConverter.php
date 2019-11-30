<?php namespace EugeneErg\SQLPreprocessor;

/*
 * if (c1) {
 *
 * }
 * elseif (c2) {
 *     break;
 * }
 * else {
 *     break(2)
 * }
 *
 * case
 *     when c1 then 1
 *     when c2 then 2
 *     else 3
 * end
 *
 *
 * switch c1
 *      case v1
 *
 *      case v2
 *
 *      case v3
 *
 *      case v4
 *
 *
 * if (v1,v2,v3,v4) {
 *     if (!v2) {
 *         //
 *         if (!v3) {
 *             //
 *         }
 *     }
 * }
 * if (v2) {
 *     //
 *     if (v1)
 * }
 *
 *
 */



/**
 * Class LogicStructureConverter
 * @package EugeneErg\SQLPreprocessor
 */
class LogicStructureConverter
{
    /**
     * @var Link[]
     */
    private $structure;

    /**
     * LogicStructureConverter constructor.
     * @param Link[] $structure
     */
    public function __construct(array $structure)
    {
        $this->structure = $structure;
    }

    /**
     * @param Link[] $structure
     * @param \Closure $getFieldNameMethod
     * @param \Closure|null $getDefaultValueMethod
     *
     * @return Link[][]
     */
    public static function toList(array $structure, \Closure $getFieldNameMethod, \Closure $getDefaultValueMethod = null)
    {
        $structure = self::argumentsToObjects($structure);
        $structure = self::findAllResults($structure, $getFieldNameMethod);

        return self::createStructuresByFields($structure, $getDefaultValueMethod);
    }

    /**
     * @param Link[] $structure
     * @param \Closure $callback
     * @param bool $isBlock
     *
     * @return object[][]
     */
    private static function findAllResults(array $structure, \Closure $callback, $isBlock = false)
    {
        $results = [];
        $conditionChain = [];

        foreach ($structure as $pos => $link) {
            if ($link->getName() === 'set') {
                list($fieldName, $link) = $callback($link);
                $results[$fieldName][$pos] = (object) ['link' => $link];
                $conditionChain = [];
            }
            else {
                if (!$isBlock) {
                    $conditionChain = [];
                }

                $childResults = self::findAllResults($link->getChildren(), $callback, !$isBlock);

                foreach ($childResults as $fieldName => $values) {
                    $path = $conditionChain;
                    $results[$fieldName] = isset($results[$fieldName])
                        ? array_replace($path, $results[$fieldName])
                        : $path;
                    $results[$fieldName][$pos] = (object) [
                        'link' => $link,
                        'children' => $values,
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * @param object[] $structure
     * @param Link $default
     * @return Link
     */
    private static function createSingleIfConditions(array $structure, Link $default)
    {
        $values = [$default];
        $default = new Link('set', [0]);
        $defaultConditions = ['or'];
        $newStructure = [];
        $hasElse = false;

        foreach ($structure as $item) {
            $isElse = $item->link->getName() === 'else';

            if (isset($item->children)) {
                if (count($defaultConditions) > 1) {
                    $newStructure[] = (object) [
                        'condition' => $defaultConditions,
                        'value' => $default,
                        'type' => 'elseif',
                    ];
                    $defaultConditions = ['or'];
                }

                if ($isElse) {
                    $hasElse = true;
                }

                $newStructure[] = (object) [
                    'condition' => $item->link->getArguments(),
                    'value' => new Link('set', [count($values)]),
                    'type' => $item->link->getName(),
                ];

                $values[] = [self::createSingleDefaultConditions($item->children, $default)];
            }
            elseif (!$isElse) {
                $defaultConditions[] = $item->link->getArguments();
            }
        }

        if (!$hasElse) {
            $newStructure[] = (object) [
                'condition' => [],
                'value' => $default,
                'type' => 'else',
            ];
        }

        return new Link('if', [$newStructure, $values]);
    }

    /**
     * @param object[] $structure
     * @param Link $default
     * @param array $switch
     * @return Link
     */
    private static function createSingleSwitchConditions(array $structure, Link $default, array $switch)
    {
        $values = [$default];
        $default = new Link('set', [0]);
        $defaultConditions = ['or'];
        $newStructure = [];
        $hasDefault = false;

        foreach ($structure as $item) {
            $isDefault = $item->link->getName() === 'default';

            if (isset($item->children)) {
                if (count($defaultConditions) > 1) {
                    $newStructure[] = (object) [
                        'condition' => $defaultConditions,
                        'value' => $default,
                        'type' => 'case',
                    ];
                    $defaultConditions = ['or'];
                }

                if ($isDefault) {
                    $hasDefault = true;
                }

                $newStructure[] = (object) [
                    'condition' => $item->link->getArguments(),
                    'value' => new Link('set', [count($values)]),
                    'type' => $item->link->getName(),
                ];

                $values[] = [self::createSingleDefaultConditions($item->children, $default)];
            }
            elseif (!$isDefault) {
                $defaultConditions[] = $item->link->getArguments();
            }
        }

        if (!$hasDefault) {
            $newStructure[] = (object) [
                'condition' => [],
                'value' => $default,
                'type' => 'default',
            ];
        }
        elseif (count($defaultConditions) > 1) {
            $newStructure[] = (object) [
                'condition' => $defaultConditions,
                'value' => $default,
                'type' => 'case',
            ];
        }

        return new Link('switch', [$switch, $newStructure, $values]);
    }

    /**
     * @param object[] $structure
     * @param Link $default
     * @return Link
     */
    private static function createSingleDefaultConditions(array $structure, Link $default)
    {
        if (!count($structure)) {
            return $default;
        }

        $lastItem = array_pop($structure);

        if ($lastItem->link->getName() === 'set') {
            return $lastItem->link;
        }

        if (count($structure)) {
            $default = self::createSingleDefaultConditions($structure, $default);
        }
        /**
         * 1) одинаковые блоки с условиями и возвратом передаются
         *    в качесвтве альтернативных условий в следующие блоки
         *
         * 2) таким образом один блок может быть множественно повторен
         *
         * 3) способы решения проблемы:
         * 3.1) вместо блока передаем число
         * 3.2) если возвращается число, то конвертируем в блок условий
         *
         * п.с таким образом можем решить и логику с break
         */

        switch ($lastItem->link->getName()) {
            case 'if':
                return self::createSingleIfConditions($lastItem->children, $default);
            case 'switch':
                return self::createSingleSwitchConditions(
                    $lastItem->children, $default, $lastItem->link->getArguments()
                );
            default:
                return self::createSingleDefaultConditions($lastItem->children, $default);
        }
    }

    /**
     * @param object[][] $structure
     * @param \Closure $callback
     *
     * @return Link[][]
     */
    private static function createStructuresByFields(array $structure, \Closure $callback)
    {
        foreach ($structure as $fieldName => $conditions) {
            if (is_null($callback)) {
                $default = new Link('set', [null]);
            }
            else {
                $default = $callback($fieldName);

                if (is_array($default)) {
                    $default = new Link('set', $default);
                }
                elseif (!$default instanceof Link) {
                    $default = new Link('set', [$default]);
                }
            }

            $structure[$fieldName] = self::createSingleDefaultConditions(
                $conditions, $default
            );
        }

        return $structure;
    }

    /**
     * @param Link[] $structure
     * @param bool $isIfBlock
     *
     * @return Link[]
     */
    private static function argumentsToObjects(array $structure)
    {
        $result = [];

        foreach ($structure as $link) {
            $name = $link->getName(Link::TYPE_LOWER);

            if ($name === 'set') {
                $result[] = $link;
            }
            else {
                $result[] = $newLink = new Link($name, [$link]);
                $newLink->setChildren(self::argumentsToObjects($link->getChildren()));
                $link->setChildren([]);
            }
        }

        return $result;
    }

    /**
     * @param Link $switch
     * @return Link
     */
    private static function switchToBreakPoint(Link $switch)
    {
        /**
         * s(c) 1 2 d 3 => array_search(c, [1,2,3]) d = 0
         * breakPoint {
         *   if (c != 3) {
         *     if (c in 1 2) {
         *       if (c != 2) {
         *         //1
         *       }
         *       //2
         *     }
         *     //d
         *   }
         *   //3
         * }
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         * if (c1) {
         *   //1
         *   if (c2) {
         *     //2
         *     break
         *   }
         * }
         * //3
         *
         * if (c1) {
         *   //1
         *   if (c2) {
         *     //2
         *   }
         * }
         * if (!c2) {
         *   //3
         * }
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         *
         * -------
         *
         * if (c in 1) {
         *   //1
         * }
         * if (c in 1 2) {
         *   //2
         * }
         * if (c in 1 2 0) {
         *   //d
         * }
         * if (c in 1 2 0 3) {
         *   //3
         * }
         *
         *
         * if
         *
         * if
         *
         *
         *
         */
        $prev = [];
        $allConditions = [];
        $hasDefault = false;

        foreach ($switch->getChildren() as $link) {
            $isDefault = $link->getName() === 'default';

            if (!$hasDefault) {
                $allConditions[] = $link->getArguments();
                $hasDefault = $isDefault;
            }
            if (!empty($prev)) {
                $newLink = new Link(
                    'if',
                    $isDefault
                        ? ['in', $switch->getArguments()[0], $allConditions]
                        : ['!=', $switch->getArguments()[0], $link->getArguments()[0]]
                );
                $newLink->setChildren($prev);
                $prev = [$newLink];
            }
            if (count($link->getChildren())) {
                $prev = array_merge($prev, $link->getChildren());
            }
        }

        if (!$hasDefault) {
            $newLink = new Link(
                'if',
                ['in', $switch->getArguments()[0], $allConditions]
            );
            $newLink->setChildren($prev);
            $prev = [$newLink];
        }

        $newLink = new Link('breakPoint', []);
        $newLink->setChildren($prev);

        return $newLink;
    }

    /**
     * @param Link[] $structure
     *
     * @return Link[]
     */
    private static function getRidOfSwitch(array $structure)
    {
        $result = [];

        foreach ($structure as $pos => $link) {
            if ($link->getName() === 'switch') {
                $link = self::switchToBreakPoint($link);
            }

            $result[] = $link;
            $link->setChildren(self::getRidOfSwitch($link->getChildren()));
        }

        return $result;
    }

    /**
     * @param Link[] $chain
     *
     * @return array|true;
     */
    private static function chainToBreakCondition(array $chain)
    {
        switch (count($chain)) {
            case 0: return false;
            case 1: return ['!', reset($chain)->getArguments()];
            default:
                $lastLink = array_pop($chain);
                $result = ['or'];

                foreach ($chain as $link) {
                    $result[] = $link->getArguments();
                }

                if ($lastLink->getName() !== 'else') {
                    $result[] = ['!', $lastLink->getArguments()];
                }
                elseif (count($result) === 2) {
                    return end($result);
                }

                return $result;
        }
    }

    /**
     * @param Link[] $structure
     * @param array $breaks
     * @return Link[]
     */
    private static function getRidOfBreak(array $structure, array &$breaks = [])
    {
        $result = [];
        $breakConditions = ['and'];
        $chainConditions = [];

        foreach ($structure as $pos => $link) {
            $name = $link->getName();

            if (!in_array($name, ['elseif', 'else'])) {
                if ($breakConditions === false) {
                    return $result;
                }
                if (count($breakConditions) > 1) {
                    $result[] = $newLink = new Link('if', $breakConditions);
                    $newLink->setChildren(self::getRidOfBreak(array_slice($structure, $pos), $breaks));

                    return $result;
                }
            }
            if ($name === 'break') {
                $level = $link->getArguments()[0] - 1;

                if (!count($chainConditions)) {
                    $breaks[$level] = false;
                }
                elseif (!isset($breaks[$level])) {
                    $breaks[$level] = ['and', self::chainToBreakCondition($chainConditions)];
                }
                elseif ($breaks[$level] !== false) {
                    $breaks[$level][] = self::chainToBreakCondition($chainConditions);
                }

                return $result;
            }
            if ($name === 'set') {
                $result[] = $link;
                $chainConditions = [];
                continue;
            }

            switch ($name) {
                case 'if':
                    $chainConditions = [$link];
                    break;
                case 'else':
                case 'elseif':
                    $chainConditions[] = $link;
                    break;
                case 'breakPoint':
                    $chainConditions = [];
                    break;
            }

            $newBreaks = [];
            $link->setChildren(self::getRidOfBreak($link->getChildren(), $newBreaks));

            if ($name === 'breakPoint') {
                $result = array_merge($result, $link->getChildren());

                if (count($newBreaks)) {
                    $newBreaksWithPoint = $newBreaks;
                    $newBreaks = [];

                    foreach ($newBreaksWithPoint as $level => $conditions) {
                        if ($level > 0) {
                            $newBreaks[$level - 1] = $conditions;
                        }
                    }
                }
            }
            else {
                $result[] = $link;
            }
            if (count($newBreaks)) {
                $breakCondition = self::chainToBreakCondition($chainConditions);

                foreach ($newBreaks as $level => $conditions) {
                    if ($conditions === false) {
                        $newBreaks[$level] = $breakCondition === false ? $breakCondition : ['and', $breakCondition];
                    }
                    elseif ($breakCondition !== false) {
                        $newBreaks[$level][] = $breakCondition;
                    }
                }

                foreach ($newBreaks as $level => $conditions) {
                    if ($conditions === false || (isset($breaks[$level]) && $breaks[$level] === false)) {
                        $breaks[$level] = false;
                    }
                    elseif (!isset($breaks[$level])) {
                        $breaks[$level] = ['and', $conditions];
                    }
                    else {
                        $breaks[$level][] = $conditions;
                    }
                }

                if ($breakConditions !== false) {
                    if (in_array(false, $newBreaks, true)) {
                        $breakConditions = false;
                    }
                    else {
                        $breakConditions = $breakCondition === false
                            ? array_merge($breakConditions, $newBreaks)
                            : array_merge($breakConditions, $breakCondition, $newBreaks);
                    }
                }
            }
        }

        return $result;
    }
}