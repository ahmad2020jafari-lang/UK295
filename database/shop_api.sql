-- Datenbank für die Shop REST API (üK295 LB1)
DROP DATABASE IF EXISTS shop_api;
CREATE DATABASE shop_api
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE shop_api;

-- Tabelle category (gemäss Datenmodell)
CREATE TABLE category (
    category_id INT          NOT NULL AUTO_INCREMENT,
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    name        VARCHAR(500) NOT NULL,
    PRIMARY KEY (category_id)
) ENGINE = InnoDB;

-- id_category darf NULL sein: Produkt ohne Kategorie = nicht gelistet.
-- Wird eine Kategorie gelöscht, verlieren die Produkte nur die Zuordnung.
CREATE TABLE product (
    product_id  INT            NOT NULL AUTO_INCREMENT,
    sku         VARCHAR(100)   NOT NULL,
    active      TINYINT(1)     NOT NULL DEFAULT 1,
    id_category INT            NULL,
    name        VARCHAR(500)   NOT NULL,
    image       VARCHAR(1000)  NOT NULL DEFAULT '',
    description TEXT           NOT NULL,
    price       DECIMAL(65, 2) NOT NULL,
    stock       INT            NOT NULL DEFAULT 0,
    PRIMARY KEY (product_id),
    UNIQUE KEY uq_product_sku (sku),
    CONSTRAINT fk_product_category
        FOREIGN KEY (id_category) REFERENCES category (category_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE = InnoDB;

-- Tabelle user (wird für das Login gebraucht)
-- Das Passwort wird nur als Hash aus password_hash() gespeichert.
CREATE TABLE user (
    user_id       INT          NOT NULL AUTO_INCREMENT,
    username      VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_user_username (username)
) ENGINE = InnoDB;

-- Testdaten
-- Login: admin / sec!ReT423*&
INSERT INTO user (user_id, username, password_hash) VALUES
    (1, 'admin', '$2y$10$hH1SrAM/5qQxhguBskPzfOTBn2a.oGSqyZkW5VwvBlkKrLNOhNaB.');

-- Kategorie 1 muss existieren, weil Bruno "Create/Update Product"
-- mit id_category 1 vor "Create Category" ausführt.
INSERT INTO category (category_id, active, name) VALUES
    (1, 1, 'Allgemein'),
    (2, 1, 'Bekleidung');

INSERT INTO product (sku, active, id_category, name, image, description, price, stock) VALUES
    ('TSHIRT-001', 1, 2, 'T-Shirt CsBe', '', 'Schwarzes Baumwoll-T-Shirt mit Logo.', 24.90, 25),
    ('MUG-001', 1, 1, 'Kaffeetasse', '', 'Weisse Tasse, 3 dl.', 12.50, 40),
    ('STICKER-001', 0, NULL, 'Sticker-Set', '', 'Noch nicht im Shop gelistet.', 4.00, 100);