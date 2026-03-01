<?php
declare(strict_types=1);

// ============================================================
//  Smart Do — functions.php
//  UNE SEULE inclusion. Contient TOUT : config, DB, JWT,
//  helpers, validation, auth, accès listes.
// ============================================================

// ─── Constantes ─────────────────────────────────────────────
if (!defined('PAGINATION_DEFAULT')) {
    define('PAGINATION_DEFAULT', 20);
    define('PAGINATION_MAX',     100);
}

// ─── Headers HTTP ───────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── PDO ────────────────────────────────────────────────────
function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host    = 'localhost';
    $dbname  = 'smart_do';
    $user    = 'root';
    $pass    = '';
    $charset = 'utf8mb4';

    try {
        $pdo = new PDO(
            "mysql:host={$host};dbname={$dbname};charset={$charset}",
            $user, $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        http_response_code(503);
        echo json_encode(['status' => 'error', 'message' => 'Base de données indisponible.']);
        exit;
    }
    return $pdo;
}

// ─── Réponses JSON ───────────────────────────────────────────
function success(mixed $data = null, string $msg = '', int $code = 200): never {
    http_response_code($code);
    $r = ['status' => 'success'];
    if ($msg  !== '') $r['message'] = $msg;
    if ($data !== null) $r['data']  = $data;
    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function error(string $msg, int $code = 400, ?array $errors = null): never {
    http_response_code($code);
    $r = ['status' => 'error', 'message' => $msg];
    if ($errors) $r['errors'] = $errors;
    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ─── Body JSON ───────────────────────────────────────────────
function body(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// ─── Helpers ────────────────────────────────────────────────
function str_clean(?string $v): ?string {
    if ($v === null) return null;
    $v = trim($v);
    return $v === '' ? null : $v;
}

function require_field(array $data, string $field): mixed {
    if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
        error("Le champ '{$field}' est obligatoire.", 422);
    }
    return $data[$field];
}

function optional_field(array $data, string $field, mixed $default = null): mixed {
    return $data[$field] ?? $default;
}

// ─── JWT HS256 ───────────────────────────────────────────────
function jwt_secret(): string {
    return getenv('JWT_SECRET') ?: 'smart_do_secret_change_me_in_production';
}

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string|false {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

function jwt_generate(int $userId, string $email, string $name): string {
    $header  = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64url_encode(json_encode([
        'sub'   => $userId,
        'email' => $email,
        'name'  => $name,
        'iat'   => time(),
        'exp'   => time() + 86400,
    ]));
    $sig = base64url_encode(hash_hmac('sha256', "{$header}.{$payload}", jwt_secret(), true));
    return "{$header}.{$payload}.{$sig}";
}

function jwt_decode(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;

    [$header, $payload, $sig] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', "{$header}.{$payload}", jwt_secret(), true));

    if (!hash_equals($expected, $sig)) return null;

    $data = json_decode((string) base64url_decode($payload), true);
    if (!is_array($data)) return null;
    if (isset($data['exp']) && $data['exp'] < time()) return null;

    return $data;
}

// ─── Auth ────────────────────────────────────────────────────
function auth(): array {
    // Récupérer le token — toutes les méthodes XAMPP
    $token = '';

    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $token = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $k => $v) {
            if (strtolower($k) === 'authorization') { $token = $v; break; }
        }
    }

    if (!preg_match('/Bearer\s+(.+)/i', $token, $m)) {
        error('Token manquant.', 401);
    }

    $payload = jwt_decode(trim($m[1]));
    if (!$payload || empty($payload['sub'])) {
        error('Token invalide ou expiré.', 401);
    }

    return [
        'id'    => (int) $payload['sub'],
        'email' => $payload['email'] ?? '',
        'name'  => $payload['name']  ?? '',
    ];
}

// ─── Accès aux listes ────────────────────────────────────────
function access_level(int $listId, int $userId): string {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare("SELECT id FROM lists WHERE id = ? AND user_id = ?");
    $stmt->execute([$listId, $userId]);
    if ($stmt->fetchColumn()) return 'owner';

    $stmt = $pdo->prepare("SELECT permission FROM shared_lists WHERE list_id = ? AND shared_with_user_id = ?");
    $stmt->execute([$listId, $userId]);
    $p = $stmt->fetchColumn();
    return $p !== false ? (string)$p : 'none';
}

function need_read(int $listId, int $userId): string {
    $level = access_level($listId, $userId);
    if ($level === 'none') error('Liste introuvable ou accès refusé.', 404);
    return $level;
}

function need_write(int $listId, int $userId): string {
    $level = access_level($listId, $userId);
    if ($level === 'none')  error('Liste introuvable ou accès refusé.', 404);
    if ($level === 'read')  error('Permission insuffisante.', 403);
    return $level;
}

function need_owner(int $listId, int $userId): void {
    $level = access_level($listId, $userId);
    if ($level !== 'owner') error('Seul le propriétaire peut effectuer cette action.', 403);
}

// ─── Format liste ────────────────────────────────────────────
function format_list(array $r): array {
    $t = (int)($r['total_items']     ?? 0);
    $c = (int)($r['completed_items'] ?? 0);
    return [
        'id'              => (int)$r['id'],
        'title'           => $r['title'],
        'type'            => $r['type'],
        'description'     => $r['description'],
        'status'          => $r['status'],
        'due_date'        => $r['due_date'],
        'due_time'        => $r['due_time'],
        'created_at'      => $r['created_at'],
        'total_items'     => $t,
        'completed_items' => $c,
        'completion_rate' => $t > 0 ? round($c / $t * 100, 1) : 0,
    ];
}

// ─── Mise à jour statut liste ────────────────────────────────
function sync_list_status(int $listId): void {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total, SUM(is_done) as done
        FROM list_items WHERE list_id = ?
    ");
    $stmt->execute([$listId]);
    $r      = $stmt->fetch();
    $total  = (int)$r['total'];
    $done   = (int)$r['done'];
    $status = ($total > 0 && $done === $total) ? 'completed' : 'active';
    $pdo->prepare("UPDATE lists SET status = ? WHERE id = ?")->execute([$status, $listId]);
}
