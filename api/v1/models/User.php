<?php

declare(strict_types=1);

/**
 * Datenbankzugriff auf die Tabelle user.
 */
class User
{
    /**
     * @param mysqli $db Offene Datenbankverbindung
     */
    public function __construct(private mysqli $db)
    {
    }

    /**
     * Sucht einen Benutzer anhand des Benutzernamens.
     * @param string $username Benutzername aus dem Login-Request
     * @return array<string, mixed>|null Benutzer inkl. password_hash oder null
     */
    public function findByUsername(string $username): ?array
    {
        // Prepared Statement: Der Benutzername wird getrennt vom SQL-Befehl
        // an die Datenbank geschickt und kann darum kein SQL einschleusen.
        $stmt = $this->db->prepare(
            'SELECT user_id, username, password_hash FROM user WHERE username = ?'
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $user ?: null;
    }
}