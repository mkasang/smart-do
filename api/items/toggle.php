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

$listId  = (int)$item['list_id'];
need_write($listId, $me['id']);

$newDone = $item['is_done'] ? 0 : 1;
$pdo->prepare("UPDATE list_items SET is_done=? WHERE id=?")->execute([$newDone, $itemId]);
sync_list_status($listId);

$s = $pdo->prepare("SELECT COUNT(*) AS t, SUM(is_done) AS d FROM list_items WHERE list_id=?");
$s->execute([$listId]);
$r = $s->fetch();

success([
    'item'        => ['id' => $itemId, 'title' => $item['title'], 'is_done' => (bool)$newDone],
    'list_status' => ($r['t'] > 0 && (int)$r['d'] === (int)$r['t']) ? 'completed' : 'active',
    'progress'    => ['total' => (int)$r['t'], 'completed' => (int)$r['d'], 'rate' => $r['t'] > 0 ? round($r['d']/$r['t']*100,1) : 0],
], $newDone ? 'Item terminé.' : 'Item non terminé.');
