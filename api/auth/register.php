<?php
declare(strict_types=1);
require_once __DIR__ . '/../../api/functions.php';

$b = body();
$name     = str_clean($b['name']     ?? '');
$email    = strtolower(trim($b['email']    ?? ''));
$password = $b['password'] ?? '';

if (!$name)                              error('Le champ name est obligatoire.', 422);
if (strlen($name) < 2)                  error('name doit faire au moins 2 caractères.', 422);
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) error('Email invalide.', 422);
if (strlen($password) < 8)              error('password doit faire au moins 8 caractères.', 422);

$pdo  = get_pdo();
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetchColumn()) error('Cet email est déjà utilisé.', 409);

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)")
    ->execute([$name, $email, $hash]);

$userId = (int)$pdo->lastInsertId();
$token  = jwt_generate($userId, $email, $name);

success(['token' => $token, 'user' => ['id' => $userId, 'name' => $name, 'email' => $email]], 'Compte créé.', 201);
