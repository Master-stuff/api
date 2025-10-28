-- Book Lending API - Sample Data
-- This script populates the database with sample data for testing

-- Insert sample users
INSERT INTO users (email, username, pwd) VALUES
('alice@example.com', 'alice', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/KFm'),
('bob@example.com', 'bob', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/KFm'),
('charlie@example.com', 'charlie', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcg7b3XeKeUxWdeS86E36P4/KFm');

-- Insert sample books
INSERT INTO books (title, author, isbn, description, owner_id) VALUES
('The Great Gatsby', 'F. Scott Fitzgerald', '978-0743273565', 'A classic American novel set in the Jazz Age', 1),
('To Kill a Mockingbird', 'Harper Lee', '978-0061120084', 'A gripping tale of racial injustice and childhood innocence', 1),
('1984', 'George Orwell', '978-0451524935', 'A dystopian novel about totalitarianism', 2),
('Pride and Prejudice', 'Jane Austen', '978-0141439518', 'A romantic novel of manners', 2),
('The Catcher in the Rye', 'J.D. Salinger', '978-0316769174', 'A story of teenage rebellion and alienation', 3);

-- Insert sample loans
INSERT INTO loans (book_id, borrower_id, owner_id, status, start_date, due_date, message) VALUES
(1, 2, 1, 'pending', '2025-01-20', '2025-02-20', 'I would love to read this classic!'),
(3, 1, 2, 'approved', '2025-01-15', '2025-02-15', 'Thanks for lending this book'),
(5, 2, 3, 'done', '2025-01-01', '2025-01-31', '2025-01-28', 'Great book, thanks!');
