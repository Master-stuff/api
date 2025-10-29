# HTTPie API Communication Guide

This guide demonstrates how to interact with the Book Lending API using HTTPie, a command-line HTTP client.

## Installation

```bash
pip install httpie
```

## Base URL

```
http://localhost:8000/api
```

## Authentication

Most endpoints require a JWT token. After login, include the token in the Authorization header:

```bash
http GET http://localhost:8000/api/books Authorization:"Bearer YOUR_TOKEN_HERE"
```

---

## Authentication Endpoints

### 1. Register a New User

```bash
http POST http://localhost:8000/api/register \
  email=john@example.com \
  username=john_doe \
  first_name=John \
  last_name=Doe \
  password=SecurePass123
```

**Response (201):**
```json
{
  "message": "User registered successfully",
  "user_id": 1
}
```

### 2. Login

```bash
http POST http://localhost:8000/api/login \
  email=john@example.com \
  password=SecurePass123
```

**Response (200):**
```json
{
  "message": "Login successful",
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user": {
    "id": 1,
    "email": "john@example.com",
    "username": "john_doe"
  }
}
```

### 3. Get Current User Profile

```bash
http GET http://localhost:8000/api/users/me \
  Authorization:"Bearer YOUR_TOKEN"
```

### 4. Get User's Books

```bash
http GET http://localhost:8000/api/users/me/books \
  Authorization:"Bearer YOUR_TOKEN"
```

### 5. Update User Profile

```bash
http PUT http://localhost:8000/api/users/me \
  Authorization:"Bearer YOUR_TOKEN" \
  username=new_username \
  email=newemail@example.com
```

### 6. Get User by ID

```bash
http GET http://localhost:8000/api/users/1
```

### 7. Get User's Books by ID

```bash
http GET http://localhost:8000/api/users/1/books
```

---

## Book Endpoints

### 1. Get All Books

```bash
http GET http://localhost:8000/api/books
```

### 2. Get Single Book

```bash
http GET http://localhost:8000/api/books/1
```

### 3. Create a New Book

```bash
http POST http://localhost:8000/api/books \
  Authorization:"Bearer YOUR_TOKEN" \
  title="The Great Gatsby" \
  author="F. Scott Fitzgerald" \
  isbn="978-0743273565" \
  description="A classic American novel" \
  cover_image="https://example.com/cover.jpg"
```

**Response (201):**
```json
{
  "message": "Book created successfully",
  "book_id": 5
}
```

### 4. Update a Book

```bash
http PUT http://localhost:8000/api/books/5 \
  Authorization:"Bearer YOUR_TOKEN" \
  title="The Great Gatsby (Updated)" \
  description="Updated description"
```

### 5. Delete a Book

```bash
http DELETE http://localhost:8000/api/books/5 \
  Authorization:"Bearer YOUR_TOKEN"
```

### 6. Upload Book Cover Image

```bash
http --form POST http://localhost:8000/api/books/5/upload-cover \
  Authorization:"Bearer YOUR_TOKEN" \
  cover@/path/to/image.jpg
```

**Response (200):**
```json
{
  "message": "Cover image uploaded successfully",
  "cover_path": "public/covers/book_5_a1b2c3d4e5f6.jpg"
}
```

---

## Loan Endpoints

### 1. Request a Book Loan

```bash
http POST http://localhost:8000/api/loans/request \
  Authorization:"Bearer YOUR_TOKEN" \
  book_id=3 \
  start_date="2025-01-20" \
  due_date="2025-02-20" \
  message="I would love to read this book!"
```

**Response (201):**
```json
{
  "message": "Loan request created successfully",
  "loan_id": 12
}
```

### 2. Get Received Loan Requests (as Book Owner)

```bash
http GET http://localhost:8000/api/loans/received \
  Authorization:"Bearer YOUR_TOKEN"
```

### 3. Get My Borrowed Books (as Borrower)

