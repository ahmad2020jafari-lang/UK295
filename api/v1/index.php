<?php

/**
 * Einstiegspunkt der API.
 *
 * Die .htaccess in diesem Ordner leitet jeden Request unter /api/v1/ hierher.
 * Diese Datei lädt alle Klassen, richtet die Fehlerbehandlung ein, baut die
 * Datenbankverbindung auf und übergibt den Request an das Routing.
 */

declare(strict_types=1);

// Fehler nie als HTML ausgeben, das würde das JSON kaputt machen.
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/helpers/Request.php';
require_once __DIR__ . '/helpers/Validator.php';
require_once __DIR__ . '/helpers/Jwt.php';

require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Category.php';
require_once __DIR__ . '/models/Product.php';

require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/CategoryController.php';
require_once __DIR__ . '/controllers/ProductController.php';

require_once __DIR__ . '/routes.php';
require_once __DIR__ . '/../../config/database.php';

// Warnungen als Exceptions behandeln, statt still weiterzulaufen.
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Fängt alle nicht behandelten Fehler ab (z. B. Datenbank nicht erreichbar).
// Details gehen ins Log, der Client bekommt nur eine allgemeine Meldung.
set_exception_handler(function (Throwable $exception): void {
    error_log('[shop-api] ' . $exception);
    Response::error('Internal server error', 500);
});

header('X-Content-Type-Options: nosniff');

$config = require __DIR__ . '/../../config/config.php';
$db = getDatabaseConnection($config['db']);

// Der Basis-Pfad mit der Versionsnummer (/api/v1) wird
// abgeschnitten, damit die Routen in routes.php kurz bleiben.
// Aus "/api/v1/category/1?x=1" wird "/category/1".
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$basePath = rtrim($config['api']['base_path'], '/');

if (str_starts_with($requestPath, $basePath . '/') || $requestPath === $basePath) {
    $requestPath = substr($requestPath, strlen($basePath));
}

// Ein abschliessender Schrägstrich wird ignoriert: /categories/ = /categories
$requestPath = '/' . trim($requestPath, '/');

dispatch($_SERVER['REQUEST_METHOD'], $requestPath, $db, $config);