<?php

declare(strict_types=1);

require __DIR__ . "/bootstrap.php";

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);

// Remove /api prefix if present
if (strpos($path, '/api/') === 0) {
    $path = substr($path, 4); // Remove '/api'
} elseif ($path === '/api') {
    $path = '/';
}

$parts = explode("/", $path);

// Remove empty parts
$parts = array_values(array_filter($parts));

$resource = $parts[0] ?? null;
$method = $_SERVER["REQUEST_METHOD"];

if ($resource === null || $resource === '') {
    http_response_code(200);
    echo json_encode([
        "message" => "Library Management REST API",
        "version" => "1.0.0",
        "endpoints" => [
            "auth" => [
                "POST /api/register" => "Register a new user",
                "POST /api/login" => "Login and get JWT token",
                "GET /api/me" => "Get current user profile (requires auth)",
                "GET /api/me/books" => "Get current user's books (requires auth)",
                "PUT /api/me" => "Update current user profile (requires auth)",
                "GET /api/{user_id}" => "Get user by ID",
                "GET /api/{user_id}/books" => "Get user's books by ID"
            ],
            "books" => [
                "GET /api/books" => "Get all books",
                "GET /api/books/{id}" => "Get a specific book",
                "POST /api/books" => "Create a new book (requires auth)",
                "PUT /api/books/{id}" => "Update a book (requires auth)",
                "DELETE /api/books/{id}" => "Delete a book (requires auth)",
                "POST /api/books/{id}/upload-cover" => "Upload book cover (requires auth)"
            ],
            "loans" => [
                "POST /api/loans/request" => "Request to borrow a book (requires auth)",
                "GET /api/loans/received" => "Get loan requests received (requires auth)",
                "GET /api/loans/my-borrowed" => "Get your loan requests (requires auth)",
                "PUT /api/loans/{id}/approve" => "Approve a loan request (requires auth)",
                "PUT /api/loans/{id}/decline" => "Decline a loan request (requires auth)",
                "PUT /api/loans/{id}/complete" => "Mark loan as complete (requires auth)"
            ],
            "reviews" => [
                "GET /api/reviews/{user_id}" => "Get reviews for a user",
                "POST /api/reviews" => "Submit a review (requires auth)"
            ]
        ],
        "documentation" => "See README.md and HTTPIE_GUIDE.md for detailed usage"
    ]);
    exit;
}

try {
    switch ($resource) {
        case "register":
        case "login":
        case "me":
            $authGateway = new AuthGateway($database);
            $bookGateway = new BookGateway($database);
            $controller = new AuthController($authGateway, $bookGateway);
            $controller->processRequest($parts, $method);
            break;

        case "books":
            $gateway = new BookGateway($database);
            $controller = new BookController($gateway);
            
            $id = $parts[1] ?? null;
            $action = $parts[2] ?? null;
            
            $controller->processRequest($method, $id, $action);
            break;

        case "loans":
            $loansGateway = new LoansGateway($database);
            $bookGateway = new BookGateway($database);
            $controller = new LoansController($loansGateway, $bookGateway);
            $controller->processRequest($parts, $method);
            break;

        case "reviews":
            $gateway = new ReviewsGateway($database);
            $controller = new ReviewsController($gateway);
            $controller->processRequest($parts, $method);
            break;

        default:
            if (is_numeric($resource)) {
                $authGateway = new AuthGateway($database);
                $bookGateway = new BookGateway($database);
                $controller = new AuthController($authGateway, $bookGateway);
                $controller->processRequest($parts, $method);
            } else {
                http_response_code(404);
                echo json_encode(["error" => "Resource not found"]);
            }
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => "Internal server error",
        "details" => ($_ENV['APP_ENV'] ?? 'production') === 'development' ? $e->getMessage() : null
    ]);
}
