<?php

declare(strict_types=1);

/**
 * Routing: Ordnet jeden Request (Methode + Pfad) der richtigen Controller-Methode zu.
 * URL-Segment. Ihr Wert wird der Controller-Methode als Parameter übergeben.
 * aufgerufen wird, muss ein gültiges JWT vorhanden sein.
 *
 * @param string               $method HTTP-Methode, z. B. "GET"
 * @param string               $path   Pfad ohne /api/v1, z. B. "/category/1"
 * @param mysqli               $db     Datenbankverbindung
 * @param array<string, mixed> $config Gesamte Konfiguration
 */
function dispatch(string $method, string $path, mysqli $db, array $config): void
{
    $categoryModel = new Category($db);

    $authController = new AuthController(new User($db), $config['jwt']);
    $categoryController = new CategoryController($categoryModel);
    $productController = new ProductController(new Product($db), $categoryModel);

    // [Methode, Pfad, Controller-Methode, öffentlich?]
    $routes = [
        ['POST',   '/authenticate',             [$authController, 'authenticate'],       true],
        ['GET',    '/products',                 [$productController, 'list'],            false],
        ['PUT',    '/product/{sku}',            [$productController, 'createOrUpdate'],  false],
        ['GET',    '/product/{sku}',            [$productController, 'get'],             false],
        ['DELETE', '/product/{sku}',            [$productController, 'delete'],          false],

        ['GET',    '/categories',               [$categoryController, 'list'],           false],
        ['POST',   '/category',                 [$categoryController, 'create'],         false],
        ['PATCH',  '/category/{category_id}',   [$categoryController, 'update'],         false],
        ['GET',    '/category/{category_id}',   [$categoryController, 'get'],            false],
        ['DELETE', '/category/{category_id}',   [$categoryController, 'delete'],         false],
    ];

    $allowedMethods = [];

    foreach ($routes as [$routeMethod, $routePath, $handler, $isPublic]) {
        // Aus "/category/{category_id}" wird der reguläre Ausdruck "#^/category/([^/]+)$#".
        $pattern = '#^' . preg_replace('#\{[a-z_]+\}#', '([^/]+)', $routePath) . '$#';

        if (preg_match($pattern, $path, $matches) !== 1) {
            continue;
        }

        // Der Pfad existiert, aber vielleicht mit einer anderen Methode.
        if ($routeMethod !== $method) {
            $allowedMethods[] = $routeMethod;
            continue;
        }

        if (!$isPublic) {
            requireAuthentication($config['jwt']['secret']);
        }

        // $matches[0] ist der ganze Pfad, ab $matches[1] kommen die Platzhalter.
        // rawurldecode macht z. B. aus "%20" wieder ein Leerzeichen.
        $parameters = array_map('rawurldecode', array_slice($matches, 1));

        $handler(...$parameters);
        return;
    }

    if ($allowedMethods !== []) {
        // 405: Pfad bekannt, Methode nicht erlaubt. Der Allow-Header nennt die erlaubten.
        header('Allow: ' . implode(', ', array_unique($allowedMethods)));
        Response::error('Method not allowed', 405);
    }

    Response::error('Endpoint not found', 404);
}

/**
 * Prüft das JWT und bricht mit 401 ab, wenn es fehlt oder ungültig ist.
 * @param string $secret JWT-Secret aus der Konfiguration
 * @return array<string, mixed> Payload des Tokens
 */
function requireAuthentication(string $secret): array
{
    $token = Request::getToken();

    if ($token === null) {
        Response::error('Authentication required', 401);
    }

    $payload = Jwt::verify($token, $secret);

    if ($payload === null) {
        Response::error('Invalid or expired token', 401);
    }

    return $payload;
}