<?php
declare(strict_types=1);

/**
 * Validierung ist gemäss den Checkliste für Backend Applikations
 * Jede öffentliche Methode gibt zwei Dinge zurück:
 *   [0] die bereinigten Daten (z. B. getrimmte Texte, active als 0 oder 1)
 *   [1] die Fehler als ['feldname' => 'Beschreibung']
 * Nur wenn die Fehlerliste leer ist, dürfen die bereinigten Daten verwendet werden.
 *
 * Die Regeln leiten sich aus dem Datenmodell ab (Datentypen und Längen der
 * Spalten). Zahlen werden streng geprüft: "stock": "3" (Text) ist keine
 * gültige Zahl, "stock": 3 schon.
 */
class Validator
{
    private const NAME_MAX_LENGTH = 500;
    private const IMAGE_MAX_LENGTH = 1000;
    private const SKU_MAX_LENGTH = 100;
    private const TEXT_MAX_BYTES = 65535;      // Maximum einer TEXT-Spalte
    private const INT_MAX = 2147483647;        // Maximum einer INT-Spalte
    private const PRICE_MAX = 999999999999.99; // Grenze, bis zu der float genau rechnet

    /**
     * Prüft die Daten einer Kategorie.
     * @param array<string, mixed> $data    Request-Body
     * @param bool                 $partial true bei PATCH: Nur mitgeschickte Felder prüfen
     * @return array{0: array<string, mixed>, 1: array<string, string>} [bereinigte Daten, Fehler]
     */
    public static function category(array $data, bool $partial = false): array
    {
        $clean = [];
        $errors = [];

        if ($partial && !array_key_exists('active', $data) && !array_key_exists('name', $data)) {
            return [[], ['body' => 'At least one of the fields active or name is required']];
        }

        if (!$partial || array_key_exists('active', $data)) {
            self::checkActive($data, $clean, $errors);
        }

        if (!$partial || array_key_exists('name', $data)) {
            self::checkName($data, $clean, $errors);
        }

        return [$clean, $errors];
    }

    /**
     * Prüft die Daten eines Produkts (PUT: alle Felder sind Pflicht).
     *
     * @param array<string, mixed> $data Request-Body
     * @return array{0: array<string, mixed>, 1: array<string, string>} [bereinigte Daten, Fehler]
     */
    public static function product(array $data): array
    {
        $clean = [];
        $errors = [];

        self::checkActive($data, $clean, $errors);
        self::checkName($data, $clean, $errors);

        // id_category: Pflichtfeld, aber null ist erlaubt (Produkt ohne Kategorie).
        if (!array_key_exists('id_category', $data)) {
            $errors['id_category'] = 'Field is required (use null for no category)';
        } elseif ($data['id_category'] === null) {
            $clean['id_category'] = null;
        } elseif (!is_int($data['id_category']) || $data['id_category'] < 1 || $data['id_category'] > self::INT_MAX) {
            $errors['id_category'] = 'Must be a positive integer or null';
        } else {
            $clean['id_category'] = $data['id_category'];
        }

        // image: leer erlaubt, sonst eine URL mit http oder https.
        $image = self::requireString($data, 'image', $errors);
        if ($image !== null) {
            if (mb_strlen($image) > self::IMAGE_MAX_LENGTH) {
                $errors['image'] = 'Must not be longer than ' . self::IMAGE_MAX_LENGTH . ' characters';
            } elseif ($image !== '' && !self::isHttpUrl($image)) {
                $errors['image'] = 'Must be a valid http or https URL or an empty string';
            } else {
                $clean['image'] = $image;
            }
        }

        // DesCipt darf leer sein und Zeilenumbrüche enthalten, aber kein HTML.
        $description = self::requireString($data, 'description', $errors);
        if ($description !== null) {
            if (strlen($description) > self::TEXT_MAX_BYTES) {
                $errors['description'] = 'Is too long';
            } elseif (!self::hasOnlyAllowedCharacters($description, true)) {
                $errors['description'] = 'Must not contain HTML tags or control characters';
            } else {
                $clean['description'] = $description;
            }
        }

        // price: Zahl >= 0 mit höchstens zwei Nachkommastellen (DECIMAL(65,2)).
        if (!array_key_exists('price', $data)) {
            $errors['price'] = 'Field is required';
        } elseif (!is_int($data['price']) && !is_float($data['price'])) {
            $errors['price'] = 'Must be a number';
        } elseif ($data['price'] < 0 || $data['price'] > self::PRICE_MAX) {
            $errors['price'] = 'Must be between 0 and ' . self::PRICE_MAX;
        } elseif (!self::hasMaxTwoDecimals($data['price'])) {
            $errors['price'] = 'Must not have more than two decimal places';
        } else {
            $clean['price'] = $data['price'];
        }

        // stock: ganze Zahl zwischen 0 und dem Maximum einer INT-Spalte.
        if (!array_key_exists('stock', $data)) {
            $errors['stock'] = 'Field is required';
        } elseif (!is_int($data['stock']) || $data['stock'] < 0 || $data['stock'] > self::INT_MAX) {
            $errors['stock'] = 'Must be an integer between 0 and ' . self::INT_MAX;
        } else {
            $clean['stock'] = $data['stock'];
        }

        return [$clean, $errors];
    }

