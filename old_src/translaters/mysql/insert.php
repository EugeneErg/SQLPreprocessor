INSERT INTO <?=
    $table
?> <?=
    implode(',', $keys)
?> <?=
    $this->makePartial('select', [
        'distinct' => $distinct,
        'selects' => $select,
        'childs' => $childs,
    ])
?>