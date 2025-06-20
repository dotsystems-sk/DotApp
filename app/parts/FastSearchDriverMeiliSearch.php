<?php
// Tested with Meilisearch 1.15.1
namespace Dotsystems\App\Parts;

use Dotsystems\App\Parts\Config;
use Dotsystems\App\Parts\FastSearch;
use Dotsystems\App\Parts\FastSearchError;

/**
 * Meilisearch Driver for FastSearch
 *
 * Provides a unified interface for search operations within the DotApp framework,
 * supporting indexing, searching, and document management with Meilisearch.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.7 FREE
 * @license   MIT License
 * @date      2014 - 2025
 */
class FastSearchDriverMeiliSearch {
    private $host;
    private $auth;
    private static $driver = null;
    private $retryAttempts;
    private $retryDelayMs;

    /**
     * Returns the singleton instance of the driver.
     *
     * @return array Driver functions
     */
    public static function driver() {
        if (self::$driver === null) {
            self::$driver = new self();
        }
        return self::$driver->getDriver();
    }

    /**
     * Constructor initializes configuration from Config::searchEngines.
     */
    public function __construct() {
        $this->host = Config::searchEngines('meilisearch_host') ?? 'http://localhost:7700';
        $this->auth = [
            'api_key' => Config::searchEngines('meilisearch_api_key') ?? '',
            'ca_file' => Config::searchEngines('meilisearch_ca_file') ?? '',
            'ca_fingerprint' => Config::searchEngines('meilisearch_ca_fingerprint') ?? ''
        ];
        $this->retryAttempts = Config::searchEngines('meilisearch_retry_attempts') ?? 3;
        $this->retryDelayMs = Config::searchEngines('meilisearch_retry_delay_ms') ?? 200;
    }

    /**
     * Maps Meilisearch error codes to FastSearch error codes.
     *
     * @param array $response HTTP response from Meilisearch
     * @param string $operation Operation that failed
     * @return FastSearchError
     */
    private function mapMeilisearchError(array $response, string $operation): FastSearchError {
        $httpCode = $response['http_code'] ?? 500;
        $errorMessage = $response['error'] ?? 'Unknown error';
        $context = ['response' => $response['response'] ?? [], 'operation' => $operation];
        $errorCode = $response['response']['code'] ?? null;

        if ($response['response'] === false || strpos($errorMessage, 'cURL error') !== false) {
            if (strpos($errorMessage, 'Failed to connect') !== false || strpos($errorMessage, 'Timed out') !== false) {
                return new FastSearchError(
                    "Connection failed for operation $operation: $errorMessage",
                    'CONNECTION',
                    $httpCode,
                    $context
                );
            }
        }

        switch ($errorCode) {
            case 'index_not_found':
                return new FastSearchError(
                    "Index not found for operation $operation: $errorMessage",
                    'INDEX_NOT_FOUND',
                    $httpCode,
                    $context
                );
            case 'index_already_exists':
                return new FastSearchError(
                    "Index already exists for operation $operation: $errorMessage",
                    'INDEX_ALREADY_EXISTS',
                    $httpCode,
                    $context
                );
            case 'document_not_found':
                return new FastSearchError(
                    "Document not found for operation $operation: $errorMessage",
                    'DOCUMENT_NOT_FOUND',
                    $httpCode,
                    $context
                );
            case 'invalid_document_id':
                return new FastSearchError(
                    "Invalid document ID for operation $operation: $errorMessage",
                    'INVALID_ID',
                    $httpCode,
                    $context
                );
            case 'invalid_request':
            case 'missing_parameter':
            case 'bad_parameter':
                return new FastSearchError(
                    "Invalid request for operation $operation: $errorMessage",
                    'INVALID_REQUEST',
                    $httpCode,
                    $context
                );
            case 'invalid_filter':
                return new FastSearchError(
                    "Invalid filter for operation $operation: $errorMessage",
                    'INVALID_PARAMETERS',
                    $httpCode,
                    $context
                );
            case 'invalid_document_fields':
                return new FastSearchError(
                    "Invalid document fields for operation $operation: $errorMessage",
                    'INVALID_FIELDS',
                    $httpCode,
                    $context
                );
            case 'api_key_not_found':
            case 'invalid_api_key':
            case 'missing_authorization_header':
                return new FastSearchError(
                    "Authentication failed for operation $operation: $errorMessage",
                    'AUTHENTICATION_FAILED',
                    $httpCode,
                    $context
                );
            case 'too_many_requests':
                return new FastSearchError(
                    "Rate limit exceeded for operation $operation: $errorMessage",
                    'RATE_LIMIT',
                    $httpCode,
                    $context
                );
            case 'internal':
                return new FastSearchError(
                    "Server error for operation $operation: $errorMessage",
                    'SERVER_ERROR',
                    $httpCode,
                    $context
                );
            default:
                switch ($httpCode) {
                    case 400:
                        return new FastSearchError(
                            "Invalid request for operation $operation: $errorMessage",
                            'INVALID_REQUEST',
                            $httpCode,
                            $context
                        );
                    case 401:
                    case 403:
                        return new FastSearchError(
                            "Authentication failed for operation $operation: $errorMessage",
                            'AUTHENTICATION_FAILED',
                            $httpCode,
                            $context
                        );
                    case 404:
                        return new FastSearchError(
                            "Resource not found for operation $operation: $errorMessage",
                            'INDEX_NOT_FOUND',
                            $httpCode,
                            $context
                        );
                    case 429:
                        return new FastSearchError(
                            "Rate limit exceeded for operation $operation: $errorMessage",
                            'RATE_LIMIT',
                            $httpCode,
                            $context
                        );
                    case 500:
                    case 503:
                        return new FastSearchError(
                            "Server error for operation $operation: $errorMessage",
                            'SERVER_ERROR',
                            $httpCode,
                            $context
                        );
                }
                return new FastSearchError(
                    "Unknown error for operation $operation: $errorMessage",
                    'UNKNOWN',
                    $httpCode,
                    $context
                );
        }
    }

