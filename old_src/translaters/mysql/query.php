<?= $needScob ? '(' : '' ?>
<?= $action ?>
<?php        if (count($where)): ?>
WHERE(<?= $this->getCurentCondition($where) ?>)
<?php endif; if (count($orders)): ?>
ORDER BY <?= implode(', ', $orders) ?>
<?php endif; if (count($groups)): ?>
 GROUP BY <?= implode(', ', $groups) ?>
<?php endif; if (count($having)): ?>
HAVING <?= $this->getCurentCondition($having) ?>
<?php endif; if (isset($limit)): ?>
LIMIT <?= ($ofset ? "$ofset," : '') . $limit ?>
<?php endif ?>
<?= $needScob ? ')' : '' ?>
<?php        if (isset($alias)): ?>
 <?= $this->quoteTable($alias) ?>
<?php endif; if (count($on)): ?>
ON(<?= $this->getParentCondition($on) ?>)
<?php endif ?>