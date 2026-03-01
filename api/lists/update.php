<?php
declare(strict_types=1);
require_once __DIR__ . '/../../api/functions.php';

$me     = auth();
$listId = (int)($params[0] ?? 0);
if (!$listId) error('ID invalide.', 400);

need_write($listId, $me['id']);

$b      = body();
$fields = [];
$bind   = [];

if (isset($b['title'])) {
    $t = str_clean($b['title']);
    if (!$t) error('title ne peut pas être vide.', 422);
    $fields[] = 'title = ?'; $bind[] = $t;
}
if (array_key_exists('description', $b)) {
    $fields[] = 'description = ?'; $bind[] = str_clean($b['description']);
}
if (isset($b['status'])) {
    if (!in_array($b['status'], ['active','completed'], true)) error('status invalide.', 422);
    $fields[] = 'status = ?'; $bind[] = $b['status'];
}
if (array_key_exists('due_date', $b)) {
    $fields[] = 'due_date = ?'; $bind[] = str_clean($b['due_date']);
}
if (array_key_exists('due_time', $b)) {
    $fields[] = 'due_time = ?'; $bind[] = str_clean($b['due_time']);
}

if (!$fields) error('Aucun champ à mettre à jour.', 422);

$bind[] = $listId;
get_pdo()->prepare("UPDATE lists SET " . implode(', ', $fields) . " WHERE id = ?")->execute($bind);

$stmt = get_pdo()->prepare("SELECT l.*, 0 AS total_items, 0 AS completed_items FROM lists l WHERE l.id = ?");
$stmt->execute([$listId]);
success(['list' => format_list($stmt->fetch())], 'Liste mise à jour.');
