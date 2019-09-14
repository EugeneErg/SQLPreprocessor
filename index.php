<?php
error_reporting(E_ALL);
$loader = require( __DIR__ . '/vendor/autoload.php' );
$loader->addPsr4( 'EugeneErg\\SQLPreprocessor\\', __DIR__ . '/src/' );

use EugeneErg\SQLPreprocessor\Topology;

    $structure = [
        'if' => ['type' => Topology::SEQUENCE_TYPE, 'next' => ['elseif', 'else']],
        'from' => Topology::PARENT_TYPE,
        'delete' => Topology::PARENT_TYPE,
        'orderby' => Topology::PARENT_TYPE,
        'groupby' => Topology::PARENT_TYPE,
        'var' => Topology::PARENT_TYPE,
        'select' => Topology::PARENT_TYPE,
        'insert',
        'update',
        'switch' => ['case', 'default'],
    ];

    $topology = new Topology($structure);

    function createStructure($array)
    {
        $result = [];
        foreach ($array as $value) {
            if (is_array($value)) {
                $result[] = createStructure($value);
            }
            else {
                $result[] = (object) [
                    'name' => $value
                ];
            }
        }
        return $result;

    }

    var_dump($topology->getStructure(createStructure([
        'switch',
            'case',
                'update',
            'default',
        'endswitch',
    ])));

    die;


    use EugeneErg\SQLPreprocessor\Variable;
    use EugeneErg\SQLPreprocessor\Translaters;
    use EugeneErg\SQLPreprocessor\Query;
    use EugeneErg\SQLPreprocessor\Raw;
    
    function dd() {
        $res = '<? ';
        foreach (func_get_args() as $item) {
            ob_start();
            call_user_func('var_dump', $item);
            $res .= str_replace('<?', '< ?', ob_get_clean());
        }
        die(highlight_string($res, true));
    }

    function sql() {
        return call_user_func_array([Query::class, 'create'], func_get_args());
    }
    function select() {
        return call_user_func_array([Query::class, 'select'], func_get_args());
    }
    function from() {
        return call_user_func_array([Query::class, 'from'], func_get_args());
    }
    function SQLSwitch() {
        return call_user_func_array([Query::class, 'switch'], func_get_args());
    }
    function SQLReturn() {
        return call_user_func_array([Query::class, 'return'], func_get_args());
    }
    function _log($text) {
        echo $text . '<br>';
    }
    
    $query =
        from($var1 = new Variable('tabel_name1'))->{
            from($var2 = new Variable('tabel_name2'))
                ->groupBy
                    ->return($var2->type)
                ->endGroupBy
            ->endfrom
        }
        ->select($var2->count()->max()->or($var2->count))->{
            sql()->return($var2->type->count())
        };
    die($query(Translaters\MySql::instance()));