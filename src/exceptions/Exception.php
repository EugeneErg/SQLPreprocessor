<?php namespace EugeneErg\SQLPreprocessor;

abstract class Exception extends \Exception
{
    protected static $errors = [];

    /**
     * @param string $name
     * @param array $arguments
     * @return Exception
     */
    public static function __callStatic($name, array $arguments)
    {
        $errors = [];
        foreach (static::$errors as $name => $option) {
            if (is_int($name) && is_string($option)) {
                $code = $name;
                $name = $option;
                $option = $code;
            }
            $errors[$name] = (array) $option;
        }

        $self = new static('');
        $options = $errors[$name];
        if (isset($options['code'])) {
            $self->code = $options['code'];
        }
        else {
            $self->code = reset($options);
        }
        if (isset($options['format'])) {
            $self->message = sprintf($options['format'], $arguments);
        }
        else {
            $name = ucfirst($name);
            $self->message = call_user_func_array([$self, "get{$name}Message"], $arguments);
        }
        return $self;
    }
}