<?php

/**
 * Book Controller
 * 
 * Handles CRUD operations for books with authentication, authorization, and file management.
 */
class BookController {
    private JwtManager $jwtManager;
    private AuthMiddleware $authMiddleware;
    private FileManager $fileManager;

    public function __construct(private BookGateway $gateway) {
        $this->jwtManager = new JwtManager($_ENV['SECRET_KEY']);
        $this->authMiddleware = new AuthMiddleware($this->jwtManager);
        $this->fileManager = new FileManager();
    }

    /**
     * Routes book requests to appropriate handlers
     * 
     * @param string $method HTTP method
     * @param string|null $id Optional book ID
     * @param string|null $action Optional action (e.g., 'upload-cover')
     */
    public function processRequest(string $method, ?string $id, ?string $action = null): void {
        try {
            if ($action === 'upload-cover' && $method === 'POST') {
                $this->handleUploadCover($id);
                return;
            }

            if ($id) {
                $this->processResourceRequest($method, $id);
            } else {
                $this->processCollectionRequest($method);
            }
        } catch (Exception $e) {
            $this->sendError(500, "Internal server error", $e->getMessage());
        }
    }

    /**
     * Handles cover image upload for a book
     * 
     * @param string|null $id Book ID
     */
    private function handleUploadCover(?string $id): void {
        if (!$id || !$this->isValidId($id)) {
            $this->sendError(400, "Invalid book ID");
            return;
        }

        $book = $this->gateway->get($id);
        if (!$book) {
            $this->sendError(404, "Book not found");
            return;
        }

        // Authenticate user
        $payload = $this->authMiddleware->authenticate();
        if (!$payload) return;

        // Authorize: only book owner can upload cover
        if ($book['owner_id'] !== $payload['id']) {
            $this->sendError(403, "Unauthorized: You can only upload covers for your own books");
            return;
        }

        // Validate file upload
        if (!isset($_FILES['cover']) || $_FILES['cover']['error'] !== UPLOAD_ERR_OK) {
            $this->sendError(400, "No file uploaded or upload error occurred");
            return;
        }

        try {
            // Upload and store file
            $filepath = $this->fileManager->uploadCoverImage($_FILES['cover'], (int)$id);

            // Delete old cover if exists
            if (!empty($book['cover_image']) && strpos($book['cover_image'], 'public/covers') === 0) {
                $this->fileManager->deleteCoverImage($book['cover_image']);
            }

            // Update database with new cover path
            $this->gateway->updateCoverImage((int)$id, $filepath);

            $this->sendSuccess(200, [
                "message" => "Cover image uploaded successfully",
                "cover_path" => $filepath
            ]);
        } catch (Exception $e) {
            $this->sendError(400, "Failed to upload cover image", $e->getMessage());
        }
    }

    /**
     * Handles requests for a specific book resource
     * 
     * @param string $method HTTP method
     * @param string $id Book ID
     */
    private function processResourceRequest(string $method, string $id): void {
        if (!$this->isValidId($id)) {
            $this->sendError(400, "Invalid book ID");
            return;
        }

        $book = $this->gateway->get($id);
        
        if (!$book) {
            $this->sendError(404, "Book not found");
            return;
        }

        match($method) {
            'GET' => $this->handleGetBook($book),
            'PUT' => $this->handleUpdateBook($book),
            'DELETE' => $this->handleDeleteBook($book),
            default => $this->sendError(405, "Method not allowed", "Allowed: GET, PUT, DELETE")
        };
    }

    /**
     * Handles requests for the book collection
     * 
     * @param string $method HTTP method
     */
    private function processCollectionRequest(string $method): void {
        match($method) {
            'GET' => $this->handleGetAllBooks(),
            'POST' => $this->handleCreateBook(),
            default => $this->sendError(405, "Method not allowed", "Allowed: GET, POST")
        };
    }

    /**
     * Handles retrieving a single book
     * 
     * @param array $book Book data
     */
    private function handleGetBook(array $book): void {
        $this->sendJson($book);
    }

    /**
     * Handles retrieving all books
     */
    private function handleGetAllBooks(): void {
        $books = $this->gateway->getAll();
        $this->sendJson($books);
    }

    /**
     * Handles creating a new book
     */
    private function handleCreateBook(): void {
        $payload = $this->authMiddleware->authenticate();
        if (!$payload) return;

        $data = $this->getJsonInput();
        if (!$data) return;

        $data['owner_id'] = $payload['id'];

        // Validate input
        $errors = $this->validateBookData($data, true);
        if (!empty($errors)) {
            $this->sendError(400, "Validation failed", implode(", ", $errors));
            return;
        }

        try {
            $bookId = $this->gateway->create($data);
            $this->sendSuccess(201, [
                "message" => "Book created successfully",
                "book_id" => $bookId
            ]);
        } catch (Exception $e) {
            $this->sendError(500, "Failed to create book", $e->getMessage());
        }
    }

