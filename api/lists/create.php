<?php
declare(strict_types=1);
require_once __DIR__ . '/../../api/functions.php';

$me    = auth();
$b     = body();
$title = str_clean($b['title'] ?? '');
$type  = $b['type'] ?? '';

if (!$title)                                         error('title est obligatoire.', 422);
if (!in_array($type, ['simple','checklist'], true))  error('type doit être simple ou checklist.', 422);

$desc     = str_clean($b['description'] ?? null);
$due_date = str_clean($b['due_date']    ?? null);
$due_time = str_clean($b['due_time']    ?? null);

if ($due_time && !$due_date) error('due_date requis quand due_time est fourni.', 422);

$pdo = get_pdo();
$pdo->prepare("
    INSERT INTO lists (user_id, title, type, description, due_date, due_time)
    VALUES (?, ?, ?, ?, ?, ?)
")->execute([$me['id'], $title, $type, $desc, $due_date, $due_time]);

$id   = (int)$pdo->lastInsertId();
$stmt = $pdo->prepare("SELECT l.*, 0 AS total_items, 0 AS completed_items FROM lists l WHERE l.id = ?");
$stmt->execute([$id]);
success(['list' => format_list($stmt->fetch())], 'Liste créée.', 201);