    /**
     * Prüft die Login-Daten.
     * Der Benutzername wird getrimmt. Das Passwort bewusst nicht: Leerzeichen
     * am Anfang oder Ende können Teil eines Passworts sein.
     * @param array<string, mixed> $data Request-Body
     * @return array{0: array<string, string>, 1: array<string, string>} [bereinigte Daten, Fehler]
     */
    public static function credentials(array $data): array
    {
        $clean = [];
        $errors = [];

        $username = self::requireString($data, 'username', $errors);
        if ($username !== null && $username === '') {
            $errors['username'] = 'Must not be empty';
        } elseif ($username !== null) {
            $clean['username'] = $username;
        }

        if (!isset($data['password']) || !is_string($data['password']) || $data['password'] === '') {
            $errors['password'] = 'Field is required and must be a non-empty string';
        } else {
            $clean['password'] = $data['password'];
        }

        return [$clean, $errors];
    }

    /**
     * Prüft eine ID aus der URL, z. B. "5" in /category/5.
     * @param string $value Wert aus der URL
     * @return int|null Die ID als Integer oder null, wenn sie ungültig ist
     */
    public static function id(string $value): ?int
    {
        // Nur Ziffern, keine führende 0 und nicht grösser als eine INT-Spalte.
        if (preg_match('/^[1-9][0-9]{0,9}$/', $value) !== 1 || (int) $value > self::INT_MAX) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Prüft eine SKU (Artikelnummer) aus der URL, z. B. "12345678".
     * Erlaubt sind Buchstaben, Ziffern, Punkt, Binde- und Unterstrich,
     * 1 bis 100 Zeichen (VARCHAR(100)). Alles andere gilt als unerlaubtes Zeichen.
     *
     * @param string $value Wert aus der URL
     * @return bool true, wenn die SKU gültig ist
     */
    public static function sku(string $value): bool
    {
        $pattern = '/^[A-Za-z0-9._-]{1,' . self::SKU_MAX_LENGTH . '}$/';

        return preg_match($pattern, $value) === 1;
    }
    /**
     * Wandelt einen booleschen Wert in 0 oder 1 um.
     * Erlaubt sind laut Checkliste true, "true", 1 sowie false, "false", 0.
     * Der Wert wird nicht direkt übernommen, sondern über if/elseif entschieden.
     * @param mixed $value Wert aus dem Request
     * @return int|null 1, 0 oder null, wenn der Wert ungültig ist
     */
    public static function toBoolInt(mixed $value): ?int
    {
        if ($value === true || $value === 'true' || $value === 1) {
            return 1;
        } elseif ($value === false || $value === 'false' || $value === 0) {
            return 0;
        }

        return null;
    }

    /**
     * active: boolescher Wert, wird als 0 oder 1 gespeichert (TINYINT(1)).
     * @param array<string, mixed>  $data   Request-Body
     * @param array<string, mixed>  $clean  Bereinigte Daten, wird ergänzt
     * @param array<string, string> $errors Fehlerliste, wird ergänzt
     */
    private static function checkActive(array $data, array &$clean, array &$errors): void
    {
        if (!array_key_exists('active', $data)) {
            $errors['active'] = 'Field is required';
            return;
        }

        $active = self::toBoolInt($data['active']);

        if ($active === null) {
            $errors['active'] = 'Must be 0, 1, true or false';
        } else {
            $clean['active'] = $active;
        }
    }

    /**
     * name: getrimmt, 1 bis 500 Zeichen, kein HTML und keine Steuerzeichen.
     * @param array<string, mixed>  $data   Request-Body
     * @param array<string, mixed>  $clean  Bereinigte Daten, wird ergänzt
     * @param array<string, string> $errors Fehlerliste, wird ergänzt
     */
    private static function checkName(array $data, array &$clean, array &$errors): void
    {
        $name = self::requireString($data, 'name', $errors);

        if ($name === null) {
            return;
        }

        if ($name === '') {
            $errors['name'] = 'Must not be empty';
        } elseif (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            $errors['name'] = 'Must not be longer than ' . self::NAME_MAX_LENGTH . ' characters';
        } elseif (!self::hasOnlyAllowedCharacters($name, false)) {
            $errors['name'] = 'Must not contain HTML tags, line breaks or control characters';
        } else {
            $clean['name'] = $name;
        }
    }

    /**
     * Prüft, ob ein Pflichtfeld vorhanden und ein String ist, und trimmt ihn.
     * Trimmen kommt laut Checkliste zuerst. So zählt "   " als leer und
     * Leerzeichen am Rand landen nicht in der Datenbank.
     * @param array<string, mixed>  $data   Request-Body
     * @param string                $field  Name des Feldes
     * @param array<string, string> $errors Fehlerliste, wird ergänzt
     * @return string|null Getrimmter Text oder null, wenn ein Fehler eingetragen wurde
     */
    private static function requireString(array $data, string $field, array &$errors): ?string
    {
        if (!array_key_exists($field, $data)) {
            $errors[$field] = 'Field is required';
            return null;
        }

        if (!is_string($data[$field])) {
            $errors[$field] = 'Must be a string';
            return null;
        }

        return trim($data[$field]);
    }

    /**
     * Sucht nach unerlaubten Zeichen: HTML-Tags und Steuerzeichen.
     * HTML wird abgelehnt, damit später im Frontend kein Code eingeschleust
     * werden kann (XSS). Steuerzeichen sind unsichtbare Zeichen wie NULL-Bytes.
     * @param string $value          Getrimmter Text
     * @param bool   $allowLineBreaks true, wenn Zeilenumbrüche und Tabs erlaubt sind
     * @return bool true, wenn keine unerlaubten Zeichen vorkommen
     */
    private static function hasOnlyAllowedCharacters(string $value, bool $allowLineBreaks): bool
    {
        if (strip_tags($value) !== $value) {
            return false;
        }

        // \p{Cc} = Steuerzeichen. Bei mehrzeiligem Text sind \n, \r und \t erlaubt.
        $pattern = $allowLineBreaks ? '/[^\P{Cc}\n\r\t]/u' : '/\p{Cc}/u';

        return preg_match($pattern, $value) === 0;
    }

    /**
     * Prüft, ob ein Text eine gültige URL mit http oder https ist.
     * @param string $value Getrimmter Text
     * @return bool true bei einer gültigen http(s)-URL
     */
    private static function isHttpUrl(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return $scheme === 'http' || $scheme === 'https';
    }

    /**
     * Prüft, ob eine Zahl höchstens zwei Nachkommastellen hat.
     * Kommazahlen sind intern nicht ganz exakt gespeichert (39999.95 ist in
     * Wirklichkeit 39999.9499999...). Darum wird mit einer kleinen Toleranz
     * verglichen statt mit ==.
     * @param int|float $value Preis
     * @return bool true, wenn höchstens zwei Nachkommastellen vorhanden sind
     */
    private static function hasMaxTwoDecimals(int|float $value): bool
    {
        return abs($value * 100 - round($value * 100)) < 0.000001;
    }
}
