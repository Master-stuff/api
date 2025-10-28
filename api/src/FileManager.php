<?php

/**
 * File Manager for Secure File Operations
 * 
 * Handles secure file uploads, validation, and storage with comprehensive
 * security checks to prevent directory traversal, malicious uploads, and
 * unauthorized access.
 */
class FileManager {
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];
    
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    private const UPLOAD_DIR = 'public/covers';
    private const TEMP_DIR = 'temp/uploads';
    
    /**
     * Validates and stores an uploaded cover image
     * 
     * Security features:
     * - MIME type validation (not just extension)
     * - File size limits
     * - Filename sanitization to prevent directory traversal
     * - Unique filename generation using hash
     * - Temporary file verification
     * 
     * @param array $file $_FILES array element
     * @param int $bookId Book ID for filename generation
     * @return string|null Relative file path on success, null on failure
     * @throws Exception If validation fails
     */
    public function uploadCoverImage(array $file, int $bookId): ?string {
        // Validate file exists and has no errors
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload failed: " . $this->getUploadErrorMessage($file['error'] ?? UPLOAD_ERR_NO_FILE));
        }

        // Validate file size
        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new Exception("File size exceeds maximum allowed size of " . (self::MAX_FILE_SIZE / 1024 / 1024) . "MB");
        }

        // Validate MIME type using finfo (more secure than extension checking)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw new Exception("Invalid file type. Allowed types: JPG, PNG, WebP");
        }

        // Ensure upload directory exists
        $this->ensureDirectoryExists(self::UPLOAD_DIR);

        // Generate secure filename using hash to prevent directory traversal
        $extension = self::ALLOWED_MIME_TYPES[$mimeType];
        $filename = $this->generateSecureFilename($bookId, $extension);
        $filepath = self::UPLOAD_DIR . '/' . $filename;

        // Move uploaded file to destination
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception("Failed to save uploaded file");
        }

        // Set proper file permissions (readable by web server, not executable)
        chmod($filepath, 0644);

        return $filepath;
    }

    /**
     * Deletes a cover image file
     * 
     * @param string $filepath Relative file path to delete
     * @return bool True if deleted successfully
     */
    public function deleteCoverImage(string $filepath): bool {
        // Prevent directory traversal attacks
        if (strpos($filepath, '..') !== false || strpos($filepath, '~') !== false) {
            return false;
        }

        // Only allow deletion from covers directory
        if (strpos($filepath, self::UPLOAD_DIR) !== 0) {
            return false;
        }

        if (file_exists($filepath)) {
            return unlink($filepath);
        }

        return true; // File doesn't exist, consider it deleted
    }

    /**
     * Generates a secure filename using hash to prevent directory traversal
     * 
     * Format: book_{bookId}_{timestamp}_{randomHash}.{extension}
     * 
     * @param int $bookId Book ID
     * @param string $extension File extension
     * @return string Secure filename
     */
    private function generateSecureFilename(int $bookId, string $extension): string {
        $timestamp = time();
        $randomHash = bin2hex(random_bytes(8)); // 16 character random string
        return "book_{$bookId}_{$timestamp}_{$randomHash}.{$extension}";
    }

    /**
     * Ensures upload directory exists and is writable
     * 
     * @param string $directory Directory path
     * @throws Exception If directory cannot be created or is not writable
     */
    private function ensureDirectoryExists(string $directory): void {
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new Exception("Failed to create upload directory");
            }
        }

        if (!is_writable($directory)) {
            throw new Exception("Upload directory is not writable");
        }
    }

    /**
     * Gets human-readable error message for upload errors
     * 
     * @param int $errorCode PHP upload error code
     * @return string Error message
     */
    private function getUploadErrorMessage(int $errorCode): string {
        return match($errorCode) {
            UPLOAD_ERR_INI_SIZE => "File exceeds upload_max_filesize directive",
            UPLOAD_ERR_FORM_SIZE => "File exceeds MAX_FILE_SIZE form directive",
            UPLOAD_ERR_PARTIAL => "File was only partially uploaded",
            UPLOAD_ERR_NO_FILE => "No file was uploaded",
            UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk",
            UPLOAD_ERR_EXTENSION => "File upload stopped by extension",
            default => "Unknown upload error"
        };
    }
}