    /**
     * Handles updating an existing book
     * 
     * @param array $book Current book data
     */
    private function handleUpdateBook(array $book): void {
        $payload = $this->authMiddleware->authenticate();
        if (!$payload) return;

        if ($book['owner_id'] !== $payload['id']) {
            $this->sendError(403, "Unauthorized: You can only update your own books");
            return;
        }

        $data = $this->getJsonInput();
        if (!$data) return;

        // Validate input
        $errors = $this->validateBookData($data, false);
        if (!empty($errors)) {
            $this->sendError(400, "Validation failed", implode(", ", $errors));
            return;
        }

        try {
            $rowsUpdated = $this->gateway->update($book, $data);
            $this->sendSuccess(200, [
                "message" => "Book updated successfully",
                "rows_updated" => $rowsUpdated
            ]);
        } catch (Exception $e) {
            $this->sendError(500, "Failed to update book", $e->getMessage());
        }
    }

    /**
     * Handles deleting a book
     * 
     * @param array $book Book data
     */
    private function handleDeleteBook(array $book): void {
        $payload = $this->authMiddleware->authenticate();
        if (!$payload) return;

        if ($book['owner_id'] !== $payload['id']) {
            $this->sendError(403, "Unauthorized: You can only delete your own books");
            return;
        }

        try {
            $this->gateway->delete($book['id']);
            $this->sendSuccess(200, ["message" => "Book deleted successfully"]);
        } catch (Exception $e) {
            $this->sendError(500, "Failed to delete book", $e->getMessage());
        }
    }

    /**
     * Validates book data
     * 
     * @param array $data Book data to validate
     * @param bool $isNew Whether this is a new book (requires all fields)
     * @return array Array of validation errors (empty if valid)
     */
    private function validateBookData(array $data, bool $isNew): array {
        $errors = [];

        if ($isNew) {
            if (empty($data['title'])) {
                $errors[] = "Title is required";
            }
            
            if (empty($data['owner_id'])) {
                $errors[] = "Owner ID is required";
            } elseif (!filter_var($data['owner_id'], FILTER_VALIDATE_INT) || $data['owner_id'] <= 0) {
                $errors[] = "Owner ID must be a positive integer";
            }
        }

        if (isset($data['title']) && strlen($data['title']) > 255) {
            $errors[] = "Title must be 255 characters or less";
        }

        if (isset($data['isbn']) && !empty($data['isbn']) && !$this->isValidISBN($data['isbn'])) {
            $errors[] = "Invalid ISBN format";
        }

        return $errors;
    }

    /**
     * Validates ISBN format (basic validation)
     * 
     * @param string $isbn ISBN to validate
     * @return bool True if valid
     */
    private function isValidISBN(string $isbn): bool {
        // Remove hyphens and spaces
        $isbn = str_replace(['-', ' '], '', $isbn);
        
        // Check if it's ISBN-10 or ISBN-13
        return preg_match('/^(?:\d{9}X|\d{10}|\d{13})$/', $isbn);
    }

    /**
     * Validates ID format
     * 
     * @param string $id ID to validate
     * @return bool True if valid
     */
    private function isValidId(string $id): bool {
        return is_numeric($id) && $id > 0;
    }

    
    /**
     * Parses JSON input from request body
     * 
     * @return array|null Parsed data or null if invalid
     */
    private function getJsonInput(): ?array {
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError(400, "Invalid JSON format", json_last_error_msg());
            return null;
        }
        
        return $input;
    }

    /**
     * Sends JSON response
     * 
     * @param mixed $data Data to encode
     * @param int $code HTTP status code
     */
    private function sendJson(mixed $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode($data);
    }

    /**
     * Sends success response
     * 
     * @param int $code HTTP status code
     * @param array $data Response data
     */
    private function sendSuccess(int $code, array $data): void {
        $this->sendJson($data, $code);
    }

    /**
     * Sends error response
     * 
     * @param int $code HTTP status code
     * @param string $error Error message
     * @param string|null $details Optional error details
     */
    private function sendError(int $code, string $error, ?string $details = null): void {
        $response = ["error" => $error];
        if ($details !== null && ($_ENV['APP_ENV'] ?? 'production') === 'development') {
            $response["details"] = $details;
        }
        $this->sendJson($response, $code);
    }
}
