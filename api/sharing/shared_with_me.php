<?php
declare(strict_types=1);
require_once __DIR__ . '/../../api/functions.php';

$me  = auth();
$pdo = get_pdo();
$bind = [$me['id']];
$w   = 'WHERE sl.shared_with_user_id=?';

if (!empty($_GET['status']) && in_array($_GET['status'], ['active','completed'], true)) {
    $w .= ' AND l.status=?'; $bind[] = $_GET['status'];
}

$stmt = $pdo->prepare("
    SELECT l.id, l.title, l.type, l.description, l.status, l.due_date, l.due_time, l.created_at,
           sl.permission, u.name AS owner_name, u.email AS owner_email,
           COUNT(i.id) AS total_items,
           SUM(CASE WHEN i.is_done=1 THEN 1 ELSE 0 END) AS completed_items
    FROM shared_lists sl
    JOIN lists l  ON l.id  = sl.list_id
    JOIN users u  ON u.id  = l.user_id
    LEFT JOIN list_items i ON i.list_id = l.id
    {$w} GROUP BY l.id, sl.id
    ORDER BY CASE WHEN l.due_date IS NULL THEN 1 ELSE 0 END, l.due_date ASC
");
$stmt->execute($bind);
$rows = $stmt->fetchAll();

$lists = array_map(function($r) {
    $d = format_list($r);
    $d['permission'] = $r['permission'];
    $d['owner']      = ['name' => $r['owner_name'], 'email' => $r['owner_email']];
    return $d;
}, $rows);

success(['lists' => $lists, 'total' => count($lists)]);
