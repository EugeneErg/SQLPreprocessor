<?php namespace EugeneErg\SQLPreprocessor\Record;

/**
 * Class Variable
 * @package EugeneErg\SQLPreprocessor\Record
 */
class Variable extends AbstractRecord
{
    /**
     * @var mixed
     */
    private $value;

    /**
     * @param mixed $value
     * @return Container
     */
    public static function create($value)
    {
        $new = new self(null, function($type, $name, array $arguments) use($value) {
            if ($type === Container::TYPE_METHOD) {
                if (!is_object($value)
                    && !method_exists($value, $name)
                    && !method_exists($value, '__call')
                ) {
                    return null;
                }

                $newValue = call_user_func_array([$value, $name], $arguments);
            }
            elseif (is_array($value)) {
                $newValue = isset($value[$name]) ? $value[$name] : null;
            }
            elseif (!is_object($value)) {
                $newValue = null;
            }
            elseif (method_exists($value, '__get')
                || isset($value->$name)
            ) {
                $newValue = $value->$name;
            }
            elseif ($value instanceof \ArrayAccess) {
                $newValue = $value[$name];
            }
            else {
                $newValue = null;
            }

            if ($newValue instanceof Container) {
                return $newValue;
            }

            if ($newValue instanceof AbstractRecord) {
                return $newValue->getContainer();
            }

            return Variable::create($newValue);
        });
        $new->value = $value;

        return $new->getContainer();
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}