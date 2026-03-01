<?php
declare(strict_types=1);
require_once __DIR__ . '/../../api/functions.php';

$me   = auth();
$pdo  = get_pdo();
$stmt = $pdo->prepare("SELECT id, name, email, created_at FROM users WHERE id = ?");
$stmt->execute([$me['id']]);
$user = $stmt->fetch();
if (!$user) error('Utilisateur introuvable.', 404);

$s = $pdo->prepare("SELECT
    COUNT(*) AS total,
    SUM(status='active') AS active,
    SUM(status='completed') AS completed
    FROM lists WHERE user_id = ?");
$s->execute([$me['id']]);
$stats = $s->fetch();

success(['user' => $user, 'stats' => ['total_lists' => (int)$stats['total'], 'active' => (int)$stats['active'], 'completed' => (int)$stats['completed']]]);