```bash
http GET http://localhost:8000/api/loans/my-borrowed \
  Authorization:"Bearer YOUR_TOKEN"
```

### 4. Approve a Loan Request

```bash
http PUT http://localhost:8000/api/loans/12/approve \
  Authorization:"Bearer YOUR_TOKEN"
```

### 5. Decline a Loan Request

```bash
http PUT http://localhost:8000/api/loans/12/decline \
  Authorization:"Bearer YOUR_TOKEN"
```

### 6. Complete a Loan

```bash
http PUT http://localhost:8000/api/loans/12/complete \
  Authorization:"Bearer YOUR_TOKEN"
```

---

## Reviews & Ratings Endpoints

### 1. Submit a Review

Submit a rating and optional comment for a user after a completed loan.

```bash
http POST http://localhost:8000/api/reviews \
  Authorization:"Bearer YOUR_TOKEN" \
  loan_id=12 \
  rating=5 \
  comment="Great book owner! Very responsive and the book was in excellent condition."
```

**Response (201):**
```json
{
  "message": "Review submitted successfully",
  "review_id": 5
}
```

**Requirements:**
- Loan must be in "done" status (completed)
- You must be either the borrower or lender in the loan
- Only one review per loan allowed
- Rating must be 1-5
- Comment is optional (max 1000 characters)

### 2. Get User Reviews and Rating Stats

Retrieve all reviews for a user along with their average rating (public endpoint).

```bash
http GET http://localhost:8000/api/reviews/1
```

**Response (200):**
```json
{
  "user_id": 1,
  "average_rating": 4.5,
  "review_count": 4,
  "reviews": [
    {
      "id": 5,
      "loan_id": 12,
      "reviewer_id": 2,
      "reviewer_username": "jane_doe",
      "reviewer_first_name": "Jane",
      "reviewer_last_name": "Doe",
      "rating": 5,
      "comment": "Great book owner! Very responsive and the book was in excellent condition.",
      "book_title": "The Great Gatsby",
      "book_author": "F. Scott Fitzgerald",
      "created_at": "2025-01-25T14:30:00+00:00"
    },
    {
      "id": 4,
      "loan_id": 10,
      "reviewer_id": 3,
      "reviewer_username": "john_smith",
      "reviewer_first_name": "John",
      "reviewer_last_name": "Smith",
      "rating": 4,
      "comment": "Good experience, book was clean and well-maintained.",
      "book_title": "1984",
      "book_author": "George Orwell",
      "created_at": "2025-01-20T10:15:00+00:00"
    }
  ]
}
```

### 3. Review Submission Examples

#### 5-Star Review with Comment

```bash
http POST http://localhost:8000/api/reviews \
  Authorization:"Bearer YOUR_TOKEN" \
  loan_id=15 \
  rating=5 \
  comment="Excellent borrower! Returned the book on time in perfect condition."
```

#### 3-Star Review with Comment

```bash
http POST http://localhost:8000/api/reviews \
  Authorization:"Bearer YOUR_TOKEN" \
  loan_id=16 \
  rating=3 \
  comment="Good experience overall, but the book had some minor wear."
```

#### Rating Without Comment

```bash
http POST http://localhost:8000/api/reviews \
  Authorization:"Bearer YOUR_TOKEN" \
  loan_id=17 \
  rating=4
```

### 4. Error Handling Examples

#### Loan Not Found

```bash
http POST http://localhost:8000/api/reviews \
  Authorization:"Bearer YOUR_TOKEN" \
  loan_id=999 \
  rating=5
```

**Response (404):**
```json
{
  "error": "Loan not found"
}
```

#### Loan Not Completed

```bash
http POST http://localhost:8000/api/reviews \
  Authorization:"Bearer YOUR_TOKEN" \
  loan_id=12 \
  rating=5
```

**Response (400):**
```json
{
  "error": "Can only review completed loans (status: done)"
}
```

