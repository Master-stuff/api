<?php

/**
 * Reviews Controller
 * 
 * Handles review submission and retrieval for user ratings.
 * Implements authorization checks to ensure only eligible users can review.
 */
class ReviewsController {
    private const MAX_COMMENT_LENGTH = 1000;
    private const MIN_RATING = 1;
    private const MAX_RATING = 5;

    private int $userId;
    private JwtManager $jwtManager;
    private AuthMiddleware $authMiddleware;

    public function __construct(private ReviewsGateway $gateway) {
        $this->jwtManager = new JwtManager($_ENV['SECRET_KEY']);
        $this->authMiddleware = new AuthMiddleware($this->jwtManager);
    }

    /**
     * Routes review requests to appropriate handlers
     * 
     * @param array $request Request path segments
     * @param string $method HTTP method
     */
    public function processRequest(array $request, string $method): void {
        try {
            $endpoint = $request[0] ?? null;
            $param = $request[1] ?? null;

            match(true) {
                $method === 'POST' && $endpoint === null
                    => $this->handleSubmitReview(),
                
                $method === 'GET' && $endpoint !== null && is_numeric($endpoint)
                    => $this->handleGetUserReviews($endpoint),
                
                default => $this->sendError(404, "Endpoint not found")
            };
        } catch (Exception $e) {
            $this->sendError(500, "Internal server error", $e->getMessage());
        }
    }

    /**
     * Handles submitting a new review (requires authentication)
     */
    private function handleSubmitReview(): void {
        $payload = $this->authMiddleware->authenticate();
        if (!$payload) return;

        $this->userId = $payload['id'];

        $input = $this->getJsonInput();
        if (!$input) return;

        // Validate input
        $validation = $this->validateReviewInput($input);
        if (!$validation['valid']) {
            $this->sendError(400, $validation['error']);
            return;
        }

        try {
            // Check if review already exists for this loan
            if ($this->gateway->reviewExists($input['loan_id'])) {
                $this->sendError(400, "A review already exists for this loan");
                return;
            }

            // Verify loan exists and is completed
            $loan = $this->gateway->getLoanDetails($input['loan_id']);
            if (!$loan) {
                $this->sendError(404, "Loan not found");
                return;
            }

            if ($loan['status'] !== 'done') {
                $this->sendError(400, "Can only review completed loans (status: done)");
                return;
            }

            // Verify user is either borrower or owner (not reviewing themselves)
            if ($loan['borrower_id'] !== $this->userId && $loan['owner_id'] !== $this->userId) {
                $this->sendError(403, "Unauthorized: You are not part of this loan");
                return;
            }

            // Determine who is being reviewed
            $rated_user_id = $loan['borrower_id'] === $this->userId 
                ? $loan['owner_id'] 
                : $loan['borrower_id'];

            // Create the review
            $reviewId = $this->gateway->createReview(
                $input['loan_id'],
                $this->userId,
                $rated_user_id,
                $input['rating'],
                $input['comment'] ?? null
            );

            $this->sendSuccess(201, [
                "message" => "Review submitted successfully",
                "review_id" => $reviewId
            ]);
        } catch (Exception $e) {
            $this->sendError(500, "Failed to submit review", $e->getMessage());
        }
    }

    /**
     * Handles retrieving all reviews for a user (public endpoint)
     * 
     * @param string $user_id User ID to get reviews for
     */
    private function handleGetUserReviews(string $user_id): void {
        if (!$this->isValidId($user_id)) {
            $this->sendError(400, "Invalid user ID");
            return;
        }

        try {
            $reviews = $this->gateway->getReviewsByUser((int)$user_id);
            $stats = $this->gateway->getUserRatingStats((int)$user_id);

            $this->sendJson([
                "user_id" => (int)$user_id,
                "average_rating" => $stats['average_rating'],
                "review_count" => $stats['review_count'],
                "reviews" => $reviews
            ]);
        } catch (Exception $e) {
            $this->sendError(500, "Failed to retrieve reviews", $e->getMessage());
        }
    }

    /**
     * Validates review input data
     * 
     * @param array $data Review data to validate
     * @return array Validation result with 'valid' boolean and optional 'error' message
     */
    private function validateReviewInput(array $data): array {
        // Check required fields
        if (!isset($data['loan_id'])) {
            return ['valid' => false, 'error' => "Missing required field: loan_id"];
        }

        if (!isset($data['rating'])) {
            return ['valid' => false, 'error' => "Missing required field: rating"];
        }

        // Validate loan_id
        if (!$this->isValidId($data['loan_id'])) {
            return ['valid' => false, 'error' => "Invalid loan_id: must be a positive integer"];
        }

        // Validate rating
        if (!is_numeric($data['rating'])) {
            return ['valid' => false, 'error' => "Rating must be a number"];
        }

        $rating = (int)$data['rating'];
        if ($rating < self::MIN_RATING || $rating > self::MAX_RATING) {
            return [
                'valid' => false,
                'error' => "Rating must be between " . self::MIN_RATING . " and " . self::MAX_RATING
            ];
        }

        // Validate comment length if provided
        if (isset($data['comment']) && strlen($data['comment']) > self::MAX_COMMENT_LENGTH) {
            return [
                'valid' => false,
                'error' => "Comment too long (max " . self::MAX_COMMENT_LENGTH . " characters)"
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
