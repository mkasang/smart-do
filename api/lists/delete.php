<?php
declare(strict_types=1);
require_once __DIR__ . '/../../api/functions.php';

$me     = auth();
$listId = (int)($params[0] ?? 0);
if (!$listId) error('ID invalide.', 400);

need_owner($listId, $me['id']);
get_pdo()->prepare("DELETE FROM lists WHERE id = ?")->execute([$listId]);
success(null, 'Liste supprimée.');