#### Review Already Exists

```bash
http POST http://localhost:8000/api/reviews \
  Authorization:"Bearer YOUR_TOKEN" \
  loan_id=12 \
  rating=5
```

**Response (400):**
```json
{
  "error": "A review already exists for this loan"
}
```

#### Invalid Rating

```bash
http POST http://localhost:8000/api/reviews \
  Authorization:"Bearer YOUR_TOKEN" \
  loan_id=12 \
  rating=10
```

**Response (400):**
```json
{
  "error": "Rating must be between 1 and 5"
}
```

#### Unauthorized User

```bash
http POST http://localhost:8000/api/reviews \
  Authorization:"Bearer WRONG_TOKEN" \
  loan_id=12 \
  rating=5
```

**Response (403):**
```json
{
  "error": "Unauthorized: You are not part of this loan"
}
```

### 5. Workflow Example: Complete Loan and Review

Here's a typical workflow for completing a loan and submitting a review:

```bash
# 1. Login and save token
TOKEN=$(http POST http://localhost:8000/api/login \
  email=john@example.com \
  password=SecurePass123 | jq -r '.token')

# 2. View completed loans
http GET http://localhost:8000/api/loans/my-borrowed \
  Authorization:"Bearer $TOKEN" | jq '.[] | select(.status=="done")'

# 3. Submit a review for a completed loan
http POST http://localhost:8000/api/reviews \
  Authorization:"Bearer $TOKEN" \
  loan_id=12 \
  rating=5 \
  comment="Excellent experience!"

# 4. View the user's reviews and rating
http GET http://localhost:8000/api/reviews/2
```

---

## Error Handling Examples

### Invalid JSON

```bash
http POST http://localhost:8000/api/users/login \
  email=test@example.com
```

**Response (400):**
```json
{
  "error": "Invalid JSON format",
  "details": "Syntax error"
}
```

### Missing Required Fields

```bash
http POST http://localhost:8000/api/books \
  Authorization:"Bearer YOUR_TOKEN" \
  author="F. Scott Fitzgerald"
```

**Response (400):**
```json
{
  "error": "Validation failed",
  "details": "Title is required"
}
```

### Unauthorized Access

```bash
http DELETE http://localhost:8000/api/books/5 \
  Authorization:"Bearer WRONG_TOKEN"
```

**Response (403):**
```json
{
  "error": "Unauthorized: You can only delete your own books"
}
```

### Resource Not Found

```bash
http GET http://localhost:8000/api/books/999
```

**Response (404):**
```json
{
  "error": "Book not found"
}
```

---

## Tips & Tricks

### Save Token to Variable

```bash
TOKEN=$(http POST http://localhost:8000/api/login \
  email=john@example.com \
  password=SecurePass123 | jq -r '.token')

echo $TOKEN
```

### Use Token in Subsequent Requests

```bash
http GET http://localhost:8000/api/users/me \
  Authorization:"Bearer $TOKEN"
```

### Pretty Print JSON Response

```bash
http GET http://localhost:8000/api/reviews/1 | jq '.'
```

### Save Response to File

```bash
http GET http://localhost:8000/api/reviews/1 > user_reviews.json
```

### Upload Multiple Files

```bash
http --form POST http://localhost:8000/api/books/5/upload-cover \
  Authorization:"Bearer YOUR_TOKEN" \
  cover@/path/to/image.jpg
```

---

## Common Status Codes

| Code | Meaning |
|------|---------|
| 200 | OK - Request successful |
| 201 | Created - Resource created successfully |
| 400 | Bad Request - Invalid input or malformed request |
| 401 | Unauthorized - Missing or invalid authentication |
| 403 | Forbidden - Authenticated but not authorized |
| 404 | Not Found - Resource doesn't exist |
| 405 | Method Not Allowed - HTTP method not supported |
| 500 | Internal Server Error - Server error |
