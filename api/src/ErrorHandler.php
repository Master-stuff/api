<?php

/**
 * Global Error and Exception Handler
 * 
 * Provides centralized error handling with appropriate HTTP responses
 * and prevents sensitive information leakage in production.
 */
class ErrorHandler {
    /**
     * Handles uncaught exceptions
     * 
     * @param Throwable $exception The uncaught exception
     */
    public static function handleException(Throwable $exception): void {
        http_response_code(500);
        
        $isDevelopment = ($_ENV['APP_ENV'] ?? 'production') === 'development';
        
        if ($isDevelopment) {
            echo json_encode([
                "error" => "Internal server error",
                "message" => $exception->getMessage(),
                "code" => $exception->getCode(),
                "file" => $exception->getFile(),
                "line" => $exception->getLine(),
                "trace" => $exception->getTraceAsString()
            ], JSON_PRETTY_PRINT);
        } else {
            echo json_encode([
                "error" => "Internal server error",
                "message" => "An unexpected error occurred. Please try again later."
            ]);
            
            error_log(sprintf(
                "Exception: %s in %s:%d\nStack trace:\n%s",
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getTraceAsString()
            ));
        }
    }

    /**
     * Converts PHP errors to exceptions for consistent handling
     * 
     * @param int $errno Error level
     * @param string $errstr Error message
     * @param string $errfile File where error occurred
     * @param int $errline Line number where error occurred
     * @return bool Always returns true to prevent default PHP error handler
     * @throws ErrorException
     */
    public static function handleError(
        int $errno,
        string $errstr,
        string $errfile,
        int $errline
    ): bool {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
}
