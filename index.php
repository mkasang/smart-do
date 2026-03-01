<?php
declare(strict_types=1);
require_once __DIR__ . '/api/functions.php';

// ─── Méthode et path ─────────────────────────────────────────
$method = strtoupper($_SERVER['REQUEST_METHOD']);

$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
if ($base && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base));
}
$path = '/' . trim($uri, '/');

// ─── Routes ──────────────────────────────────────────────────
$routes = [
    ['POST',   '/api/register',                    'auth/register'],
    ['POST',   '/api/login',                       'auth/login'],
    ['GET',    '/api/profile',                     'auth/profile'],
    ['GET',    '/api/users/search',                'auth/search'],

    ['GET',    '/api/lists',                       'lists/index'],
    ['POST',   '/api/lists',                       'lists/create'],
    ['GET',    '/api/lists/shared',                'sharing/shared_with_me'],
    ['GET',    '/api/lists/{id}',                  'lists/show'],
    ['PUT',    '/api/lists/{id}',                  'lists/update'],
    ['DELETE', '/api/lists/{id}',                  'lists/delete'],
    ['POST',   '/api/lists/{id}/duplicate',        'lists/duplicate'],

    ['POST',   '/api/items',                       'items/create'],
    ['PUT',    '/api/items/{id}',                  'items/update'],
    ['PATCH',  '/api/items/{id}/toggle',           'items/toggle'],
    ['DELETE', '/api/items/{id}',                  'items/delete'],

    ['POST',   '/api/lists/{id}/share',            'sharing/share'],
    ['DELETE', '/api/lists/{id}/share/{uid}',      'sharing/unshare'],

    ['GET',    '/api/calendar',                    'calendar/index'],
    ['GET',    '/api/stats',                       'stats/index'],
];

// ─── Dispatch ────────────────────────────────────────────────
$params  = [];
$handler = null;

foreach ($routes as [$rMethod, $rPath, $rHandler]) {
    if ($method !== $rMethod) continue;

    // Convertir {id} en regex
    $pattern = preg_replace('/\{[^}]+\}/', '(\d+)', $rPath);
    $pattern = '#^' . $pattern . '$#';

    if (preg_match($pattern, $path, $m)) {
        $params  = array_slice($m, 1);
        $handler = $rHandler;
        break;
    }
}

if (!$handler) {
    // Vérifier si la route existe avec une autre méthode
    foreach ($routes as [$rMethod, $rPath]) {
        $pattern = '#^' . preg_replace('/\{[^}]+\}/', '(\d+)', $rPath) . '$#';
        if (preg_match($pattern, $path)) {
            error("Méthode {$method} non autorisée.", 405);
        }
    }
    error("Route introuvable. [{$method}] {$path}", 404);
}

$file = __DIR__ . '/api/' . $handler . '.php';
if (!file_exists($file)) {
    error('Handler introuvable : ' . $handler, 500);
}

require $file;
