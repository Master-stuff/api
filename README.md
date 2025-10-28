# Book Lending API

A RESTful API for managing book lending and borrowing between users. Built with PHP and MySQL.

## Features

- **User Authentication**: Register, login, and manage user profiles
- **Book Management**: List, create, update, and delete books
- **Loan System**: Request, approve, and manage book loans
- **Reviews & Ratings**: Rate and review users after completed transactions
- **File Management**: Upload and manage book cover images

## Getting Started

### Prerequisites

- PHP 8.0+
- MySQL 5.7+
- Composer (for dependency management)
- HTTPie (optional, for testing API endpoints)

### Installation

1. Clone the repository

2. Install dependencies:
   \`\`\`bash
   cd api
   composer install
   \`\`\`

3. Set up environment variables in `api/.env`:
   \`\`\`
   DB_HOST=localhost
   DB_USER=root
   DB_PASS=password
   DB_NAME=book_lending
   SECRET_KEY=your_secret_key_here
   APP_ENV=development
   \`\`\`

4. Create the database and run migrations:
   \`\`\`bash
   mysql -u root -p < api/scripts/001_init_database.sql
   mysql -u root -p < api/scripts/002_seed_data.sql
   mysql -u root -p < api/scripts/003_migrations.sql
   mysql -u root -p < api/scripts/004_reviews_table.sql
   \`\`\`

5. Start your PHP server from the root directory:
   \`\`\`bash
   php -S localhost:8000
   \`\`\`

6. Access the frontend:
   - Open `http://localhost:8000/` in your browser (serves `public/index.html`)
   - API endpoints are available at `http://localhost:8000/api/*`

## Project Structure

\`\`\`
/
├── .htaccess (Root routing configuration)
├── public/ (Frontend files)
│   ├── index.html (Frontend example)
│   └── covers/ (Uploaded book covers)
└── api/
    ├── .htaccess (API-specific configuration)
    ├── index.php (API entry point)
    ├── bootstrap.php (Application bootstrap)
    ├── composer.json
    ├── src/
    │   ├── AuthController.php
    │   ├── AuthGateway.php
    │   ├── AuthMiddleware.php
    │   ├── BookController.php
    │   ├── BookGateway.php
    │   ├── Database.php
    │   ├── ErrorHandler.php
    │   ├── FileManager.php
    │   ├── JwtManager.php
    │   ├── LoansController.php
    │   ├── LoansGateway.php
    │   ├── ReviewsController.php
    │   ├── ReviewsGateway.php
    │   └── ValidationErrors.php
    └── scripts/
        ├── 001_init_database.sql
        ├── 002_seed_data.sql
        ├── 003_migrations.sql
        └── 004_reviews_table.sql
\`\`\`

## URL Structure

- **Frontend**: `http://localhost:8000/` → serves `public/index.html`
- **API**: `http://localhost:8000/api/*` → routes to `api/index.php`
- **Static Files**: `http://localhost:8000/style.css` → serves `public/style.css`

## Frontend

### Example Frontend (public/index.html)

A simple HTML/JavaScript frontend is included in `public/index.html` that demonstrates:
- User registration and login
- Creating and browsing books
- Requesting and managing loans
- Submitting and viewing reviews

**For Production:**
- Use a modern frontend framework (React, Vue, Angular, etc.)
- Place your frontend code in `public/` or a separate repository
- Configure CORS properly in `.htaccess` for cross-origin requests
- Use environment variables for API base URL

### Frontend Structure Options

**Option 1: In public/ (Current Setup)**
\`\`\`
/
├── .htaccess
├── public/
│   ├── index.html
│   ├── css/
│   ├── js/
│   └── ...
└── api/
    ├── index.php
    └── src/
\`\`\`

**Option 2: Separate Frontend Repository**
\`\`\`
/backend (this repo)
  ├── public/
  └── api/
      ├── index.php
      └── src/

/frontend (separate repo)
  ├── src/
  ├── package.json
  └── ...
\`\`\`

**Option 3: Next.js/React Frontend**
\`\`\`
/backend (this repo)
  ├── public/
  └── api/
      ├── index.php
      └── src/

/frontend (Next.js app)
  ├── app/
  ├── components/
  ├── package.json
  └── ...
\`\`\`

## API Endpoints

### Authentication
- `POST /api/register` - Register a new user
- `POST /api/login` - Login user

### Books
- `GET /api/books` - Get all books
- `POST /api/books` - Create a new book (requires auth)
- `GET /api/books/{bookId}` - Get single book
- `PUT /api/books/{bookId}` - Update book (requires auth & ownership)
- `DELETE /api/books/{bookId}` - Delete book (requires auth & ownership)
- `POST /api/books/{bookId}/upload-cover` - Upload book cover (requires auth & ownership)

### Loans
- `POST /api/loans/request` - Request to borrow a book (requires auth)
- `GET /api/loans/received` - Get received loan requests (requires auth)
- `GET /api/loans/my-borrowed` - Get borrowed books (requires auth)
- `PUT /api/loans/{loanId}/approve` - Approve loan request (requires auth & ownership)
- `PUT /api/loans/{loanId}/decline` - Decline loan request (requires auth & ownership)
- `PUT /api/loans/{loanId}/complete` - Complete loan (requires auth & ownership)

### Reviews & Ratings
- `POST /api/reviews` - Submit a review (requires auth)
- `GET /api/reviews/{userId}` - Get user's reviews and rating stats (public)

## Reviews & Ratings

Users can rate and review each other after a loan is completed. Each review includes:
- **Rating**: 1-5 stars
- **Comment**: Optional text feedback (max 1000 characters)
- **Loan ID**: Reference to the completed loan

### Eligibility
- Only users involved in a completed loan can submit a review
- Each loan can only have one review
- Users cannot review themselves

### Rating Statistics
When retrieving user reviews, you get:
- **Average Rating**: Calculated from all reviews
- **Review Count**: Total number of reviews received
- **Reviews List**: All individual reviews with reviewer details

## Authentication

Most endpoints require JWT authentication. Include the token in the Authorization header:

\`\`\`
Authorization: Bearer YOUR_TOKEN_HERE
\`\`\`

## Error Handling

The API returns appropriate HTTP status codes:
- `200` - OK
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `500` - Internal Server Error

## Testing

See [HTTPIE_GUIDE.md](HTTPIE_GUIDE.md) for detailed examples of testing all endpoints with HTTPie.

## License

MIT
