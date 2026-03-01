<?php
declare(strict_types=1);
require_once __DIR__ . '/../../api/functions.php';

$me   = auth();
$date = trim($_GET['date'] ?? '');
if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) error('date requis (YYYY-MM-DD).', 422);

$pdo = get_pdo();

$own = $pdo->prepare("
    SELECT l.*, COUNT(i.id) AS total_items, SUM(CASE WHEN i.is_done=1 THEN 1 ELSE 0 END) AS completed_items
    FROM lists l LEFT JOIN list_items i ON i.list_id=l.id
    WHERE l.user_id=? AND l.due_date=? GROUP BY l.id
    ORDER BY CASE WHEN l.due_time IS NULL THEN 1 ELSE 0 END, l.due_time
");
$own->execute([$me['id'], $date]);

$shared = $pdo->prepare("
    SELECT l.*, sl.permission, u.name AS owner_name,
           COUNT(i.id) AS total_items, SUM(CASE WHEN i.is_done=1 THEN 1 ELSE 0 END) AS completed_items
    FROM shared_lists sl
    JOIN lists l ON l.id=sl.list_id JOIN users u ON u.id=l.user_id
    LEFT JOIN list_items i ON i.list_id=l.id
    WHERE sl.shared_with_user_id=? AND l.due_date=? GROUP BY l.id, sl.id
    ORDER BY CASE WHEN l.due_time IS NULL THEN 1 ELSE 0 END, l.due_time
");
$shared->execute([$me['id'], $date]);

$ownLists    = array_map('format_list', $own->fetchAll());
$sharedRows  = $shared->fetchAll();
$sharedLists = array_map(function($r) {
    $d = format_list($r);
    $d['permission'] = $r['permission'];
    $d['owner_name'] = $r['owner_name'];
    return $d;
}, $sharedRows);

success([
    'date'         => $date,
    'own_lists'    => $ownLists,
    'shared_lists' => $sharedLists,
    'summary'      => ['total' => count($ownLists) + count($sharedLists)],
]);
