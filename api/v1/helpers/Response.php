<?php

declare(strict_types=1);

/**
 * Hilfsklasse für einheitliche JSON-Antworten.
 *
 * Alle Controller antworten über diese Klasse. Dadurch haben alle Antworten
 * denselben Content-Type und Fehler immer dieselbe Struktur:
 * {"error": "...", "details": {...}}
 */
class Response
{
    /**
     * Sendet Daten als JSON mit dem passenden HTTP-Statuscode und beendet den Request.
     *
     * @param mixed $data       Daten, die als JSON ausgegeben werden
     * @param int   $statusCode HTTP-Statuscode, z. B. 200 oder 201
     * @return void Beendet das Skript (exit)
     */
    public static function json(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        // JSON_UNESCAPED_UNICODE: Umlaute bleiben lesbar (ä statt \u00e4)
        // JSON_UNESCAPED_SLASHES: URLs bleiben lesbar (https:// statt https:\/\/)
        // JSON_PRESERVE_ZERO_FRACTION: 10.0 bleibt 10.0 und wird nicht zu 10
        echo json_encode(
            $data,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PRESERVE_ZERO_FRACTION
        );
        exit;
    }

    /**
     * Sendet eine Fehlermeldung im einheitlichen Fehlerformat.
     *
     * @param string                $message    Kurze Beschreibung des Fehlers
     * @param int                   $statusCode HTTP-Statuscode, z. B. 400 oder 404
     * @param array<string, string> $details    Fehler pro Feld
     * @return void Beendet das Skript (exit)
     */
    public static function error(string $message, int $statusCode, array $details = []): void
    {
        $body = ['error' => $message];

        if ($details !== []) {
            $body['details'] = $details;
        }

        self::json($body, $statusCode);
    }

    /**
     * Antwort ohne Inhalt, z. B. nach einem erfolgreichen DELETE.
     *
     * @return void Beendet das Skript (exit)
     */
    public static function noContent(): void
    {
        http_response_code(204);
        exit;
    }
}