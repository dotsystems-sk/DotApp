<?php
namespace Dotsystems\App\Parts;

/**
 * CLASS FastSearchError - DotApp Search Error Handler
 *
 * Represents an error that occurred during a FastSearch operation within the DotApp framework.
 * Provides standardized error handling with specific error codes, HTTP status, and context
 * for search-related operations across multiple search engines.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.8 FREE
 * @license   MIT License
 * @date      2014 - 2025
 */
class FastSearchError extends \Exception {
    // Define valid error codes
    const VALID_ERROR_CODES = [
        'INVALID_FIELDS', 'EMPTY_FIELDS', 'INVALID_ID', 'INVALID_PROPERTIES',
        'INVALID_PARAMETERS', 'INVALID_PROPERTY',
        'INVALID_OPTION', 'INDEX_NOT_FOUND', 'INDEX_ALREADY_EXISTS',
        'INDEX_CREATION_FAILED', 'INDEX_DELETION_FAILED', 'DOCUMENT_NOT_FOUND',
        'INDEXING_FAILED', 'SEARCH_FAILED', 'UPDATE_FAILED', 'DELETION_FAILED',
        'CLEAR_FAILED', 'REFRESH_FAILED', 'SCHEMA_RETRIEVAL_FAILED',
        'SETTINGS_UPDATE_FAILED', 'CONNECTION', 'AUTHENTICATION_FAILED', 'RATE_LIMIT',
        'SERVER_ERROR', 'UNKNOWN', 'INVALID_REQUEST'
    ];

    private $errorCode;
    private $httpStatus;
    private $context;

    /**
     * @param string $message Error message
     * @param string $errorCode Unique error code from VALID_ERROR_CODES
     * @param int|null $httpStatus HTTP status
     * @param array $context Additional context
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message,
        string $errorCode,
        ?int $httpStatus = null,
        array $context = [],
        ?\Throwable $previous = null
    ) {
        if (!in_array($errorCode, self::VALID_ERROR_CODES)) {
            throw new \InvalidArgumentException("Invalid error code: $errorCode");
        }
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
        $this->context = $context;
    }

    public function getErrorCode(): string {
        return $this->errorCode;
    }

    public function getHttpStatus(): ?int {
        return $this->httpStatus;
    }

    public function getContext(): array {
        return $this->context;
    }

    /**
     * Maps an HTTP response to a standardized FastSearch error.
     *
     * @param array $response HttpHelper response
     * @param string $operation Operation that failed
     * @return self
     */
    public static function fromHttpResponse(array $response, string $operation): self {
        $httpCode = $response['http_code'] ?? 500;
        $errorMessage = $response['error'] ?? 'Unknown error';
        $context = ['response' => $response['response'] ?? [], 'operation' => $operation];

        switch ($httpCode) {
            case 400:
                return new self(
                    "Invalid request for operation $operation: $errorMessage",
                    'INVALID_REQUEST',
                    $httpCode,
                    $context
                );
            case 401:
            case 403:
                return new self(
                    "Authentication failed for operation $operation: $errorMessage",
                    'AUTHENTICATION_FAILED',
                    $httpCode,
                    $context
                );
            case 404:
                return new self(
                    "Resource not found for operation $operation: $errorMessage",
                    'INDEX_NOT_FOUND',
                    $httpCode,
                    $context
                );
            case 429:
                return new self(
                    "Rate limit exceeded for operation $operation: $errorMessage",
                    'RATE_LIMIT',
                    $httpCode,
                    $context
                );
            case 500:
            case 503:
                return new self(
                    "Server error for operation $operation: $errorMessage",
                    'SERVER_ERROR',
                    $httpCode,
                    $context
                );
            default:
                return new self(
                    "Unknown error for operation $operation: $errorMessage",
                    'UNKNOWN',
                    $httpCode,
                    $context
                );
        }
    }

    /**
     * Creates an error from a validation error.
     *
     * @param string $message Error message
     * @param string $errorCode Error code
     * @param array $context Additional context
     * @return self
     */
    public static function fromValidationError(string $message, string $errorCode, array $context = []): self {
        return new self($message, $errorCode, null, $context);
    }
}

?>