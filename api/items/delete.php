<?php
declare(strict_types=1);
require_once __DIR__ . '/../../api/functions.php';

$me     = auth();
$itemId = (int)($params[0] ?? 0);
if (!$itemId) error('ID invalide.', 400);

$pdo  = get_pdo();
$stmt = $pdo->prepare("SELECT * FROM list_items WHERE id=?");
$stmt->execute([$itemId]);
$item = $stmt->fetch();
if (!$item) error('Item introuvable.', 404);

$listId = (int)$item['list_id'];
need_write($listId, $me['id']);
$pdo->prepare("DELETE FROM list_items WHERE id=?")->execute([$itemId]);
sync_list_status($listId);
success(null, 'Item supprimé.');
