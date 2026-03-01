<?php
declare(strict_types=1);
require_once __DIR__ . '/../../api/functions.php';

$me     = auth();
$listId = (int)($params[0] ?? 0);
if (!$listId) error('ID invalide.', 400);

need_read($listId, $me['id']);
$pdo  = get_pdo();
$stmt = $pdo->prepare("SELECT * FROM lists WHERE id = ?");
$stmt->execute([$listId]);
$list = $stmt->fetch();
if (!$list) error('Liste introuvable.', 404);

$iStmt = $pdo->prepare("SELECT title FROM list_items WHERE list_id = ?");
$iStmt->execute([$listId]);
$items = $iStmt->fetchAll();

$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT INTO lists (user_id, title, type, description, status, due_date, due_time) VALUES (?,?,?,?,'active',?,?)")
        ->execute([$me['id'], 'Copie de ' . $list['title'], $list['type'], $list['description'], $list['due_date'], $list['due_time']]);
    $newId = (int)$pdo->lastInsertId();
    foreach ($items as $item) {
        $pdo->prepare("INSERT INTO list_items (list_id, title, is_done) VALUES (?, ?, 0)")->execute([$newId, $item['title']]);
    }
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error('Erreur lors de la duplication.', 500);
}

$stmt = $pdo->prepare("SELECT l.*, COUNT(i.id) AS total_items, 0 AS completed_items FROM lists l LEFT JOIN list_items i ON i.list_id=l.id WHERE l.id=? GROUP BY l.id");
$stmt->execute([$newId]);
success(['list' => format_list($stmt->fetch())], 'Liste dupliquée.', 201);
