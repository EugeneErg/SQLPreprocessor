SELECT <?=
    $distinct
        ? 'DISTINCT'
        : ''
?> <?=
    implode(', ', $selects)
?> <?=
    count($childs)
        ? ' FROM ' . implode(' ', $childs)
        : ''
?>