    /**
     * Executes an HTTP request with retry logic for transient errors.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param string $url Target URL
     * @param array $data Data to send (JSON-encoded for POST/PUT)
     * @param array $headers Additional HTTP headers
     * @param array $queryParams Query parameters for GET requests
     * @param string|null $rawBody Raw body for bulk operations
     * @return array ['success' => bool, 'data' => mixed, 'error' => FastSearchError|null]
     */
    private function executeWithRetry(
        string $method,
        string $url,
        array $data = [],
        array $headers = [],
        array $queryParams = [],
        ?string $rawBody = null
    ): array {
        $attempts = 0;
        $maxAttempts = $this->retryAttempts;
        $baseDelayMs = $this->retryDelayMs;
        $retryableCodes = [408, 429, 503];

        while ($attempts < $maxAttempts) {
            $response = HttpHelper::request($method, $url, $data, $this->auth, $headers, $queryParams, $rawBody);

            if ($response['success']) {
                return [
                    'success' => true,
                    'data' => $response['response'],
                    'error' => null
                ];
            }

            $error = $this->mapMeilisearchError($response, strtolower($method) . '_request');

            if (in_array($response['http_code'], $retryableCodes) || $error->getErrorCode() === 'RATE_LIMIT' || $error->getErrorCode() === 'CONNECTION') {
                $attempts++;
                if ($attempts < $maxAttempts) {
                    $delayMs = $baseDelayMs * (2 ** $attempts);
                    usleep($delayMs * 1000);
                    continue;
                }
            }

            return [
                'success' => false,
                'data' => null,
                'error' => $error
            ];
        }

        return [
            'success' => false,
            'data' => null,
            'error' => $this->mapMeilisearchError($response, strtolower($method) . '_request')
        ];
    }

