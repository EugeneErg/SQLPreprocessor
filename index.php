<?php
    error_reporting(E_ALL);
    $loader = require( __DIR__ . '/vendor/autoload.php' );
    $loader->addPsr4( 'EugeneErg\\SQLPreprocessor\\', __DIR__ . '/src/' );

    use EugeneErg\SQLPreprocessor\Variable;
    use EugeneErg\SQLPreprocessor\Translaters;
    use EugeneErg\SQLPreprocessor\SQL;
    use EugeneErg\SQLPreprocessor\Query;
    
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
        return call_user_func_array([SQL::class, 'create'], func_get_args());
    }
    function select() {
        return call_user_func_array([SQL::class, 'select'], func_get_args());
    }
    function from() {
        return call_user_func_array([SQL::class, 'from'], func_get_args());
    }
    function SQLSwitch() {
        return call_user_func_array([SQL::class, 'switch'], func_get_args());
    }
    function SQLReturn() {
        return call_user_func_array([SQL::class, 'return'], func_get_args());
    }
    function _log($text) {
        echo $text . '<br>';
    }

    $var1 = new Variable('table_name1');
    $var2 = new Variable('table_name2');

    $query =
        from($var1)->{
            from($var2)
                ->groupBy->{
                    $var2->type
                }
                ->orderBy(true)
                    ->return($var2->type)
                ->endOrderBy
            ->endfrom
        }
        ->select($var2->count()->max()->or($var2->count))->{
            $var2->type->count()
        };

    die($query(Translaters\MySql::instance()));