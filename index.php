<?php
error_reporting(E_ALL);
$loader = require( __DIR__ . '/vendor/autoload.php' );
$loader->addPsr4( 'EugeneErg\\SQLPreprocessor\\', __DIR__ . '/src/' );


use EugeneErg\SQLPreprocessor\Builder;

$query = Builder::raw("
    select
        `table`.`field_1`
    from `table`
");

$query();