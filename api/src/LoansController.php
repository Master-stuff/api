<?php

/**
 * Loans Controller
 * 
 * Handles book loan requests, approvals, and completions.
 * Implements authorization checks to ensure users can only modify their own loans.
 */
class LoansController {
    private const STATUS_PENDING = 'pending';
    private const STATUS_APPROVED = 'approved';
    private const STATUS_CANCELLED = 'cancelled';
    private const STATUS_DONE = 'done';
    
    private const MAX_MESSAGE_LENGTH = 500;
    private const DATE_FORMAT = 'Y-m-d';

    private int $userId;
    private JwtManager $jwtManager;
    private AuthMiddleware $authMiddleware;

    public function __construct(
        private LoansGateway $gateway,
        private BookGateway $gatebook
    ) {
        $this->jwtManager = new JwtManager($_ENV['SECRET_KEY']);
        $this->authMiddleware = new AuthMiddleware($this->jwtManager);
    }

    /**
     * Routes loan requests to appropriate handlers
     * 
     * @param array $request Request path segments
     * @param string $method HTTP method
     */
    public function processRequest(array $request, string $method): void {
        try {
            $payload = $this->authMiddleware->authenticate();
            if (!$payload) return;
            
            $this->userId = $payload['id'];
            
            $this->routeRequest($request, $method);
        } catch (Exception $e) {
            $this->sendError(500, "Internal server error", $e->getMessage());
        }
    }

    /**
     * Routes requests based on endpoint and method
     * 
     * @param array $request Request path segments
     * @param string $method HTTP method
     */
    private function routeRequest(array $request, string $method): void {
        $endpoint = $request[0] ?? null;
        $action = $request[1] ?? null;

        match(true) {
            $method === 'POST' && $endpoint === 'request'
                => $this->handleRequestLoan(),
            
            $method === 'GET' && $endpoint === 'received'
                => $this->handleReceivedLoans(),
            
            $method === 'GET' && $endpoint === 'my-borrowed'
                => $this->handleMyLoans(),
            
            $method === 'PUT' && $action === 'approve'
                => $this->handleLoanStatusChange($endpoint, 'approve'),
            
            $method === 'PUT' && $action === 'decline'
                => $this->handleLoanStatusChange($endpoint, 'decline'),
            
            $method === 'PUT' && $action === 'complete'
                => $this->handleLoanStatusChange($endpoint, 'complete'),
            
            default => $this->sendError(404, "Endpoint not found")
        };
    }

    /**
     * Handles creating a new loan request
     */
    private function handleRequestLoan(): void {
        $input = $this->getJsonInput();
        if (!$input) return;

        // Validate input
        $validation = $this->validateLoanRequest($input);
        if (!$validation['valid']) {
            $this->sendError(400, $validation['error']);
            return;
        }

        try {
            $loanId = $this->gateway->createLoanRequest(
                $input["book_id"],
                $this->userId,
                $input["start_date"] ?? null,
                $input["due_date"] ?? null,
                $input["message"] ?? null
            );

            $this->sendSuccess(201, [
                "message" => "Loan request created successfully",
                "loan_id" => $loanId
            ]);
        } catch (Exception $e) {
            $this->sendError(500, "Failed to create loan request", $e->getMessage());
        }
    }

    /**
     * Validates loan request data
     * 
     * @param array $data Loan request data
     * @return array Validation result with 'valid' boolean and optional 'error' message
     */
    private function validateLoanRequest(array $data): array {
        // Check required fields
        if (!isset($data["book_id"])) {
            return ['valid' => false, 'error' => "Missing required field: book_id"];
        }

        if (!$this->isValidId($data["book_id"])) {
            return ['valid' => false, 'error' => "Invalid book_id: must be a positive integer"];
        }

        $book = $this->gatebook->get($data['book_id']);
        if (!$book) {
            return ['valid' => false, 'error' => "Book not found"];
        }

        if ($book['owner_id'] === $this->userId) {
            return ['valid' => false, 'error' => "Cannot borrow your own book"];
        }

        // Validate dates
        if (isset($data["start_date"]) && !$this->isValidDate($data["start_date"])) {
            return ['valid' => false, 'error' => "Invalid start_date format. Use " . self::DATE_FORMAT];
        }

        if (isset($data["due_date"]) && !$this->isValidDate($data["due_date"])) {
            return ['valid' => false, 'error' => "Invalid due_date format. Use " . self::DATE_FORMAT];
        }

        if (isset($data["start_date"], $data["due_date"]) &&
            strtotime($data["due_date"]) < strtotime($data["start_date"])) {
            return ['valid' => false, 'error' => "Due date must be after start date"];
        }

        // Validate message length
        if (isset($data["message"]) && strlen($data["message"]) > self::MAX_MESSAGE_LENGTH) {
            return ['valid' => false, 'error' => "Message too long (max " . self::MAX_MESSAGE_LENGTH . " characters)"];
        }

        return ['valid' => true];
    }

