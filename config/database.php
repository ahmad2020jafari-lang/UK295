<?php

// Be strict about the types of values I pass to functions.
declare(strict_types=1);

/**
 * Liefert die Datenbankverbindung (mysqli).
 * Die Verbindung wird beim ersten Aufruf aufgebaut und danach wiederverwendet,
 * damit pro Request nur eine Verbindung offen ist.
 *
 * @param array<string, mixed> $dbConfig Teil "db" aus config.php
 * @return mysqli
 */
function getDatabaseConnection(array $dbConfig): mysqli
{
    static $connection = null;

    if ($connection instanceof mysqli) {
        return $connection;
    }

    // Fehler von mysqli als Exceptions werfen statt nur Warnungen auszugeben.
    // So landen Datenbankfehler im zentralen Fehler-Handler in index.php.
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $connection = new mysqli(
        $dbConfig['host'],
        $dbConfig['user'],
        $dbConfig['password'],
        $dbConfig['name'],
        (int) $dbConfig['port']
    );

    // utf8mb4, damit Umlaute und Sonderzeichen korrekt übertragen werden.
    $connection->set_charset('utf8mb4');

    return $connection;
}