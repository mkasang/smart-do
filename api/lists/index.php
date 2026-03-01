<?php
declare(strict_types=1);
require_once __DIR__ . '/../../api/functions.php';

$me     = auth();
$page   = max(1, (int)($_GET['page']  ?? 1));
$limit  = min(max(1, (int)($_GET['limit'] ?? PAGINATION_DEFAULT)), PAGINATION_MAX);
$offset = ($page - 1) * $limit;

$pdo    = get_pdo();
$where  = ['l.user_id = ?'];
$bind   = [$me['id']];

if (!empty($_GET['status']) && in_array($_GET['status'], ['active','completed'], true)) {
    $where[] = 'l.status = ?'; $bind[] = $_GET['status'];
}
if (!empty($_GET['type']) && in_array($_GET['type'], ['simple','checklist'], true)) {
    $where[] = 'l.type = ?'; $bind[] = $_GET['type'];
}
if (!empty($_GET['search'])) {
    $s = '%' . $_GET['search'] . '%';
    $where[] = '(l.title LIKE ? OR l.description LIKE ?)';
    $bind[] = $s; $bind[] = $s;
}
if (!empty($_GET['date'])) {
    $where[] = 'l.due_date = ?'; $bind[] = $_GET['date'];
}

$w = 'WHERE ' . implode(' AND ', $where);

$c = $pdo->prepare("SELECT COUNT(*) FROM lists l {$w}");
$c->execute($bind);
$total = (int)$c->fetchColumn();

$stmt = $pdo->prepare("
    SELECT l.id, l.title, l.type, l.description, l.status,
           l.due_date, l.due_time, l.created_at,
           COUNT(i.id) AS total_items,
           SUM(CASE WHEN i.is_done=1 THEN 1 ELSE 0 END) AS completed_items
    FROM lists l LEFT JOIN list_items i ON i.list_id = l.id
    {$w} GROUP BY l.id
    ORDER BY CASE WHEN l.due_date IS NULL THEN 1 ELSE 0 END,
             l.due_date ASC, l.due_time ASC, l.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([...$bind, $limit, $offset]);
$rows = $stmt->fetchAll();

success([
    'lists'      => array_map('format_list', $rows),
    'pagination' => [
        'total'       => $total,
        'page'        => $page,
        'limit'       => $limit,
        'total_pages' => $limit > 0 ? (int)ceil($total / $limit) : 0,
        'has_next'    => ($page * $limit) < $total,
        'has_prev'    => $page > 1,
    ],
]);
