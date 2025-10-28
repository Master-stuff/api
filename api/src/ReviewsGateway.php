<?php

/**
 * Reviews Data Gateway
 * 
 * Handles all database operations related to user reviews and ratings.
 * Uses prepared statements to prevent SQL injection.
 */
class ReviewsGateway {
    private PDO $conn;

    public function __construct(Database $database) {
        $this->conn = $database->getConnection();
    }

    /**
     * Creates a new review for a user after a completed loan
     * 
     * @param int $loan_id Loan ID
     * @param int $reviewer_id User ID of the reviewer
     * @param int $rated_user_id User ID being reviewed
     * @param int $rating Rating value (1-5)
     * @param string|null $comment Optional review comment
     * @return string The ID of the newly created review
     */
    public function createReview(
        int $loan_id,
        int $reviewer_id,
        int $rated_user_id,
        int $rating,
        ?string $comment
    ): string {
        $sql = "INSERT INTO reviews (
                    loan_id, reviewer_id, rated_user_id, rating, comment
                )
                VALUES (
                    :loan_id, :reviewer_id, :rated_user_id, :rating, :comment
                )";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':loan_id' => $loan_id,
            ':reviewer_id' => $reviewer_id,
            ':rated_user_id' => $rated_user_id,
            ':rating' => $rating,
            ':comment' => $comment
        ]);
        
        return $this->conn->lastInsertId();
    }

    /**
     * Retrieves all reviews for a specific user with reviewer details
     * 
     * @param int $user_id User ID to get reviews for
     * @return array Array of reviews with reviewer information
     */
    public function getReviewsByUser(int $user_id): array {
        $sql = "SELECT 
                    r.*,
                    u.username AS reviewer_username,
                    u.first_name AS reviewer_first_name,
                    u.last_name AS reviewer_last_name,
                    b.title AS book_title,
                    b.author AS book_author
                FROM reviews r
                INNER JOIN users u ON r.reviewer_id = u.id
                INNER JOIN loans l ON r.loan_id = l.id
                INNER JOIN books b ON l.book_id = b.id
                WHERE r.rated_user_id = :user_id
                ORDER BY r.created_at DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieves average rating and review count for a user
     * 
     * @param int $user_id User ID
     * @return array Array with 'average_rating' and 'review_count'
     */
    public function getUserRatingStats(int $user_id): array {
        $sql = "SELECT 
                    COALESCE(AVG(rating), 0) AS average_rating,
                    COUNT(*) AS review_count
                FROM reviews
                WHERE rated_user_id = :user_id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'average_rating' => round((float)$result['average_rating'], 2),
            'review_count' => (int)$result['review_count']
        ];
    }

    /**
     * Checks if a review already exists for a loan
     * 
     * @param int $loan_id Loan ID
     * @return bool True if review exists
     */
    public function reviewExists(int $loan_id): bool {
        $sql = "SELECT COUNT(*) as count FROM reviews WHERE loan_id = :loan_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':loan_id' => $loan_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    /**
     * Retrieves a single review by ID
     * 
     * @param int $review_id Review ID
     * @return array|false Review data or false if not found
     */
    public function getReviewById(int $review_id): array|false {
        $sql = "SELECT 
                    r.*,
                    u.username AS reviewer_username,
                    u.first_name AS reviewer_first_name,
                    u.last_name AS reviewer_last_name
                FROM reviews r
                INNER JOIN users u ON r.reviewer_id = u.id
                WHERE r.id = :review_id
                LIMIT 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':review_id' => $review_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieves a review by loan ID
     * 
     * @param int $loan_id Loan ID
     * @return array|false Review data or false if not found
     */
    public function getReviewByLoanId(int $loan_id): array|false {
        $sql = "SELECT * FROM reviews WHERE loan_id = :loan_id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':loan_id' => $loan_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieves loan details to validate review eligibility
     * 
     * @param int $loan_id Loan ID
     * @return array|false Loan data or false if not found
     */
    public function getLoanDetails(int $loan_id): array|false {
        $sql = "SELECT * FROM loans WHERE id = :loan_id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':loan_id' => $loan_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
