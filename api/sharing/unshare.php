<?php
declare(strict_types=1);
require_once __DIR__ . '/../../api/functions.php';

$me     = auth();
$listId = (int)($params[0] ?? 0);
$userId = (int)($params[1] ?? 0);
if (!$listId || !$userId) error('IDs invalides.', 400);

need_owner($listId, $me['id']);

$pdo  = get_pdo();
$stmt = $pdo->prepare("SELECT id FROM shared_lists WHERE list_id=? AND shared_with_user_id=?");
$stmt->execute([$listId, $userId]);
if (!$stmt->fetchColumn()) error('Partage introuvable.', 404);

$pdo->prepare("DELETE FROM shared_lists WHERE list_id=? AND shared_with_user_id=?")->execute([$listId, $userId]);
success(null, 'Partage supprimé.');
