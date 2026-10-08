<?php

declare(strict_types=1);

/**
 * Verarbeitet alle Requests rund um Produkte.
 * Ein Produkt wird in der URL über seine SKU angesprochen: /product/{sku}
 */
class ProductController
{
    /**
     * @param Product  $products   Model für die Tabelle product
     * @param Category $categories Model für die Tabelle category (Prüfung von id_category)
     */
    public function __construct(
        private Product $products,
        private Category $categories
    ) {
    }

    /**
     * GET /products – alle Produkte auflisten.
     */
    public function list(): void
    {
        Response::json($this->products->getAll());
    }

    /**
     * GET /product/{sku} – ein Produkt lesen.
     * @param string $sku SKU aus der URL
     */
    public function get(string $sku): void
    {
        $this->requireValidSku($sku);
        $product = $this->products->getBySku($sku);

        if ($product === null) {
            Response::error('Product not found', 404);
        }

        Response::json($product);
    }

    /**
     * PUT /product/{sku} – Produkt erstellen oder vollständig ersetzen.
     * PUT bedeutet: "Speichere die Ressource unter genau dieser Adresse."
     * Gibt es die SKU noch nicht, wird das Produkt erstellt (201),
     * sonst wird es mit den neuen Daten überschrieben (200).
     *
     * @param string $sku SKU aus der URL
     */
    public function createOrUpdate(string $sku): void
    {
        $this->requireValidSku($sku);
        $data = Request::getJsonBody();

        // $fields enthält nur bekannte, geprüfte und bereinigte Felder.
        // Unbekannte Felder aus dem Body werden dadurch ignoriert.
        [$fields, $errors] = Validator::product($data);
        if ($errors !== []) {
            Response::error('Invalid request data', 400, $errors);
        }

        // Die Kategorie muss existieren, sonst würde der Fremdschlüssel verletzt.
        if ($fields['id_category'] !== null && !$this->categories->exists($fields['id_category'])) {
            Response::error('Category does not exist', 409, [
                'id_category' => 'No category with id ' . $fields['id_category'],
            ]);
        }

        $isNew = $this->products->createOrUpdate($sku, $fields);

        Response::json($this->products->getBySku($sku), $isNew ? 201 : 200);
    }

    /**
     * DELETE /product/{sku} – Produkt löschen.
     * @param string $sku SKU aus der URL
     */
    public function delete(string $sku): void
    {
        $this->requireValidSku($sku);

        if (!$this->products->delete($sku)) {
            Response::error('Product not found', 404);
        }

        Response::noContent();
    }

    /**
     * Antwortet mit 400, wenn die SKU aus der URL ungültig ist.
     * @param string $sku aus der URL
     */
    private function requireValidSku(string $sku): void
    {
        if (!Validator::sku($sku)) {
            Response::error(
                'Invalid sku, allowed are letters, digits, ".", "-" and "_" (max. 100 characters)',
                400
            );
        }
    }
}