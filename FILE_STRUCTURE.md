# Project File Structure

This document describes the organization and purpose of files in the Book Lending API project.

## Directory Overview

\`\`\`
project-root/
├── src/                          # PHP source code
├── public/                       # Public assets
├── scripts/                      # Database and utility scripts
├── vendor/                       # Composer dependencies
├── bootstrap.php                 # Application bootstrap
├── index.php                     # Main entry point
├── composer.json                 # PHP dependencies
├── .env                          # Environment variables (not in repo)
├── README.md                     # Main documentation
├── HTTPIE_GUIDE.md              # HTTPie usage guide
├── FILE_STRUCTURE.md            # This file
└── docs.md                       # Additional documentation
\`\`\`

## Core Files

### `index.php`
- **Purpose**: Main entry point for all API requests
- **Responsibility**: 
  - Route incoming requests to appropriate controllers
  - Handle CORS headers
  - Set error handlers
  - Manage global exception handling

### `bootstrap.php`
- **Purpose**: Application initialization
- **Responsibility**:
  - Load environment variables
  - Initialize database connection
  - Set up configuration

## Source Code (`src/`)

### Authentication & Authorization

#### `Auth.php`
- **Purpose**: User authentication logic
- **Methods**:
  - `authenticate()` - Verify user credentials
  - `generateToken()` - Create JWT token
  - `validateToken()` - Verify JWT token

#### `AuthController.php`
- **Purpose**: Handle authentication endpoints
- **Endpoints**:
  - `POST /users/register` - Register new user
  - `POST /users/login` - User login
  - `GET /users/me` - Get current user profile
  - `PUT /users/me` - Update user profile
  - `GET /users/{id}` - Get user by ID
  - `GET /users/{id}/books` - Get user's books

#### `AuthGateway.php`
- **Purpose**: Database operations for users
- **Methods**:
  - `createUser()` - Insert new user
  - `getUserById()` - Fetch user by ID
  - `verifyUser()` - Verify login credentials
  - `updateUser()` - Update user data
  - `getUserByEmail()` - Find user by email

#### `AuthMiddleware.php`
- **Purpose**: JWT token validation middleware
- **Methods**:
  - `authenticate()` - Validate and extract JWT token
  - `extractToken()` - Parse Authorization header

#### `JwtManager.php`
- **Purpose**: JWT token creation and validation
- **Methods**:
  - `createToken()` - Generate JWT token
  - `validateToken()` - Verify token signature and expiration
  - `decodeToken()` - Extract payload from token

### Book Management

#### `BookController.php`
- **Purpose**: Handle book endpoints
- **Endpoints**:
  - `GET /books` - Get all books
  - `GET /books/{id}` - Get single book
  - `POST /books` - Create new book
  - `PUT /books/{id}` - Update book
  - `DELETE /books/{id}` - Delete book
  - `POST /books/{id}/upload-cover` - Upload cover image

#### `BookGateway.php`
- **Purpose**: Database operations for books
- **Methods**:
  - `create()` - Insert new book
  - `get()` - Fetch book by ID
  - `getAll()` - Fetch all books
  - `getByOwner()` - Fetch books by owner
  - `update()` - Update book data
  - `delete()` - Delete book
  - `updateCoverImage()` - Update cover image path

### Loan Management

#### `LoansController.php`
- **Purpose**: Handle loan endpoints
- **Endpoints**:
  - `POST /loans/request` - Request a book loan
  - `GET /loans/received` - Get received loan requests
  - `GET /loans/my-borrowed` - Get borrowed books
  - `PUT /loans/{id}/approve` - Approve loan request
  - `PUT /loans/{id}/decline` - Decline loan request
  - `PUT /loans/{id}/complete` - Complete loan

#### `LoansGateway.php`
- **Purpose**: Database operations for loans
- **Methods**:
  - `createLoanRequest()` - Create new loan request
  - `getLoanById()` - Fetch loan by ID
  - `getReceivedLoans()` - Get loans received by user
  - `getMyLoans()` - Get loans made by user
  - `approveLoan()` - Approve loan request
  - `declineLoan()` - Decline loan request
  - `completeLoan()` - Mark loan as complete
  - `updateLoanStatus()` - Update loan status

### File Management

#### `FileManager.php`
- **Purpose**: Secure file upload and management
- **Methods**:
  - `uploadCoverImage()` - Upload and validate image file
  - `deleteCoverImage()` - Delete image file
  - `validateMimeType()` - Verify file MIME type
  - `validateFileSize()` - Check file size limits
  - `generateSecureFilename()` - Create secure filename

### Database

#### `Database.php`
- **Purpose**: Database connection and query execution
- **Methods**:
  - `__construct()` - Initialize PDO connection
  - `query()` - Execute prepared statement
  - `beginTransaction()` - Start database transaction
  - `commit()` - Commit transaction
  - `rollback()` - Rollback transaction

### Error Handling

#### `ErrorHandler.php`
- **Purpose**: Global error and exception handling
- **Methods**:
  - `handleError()` - Handle PHP errors
  - `handleException()` - Handle exceptions
  - `logError()` - Log errors to file

#### `ValidationErrors.php`
- **Purpose**: Input validation for all endpoints
- **Methods**:
  - `validateRegister()` - Validate registration data
  - `validateLogin()` - Validate login data
  - `validateUpdate()` - Validate user update data
  - `validateBook()` - Validate book data
  - `validateLoan()` - Validate loan data

## Public Assets (`public/`)

\`\`\`
public/
├── covers/                       # Book cover images
│   ├── book_1_a1b2c3d4e5f6.jpg
│   ├── book_2_b2c3d4e5f6g7.png
│   └── ...
└── placeholder.jpg              # Default placeholder image
\`\`\`

## Database Scripts (`scripts/`)

\`\`\`
scripts/
├── 001_init_database.sql        # Initial schema creation
├── 002_seed_data.sql            # Sample data
└── 003_migrations.sql           # Schema updates
\`\`\`

## Configuration Files

### `.env` (Not in repository)
\`\`\`
APP_ENV=development
DB_HOST=localhost
DB_USER=root
DB_PASS=password
DB_NAME=book_lending
SECRET_KEY=your-secret-key-here
\`\`\`

### `composer.json`
- Defines PHP dependencies
- Currently uses: `firebase/php-jwt` for JWT handling

## Data Flow

### User Registration Flow
\`\`\`
POST /users/register
  ↓
AuthController::handleRegister()
  ↓
ValidationErrors::validateRegister()
  ↓
AuthGateway::createUser()
  ↓
Database::query()
  ↓
Response: 201 Created
\`\`\`

### Book Upload Flow
\`\`\`
POST /books/{id}/upload-cover
  ↓
AuthMiddleware::authenticate()
  ↓
BookController::handleUploadCover()
  ↓
FileManager::uploadCoverImage()
  ↓
FileManager::validateMimeType()
  ↓
FileManager::validateFileSize()
  ↓
BookGateway::updateCoverImage()
  ↓
Response: 200 OK
\`\`\`

### Loan Request Flow
\`\`\`
POST /loans/request
  ↓
AuthMiddleware::authenticate()
  ↓
LoansController::handleRequestLoan()
  ↓
LoansController::validateLoanRequest()
  ↓
LoansGateway::createLoanRequest()
  ↓
Response: 201 Created
\`\`\`

## Security Considerations

- **JWT Tokens**: Stored in Authorization header, validated on each request
- **Password Hashing**: Uses bcrypt (PASSWORD_BCRYPT)
- **File Uploads**: MIME type validation, secure filename generation
- **SQL Injection**: Prevented using prepared statements
- **CORS**: Configured to allow cross-origin requests
- **Authorization**: User ownership checks on all modifications

## Performance Optimizations

- **Database Queries**: Use prepared statements to prevent SQL injection
- **Transactions**: Used for multi-step operations (e.g., loan approval)
- **Caching**: JWT tokens cached in memory during request lifecycle
- **File Storage**: Secure filenames prevent directory traversal attacks
