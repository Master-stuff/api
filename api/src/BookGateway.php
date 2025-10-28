<?php

/**
 * Book Data Gateway
 * 
 * Handles all database operations related to books.
 * Uses prepared statements to prevent SQL injection.
 */
class BookGateway {
    private PDO $conn;

    public function __construct(Database $database) {
        $this->conn = $database->getConnection();
    }

    /**
     * Retrieves all books from the database
     * 
     * @return array Array of all books
     */
    public function getAll(): array {
        $sql = "SELECT 
                    b.*,
                    u.username AS owner_username,
                    u.first_name AS owner_first_name,
                    u.last_name AS owner_last_name
                FROM books b
                LEFT JOIN users u ON b.owner_id = u.id
                ORDER BY b.created_at DESC";

        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Creates a new book
     * 
     * @param array $data Book data
     * @return string The ID of the newly created book
     */
    public function create(array $data): string {
        $sql = "INSERT INTO books (
                    title, owner_id, language, isbn, 
                    genre, description, author, cover_image
                )
                VALUES (
                    :title, :owner_id, :language, :isbn,
                    :genre, :description, :author, :cover_image
                )";
        
        $stmt = $this->conn->prepare($sql);
        
        $stmt->execute([
            ':title' => $data['title'],
            ':owner_id' => (int)$data['owner_id'],
            ':language' => $data['language'] ?? 'English',
            ':isbn' => $data['isbn'] ?? null,
            ':genre' => $data['genre'] ?? null,
            ':description' => $data['description'] ?? null,
            ':author' => $data['author'] ?? 'Unknown',
            ':cover_image' => $data['cover_image'] ?? null
        ]);

        return $this->conn->lastInsertId();
    }

    /**
     * Retrieves a single book by ID
     * 
     * @param string|int $id Book ID
     * @return array|false Book data or false if not found
     */
    public function get(string|int $id): array|false {
        $sql = "SELECT 
                    b.*,
                    u.username AS owner_username,
                    u.first_name AS owner_first_name,
                    u.last_name AS owner_last_name,
                    u.email AS owner_email
                FROM books b
                LEFT JOIN users u ON b.owner_id = u.id
                WHERE b.id = :id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => (int)$id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Updates an existing book
     * 
     * @param array $current Current book data
     * @param array $new New book data (only provided fields will be updated)
     * @return int Number of rows affected
     */
    public function update(array $current, array $new): int {
        $sql = "UPDATE books
                SET title = :title,
                    author = :author,
                    language = :language,
                    isbn = :isbn,
                    genre = :genre,
                    description = :description,
                    cover_image = :cover_image
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        
        $stmt->execute([
            ':title' => $new['title'] ?? $current['title'],
            ':author' => $new['author'] ?? $current['author'],
            ':language' => $new['language'] ?? $current['language'],
            ':isbn' => $new['isbn'] ?? $current['isbn'],
            ':genre' => $new['genre'] ?? $current['genre'],
            ':description' => $new['description'] ?? $current['description'],
            ':cover_image' => $new['cover_image'] ?? $current['cover_image'],
            ':id' => $current['id']
        ]);

        return $stmt->rowCount();
    }

    /**
     * Updates only the cover image for a book
     * 
     * New method for file management system
     * 
     * @param int $bookId Book ID
     * @param string $coverPath Path to the cover image file
     * @return int Number of rows affected
     */
    public function updateCoverImage(int $bookId, string $coverPath): int {
        $sql = "UPDATE books SET cover_image = :cover_image WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':cover_image' => $coverPath,
            ':id' => $bookId
        ]);
        return $stmt->rowCount();
    }

    /**
     * Retrieves all books owned by a specific user
     * 
     * @param int $ownerId Owner's user ID
     * @return array Array of books
     */
    public function getByOwner(int $ownerId): array {
        $sql = "SELECT * FROM books 
                WHERE owner_id = :owner_id
                ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':owner_id' => $ownerId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Deletes a book
     * 
     * @param string|int $id Book ID to delete
     * @return int Number of rows affected
     */
    public function delete(string|int $id): int {
        $this->conn->beginTransaction();
        
        try {
            // Delete related loans first (foreign key constraint)
            $sqlLoans = "DELETE FROM loans WHERE book_id = :id";
            $stmtLoans = $this->conn->prepare($sqlLoans);
            $stmtLoans->execute([':id' => (int)$id]);
            
            // Delete the book
            $sqlBook = "DELETE FROM books WHERE id = :id";
            $stmtBook = $this->conn->prepare($sqlBook);
            $stmtBook->execute([':id' => (int)$id]);
            
            $rowCount = $stmtBook->rowCount();
            
            $this->conn->commit();
            return $rowCount;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
}