    /**
     * Handles retrieving loans received by the current user (as book owner)
     */
    private function handleReceivedLoans(): void {
        try {
            $loans = $this->gateway->getReceivedLoans($this->userId);
            $this->sendJson($loans);
        } catch (Exception $e) {
            $this->sendError(500, "Failed to retrieve received loans", $e->getMessage());
        }
    }

    /**
     * Handles retrieving loans made by the current user (as borrower)
     */
    private function handleMyLoans(): void {
        try {
            $loans = $this->gateway->getMyLoans($this->userId);
            $this->sendJson($loans);
        } catch (Exception $e) {
            $this->sendError(500, "Failed to retrieve your loans", $e->getMessage());
        }
    }

    /**
     * Handles loan status changes (approve, decline, complete)
     * 
     * @param string $loanId Loan ID
     * @param string $action Action to perform (approve, decline, complete)
     */
    private function handleLoanStatusChange(string $loanId, string $action): void {
        // Validate loan ID
        if (!$this->isValidId($loanId)) {
            $this->sendError(400, "Invalid loan ID");
            return;
        }

        try {
            $loan = $this->gateway->getLoanById((int)$loanId);
            
            if (!$loan) {
                $this->sendError(404, "Loan not found");
                return;
            }

            if (!$this->canModifyLoan($loan)) {
                $this->sendError(403, "Unauthorized: You can only modify loans for your own books");
                return;
            }

            $validation = $this->validateStatusTransition($loan['status'], $action);
            if (!$validation['valid']) {
                $this->sendError(400, $validation['error']);
                return;
            }

            // Perform the action
            $success = match($action) {
                'approve' => $this->gateway->approveLoan((int)$loanId, $this->userId),
                'decline' => $this->gateway->declineLoan((int)$loanId, $this->userId),
                'complete' => $this->gateway->completeLoan((int)$loanId, $this->userId),
                default => false
            };

            if ($success) {
                $this->sendSuccess(200, ["message" => "Loan {$action}d successfully"]);
            } else {
                $this->sendError(500, "Failed to {$action} loan");
            }
        } catch (Exception $e) {
            $this->sendError(500, "Failed to {$action} loan", $e->getMessage());
        }
    }

    /**
     * Checks if the current user can modify a loan
     * 
     * @param array $loan Loan data
     * @return bool True if user is the book owner
     */
    private function canModifyLoan(array $loan): bool {
        return $loan['owner_id'] === $this->userId;
    }

    /**
     * Validates if a status transition is allowed
     * 
     * @param string $currentStatus Current loan status
     * @param string $action Action to perform
     * @return array Validation result with 'valid' boolean and optional 'error' message
     */
    private function validateStatusTransition(string $currentStatus, string $action): array {
        $validTransitions = [
            'approve' => [self::STATUS_PENDING],
            'decline' => [self::STATUS_PENDING],
            'complete' => [self::STATUS_APPROVED]
        ];

        if (!isset($validTransitions[$action])) {
            return ['valid' => false, 'error' => "Invalid action"];
        }

        if (!in_array($currentStatus, $validTransitions[$action])) {
            return [
                'valid' => false,
                'error' => "Cannot {$action} loan with status: {$currentStatus}"
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validates if a value is a valid positive integer ID
     * 
     * @param mixed $id ID to validate
     * @return bool True if valid
     */
    private function isValidId(mixed $id): bool {
        return is_numeric($id) && $id > 0;
    }

    /**
     * Validates date format
     * 
     * @param string|null $date Date string to validate
     * @return bool True if valid or null
     */
    private function isValidDate(?string $date): bool {
        if ($date === null) return true;
        
        $d = \DateTime::createFromFormat(self::DATE_FORMAT, $date);
        return $d && $d->format(self::DATE_FORMAT) === $date;
    }

    
    /**
     * Parses JSON input from request body
     * 
     * @return array|null Parsed data or null if invalid
     */
    private function getJsonInput(): ?array {
        $input = json_decode(file_get_contents("php://input"), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError(400, "Invalid JSON format", json_last_error_msg());
            return null;
        }
        
        return $input;
    }

    /**
     * Sends JSON response
     * 
     * @param mixed $data Data to encode
     * @param int $code HTTP status code
     */
    private function sendJson(mixed $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode($data);
    }

    /**
     * Sends success response
     * 
     * @param int $code HTTP status code
     * @param array $data Response data
     */
    private function sendSuccess(int $code, array $data): void {
        $this->sendJson($data, $code);
    }

    /**
     * Sends error response
     * 
     * @param int $code HTTP status code
     * @param string $error Error message
     * @param string|null $details Optional error details
     */
    private function sendError(int $code, string $error, ?string $details = null): void {
        $response = ["error" => $error];
        if ($details !== null && ($_ENV['APP_ENV'] ?? 'production') === 'development') {
            $response["details"] = $details;
        }
        $this->sendJson($response, $code);
    }
}
