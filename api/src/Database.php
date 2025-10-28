<?php

/**
 * Database Connection Manager
 * 
 * Handles PDO database connections with secure configuration.
 * Uses constructor property promotion for cleaner code.
 */
class Database {
    /**
     * @param string $host Database host address
     * @param string $name Database name
     * @param string $user Database username
     * @param string $password Database password
     */
    public function __construct(
        private string $host,
        private string $name,
        private string $user,
        private string $password
    ) {}

    /**
     * Creates and returns a configured PDO connection
     * 
     * Security features:
     * - UTF-8 charset to prevent encoding attacks
     * - Prepared statements (EMULATE_PREPARES = false)
     * - Type-safe fetching (STRINGIFY_FETCHES = false)
     * - Error mode set to exceptions for better error handling
     * 
     * @return PDO Configured database connection
     * @throws PDOException If connection fails
     */
    public function getConnection(): PDO {
        $dsn = "mysql:host={$this->host};dbname={$this->name};charset=utf8mb4";
        
        $pdo = new PDO($dsn, $this->user, $this->password, [
            PDO::ATTR_EMULATE_PREPARES => false,    // Use real prepared statements
            PDO::ATTR_STRINGIFY_FETCHES => false,   // Return proper data types
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC // Default to associative arrays
        ]);
        
        return $pdo;
    }
}
