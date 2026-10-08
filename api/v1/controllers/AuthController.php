<?php

declare(strict_types=1);

/**
 * Zuständig für das Login: POST /authenticate
 * Dies ist der einzige Endpoint, der ohne Token aufgerufen werden darf.
 */
class AuthController
{
    /**
     * @param User                 $users     Model für die Tabelle user
     * @param array<string, mixed> $jwtConfig Teil "jwt" aus config.php
     */
    public function __construct(
        private User $users,
        private array $jwtConfig
    ) {
    }

    /**
     * Prüft Benutzername und Passwort und gibt bei Erfolg ein JWT zurück.
     * Das Token kommt im JSON-Body zurück und wird zusätzlich als HttpOnly-Cookie
     * gesetzt. Bruno (und später ein Browser-Frontend) schickt das Cookie bei den
     * nächsten Requests automatisch mit.
     */
    public function authenticate(): void
    {
        $data = Request::getJsonBody();

        [$credentials, $errors] = Validator::credentials($data);
        if ($errors !== []) {
            Response::error('Invalid request data', 400, $errors);
        }

        $user = $this->users->findByUsername($credentials['username']);

        // password_verify vergleicht das eingegebene Passwort mit dem Hash aus der DB.
        // Bewusst dieselbe Meldung für "Benutzer unbekannt" und "Passwort falsch",
        // damit man nicht herausfinden kann, welche Benutzernamen existieren.
        if ($user === null || !password_verify($credentials['password'], $user['password_hash'])) {
            Response::error('Invalid username or password', 401);
        }

        $issuedAt = time();
        $expiresAt = $issuedAt + (int) $this->jwtConfig['lifetime'];

        $token = Jwt::create(
            [
                'iss'      => $this->jwtConfig['issuer'],  // wer das Token ausgestellt hat
                'iat'      => $issuedAt,                    // wann es ausgestellt wurde
                'exp'      => $expiresAt,                   // wann es abläuft
                'sub'      => (string) $user['user_id'],    // für wen es gilt
                'username' => $user['username'],
            ],
            $this->jwtConfig['secret']
        );

        // HttpOnly: JavaScript kann das Cookie nicht auslesen (Schutz vor XSS).
        // SameSite=Strict: Das Cookie wird nicht bei Requests von fremden Seiten mitgeschickt.
        setcookie('token', $token, [
            'expires'  => $expiresAt,
            'path'     => '/api/v1',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        Response::json([
            'token'      => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int) $this->jwtConfig['lifetime'],
        ]);
    }
}