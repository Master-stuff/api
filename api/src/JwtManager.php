<?php

/**
 * JWT Token Manager
 * 
 * Handles creation, validation, and decoding of JSON Web Tokens
 * using HMAC-SHA256 algorithm for secure authentication.
 */
class JwtManager {
    private const ALGORITHM = 'HS256';
    private const TOKEN_EXPIRY = 86400; // 24 hours in seconds
    
    /**
     * @param string $secretKey Secret key for signing tokens (should be strong and random)
     */
    public function __construct(private string $secretKey) {
        if (strlen($secretKey) < 32) {
            throw new InvalidArgumentException('Secret key must be at least 32 characters');
        }
    }

    /**
     * Creates a new JWT token with the given payload
     * 
     * @param array $payload Data to encode in the token (user ID, email, etc.)
     * @param int|null $expiry Custom expiry time in seconds (null = default 24h)
     * @return string The generated JWT token
     */
    public function createToken(array $payload, ?int $expiry = null): string {
        $payload['iat'] = time(); // Issued at
        $payload['exp'] = time() + ($expiry ?? self::TOKEN_EXPIRY); // Expiration
        
        // Create header
        $header = ['alg' => self::ALGORITHM, 'typ' => 'JWT'];
        $base64UrlHeader = $this->base64UrlEncode(json_encode($header));
        
        // Create payload
        $base64UrlPayload = $this->base64UrlEncode(json_encode($payload));
        
        // Create signature
        $signature = hash_hmac(
            'sha256',
            $base64UrlHeader . '.' . $base64UrlPayload,
            $this->secretKey,
            true
        );
        $base64UrlSignature = $this->base64UrlEncode($signature);
        
        return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
    }

    /**
     * Validates a JWT token's signature and expiration
     * 
     * @param string $token The JWT token to validate
     * @return bool True if token is valid and not expired
     */
    public function validateToken(string $token): bool {
        try {
            $parts = explode('.', $token);
            
            if (count($parts) !== 3) {
                return false;
            }
            
            [$base64UrlHeader, $base64UrlPayload, $base64UrlSignature] = $parts;
            
            // Verify signature
            $signature = $this->base64UrlDecode($base64UrlSignature);
            $expectedSignature = hash_hmac(
                'sha256',
                $base64UrlHeader . '.' . $base64UrlPayload,
                $this->secretKey,
                true
            );
            
            if (!hash_equals($signature, $expectedSignature)) {
                return false;
            }
            
            $payload = json_decode($this->base64UrlDecode($base64UrlPayload), true);
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return false; // Token expired
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Decodes a JWT token and returns its payload
     * 
     * @param string $token The JWT token to decode
     * @return array|null The decoded payload or null if invalid/expired
     */
    public function decodeToken(string $token): ?array {
        if (!$this->validateToken($token)) {
            return null;
        }
        
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }
            
            $payload = $this->base64UrlDecode($parts[1]);
            return json_decode($payload, true);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Encodes data to base64url format (URL-safe base64)
     * 
     * @param string $data Data to encode
     * @return string Base64url encoded string
     */
    private function base64UrlEncode(string $data): string {
        $base64 = base64_encode($data);
        $base64Url = strtr($base64, '+/', '-_');
        return rtrim($base64Url, '=');
    }

    /**
     * Decodes base64url formatted data
     * 
     * @param string $data Base64url encoded string
     * @return string Decoded data
     */
    private function base64UrlDecode(string $data): string {
        $base64 = strtr($data, '-_', '+/');
        $base64Padded = str_pad($base64, strlen($base64) % 4, '=', STR_PAD_RIGHT);
        return base64_decode($base64Padded);
    }
}