    /**
     * Returns the driver functions for FastSearch.
     */
    private function getDriver(): array {
        $driver = [];

        /**
         * Configures an index with the specified schema.
         */
        $driver['configureIndex'] = function (string $index, $fields, FastSearch $fs): array {
            if ($fields === FastSearch::NO_INDEX) {
                return ['success' => true, 'data' => null, 'error' => null];
            }

            if (!is_array($fields)) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => new FastSearchError(
                        "Fields must be an array of field definitions (name => type).",
                        'INVALID_FIELDS',
                        null,
                        ['index' => $index]
                    ),
                ];
            }
            foreach ($fields as $name => $type) {
                if (!in_array($type, ['string', 'text', 'keyword', 'integer', 'float', 'boolean'])) {
                    return [
                        'success' => false,
                        'data' => null,
                        'error' => new FastSearchError(
                            "Invalid field type '$type' for field '$name'.",
                            'INVALID_FIELDS',
                            null,
                            ['index' => $index, 'field' => $name, 'type' => $type]
                        ),
                    ];
                }
            }

            $response = $this->executeWithRetry('GET', "{$this->host}/indexes/{$index}");
            if ($response['success']) {
                $searchableAttributes = array_keys($fields);
                $updateResponse = $this->executeWithRetry('PUT', "{$this->host}/indexes/{$index}/settings/searchable-attributes", $searchableAttributes);
                if (!$updateResponse['success']) {
                    return $updateResponse;
                }
                return ['success' => true, 'data' => null, 'error' => null];
            }
            if ($response['error'] && $response['error']->getErrorCode() === 'INDEX_NOT_FOUND') {
                $createResponse = $this->executeWithRetry('POST', "{$this->host}/indexes", ['uid' => $index, 'primaryKey' => 'id']);
                if ($createResponse['success']) {
                    $searchableAttributes = array_keys($fields);
                    $updateResponse = $this->executeWithRetry('PUT', "{$this->host}/indexes/{$index}/settings/searchable-attributes", $searchableAttributes);
                    if ($updateResponse['success']) {
                        return ['success' => true, 'data' => null, 'error' => null];
                    }
                    return $updateResponse;
                }
                return $createResponse;
            }
            return $response;
        };

        /**
         * Checks if an index exists.
         */
        $driver['indexExists'] = function (string $index, FastSearch $fs): array {
            $response = $this->executeWithRetry('GET', "{$this->host}/indexes/{$index}");
            return [
                'success' => true,
                'data' => $response['success'],
                'error' => $response['error'] && $response['error']->getErrorCode() !== 'INDEX_NOT_FOUND' ? $response['error'] : null,
            ];
        };

        /**
         * Deletes an index.
         */
        $driver['deleteIndex'] = function (string $index, FastSearch $fs): array {
            $response = $this->executeWithRetry('DELETE', "{$this->host}/indexes/{$index}");
            if ($response['success'] || ($response['error'] && $response['error']->getErrorCode() === 'INDEX_NOT_FOUND')) {
                return ['success' => true, 'data' => null, 'error' => null];
            }
            return $response;
        };

        /**
         * Indexes a single document.
         */
        $driver['index'] = function (string $index, string $id, array $document, FastSearch $fs): array {
            if (empty($id)) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => new FastSearchError(
                        "Document ID cannot be empty.",
                        'INVALID_ID',
                        null,
                        ['index' => $index]
                    ),
                ];
            }
            $document['id'] = $id;
            $response = $this->executeWithRetry('POST', "{$this->host}/indexes/{$index}/documents", [$document]);
            if ($response['success']) {
                return ['success' => true, 'data' => null, 'error' => null];
            }
            return $response;
        };

        /**
         * Bulk indexes multiple documents.
         */
        $driver['bulkIndex'] = function (string $index, array $documents, FastSearch $fs): array {
            if (empty($documents)) {
                return ['success' => true, 'data' => [], 'error' => null];
            }
            $bulkDocuments = [];
            foreach ($documents as $id => $document) {
                if (empty($id)) {
                    return [
                        'success' => false,
                        'data' => null,
                        'error' => new FastSearchError(
                            "Document ID cannot be empty in bulk index.",
                            'INVALID_ID',
                            null,
                            ['index' => $index, 'document_id' => $id]
                        ),
                    ];
                }
                $document['id'] = $id;
                $bulkDocuments[] = $document;
            }
            $response = $this->executeWithRetry('POST', "{$this->host}/indexes/{$index}/documents", $bulkDocuments);
            if ($response['success']) {
                $results = [];
                foreach ($documents as $id => $doc) {
                    $results[$id] = true;
                }
                return ['success' => true, 'data' => $results, 'error' => null];
            }
            return $response;
        };

        /**
         * Normalizes search options for Meilisearch.
         */
        $driver['normalizeOptions'] = function (array $options): array {
            $numTypos = isset($options['typo_tolerance'])
                ? ($options['typo_tolerance'] === false
                    ? 0
                    : ($options['typo_tolerance'] === 'auto'
                        ? 2
                        : (int)$options['typo_tolerance']))
                : 2;

            $typoTolerance = $numTypos === 0
                ? ['enabled' => false]
                : [
                    'enabled' => true,
                    'minWordSizeForTypos' => [
                        'oneTypo' => max(3, $numTypos),
                        'twoTypos' => max(7, $numTypos + 4)
                    ]
                ];

            $normalized = [
                'typo_tolerance' => $typoTolerance,
                'num_typos' => $numTypos,
                'case_sensitive' => $options['case_sensitive'] ?? false,
                'search_fields' => $options['search_fields'] ?? ['*'],
                'return_fields' => $options['return_fields'] ?? ['*'],
                'sort' => $options['sort'] ?? [],
                'highlight' => $options['highlight'] ?? false,
                'match_type' => $options['match_type'] ?? 'any',
                'facets' => $options['facets'] ?? [],
            ];

            return $normalized;
        };

        /**
         * Searches documents with support for filters and facets.
         */
        $driver['search'] = function (string $index, string $query, array $filters, int $limit, int $offset, array $options, FastSearch $fs): array {
            if ($limit < 0 || $offset < 0) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => new FastSearchError(
                        "Limit and offset must be non-negative.",
                        'INVALID_PARAMETERS',
                        null,
                        ['limit' => $limit, 'offset' => $offset]
                    ),
                ];
            }

            // Configure typo tolerance settings
            $typoSettingsResponse = $this->executeWithRetry('PATCH', "{$this->host}/indexes/{$index}/settings/typo-tolerance", $options['typo_tolerance']);
            if (!$typoSettingsResponse['success']) {
                return $typoSettingsResponse;
            }

            $body = [
                'q' => $query,
                'limit' => $limit,
                'offset' => $offset,
                'attributesToRetrieve' => $options['return_fields'],
                'attributesToSearchOn' => $options['search_fields'],
            ];

            // Apply filters
            if (!empty($filters)) {
                $filterExpressions = [];
                foreach ($filters as $field => $value) {
                    if (is_array($value) && (isset($value['gte']) || isset($value['lte']))) {
                        if (isset($value['gte'])) {
                            $filterExpressions[] = "{$field} >= {$value['gte']}";
                        }
                        if (isset($value['lte'])) {
                            $filterExpressions[] = "{$field} <= {$value['lte']}";
                        }
                    } else {
                        $filterExpressions[] = "{$field} = " . (is_string($value) ? "\"{$value}\"" : $value);
                    }
                }
                $body['filter'] = implode(' AND ', $filterExpressions);
            }

            // Apply sort
            if (!empty($options['sort'])) {
                $body['sort'] = [];
                foreach ($options['sort'] as $field => $order) {
                    $body['sort'][] = "{$field}:" . ($order === 'asc' ? 'asc' : 'desc');
                }
            }

            // Apply highlight
            if ($options['highlight']) {
                $body['attributesToHighlight'] = $options['search_fields'];
                $body['highlightPreTag'] = '<mark>';
                $body['highlightPostTag'] = '</mark>';
            }

            // Apply facets
            if (!empty($options['facets'])) {
                $body['facets'] = $options['facets'];
            }

            // Apply match type (Meilisearch uses matchingStrategy)
            if ($options['match_type'] === 'all') {
                $body['matchingStrategy'] = 'all';
            } elseif ($options['match_type'] === 'any') {
                $body['matchingStrategy'] = 'last';
            }

            $response = $this->executeWithRetry('POST', "{$this->host}/indexes/{$index}/search", $body);
            if (!$response['success']) {
                return $response;
            }

            $results = [];
            if (isset($response['data']['hits'])) {
                foreach ($response['data']['hits'] as $hit) {
                    $result = $hit;
                    if ($options['highlight'] && isset($hit['_formatted'])) {
                        $result['highlight'] = array_intersect_key($hit['_formatted'], array_flip($options['search_fields']));
                    }
                    $results[] = $result;
                }
            }

            // Process facets
            if (!empty($options['facets']) && isset($response['data']['facetDistribution'])) {
                $results['facets'] = [];
                foreach ($options['facets'] as $field) {
                    if (isset($response['data']['facetDistribution'][$field])) {
                        $results['facets'][$field] = [];
                        foreach ($response['data']['facetDistribution'][$field] as $value => $results) {
                            $results['facets'][$field][] = [
                                'value' => $value,
                                'results' => $results,
                            ];
                        }
                    }
                }
            }

            // Apply case-sensitive filtering (Meilisearch doesn't support native case-sensitive search)
            if ($options['case_sensitive'] && !empty($results)) {
                $results = array_filter($results, function ($hit) use ($query, $options) {
                    foreach ($options['search_fields'] as $field) {
                        if (isset($hit[$field])) {
                            // Compute Levenshtein distance (case-sensitive)
                            $distance = levenshtein($hit[$field], $query);
                            // Accept if within typo tolerance
                            if ($distance <= $options['num_typos']) {
                                return true;
                            }
                        }
                    }
                    return false;
                });
                $results = array_values($results);
            }

            return ['success' => true, 'data' => $results, 'error' => null];
        };

        /**
         * Updates a document.
         */
        $driver['update'] = function (string $index, string $id, array $document, FastSearch $fs): array {
            if (empty($id)) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => new FastSearchError(
                        "Document ID cannot be empty.",
                        'INVALID_ID',
                        null,
                        ['index' => $index]
                    ),
                ];
            }
            $document['id'] = $id;
            $response = $this->executeWithRetry('PUT', "{$this->host}/indexes/{$index}/documents", [$document]);
            if ($response['success']) {
                return ['success' => true, 'data' => null, 'error' => null];
            }
            return $response;
        };

        /**
         * Deletes a document.
         */
        $driver['delete'] = function (string $index, string $id, FastSearch $fs): array {
            if (empty($id)) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => new FastSearchError(
                        "Document ID cannot be empty.",
                        'INVALID_ID',
                        null,
                        ['index' => $index]
                    ),
                ];
            }
            $response = $this->executeWithRetry('DELETE', "{$this->host}/indexes/{$index}/documents/{$id}");
            if ($response['success'] || ($response['error'] && $response['error']->getErrorCode() === 'DOCUMENT_NOT_FOUND')) {
                return ['success' => true, 'data' => null, 'error' => null];
            }
            return $response;
        };

        /**
         * Clears all documents from an index.
         */
        $driver['clear'] = function (string $index, FastSearch $fs): array {
            $response = $this->executeWithRetry('DELETE', "{$this->host}/indexes/{$index}/documents");
            if ($response['success']) {
                return ['success' => true, 'data' => null, 'error' => null];
            }
            return $response;
        };

        /**
         * Refreshes an index (no-op in Meilisearch as indexing is synchronous).
         */
        $driver['refresh'] = function (string $index, FastSearch $fs): array {
            $response = $this->executeWithRetry('GET', "{$this->host}/indexes/{$index}");
            if ($response['success']) {
                return ['success' => true, 'data' => null, 'error' => null];
            }
            return $response;
        };

        /**
         * Retrieves the schema of an index.
         */
        $driver['getIndexSchema'] = function (string $index, FastSearch $fs): array {
            $response = $this->executeWithRetry('GET', "{$this->host}/indexes/{$index}/settings/searchable-attributes");
            if ($response['success']) {
                $schema = [];
                foreach ($response['data'] as $field) {
                    $schema[$field] = 'text';
                }
                return ['success' => true, 'data' => $schema, 'error' => null];
            }
            return $response;
        };

        /**
         * Updates index settings.
         */
        $driver['updateIndexSettings'] = function (string $index, array $settings, FastSearch $fs): array {
            $response = $this->executeWithRetry('PATCH', $settings);
            if ($response['success']) {
                return ['success' => true, 'data' => null, 'error' => null];
            }
            return $response;
        };

        return $driver;
    }
}
?>