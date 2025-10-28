-- Book Lending API - Database Migrations
-- Run this script to update the database schema

-- Add cover_image_path column if it doesn't exist (for local file storage)
ALTER TABLE books ADD COLUMN IF NOT EXISTS cover_image_path VARCHAR(500);

-- Add indexes for performance optimization
CREATE INDEX IF NOT EXISTS idx_loans_dates ON loans(start_date, due_date);
CREATE INDEX IF NOT EXISTS idx_users_created ON users(created_at);

-- Add check constraint for valid email format (MySQL 8.0.16+)
-- ALTER TABLE users ADD CONSTRAINT check_email_format CHECK (email REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}$');

-- Add audit trail table for tracking changes
CREATE TABLE IF NOT EXISTS audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    table_name VARCHAR(100) NOT NULL,
    record_id INT NOT NULL,
    action ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL,
    user_id INT,
    changes JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_table_record (table_name, record_id),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
