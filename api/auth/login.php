<?php
declare(strict_types=1);
require_once __DIR__ . '/../../api/functions.php';

$b        = body();
$email    = strtolower(trim($b['email']    ?? ''));
$password = $b['password'] ?? '';

if (!$email || !$password) error('Email et mot de passe requis.', 422);

$pdo  = get_pdo();
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    error('Email ou mot de passe incorrect.', 401);
}

$token = jwt_generate((int)$user['id'], $user['email'], $user['name']);
success(['token' => $token, 'user' => ['id' => (int)$user['id'], 'name' => $user['name'], 'email' => $user['email']]], 'Connexion réussie.');
