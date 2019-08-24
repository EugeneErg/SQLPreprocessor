DELETE <?= count($delete) > 1 ? implode(',', $delete) : '' ?>
FROM <?= implode(' ', $childs) ?>