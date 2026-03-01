<?php
declare(strict_types=1);
require_once __DIR__ . '/../../api/functions.php';

$me    = auth();
$query = trim($_GET['query'] ?? '');
if (strlen($query) < 2) error('La recherche doit contenir au moins 2 caractères.', 422);

$pdo  = get_pdo();
$stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE (name LIKE ? OR email LIKE ?) AND id != ? LIMIT 20");
$stmt->execute(["%{$query}%", "%{$query}%", $me['id']]);
$users = $stmt->fetchAll();

success(['users' => $users, 'total' => count($users)]);
