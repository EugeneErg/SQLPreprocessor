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
    
    $query =
        from($var1 = new Variable('tabel_name1'))->{
            from($var2 = new Variable('tabel_name2'))
                ->groupBy
                    ->return($var2->parent_id)
                ->endGroupBy
                ->return($var1->id, $var2->type)
                    ->and($var2->type->and(1)->and(0)->and(1), '&&', 3, '=', 1)
                    ->and($var2->count(), '>', 4)
            ->endfrom
        }
        ->select()->{
            sql()->return($var1->id, $var2->type)
        };


    die($query(Translaters\MySql::instance()));