<?php namespace EugeneErg\SQLPreprocessor;


class SQL
{
    private $hash;

    public function __clone()
    {

    }

    public function __wakeup()
    {

    }




    private function hashes($hash = null)
    {
        static $hashes = [];
        if (is_null($hash)) {
            $hashes[$hash = spl_object_hash($this)] = $this;
            return $hash;
        }
        if (isset($hashes[$hash])) {
            return $hashes[$hash];
        }
    }

    private function __construct()
    {
        $this->hash = $this->hashes();
    }

    public function __call($name, $args)
    {
        $this->functions[] = new SQLFunction($name, $args);
        return $this;
    }

    public static function __callStatic($name, $args)
    {
        $sql = new self();
        return $sql->__call($name, $args);
    }

    public function __get($name)
    {
        if (!is_null($sql = $this->hashes($name))) {
            $this->functions[] = &$sql->functions;
            return $this;
        }
        return $this->__call($name, []);
    }

    public function __toString()
    {
        return $this->hash;
    }

    public function structure()
    {
        /*
        static $structure;
        if (isset($structure)) {
            return $structure;
        }
        $structure = new Structure();
        $if = new Structure(1, 1, ['or', 'and']);
        $else = new Structure();
        $switch = new Structure(1, 1);
        $case = new Structure(0, 0, ['or', 'and']);
        $default = new Structure();
        $select = new Structure(1);
        $var = new Structure();
        $from = new Structure();
        $orderby = new Structure();
        $groupby = new Structure();
        $into = new Structure(1, 1);
        $insert = new Structure();
        $update = new Structure(1);
        $delete = new Structure(1);
        $return = new Structure(0, 1, ['or', 'and']);//ретерн не имеет дочерних блоков, поэтому не будет нуждаться в закрывающей функции


        //блок if
        $if->addBlock('else', $else);
        $if->addBlock('elseif', $if);
        $if->addBlock('endif');
        $else->addBlock('endif');


        //блок switch
        $switch->addBlock('case', $case);
        $switch->addBlock('default', $default);
        $case->addBlock('case', $case);
        $case->addBlock('default', $default);
        $case->addBlock('endswitch');
        $default->addBlock('endswitch');


        // блок from
        foreach (['from', 'delete'] as $name) {
            $$name->addChild('from', $from);
            $$name->addChild('return', $return, 'return');
            //$$name->addChild('if', $if, 'if');
            //$$name->addChild('switch', $switch, 'switch');
            $$name->addChild('var', $var);
            $$name->addChild('groupby', $groupby);
            $$name->addChild('orderby', $orderby);
        }


        // блок delete
        $delete->addChild('delete', $delete);


        //блок var if else case default orderby groupby update insert
        foreach (['var', 'if', 'else', 'case', 'default', 'orderby', 'groupby', 'insert', 'update', 'select'] as $name) {
            $$name->addChild('switch', $switch, 'switch');
            $$name->addChild('if', $if, 'if');
            $$name->addChild('return', $return, 'return');
        }

        $var->addBlock('var', $var);
        $var->addBlock('endvar');


        $orderby->addBlock('orderby', $orderby);
        $orderby->addBlock('endorderby');


        $groupby->addBlock('groupby', $groupby);
        $groupby->addBlock('endgroupby');


        //блок select
        $select->addChild('select', $select, 'select');


        //блок into
        $into->addBlock('insert', $insert);
        $insert->addBlock('insert', $insert);
        $insert->addBlock('endinto', $insert);

        $structure->addChild('from', $from, [], null, 1);

        $structure->addChild('select', $select, 'select');
        $structure->addChild('into', $into, 'insert');
        $structure->addChild('update', $update, 'update');
        $structure->addChild('delete', $delete, 'delete', null, 1);

        return $structure;
*/


        static $root;
        if (!$root) {

            $from = new Structure(function($from) {

            }, [


            ]);

            $root = new Structure(function($root) {
                if ($root->count('select', 'delete', 'update', 'insert') !== 1) {
                    return false;
                }
                if ($root->count('insert') && !$root->count('into')) {
                    return false;
                }

            }, [
                'from' => $from,
                'insert' => $insert,
                'update' => $update,
                'delete' => $delete,
                'select' => $select,
            ]);
        };
        return $root;
    }

    public function __invoke(Translater $sqlClass, \Closure $function = null)
    {
        $structure = new \StdClass();
        $structure->childs = Self::structure()->validation($this->functions, $levels);
        $structure->union = [];
        $type = reset($levels);
        $query = $this->getQueryTree($structure, ['from', 'delete', 'var']);//содержит основной запрос
        $select = $structure->select = new \StdClass();//формирует структуру результата
        $structure->select->childs = [];
        $this->getFields($structure, $query);
        $this->parseTreeFunctions($structure, ['orderby', 'groupby', 'insert', 'into', 'select', 'var', 'from', 'delete'], $query);
        unset($structure);
        $query->analyze();
        $request = $sqlClass->translate($query);
        if (is_null($function)) {
            return $request;
        }
        return $this->createResult($function($request), $select);
    }
}