<?php

/**
 * Datenbankverbindung JWT und Basis-Pfad
 * Die Datei gibt ein Array zurück: $config = require 'config.php';
 */

// Be strict about the types of values I pass to functions.
declare(strict_types=1);

$config = [
    'api' => [
        // Basis-Pfad und Version der API (entspricht $app->setBasePath('/api/v1') in Slim).
        'base_path' => '/api/v1',
    ],
    'db' => [
        'host'     => 'localhost',
        'user'     => 'root',
        'password' => '',
        'name'     => 'shop_api',
        'port'     => 3306,
    ],
    'jwt' => [
        // Nur für die lokale Entwicklung.
        'secret'   => 'dev-only-secret-change-me-in-config-local-php-uek295',
        'issuer'   => 'http://localhost/api/v1',
        'lifetime' => 3600, // Tokens Gültigkeit  in Sekunden (1 Stunde)
    ],
];

$localConfigFile = __DIR__ . '/config.local.php';

if (file_exists($localConfigFile)) {
    //überschreibt nur die Werte, die lokal gesetzt sind.
    $config = array_replace_recursive($config, require $localConfigFile);
}

return $config;