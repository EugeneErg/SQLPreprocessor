<?php namespace EugeneErg\SQLPreprocessor;

use EugeneErg\SQLPreprocessor\Raw\ItemAbstract;

/**
 * Class ParseException
 * @package EugeneErg\SQLPreprocessor
 *
 * @method static self incorrectCountArguments(int $count, int $min = null, int $max = null)
 * @method static self incorrectLink(object $link)
 * @method static self notAccessMethod(ItemAbstract $item, string $methodName)
 * @method static self incorrectParserClass(string $className);
 *
 */
class ParseException extends Exception
{
    protected static $errors = [
        'incorrectCountArguments',
        'incorrectLink',
        'notAccessMethod',
        'incorrectParserClass',
    ];

    /**
     * @param int $count
     * @param int|null $min
     * @param int|null $max
     * @return self
     *

     */
    private function getIncorrectCountArgumentsMessage($count, $min = null, $max = null)
    {
        return "";// todo
    }

    private function getIncorrectLinkMessage(\stdClass $chain)
    {
        return "";// todo
    }

    private function getNotAccessMethodMessage(ItemAbstract $item, $methodName)
    {
        return "";// todo
    }

    private function getIncorrectParserClassMessage($className)
    {
        return "";// todo
    }
}