<?php

declare(strict_types=1);

/**
 * Verarbeitet alle Requests rund um Kategorien.
 * Ablauf in jeder Methode: Eingaben prüfen → Model aufrufen → Antwort senden.
 * SQL steht ausschliesslich im Model.
 */
class CategoryController
{
    /**
     * @param Category $categories Model für die Tabelle category
     */
    public function __construct(private Category $categories)
    {
    }

    /**
     * GET /categories – alle Kategorien auflisten.
     */
    public function list(): void
    {
        Response::json($this->categories->getAll());
    }

    /**
     * GET /category/{category_id} – eine Kategorie lesen.
     *
     * @param string $idParam ID aus der URL
     */
    public function get(string $idParam): void
    {
        $id = $this->requireValidId($idParam);
        $category = $this->categories->getById($id);

        if ($category === null) {
            Response::error('Category not found', 404);
        }

        Response::json($category);
    }

    /**
     * POST /category – neue Kategorie erstellen.
     */
    public function create(): void
    {
        $data = Request::getJsonBody();

        // $category enthält nur geprüfte und bereinigte Werte (getrimmt, active als 0/1).
        [$category, $errors] = Validator::category($data);
        if ($errors !== []) {
            Response::error('Invalid request data', 400, $errors);
        }

        $newId = $this->categories->create($category['active'], $category['name']);

        // 201 Created: Es wurde eine neue Ressource angelegt.
        Response::json($this->categories->getById($newId), 201);
    }

    /**
     * PATCH /category/{category_id} – Kategorie teilweise ändern.
     * Nur die mitgeschickten Felder werden geändert, die anderen bleiben.
     * @param string $idParam ID aus der URL
     */
    public function update(string $idParam): void
    {
        $id = $this->requireValidId($idParam);

        // Zuerst prüfen, ob es die Kategorie gibt (404), dann den Body (400).
        if (!$this->categories->exists($id)) {
            Response::error('Category not found', 404);
        }

        $data = Request::getJsonBody();

        // Der Validator gibt nur bekannte Felder zurück, alles andere wird ignoriert.
        [$fields, $errors] = Validator::category($data, true);
        if ($errors !== []) {
            Response::error('Invalid request data', 400, $errors);
        }

        $this->categories->update($id, $fields);

        Response::json($this->categories->getById($id));
    }

    /**
     * DELETE /category/{category_id} – Kategorie löschen.
     * @param string $idParam ID aus der URL
     */
    public function delete(string $idParam): void
    {
        $id = $this->requireValidId($idParam);

        if (!$this->categories->delete($id)) {
            Response::error('Category not found', 404);
        }

        // 204 No Content: erfolgreich gelöscht, es gibt nichts mehr zurückzugeben.
        Response::noContent();
    }

    /**
     * Wandelt die ID aus der URL in einen Integer um oder antwortet mit 400.
     * @param string $idParam ID aus der URL
     * @return int Gültige ID
     */
    private function requireValidId(string $idParam): int
    {
        $id = Validator::id($idParam);

        if ($id === null) {
            Response::error('Invalid category_id, must be a positive integer', 400);
        }

        return $id;
    }
}