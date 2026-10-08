<?php

declare(strict_types=1);

/**
 * Erstellt und prüft JSON Web Tokens (JWT) mit dem Verfahren HS256.
 *
 * Ein JWT besteht aus drei Teilen, getrennt durch Punkte:
 *   header.payload.signature
 *
 * - header:    welcher Algorithmus verwendet wird ({"alg":"HS256","typ":"JWT"})
 * - payload:   die Daten (Claims), z. B. Benutzername und Ablaufzeit
 * - signature: HMAC-SHA256 über "header.payload" mit dem geheimen Schlüssel
 *
 * Header und Payload sind nur Base64URL-codiert, also für jeden lesbar.
 * Die Sicherheit kommt von der Signatur: Ohne das Secret kann niemand ein
 * Token verändern, ohne dass die Prüfung fehlschlägt.
 */
class Jwt
{
    private const ALGORITHM = 'HS256';

    /**
     * Erstellt ein signiertes Token.
     *
     * @param array<string, mixed> $payload Claims, die ins Token geschrieben werden
     * @param string               $secret  Geheimer Schlüssel aus der Konfiguration
     * @return string Das fertige JWT
     */
    public static function create(array $payload, string $secret): string
    {
        $header = ['alg' => self::ALGORITHM, 'typ' => 'JWT'];

        $encodedHeader = self::base64UrlEncode(json_encode($header));
        $encodedPayload = self::base64UrlEncode(json_encode($payload));

        $signature = self::sign($encodedHeader . '.' . $encodedPayload, $secret);

        return $encodedHeader . '.' . $encodedPayload . '.' . $signature;
    }

    /**
     * Prüft ein Token und gibt bei Erfolg den Payload zurück.
     * Geprüft werden: Aufbau, Algorithmus, Signatur und Ablaufzeit (exp).
     *
     * @param string $token  Das Token aus dem Request
     * @param string $secret Geheimer Schlüssel aus der Konfiguration
     * @return array<string, mixed>|null Payload oder null, wenn das Token ungültig ist
     */
    public static function verify(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $signature] = $parts;

        // Die Signatur wird neu berechnet und mit der mitgeschickten verglichen.
        // hash_equals vergleicht in konstanter Zeit und verhindert so
        // Timing-Angriffe.
        $expectedSignature = self::sign($encodedHeader . '.' . $encodedPayload, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $header = json_decode(self::base64UrlDecode($encodedHeader), true);
        $payload = json_decode(self::base64UrlDecode($encodedPayload), true);

        // Nur HS256 wird akzeptiert. So kann ein Angreifer nicht z. B. "none"
        // als Algorithmus angeben.
        if (!is_array($header) || ($header['alg'] ?? '') !== self::ALGORITHM) {
            return null;
        }

        if (!is_array($payload) || !isset($payload['exp']) || !is_int($payload['exp'])) {
            return null;
        }

        if ($payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Berechnet die HMAC-SHA256-Signatur.
     *
     * @param string $data   "header.payload"
     * @param string $secret Geheimer Schlüssel
     * @return string Base64URL-codierte Signatur
     */
    private static function sign(string $data, string $secret): string
    {
        return self::base64UrlEncode(hash_hmac('sha256', $data, $secret, true));
    }

    /**
     * Base64URL ist Base64 ohne die Zeichen "+", "/" und "=", die in URLs Probleme machen.
     *
     * @param string $data Rohdaten
     * @return string Codierter Text
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Gegenstück zu base64UrlEncode().
     *
     * @param string $data Base64URL-codierter Text
     * @return string Rohdaten (leer bei ungültiger Eingabe)
     */
    private static function base64UrlDecode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}