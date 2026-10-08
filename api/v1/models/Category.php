<?php

declare(strict_types=1);

/**
 * Datenbankzugriff auf die Tabelle category.
 * Alle Abfragen mit Werten von aussen laufen über Prepared Statements.
 * Die Platzhalter "?" werden mit bind_param() befüllt, die Typen stehen
 * im ersten Parameter: i = Integer, s = String.
 */
class Category
{
    /**
     * @param mysqli $db Offene Datenbankverbindung
     */
    public function __construct(private mysqli $db)
    {
    }

    /**
     * Liefert alle Kategorien, sortiert nach ID.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        // Keine Benutzereingaben im SQL, darum ist hier kein Prepared Statement nötig.
        $result = $this->db->query(
            'SELECT category_id, active, name FROM category ORDER BY category_id'
        );

        return array_map([$this, 'format'], $result->fetch_all(MYSQLI_ASSOC));
    }

    /**
     * Liefert eine Kategorie oder null, wenn es sie nicht gibt.
     * @param int $id category_id
     * @return array<string, mixed>|null
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT category_id, active, name FROM category WHERE category_id = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? $this->format($row) : null;
    }

    /**
     * Prüft, ob eine Kategorie existiert (z. B. bevor ein Produkt sie verwendet).
     *
     * @param int $id category_id
     * @return bool
     */
    public function exists(int $id): bool
    {
        return $this->getById($id) !== null;
    }

    /**
     * Legt eine neue Kategorie an.
     * @param int    $active 0 oder 1
     * @param string $name   Name der Kategorie
     * @return int Die neue category_id
     */
    public function create(int $active, string $name): int
    {
        $stmt = $this->db->prepare('INSERT INTO category (active, name) VALUES (?, ?)');
        $stmt->bind_param('is', $active, $name);
        $stmt->execute();
        $stmt->close();

        return $this->db->insert_id;
    }

    /**
     * Ändert eine Kategorie. Nur die übergebenen Felder werden angepasst (PATCH).
     * @param int                  $id     category_id
     * @param array<string, mixed> $fields Neue Werte, erlaubt sind active und name
     */
    public function update(int $id, array $fields): void
    {
        // Die Spaltennamen kommen nicht vom Benutzer, sondern aus dieser festen
        // Liste. Die Werte selbst gehen weiterhin über Platzhalter.
        $allowedColumns = ['active' => 'i', 'name' => 's'];

        $setParts = [];
        $types = '';
        $values = [];

        foreach ($allowedColumns as $column => $type) {
            if (array_key_exists($column, $fields)) {
                $setParts[] = $column . ' = ?';
                $types .= $type;
                $values[] = $fields[$column];
            }
        }

        if ($setParts === []) {
            return;
        }

        $types .= 'i';
        $values[] = $id;

        $stmt = $this->db->prepare(
            'UPDATE category SET ' . implode(', ', $setParts) . ' WHERE category_id = ?'
        );
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Löscht eine Kategorie. Zugeordnete Produkte erhalten id_category = NULL
     * (geregelt über ON DELETE SET NULL in der Datenbank).
     * @param int $id category_id
     * @return bool true, wenn eine Zeile gelöscht wurde
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM category WHERE category_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();

        $deleted = $stmt->affected_rows > 0;
        $stmt->close();

        return $deleted;
    }

    /**
     * Wandelt eine Datenbankzeile in die Struktur der API um.
     * mysqli liefert Zahlen teilweise als Text. Hier werden sie in echte
     * Integer umgewandelt, damit das JSON dieselben Typen hat wie der Request.
     * @param array<string, mixed> $row Zeile aus der Datenbank
     * @return array<string, mixed>
     */
    private function format(array $row): array
    {
        return [
            'category_id' => (int) $row['category_id'],
            'active'      => (int) $row['active'],
            'name'        => $row['name'],
        ];
    }
}