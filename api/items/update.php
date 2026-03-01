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

need_write((int)$item['list_id'], $me['id']);

$b     = body();
$title = str_clean($b['title'] ?? '');
if (!$title) error('title est obligatoire.', 422);

$pdo->prepare("UPDATE list_items SET title=? WHERE id=?")->execute([$title, $itemId]);
success(['item' => ['id' => $itemId, 'title' => $title, 'is_done' => (bool)$item['is_done']]], 'Item mis à jour.');
