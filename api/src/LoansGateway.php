<?php

/**
 * Loans Data Gateway
 * 
 * Handles all database operations related to book loans.
 * Uses prepared statements and transactions for data integrity.
 */
class LoansGateway {
    private PDO $conn;

    public function __construct(Database $database) {
        $this->conn = $database->getConnection();
    }

    /**
     * Creates a new loan request
     * 
     * @param int $book_id Book ID being requested
     * @param int $borrower_id User ID of the borrower
     * @param string|null $start_date Requested start date
     * @param string|null $due_date Requested due date
     * @param string|null $message Optional message to book owner
     * @return string The ID of the newly created loan
     */
    public function createLoanRequest(
        int $book_id,
        int $borrower_id,
        ?string $start_date,
        ?string $due_date,
        ?string $message
    ): string {
        $sql = "INSERT INTO loans (
                    book_id, borrower_id, owner_id, status,
                    start_date, due_date, message
                )
                VALUES (
                    :book_id,
                    :borrower_id,
                    (SELECT owner_id FROM books WHERE id = :book_id),
                    'pending',
                    :start_date,
                    :due_date,
                    :message
                )";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':book_id' => $book_id,
            ':borrower_id' => $borrower_id,
            ':start_date' => $start_date,
            ':due_date' => $due_date,
            ':message' => $message
        ]);
        
        return $this->conn->lastInsertId();
    }
    
    /**
     * Retrieves all loan requests received by a book owner
     * 
     * @param int $owner_id Owner's user ID
     * @return array Array of loans with book and borrower details
     */
    public function getReceivedLoans(int $owner_id): array {
        $sql = "SELECT 
                    l.*,
                    b.title AS book_title,
                    b.author AS book_author,
                    b.cover_image AS book_cover,
                    u.first_name AS borrower_first_name,
                    u.last_name AS borrower_last_name,
                    u.email AS borrower_email,
                    u.username AS borrower_username
                FROM loans l
                INNER JOIN books b ON l.book_id = b.id
                INNER JOIN users u ON l.borrower_id = u.id
                WHERE l.owner_id = :owner_id
                ORDER BY 
                    CASE l.status
                        WHEN 'pending' THEN 1
                        WHEN 'approved' THEN 2
                        WHEN 'done' THEN 3
                        WHEN 'cancelled' THEN 4
                    END,
                    l.created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':owner_id' => $owner_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieves all loans made by a borrower
     * 
     * @param int $borrower_id Borrower's user ID
     * @return array Array of loans with book and owner details
     */
    public function getMyLoans(int $borrower_id): array {
        $sql = "SELECT 
                    l.*,
                    b.title AS book_title,
                    b.author AS book_author,
                    b.cover_image AS book_cover,
                    b.isbn AS book_isbn,
                    u.first_name AS owner_first_name,
                    u.last_name AS owner_last_name,
                    u.email AS owner_email,
                    u.username AS owner_username
                FROM loans l
                INNER JOIN books b ON l.book_id = b.id
                INNER JOIN users u ON l.owner_id = u.id
                WHERE l.borrower_id = :borrower_id
                ORDER BY 
                    CASE l.status
                        WHEN 'approved' THEN 1
                        WHEN 'pending' THEN 2
                        WHEN 'done' THEN 3
                        WHEN 'cancelled' THEN 4
                    END,
                    l.created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':borrower_id' => $borrower_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieves a single loan by ID with full details
     * 
     * @param int $loan_id Loan ID
     * @return array|false Loan data with book and user details or false if not found
     */
    public function getLoanById(int $loan_id): array|false {
        $sql = "SELECT 
                    l.*,
                    b.title AS book_title,
                    b.author AS book_author,
                    b.isbn AS book_isbn,
                    b.cover_image AS book_cover,
                    u1.first_name AS borrower_first_name,
                    u1.last_name AS borrower_last_name,
                    u1.email AS borrower_email,
                    u1.username AS borrower_username,
                    u2.first_name AS owner_first_name,
                    u2.last_name AS owner_last_name,
                    u2.email AS owner_email,
                    u2.username AS owner_username
                FROM loans l
                INNER JOIN books b ON l.book_id = b.id
                INNER JOIN users u1 ON l.borrower_id = u1.id
                INNER JOIN users u2 ON l.owner_id = u2.id
                WHERE l.id = :loan_id
                LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':loan_id' => $loan_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Approves a loan request
     * 
     * @param int $loan_id Loan ID
     * @param int $owner_id Owner's user ID (for authorization)
     * @return bool True if successful
     */
    public function approveLoan(int $loan_id, int $owner_id): bool {
        return $this->updateLoanStatus(
            $loan_id,
            $owner_id,
            'approved',
            ['start_date' => 'NOW()'],
            'pending'
        );
    }

    /**
     * Declines a loan request
     * 
     * @param int $loan_id Loan ID
     * @param int $owner_id Owner's user ID (for authorization)
     * @return bool True if successful
     */
    public function declineLoan(int $loan_id, int $owner_id): bool {
        return $this->updateLoanStatus($loan_id, $owner_id, 'cancelled', [], 'pending');
    }

    /**
     * Marks a loan as completed (book returned)
     * 
     * @param int $loan_id Loan ID
     * @param int $owner_id Owner's user ID (for authorization)
     * @return bool True if successful
     */
    public function completeLoan(int $loan_id, int $owner_id): bool {
        return $this->updateLoanStatus(
            $loan_id,
            $owner_id,
            'done',
            ['return_date' => 'NOW()'],
            'approved'
        );
    }

    /**
     * Updates loan status with optional additional fields
     * 
     * @param int $loan_id Loan ID
     * @param int $owner_id Owner's user ID (for authorization)
     * @param string $new_status New status value
     * @param array $additional_fields Additional fields to update (field => value)
     * @param string|null $required_current_status Required current status (null = any)
     * @return bool True if successful
     */
    private function updateLoanStatus(
        int $loan_id,
        int $owner_id,
        string $new_status,
        array $additional_fields = [],
        ?string $required_current_status = null
    ): bool {
        $set_clauses = ["status = :status"];
        $params = [
            ':loan_id' => $loan_id,
            ':owner_id' => $owner_id,
            ':status' => $new_status
        ];

        // Add additional fields (like start_date, return_date)
        foreach ($additional_fields as $field => $value) {
            $set_clauses[] = "{$field} = {$value}";
        }

        $where_clauses = ["id = :loan_id", "owner_id = :owner_id"];
        
        // Optionally require specific current status
        if ($required_current_status !== null) {
            $where_clauses[] = "status = :current_status";
            $params[':current_status'] = $required_current_status;
        }

        $sql = "UPDATE loans 
                SET " . implode(', ', $set_clauses) . "
                WHERE " . implode(' AND ', $where_clauses);

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount() > 0;
    }
}
