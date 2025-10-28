<?php

/**
 * Authentication Data Gateway
 * 
 * Handles all database operations related to user authentication and management.
 * Uses prepared statements to prevent SQL injection.
 */
class AuthGateway {
    private PDO $conn;

    public function __construct(Database $database) {
        $this->conn = $database->getConnection();
    }
    
    /**
     * Creates a new user in the database
     * 
     * @param array $data User data (first_name, last_name, username, email, password)
     * @return string The ID of the newly created user
     * @throws PDOException If database operation fails
     */
    public function createUser(array $data): string {
        $sql = "INSERT INTO users (first_name, last_name, username, email, pwd)
                VALUES (:first_name, :last_name, :username, :email, :pwd)";
        
        $stmt = $this->conn->prepare($sql);
        
        $stmt->execute([
            ':first_name' => $data['first_name'],
            ':last_name' => $data['last_name'],
            ':username' => $data['username'],
            ':email' => $data['email'],
            ':pwd' => $data['password'] // Should already be hashed
        ]);

        return $this->conn->lastInsertId();
    }

    /**
     * Retrieves a user by email address
     * 
     * @param string $email User's email address
     * @return array|false User data or false if not found
     */
    public function getUserByEmail(string $email): array|false {
        $sql = "SELECT * FROM users WHERE email = :email LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':email' => $email]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieves a user by username
     * 
     * @param string $username User's username
     * @return array|false User data or false if not found
     */
    public function getUserByUsername(string $username): array|false {
        $sql = "SELECT * FROM users WHERE username = :username LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':username' => $username]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieves a user by ID
     * 
     * @param int $id User's ID
     * @return array|false User data or false if not found
     */
    public function getUserById(int $id): array|false {
        $sql = "SELECT * FROM users WHERE id = :id LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Verifies user credentials for login
     * 
     * @param array $data Login data (email, password)
     * @return array|null User data (id, email) if credentials are valid, null otherwise
     */
    public function verifyUser(array $data): ?array {
        $user = $this->getUserByEmail($data['email']);
        
        if (!$user) {
            return null;
        }
        
        if (password_verify($data['password'], $user['pwd'])) {
            return [
                "id" => $user['id'],
                "email" => $user['email'],
                "username" => $user['username']
            ];
        }
        
        return null;
    }
    
    /**
     * Updates user information
     * 
     * @param array $current Current user data
     * @param array $new New user data (only provided fields will be updated)
     * @return int Number of rows affected
     */
    public function updateUser(array $current, array $new): int {
        $sql = "UPDATE users
                SET first_name = :first_name,
                    last_name = :last_name,
                    username = :username,
                    pwd = :pwd
                WHERE id = :id";
        
        $stmt = $this->conn->prepare($sql);
        
        $password = isset($new['password']) && !empty($new['password'])
            ? password_hash($new['password'], PASSWORD_BCRYPT)
            : $current['pwd'];
        
        $stmt->execute([
            ':first_name' => $new['first_name'] ?? $current['first_name'],
            ':last_name' => $new['last_name'] ?? $current['last_name'],
            ':username' => $new['username'] ?? $current['username'],
            ':pwd' => $password,
            ':id' => $current['id']
        ]);

        return $stmt->rowCount();
    }
}
