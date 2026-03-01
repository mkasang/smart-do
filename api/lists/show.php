<?php
declare(strict_types=1);
require_once __DIR__ . '/../../api/functions.php';

$me     = auth();
$listId = (int)($params[0] ?? 0);
if (!$listId) error('ID invalide.', 400);

$level = need_read($listId, $me['id']);
$pdo   = get_pdo();

$stmt = $pdo->prepare("
    SELECT l.*, u.name AS owner_name, u.email AS owner_email,
           COUNT(i.id) AS total_items,
           SUM(CASE WHEN i.is_done=1 THEN 1 ELSE 0 END) AS completed_items
    FROM lists l
    JOIN users u ON u.id = l.user_id
    LEFT JOIN list_items i ON i.list_id = l.id
    WHERE l.id = ? GROUP BY l.id
");
$stmt->execute([$listId]);
$list = $stmt->fetch();
if (!$list) error('Liste introuvable.', 404);

$iStmt = $pdo->prepare("SELECT id, title, is_done, created_at FROM list_items WHERE list_id = ? ORDER BY id");
$iStmt->execute([$listId]);
$items = $iStmt->fetchAll();

$data = format_list($list);
$data['access_level'] = $level;
$data['owner']        = ['name' => $list['owner_name'], 'email' => $list['owner_email']];
$data['items']        = array_map(fn($i) => [
    'id'         => (int)$i['id'],
    'title'      => $i['title'],
    'is_done'    => (bool)$i['is_done'],
    'created_at' => $i['created_at'],
], $items);

success(['list' => $data]);
