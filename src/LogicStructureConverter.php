<?php namespace EugeneErg\SQLPreprocessor;

class LogicStructureConverter
{
    private function __construct() {}

    /**
     * @param Link[] $structure
     * @param \Closure|null $callback
     */
    public static function toList(array $structure, \Closure $callback = null)
    {
        $structure = self::argumentsToObjects($structure);
        $structure = self::getRidOfSwitch($structure);
        $structure = self::getRidOfBreak($structure);
        self::createStructuresByFields($structure, $callback);

        //$structure = self::optimizeConditions($structure);
    }

    /**
     * @param Link[] $structure
     *
     * @return array
     */
    private static function findAllResults(array $structure)
    {
        $results = [];
        $conditionChain = [];

        foreach ($structure as $pos => $link) {
            if ($link->getName() === 'result') {
                $arguments = $link->getArguments();
                $fieldName = array_shift($arguments);
                $results[$fieldName][$pos] = ['link' => new Link('result', $arguments)];
                $conditionChain = [];
            }
            else {
                if ($link->getName() === 'if') {
                    $conditionChain = [];
                }

                $conditionChain[$pos] = ['link' => $link];
                $childResults = self::findAllResults($link->getChildren());

                foreach ($childResults as $fieldName => $values) {
                    reset($conditionChain);
                    $ifPos = key($conditionChain);

                    $path = [$ifPos => $conditionChain];
                    $path[$ifPos][$pos]['children'] = $values;

                    if (isset($results[$fieldName])) {
                        $results[$fieldName] = array_replace($results[$fieldName], $path);
                        ksort($results[$fieldName]);
                    }
                    else {
                        $results[$fieldName] = $path;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * @param Link[] $structure
     * @param \Closure|null $callback
     *
     * @return Link[]
     */
    private static function createStructuresByFields(array $structure, \Closure $callback = null)
    {
        $result = self::findAllResults($structure);
        $fieldValues = [];

        foreach ($structure as $fieldName => $conditions) {
            $result[$fieldName] = self::createSingleConditions(
                $conditions, $callback
                ? function() use($callback, $fieldName, &$fieldValues) {
                    if (array_key_exists($fieldName, $fieldValues)) {
                        return $fieldValues[$fieldName];
                    }

                    return $fieldValues[$fieldName] = $callback($fieldName);
                }
                : null
            );
        }

        return $result;
    }

    private static function isSingleIf(array $structure, $pos = 0)
    {
        return isset($structure[$pos + 1])
            && $structure[$pos]->getName() === 'if'
            && !in_array($structure[$pos + 1]->getName(), ['elseif', 'else']);
    }

    /**
     * @param Link[][][]|array[] $structure
     * @param mixed $callback
     *
     * @return Link[]
     */
    private static function createSingleConditions(array $structure, $callback)
    {
        //if elseif else return
        /**
         * if c1
         *  -
         * elseif c2
         *  +
         * elseif c3
         *  -
         * else
         *  +
         *
         *
         * if c4
         *  +
         * elseif c5
         *  -
         * elseif c6
         *  +
         *
         *
         * if c4 {
         *  +
         * }
         * elseif c5 {
         *   if c1
         *    -
         *   elseif c2
         *    +
         *   elseif c3
         *    -
         *   else
         *    +
         * }
         * elseif c6 {
         *  +
         * }
         *
         *
         * if c1 +
         * elseif c2 -
         * elseif c3 +
         * elseif c4 -
         * elseif c5 +
         *
         * if c1 +
         * elseif !c2 c3 +
         * elseif !c2 !c4 c5 +
         * else -
         *
         *
         *
         *
         * if_1 {if_2 {}} (not else) => if_1_2 {}
         *
         * if_1 {} if_2 {} =>
         *
         * (if elseif? else?)* => (if elseif? else?)?
         *
         */

        $lastItem = array_pop($structure);

        if (!is_array($lastItem)) {//return
            return $lastItem;
        }
        if (count($structure)) {
            $default = self::createSingleConditions($structure, $callback);
        }
        elseif ($callback instanceof \Closure) {
            $default = [$callback()];
        }
        else {
            $default = [$callback];
        }

        ksort($lastItem);
        $notCondition = ['or'];
        $result = [];

        foreach ($lastItem as $option) {
            $link = $option['link'];

            if (isset($option['children'])) {
                if (count($notCondition) > 1) {
                    $result[] = $newLink = new Link(count($result) ? 'elseif' : 'if', $notCondition);
                    $notCondition = ['or'];
                    $newLink->setChildren([$default]);
                }

                $link->setChildren(self::createSingleConditions($option['children'], $default));
                $result[] = $link;
            }
            elseif ($link->getName() === 'else') {
                $notCondition = ['or'];
            }
            else {
                $notCondition[] = $link->getArguments();
            }
        }

        if (count($notCondition) > 1) {
            $result[] = $newLink = new Link('else', $notCondition);
            $newLink->setChildren([$default]);
        }

        return $result;
    }

    /**
     * @param Link[] $structure
     * @param bool $isIfBlock
     *
     * @return Link[]
     */
    private static function argumentsToObjects(array $structure, $isIfBlock = false)
    {
        $result = [];

        foreach ($structure as $link) {
            switch ($name = $link->getName(Link::TYPE_LOWER)) {
                case 'break':
                    $result[] = new Link($name, max(1, (int) reset($link->getArguments())));
                    break;
                case 'result':
                    $result[] = $link;
                    break;
                case 'if':
                    if (!$isIfBlock) {
                        $result = array_merge($result, self::argumentsToObjects($link->getArguments(), true));
                        break;
                    }
                default:
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
         * s(c) 1 2 d 3
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

            if (!in_array($name, ['elseif', 'else']) && count($breakConditions) > 1) {
                $result[] = $newLink = new Link('if', $breakConditions);
                $newLink->setChildren(self::getRidOfBreak(array_slice($structure, $pos), $breaks));

                return $result;
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
            if ($name === 'return') {
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

            $link->setChildren(self::getRidOfBreak($link->getChildren(), $newBreaks = []));

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
                        $conditions = $breakCondition;
                    }
                    elseif ($breakCondition !== false) {
                        $conditions[] = $breakCondition;
                    }
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

                if ($breakCondition === false) {
                    $breakConditions = array_merge($breakConditions, $newBreaks);
                }
                else {
                    $breakConditions = array_merge($breakConditions, $breakCondition, $newBreaks);
                }
            }
        }

        return $result;
    }
}