<?php
error_reporting(E_ALL);
$loader = require( __DIR__ . '/vendor/autoload.php' );
$loader->addPsr4( 'EugeneErg\\SQLPreprocessor\\', __DIR__ . '/src/' );

use EugeneErg\SQLPreprocessor\LogicStructureConverter;
use EugeneErg\SQLPreprocessor\Raw;
use EugeneErg\SQLPreprocessor\Parsers\ParserAbstract;
use EugeneErg\SQLPreprocessor\Topology;


$query = (new Topology([
    'if' => ['type' => Topology::SEQUENCE_TYPE, 'next' => ['elseif', 'else']],
    'break',
    'set',
    'switch' => ['case', 'default'],
]))->getStructure(new Raw("
    switch (1)
        case 1:
            `qwert` = 12;
            break;
        case 2:
            `qwert` = 20;
            break;
        default:
            `qwert` = 0;
    endswitch
"));

$structure = $query(ParserAbstract::TYPE_SELECT);

var_dump(LogicStructureConverter::toList($structure));
die;

$query = Builder::raw("
    select
        `table`.`field_1`
    from `table`
");

$query();