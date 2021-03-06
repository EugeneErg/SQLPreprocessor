<?php namespace EugeneErg\SQLPreprocessor;

final class Variable
{
    const IS_TABLE_CONTENT = 'content';
    const IS_TABLE_NAME = 'name';
    const IS_TABLE_FIELD = 'field';
    const IS_QUERY = 'query';
    const IS_SUBQUERY = 'subQuery';
    private $type;
    private $name;
    private $parent;
    private $fields = [];
    private $keys = [];
    private $values = [];
    private $query;

    private function __clone()
    {
    }

    private function __wakeup()
    {
    }

    public function __debugInfo()
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'context' => spl_object_hash($this->getTableVar()),
            'parent' => is_null($this->parent) ? 'NULL' : $this->parent->type . '(' . $this->parent->name . ')',
            'fields' => $this->fields,
        ];
    }

    private static function canBeString($value)
    {
        return (string)$value;
    }

    private function setKeysFromValue($values)
    {
        $this->keys = array_keys(call_user_func('array_replace', $values));
    }

    private function setKeys(array $keys)
    {
        $this->keys = [];
        foreach ($keys as $key => $default) {
            if (is_integer($key)) {
                $key = $default;
                $default = null;
            }
            if (isset($this->keys[$key])) {
                throw new \Exception('You can not specify the same name of fields in a table');
            }
            $this->keys[$key] = $default;
        }
    }

    public function __construct($nameOrValue = null, $keys = [])
    {
        if (isset($this->type)) {
            throw new \Exception('This method is magical and can only be called as "new Variable(...)"');
        }
        $this->setKeys((array)$keys);
        if ($nameOrValue instanceof SQL) {
            $this->type = Self::IS_SUBQUERY;
            $this->query = $nameOrValue;
        } elseif (is_array($nameOrValue)) {
            if (!count($keys)) {
                $this->setKeysFromValue($nameOrValue);
            }
            $this->type = Self::IS_TABLE_CONTENT;
            $this->values = $nameOrValue;
        } elseif (is_null($nameOrValue)) {
            $this->type = Self::IS_QUERY;
        } else {
            $this->type = Self::IS_TABLE_NAME;
            $this->name = $nameOrValue;
        }
    }

    public function getType()
    {
        return $this->type;
    }

    public function getTableVar()
    {
        if ($this->type == Self::IS_TABLE_FIELD) {
            return $this->parent;
        }
        return $this;
    }

    public function getValue()
    {
        switch ($this->type) {
            case Self::IS_TABLE_NAME:
            case Self::IS_TABLE_FIELD:
                return $this->name;
            case Self::IS_SUBQUERY:
                return $this->query;
            case Self::IS_TABLE_CONTENT:
                $result = [];
                $keys = count($this->keys) ? $this->keys : array_keys($this->fields);
                foreach ($this->values as $values) {
                    $values = (array)$values;
                    $num = 0;
                    foreach ($keys as $key => $default) {
                        if (!isset($this->fields[$key])) {
                            continue;
                        }
                        if (array_key_exists($num, $values)) {
                            $value = $values[$num];
                        } elseif (array_key_exists($key, $values)) {
                            $value = $values[$key];
                        } else {
                            $value = $default;
                        }
                        $result[][$key] = $value;
                        Self::canBeString($value);
                        $num++;
                    }
                }
                return $result;
        }
    }

    public function __get($name)
    {
        if (in_array($this->type, [Self::IS_TABLE_FIELD, Self::IS_QUERY])) {
            throw new \Exception('Table field can not have child fields');
        }
        if (!isset($this->fields[$name])) {
            if (count($this->keys) && !array_key_exists($name, $this->keys)) {
                throw new \Exception("In this table there is no field with this name '{$name}'");
            }
            $this->fields[$name] = clone $this;
            $this->fields[$name]->type = Self::IS_TABLE_FIELD;
            $this->fields[$name]->name = $name;
            $this->fields[$name]->parent = $this;
        }
        return $this->fields[$name];
    }

    public function __call($name, $args)
    {
        return call_user_func_array([SQL::from($this, $this), $name], $args);
    }

    public function __invoke($variable)
    {
        $context = $this->getTableVar();
        if ($variable instanceof Self
            && $variable->getTableVar() === $context
        ) {
            return SQL::from($variable);
        }
        return SQL::from($context, $variable);
    }
}