<?php
    error_reporting(E_ALL);
    $loader = require( __DIR__ . '/vendor/autoload.php' );
    $loader->addPsr4( 'EugeneErg\\SQLPreprocessor\\', __DIR__ . '/src/' );

    use EugeneErg\SQLPreprocessor\Variable;
    use EugeneErg\SQLPreprocessor\Translaters;
    use EugeneErg\SQLPreprocessor\SQL;
    
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
        from($var = new Variable('tabel_name'))->{
            from($var2 = new Variable('tabel_name2'))
                ->from($var3 = new Variable('tabel_name3'))
                ->endfrom
            ->endfrom
        }
        ->select()->{
            sql()->return($var2->id, $var3->id, $var->id)
        };

    $query(Translaters\MySql::instance(), function($query) {
        dd($query);
    });