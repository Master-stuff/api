-- Reviews & Ratings Table
-- Stores user reviews and ratings after completed loans

CREATE TABLE IF NOT EXISTS reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    loan_id INT NOT NULL UNIQUE,
    reviewer_id INT NOT NULL,
    rated_user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (rated_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_loan_id (loan_id),
    INDEX idx_reviewer_id (reviewer_id),
    INDEX idx_rated_user_id (rated_user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index for efficient average rating queries
CREATE INDEX idx_reviews_rated_user ON reviews(rated_user_id);
