<?php
declare(strict_types=1);
require_once __DIR__ . '/../../api/functions.php';

$me     = auth();
$b      = body();
$listId = (int)($b['list_id'] ?? 0);
$title  = str_clean($b['title'] ?? '');

if (!$listId) error('list_id est obligatoire.', 422);
if (!$title)  error('title est obligatoire.', 422);

need_write($listId, $me['id']);
$pdo = get_pdo();
$pdo->prepare("INSERT INTO list_items (list_id, title, is_done) VALUES (?, ?, 0)")->execute([$listId, $title]);
$id   = (int)$pdo->lastInsertId();
$pdo->prepare("UPDATE lists SET status='active' WHERE id=? AND status='completed'")->execute([$listId]);

$stmt = $pdo->prepare("SELECT * FROM list_items WHERE id=?");
$stmt->execute([$id]);
$item = $stmt->fetch();
success(['item' => ['id' => (int)$item['id'], 'list_id' => $listId, 'title' => $item['title'], 'is_done' => (bool)$item['is_done'], 'created_at' => $item['created_at']]], 'Item créé.', 201);
