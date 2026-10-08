<?php

declare(strict_types=1);

/**
 * Hilfsklasse zum Auslesen des eingehenden Requests.
 */
class Request
{
    /**
     * Liest den Request-Body und wandelt das JSON in ein Array um.
     * Ist der Body leer, kein gültiges JSON oder kein JSON-Objekt, wird direkt
     * mit 400 Bad Request geantwortet.
     *
     * @return array<string, mixed>
     */
    public static function getJsonBody(): array
    {
        $rawBody = file_get_contents('php://input');

        if ($rawBody === false || trim($rawBody) === '') {
            Response::error('Request body is missing', 400);
        }

        // Ohne "true" liefert json_decode ein Objekt (stdClass) für {...}.
        // So lässt sich ein JSON-Objekt sicher von einer Liste [...] oder
        // einem einzelnen Wert unterscheiden. null bedeutet ungültiges JSON.
        $data = json_decode($rawBody);

        if (!$data instanceof stdClass) {
            Response::error('Request body must be a valid JSON object', 400);
        }

        // Für die weitere Verarbeitung als assoziatives Array zurückgeben.
        return json_decode($rawBody, true);
    }

    /**
     * Sucht das JWT im Request.
     * Zuerst wird der Header "Authorization: Bearer <token>" geprüft, danach
     * das Cookie "token", das beim Login gesetzt wird.
     *
     * @return string|null Das Token oder null, wenn keines mitgeschickt wurde
     */
    public static function getToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        // Fallback: Apache gibt den Header nicht immer an $_SERVER weiter.
        if ($header === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    $header = $value;
                    break;
                }
            }
        }

        if (preg_match('/^Bearer\s+(\S+)$/i', trim($header), $matches) === 1) {
            return $matches[1];
        }

        if (!empty($_COOKIE['token']) && is_string($_COOKIE['token'])) {
            return $_COOKIE['token'];
        }

        return null;
    }
}