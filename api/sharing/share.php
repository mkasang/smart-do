<?php
declare(strict_types=1);
require_once __DIR__ . '/../../api/functions.php';

$me     = auth();
$listId = (int)($params[0] ?? 0);
if (!$listId) error('ID invalide.', 400);

need_owner($listId, $me['id']);

$b          = body();
$targetId   = (int)($b['user_id']    ?? 0);
$permission = $b['permission'] ?? '';

if (!$targetId)                                           error('user_id est obligatoire.', 422);
if (!in_array($permission, ['read','edit'], true))        error('permission doit être read ou edit.', 422);
if ($targetId === $me['id'])                              error('Vous ne pouvez pas partager avec vous-même.', 422);

$pdo  = get_pdo();
$stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id=?");
$stmt->execute([$targetId]);
$target = $stmt->fetch();
if (!$target) error('Utilisateur cible introuvable.', 404);

$stmt = $pdo->prepare("SELECT id, permission FROM shared_lists WHERE list_id=? AND shared_with_user_id=?");
$stmt->execute([$listId, $targetId]);
$existing = $stmt->fetch();

if ($existing) {
    if ($existing['permission'] === $permission) error('Déjà partagé avec cette permission.', 409);
    $pdo->prepare("UPDATE shared_lists SET permission=? WHERE id=?")->execute([$permission, $existing['id']]);
    success(['user' => $target, 'permission' => $permission], 'Permission mise à jour.');
}

$pdo->prepare("INSERT INTO shared_lists (list_id, owner_id, shared_with_user_id, permission) VALUES (?,?,?,?)")
    ->execute([$listId, $me['id'], $targetId, $permission]);

success(['user' => $target, 'permission' => $permission], 'Liste partagée.', 201);
