<?php

/**
 * Authentication Controller
 * 
 * Handles user registration, login, profile retrieval, and updates.
 * Implements JWT-based authentication.
 */
class AuthController {
    private JwtManager $jwtManager;
    private AuthMiddleware $authMiddleware;

    public function __construct(
        private AuthGateway $gateway,
        private BookGateway $gatebook
    ) {
        $this->jwtManager = new JwtManager($_ENV['SECRET_KEY']);
        $this->authMiddleware = new AuthMiddleware($this->jwtManager);
    }

    /**
     * Routes authentication requests to appropriate handlers
     * 
     * @param array $request Request path segments
     * @param string $method HTTP method
     */
    public function processRequest(array $request, string $method): void {
        $endpoint = $request[0] ?? null;
        $subEndpoint = $request[1] ?? null;

        try {
            match(true) {
                $endpoint === "register" && $method === "POST"
                    => $this->handleRegister(),
                
                $endpoint === "login" && $method === "POST"
                    => $this->handleLogin(),
                
                $endpoint === "me" && $method === "GET"
                    => $this->handleGetProfile($subEndpoint),
                
                $endpoint === "me" && $method === "PUT"
                    => $this->handleUpdateProfile(),
                
                is_numeric($endpoint) && $method === "GET"
                    => $this->handleGetUserById((int)$endpoint, $subEndpoint),
                
                default => $this->sendError(404, "Endpoint not found")
            };
        } catch (Exception $e) {
            $this->sendError(500, "Internal server error", $e->getMessage());
        }
    }

    /**
     * Handles user registration
     */
    private function handleRegister(): void {
        $data = $this->getJsonInput();
        if (!$data) return;

        // Validate input
        $errors = ValidationErrors::validateRegister($data, $this->gateway);
        if (!empty($errors)) {
            $this->sendError(400, "Validation failed", implode(", ", $errors));
            return;
        }

        $data["password"] = password_hash($data["password"], PASSWORD_BCRYPT);

        try {
            $userId = $this->gateway->createUser($data);
            $this->sendSuccess(201, [
                "message" => "User registered successfully",
                "user_id" => $userId
            ]);
        } catch (Exception $e) {
            $this->sendError(500, "Registration failed", $e->getMessage());
        }
    }

    /**
     * Handles user login
     */
    private function handleLogin(): void {
        $data = $this->getJsonInput();
        if (!$data) return;

        // Validate input
        $errors = ValidationErrors::validateLogin($data);
        if (!empty($errors)) {
            $this->sendError(400, "Validation failed", implode(", ", $errors));
            return;
        }

        // Verify credentials
        $user = $this->gateway->verifyUser($data);
        
        if (!$user) {
            $this->sendError(401, "Invalid credentials");
            return;
        }

        $token = $this->jwtManager->createToken([
            "id" => $user["id"],
            "email" => $user["email"],
            "username" => $user["username"]
        ]);

        $this->sendSuccess(200, [
            "message" => "Login successful",
            "token" => $token,
            "user" => [
                "id" => $user["id"],
                "email" => $user["email"],
                "username" => $user["username"]
            ]
        ]);
    }

    /**
     * Handles retrieving current user's profile
     * 
     * @param string|null $subEndpoint Optional sub-endpoint (e.g., "books")
     */
    private function handleGetProfile(?string $subEndpoint): void {
        $payload = $this->authMiddleware->authenticate();
        if (!$payload) return;

        try {
            if ($subEndpoint === "books") {
                // Get user's books
                $books = $this->gatebook->getByOwner($payload['id']);
                $this->sendJson($books);
            } else {
                // Get user profile
                $user = $this->gateway->getUserById($payload['id']);
                
                if (!$user) {
                    $this->sendError(404, "User not found");
                    return;
                }
                
                unset($user['pwd']);
                $this->sendJson($user);
            }
        } catch (Exception $e) {
            $this->sendError(500, "Failed to retrieve profile", $e->getMessage());
        }
    }

    /**
     * Handles updating current user's profile
     */
    private function handleUpdateProfile(): void {
        $payload = $this->authMiddleware->authenticate();
        if (!$payload) return;

        $data = $this->getJsonInput();
        if (!$data) return;

        // Validate input
        $errors = ValidationErrors::validateUpdate($data, $this->gateway);
        if (!empty($errors)) {
            $this->sendError(400, "Validation failed", implode(", ", $errors));
            return;
        }

        try {
            $currentUser = $this->gateway->getUserById($payload['id']);
            
            if (!$currentUser) {
                $this->sendError(404, "User not found");
                return;
            }

            $rowsUpdated = $this->gateway->updateUser($currentUser, $data);

            $this->sendSuccess(200, [
                "message" => "Profile updated successfully",
                "rows_updated" => $rowsUpdated
            ]);
        } catch (Exception $e) {
            $this->sendError(500, "Update failed", $e->getMessage());
        }
    }

    /**
     * Handles retrieving a user by ID
     * 
     * @param int $userId User ID to retrieve
     * @param string|null $subEndpoint Optional sub-endpoint (e.g., "books")
     */
    private function handleGetUserById(int $userId, ?string $subEndpoint): void {
        try {
            if ($subEndpoint === "books") {
                // Get user's books
                $books = $this->gatebook->getByOwner($userId);
                $this->sendJson($books);
            } else {
                // Get user profile
                $user = $this->gateway->getUserById($userId);
                
                if (!$user) {
                    $this->sendError(404, "User not found");
                    return;
                }
                
                unset($user['pwd']);
                $this->sendJson($user);
            }
        } catch (Exception $e) {
            $this->sendError(500, "Failed to retrieve user", $e->getMessage());
        }
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
