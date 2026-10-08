# UK295
# Shop REST API (üK295 LB1)

Eine REST API für einen Online-Shop, mit der sich Produkte und Kategorien verwalten lassen. Sie ist bewusst ohne Framework gebaut: reines PHP 8, MariaDB über `mysqli` und eine eigene, kleine JWT-Umsetzung. Das Frontend kommt in einem späteren Projekt dazu, deshalb liegt der Fokus hier auf einer sauberen Struktur und einer vollständigen Dokumentation.

## Voraussetzungen

- XAMPP 8 (Apache, PHP 8.0 oder neuer, MariaDB/MySQL), entwickelt auf macOS
- Git
- [Bruno](https://www.usebruno.com/) zum Testen

In XAMPP ist `mod_rewrite` standardmässig aktiv und `AllowOverride All` für `htdocs` gesetzt. Beides braucht die API, weil das Routing über eine `.htaccess` läuft.

## Installation

1. **Repository klonen.** Dieses Repository *ist* der Inhalt von `htdocs`. Am einfachsten den Inhalt des bestehenden `htdocs` sichern und das Repository direkt dort hinein klonen:

   ```bash
   cd /Applications/XAMPP/xamppfiles
   git clone https://github.com/<benutzername>/UK295.git htdocs
   ```

2. **Apache und MySQL starten** (XAMPP Manager: `/Applications/XAMPP/manager-osx.app`).

3. **Datenbank importieren.** In phpMyAdmin (`http://localhost/phpmyadmin`) den Tab *Importieren* öffnen und `database/shop_api.sql` hochladen. Alternativ auf der Konsole:

   ```bash
   /Applications/XAMPP/xamppfiles/bin/mysql -u root < database/shop_api.sql
   ```

   Das Skript legt die Datenbank `shop_api` neu an, inklusive Testdaten. Ein erneuter Import setzt alles auf den Anfangszustand zurück.

4. **Fertig.** Ein kurzer Test im Browser: `http://localhost/api/v1/products` muss mit `401` und `{"error": "Authentication required"}` antworten.

## Konfiguration

Die Einstellungen stehen in `config/config.php`. Die Standardwerte passen zu einer frischen XAMPP-Installation (Benutzer `root`, kein Passwort), daher läuft das Projekt ohne Anpassung.

Wer andere Werte braucht (zum Beispiel ein Datenbank-Passwort oder ein eigenes JWT-Secret), legt die Datei `config/config.local.php` an und trägt dort nur die Abweichungen ein. Sie wird automatisch geladen und überschreibt die Standardwerte. Diese Datei darf nicht committet werden, weil sie geheime Werte enthält.

Der Ordner `config/` ist über eine `.htaccess` gesperrt und kann nicht im Browser aufgerufen werden. Dasselbe gilt für `database/`.

## Authentifizierung

Bis auf `POST /authenticate` sind alle Endpoints geschützt.

```http
POST http://localhost/api/v1/authenticate
Content-Type: application/json

{
  "username": "admin",
  "password": "sec!ReT423*&"
}
```

Die Antwort enthält ein JWT:

```json
{
  "token": "eyJhbGciOiJIUzI1NiIs...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

Das Token wird auf zwei Wegen akzeptiert:

- als Header `Authorization: Bearer <token>`
- als Cookie `token`, das der Login automatisch setzt (HttpOnly)

Die Bruno-Collection verwendet das Cookie. Nach dem Request *Authenticate* funktionieren deshalb alle anderen Requests ohne manuelles Kopieren des Tokens.

Das Token ist mit HS256 signiert, eine Stunde gültig und enthält die Claims `iss`, `iat`, `exp`, `sub` und `username`.

## Endpoints

Basis-URL: `http://localhost/api/v1`

| Methode | Pfad | Zweck |
|---|---|---|
| POST | `/authenticate` | Anmelden, gibt ein JWT zurück (öffentlich) |
| GET | `/products` | Alle Produkte auflisten |
| PUT | `/product/{sku}` | Produkt erstellen (201) oder ersetzen (200) |
| GET | `/product/{sku}` | Ein Produkt lesen |
| DELETE | `/product/{sku}` | Ein Produkt löschen |
| GET | `/categories` | Alle Kategorien auflisten |
| POST | `/category` | Kategorie erstellen |
| PATCH | `/category/{category_id}` | Kategorie teilweise ändern |
| GET | `/category/{category_id}` | Eine Kategorie lesen |
| DELETE | `/category/{category_id}` | Eine Kategorie löschen |

Produkte werden in der URL über ihre **SKU** (Artikelnummer) angesprochen, z. B. `/product/12345678`. Die `product_id` vergibt die Datenbank. Damit kann jedes Feld des Datenmodells ausser den IDs über die API gesetzt werden, auch die SKU, die in keinem Request-Body vorkommt.

Die genauen Request- und Response-Strukturen stehen in der OpenAPI-Dokumentation.

## Statuscodes

| Code | Bedeutung |
|---|---|
| 200 | OK |
| 201 | Ressource erstellt |
| 204 | Gelöscht, keine Antwort im Body |
| 400 | Ungültiges JSON oder Validierungsfehler |
| 401 | Kein oder ungültiges Token, falsche Login-Daten |
| 404 | Ressource oder Endpoint nicht gefunden |
| 405 | Methode für diesen Pfad nicht erlaubt |
| 409 | Die angegebene Kategorie existiert nicht |
| 500 | Interner Fehler (Details nur im Apache-Log) |

## OpenAPI und Swagger

- OpenAPI-Datei: `public/openapi.yaml`
- Swagger UI: `http://localhost/public/swagger/`

Swagger UI liegt lokal im Projekt und braucht deshalb keine Internetverbindung. Über *Try it out* lassen sich die Endpoints direkt im Browser ausprobieren. Zuerst `/authenticate` ausführen, danach schickt der Browser das Cookie automatisch mit.

## Testen mit Bruno

Die offizielle Collection *üK295 LB1* ist nicht Teil dieses Repositorys. Sie wird in Bruno geöffnet und der Reihe nach ausgeführt, beginnend mit *Authenticate*.

Hinweis zur Reihenfolge: *Create/Update Product* verwendet `id_category: 1`, und am Ende löscht *Delete Category* die Kategorie 1. Für einen zweiten kompletten Durchlauf darum vorher den Dump neu importieren, sonst antwortet *Create/Update Product* mit `409`, weil die Kategorie fehlt.

## Projektstruktur

```text
htdocs/
├── api/v1/
│   ├── .htaccess          Leitet alle Requests an index.php
│   ├── index.php          Einstiegspunkt, Fehlerbehandlung
│   ├── routes.php         Routing und JWT-Prüfung
│   ├── controllers/       Verarbeiten die HTTP-Requests
│   ├── models/            Datenbankzugriff (nur Prepared Statements)
│   └── helpers/           Response, Request, Validator, Jwt
├── config/                Konfiguration und DB-Verbindung (gesperrt)
├── database/              SQL-Dump (gesperrt)
├── public/
│   ├── openapi.yaml       API-Dokumentation
│   └── swagger/           Swagger UI
└── README.md
```

Ein Request läuft so durch die Anwendung:

```text
Request → .htaccess → index.php → routes.php → JWT prüfen → Controller → Model → Datenbank
```

## Sicherheit in Kürze

- Alle SQL-Abfragen mit Werten von aussen laufen über `mysqli` Prepared Statements.
- Passwörter liegen nur als Hash (`password_hash`) in der Datenbank.
- Das JWT wird bei jedem Request auf Signatur, Algorithmus und Ablaufzeit geprüft.
- Fehlerdetails gehen ins Log, nicht an den Client.

## Code-Style

Der Code hält sich an den Standard PSR-12 (Einrückung, Klammern, Benennung).
Eine Abweichung gibt es bewusst: Die Klassen haben keinen Namespace. Das Projekt verwendet kein Composer, und die Dateien werden in `api/v1/index.php` mit `require_once` von Hand eingebunden. Namespaces wären vor allem für einen Autoloader nötig, deshalb wurde hier darauf verzichtet.