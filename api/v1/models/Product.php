<?php

declare(strict_types=1);

/**
 * Datenbankzugriff auf die Tabelle product.
 * Ein Produkt wird über seine SKU (Artikelnummer) angesprochen, z. B.
 * /product/12345678. product_id ist die interne ID der Datenbank.
 */
class Product
{
    /** Spalten, die in jeder Antwort enthalten sind (Reihenfolge = JSON-Reihenfolge). */
    private const COLUMNS = 'product_id, sku, active, id_category, name, image, description, price, stock';

    /**
     * @param mysqli $db Offene Datenbankverbindung
     */
    public function __construct(private mysqli $db)
    {
    }

    /**
     * Liefert alle Produkte, sortiert nach ID.
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $result = $this->db->query(
            'SELECT ' . self::COLUMNS . ' FROM product ORDER BY product_id'
        );

        return array_map([$this, 'format'], $result->fetch_all(MYSQLI_ASSOC));
    }

    /**
     * Liefert ein Produkt anhand der SKU oder null.
     * @param string $sku Artikelnummer aus der URL
     * @return array<string, mixed>|null
     */
    public function getBySku(string $sku): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ' . self::COLUMNS . ' FROM product WHERE sku = ?'
        );
        $stmt->bind_param('s', $sku);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? $this->format($row) : null;
    }

    /**
     * Legt ein Produkt an oder ersetzt es vollständig (Verhalten von PUT).
     * @param string               $sku  Artikelnummer aus der URL
     * @param array<string, mixed> $data Validierte Felder aus dem Request-Body
     * @return bool true, wenn das Produkt neu erstellt wurde, false bei einem Update
     */
    public function createOrUpdate(string $sku, array $data): bool
    {
        $isNew = $this->getBySku($sku) === null;

        // Der Preis wird als Text mit genau zwei Nachkommastellen übergeben.
        // So rundet MySQL nichts und es gibt keine float-Ungenauigkeiten.
        $price = number_format((float) $data['price'], 2, '.', '');
        $active = $data['active'];
        $idCategory = $data['id_category']; // darf null sein, bind_param schreibt dann NULL
        $name = $data['name'];
        $image = $data['image'];
        $description = $data['description'];
        $stock = $data['stock'];

        if ($isNew) {
            $stmt = $this->db->prepare(
                'INSERT INTO product (sku, active, id_category, name, image, description, price, stock)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'siissssi',
                $sku,
                $active,
                $idCategory,
                $name,
                $image,
                $description,
                $price,
                $stock
            );
        } else {
            $stmt = $this->db->prepare(
                'UPDATE product
                 SET active = ?, id_category = ?, name = ?, image = ?, description = ?, price = ?, stock = ?
                 WHERE sku = ?'
            );
            $stmt->bind_param(
                'iissssis',
                $active,
                $idCategory,
                $name,
                $image,
                $description,
                $price,
                $stock,
                $sku
            );
        }

        $stmt->execute();
        $stmt->close();

        return $isNew;
    }

    /**
     * Löscht ein Produkt anhand der SKU.
     * @param string $sku Artikelnummer aus der URL
     * @return bool true, wenn eine Zeile gelöscht wurde
     */
    public function delete(string $sku): bool
    {
        $stmt = $this->db->prepare('DELETE FROM product WHERE sku = ?');
        $stmt->bind_param('s', $sku);
        $stmt->execute();

        $deleted = $stmt->affected_rows > 0;
        $stmt->close();

        return $deleted;
    }

    /**
     * Wandelt eine Datenbankzeile in die Struktur der API um (richtige JSON-Typen).
     * @param array<string, mixed> $row Zeile aus der Datenbank
     * @return array<string, mixed>
     */
    private function format(array $row): array
    {
        return [
            'product_id'  => (int) $row['product_id'],
            'sku'         => $row['sku'],
            'active'      => (int) $row['active'],
            'id_category' => $row['id_category'] === null ? null : (int) $row['id_category'],
            'name'        => $row['name'],
            'image'       => $row['image'],
            'description' => $row['description'],
            'price'       => (float) $row['price'],
            'stock'       => (int) $row['stock'],
        ];
    }
}