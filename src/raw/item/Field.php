<?php namespace EugeneErg\SQLPreprocessor\Raw\Item;

class Field extends ValueItem
{
    const TEMPLATE = '(?:\\.\\s*)?`(?:[^`]*(?:``)*)+`';

    /**
     * @var bool
     */
    private $isMethod;

    public function __construct($value)
    {
        $this->isMethod = $value[0] === '.';
        if ($this->isMethod) {
            $value = trim(substr($value, 1));
        }
        parent::__construct(str_replace('``','`', substr($value, 1, -1)));
    }

    public function __toString()
    {
        return ($this->isMethod ? '.' : '') . '`' . str_replace('`', '``', $this->getValue()) . '`';
    }
}