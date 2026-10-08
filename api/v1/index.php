<?php

/**
* Route - Endpoint
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/helpers/Request.php';
require_once __DIR__ . '/helpers/Validator.php';
require_once __DIR__ . '/helpers/Jwt.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/../../config/database.php';

$config = require __DIR__ . '/../../config/config.php';
$db = getDatabaseConnection($config['db']);

// Basis-Pfad abschneiden: aus "/api/v1/authenticate" wird "/authenticate"
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$path = '/' . trim(substr($path, strlen($config['api']['base_path'])), '/');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $path === '/authenticate') {
    $controller = new AuthController(new User($db), $config['jwt']);
    $controller->authenticate();
}

Response::error('Endpoint not found', 404);