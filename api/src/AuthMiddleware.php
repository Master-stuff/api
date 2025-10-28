<?php

/**
 * Authentication Middleware
 * 
 * Extracts and validates JWT tokens from Authorization headers.
 * Provides centralized authentication logic to avoid code duplication.
 */
class AuthMiddleware {
    private JwtManager $jwtManager;
    
    /**
     * @param JwtManager $jwtManager JWT manager instance for token validation
     */
    public function __construct(JwtManager $jwtManager) {
        $this->jwtManager = $jwtManager;
    }
    
    /**
     * Authenticates the current request and returns user payload
     * 
     * @return array|null User payload from JWT or null if authentication fails
     */
    public function authenticate(): ?array {
        $token = $this->extractToken();
        
        if (!$token) {
            $this->sendUnauthorized("Missing or invalid authorization header");
            return null;
        }
        
        $payload = $this->jwtManager->decodeToken($token);
        
        if (!$payload) {
            $this->sendUnauthorized("Invalid or expired token");
            return null;
        }
        
        return $payload;
    }
    
    /**
     * Extracts JWT token from Authorization header
     * 
     * @return string|null The extracted token or null if not found
     */
    private function extractToken(): ?string {
        $headers = getallheaders();
        $auth = $headers["Authorization"] ?? "";
        
        if (preg_match('/Bearer\s+(\S+)/', $auth, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Sends 401 Unauthorized response
     * 
     * @param string $message Error message
     */
    private function sendUnauthorized(string $message): void {
        http_response_code(401);
        echo json_encode(["error" => $message]);
    }
}
