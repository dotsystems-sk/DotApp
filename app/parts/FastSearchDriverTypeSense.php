<?php
// Tested with Typesense 28
namespace Dotsystems\App\Parts;

use Dotsystems\App\Parts\Config;
use Dotsystems\App\Parts\FastSearch;
use Dotsystems\App\Parts\FastSearchError;

/**
 * Typesense Driver for FastSearch
 *
 * Provides a unified interface for search operations within the DotApp framework,
 * supporting indexing, searching, and document management with Typesense.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.7 FREE
 * @license   MIT License
 * @date      2014 - 2025
 */
class FastSearchDriverTypeSense {
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
        $this->host = Config::searchEngines('typesense_host') ? Config::searchEngines('typesense_host') : 'http://localhost:8108';
        $this->auth = array(
            'api_key' => Config::searchEngines('typesense_api_key') ? Config::searchEngines('typesense_api_key') : '',
            'ca_file' => Config::searchEngines('typesense_ca_file') ? Config::searchEngines('typesense_ca_file') : '',
            'ca_fingerprint' => Config::searchEngines('typesense_ca_fingerprint') ? Config::searchEngines('typesense_ca_fingerprint') : ''
        );
        $this->retryAttempts = Config::searchEngines('typesense_retry_attempts') ? Config::searchEngines('typesense_retry_attempts') : 3;
        $this->retryDelayMs = Config::searchEngines('typesense_retry_delay_ms') ? Config::searchEngines('typesense_retry_delay_ms') : 200;
    }

    /**
     * Maps Typesense error codes to FastSearch error codes.
     *
     * @param array $response HTTP response from Typesense
     * @param string $operation Operation that failed
     * @return FastSearchError
     */
    private function mapTypesenseError(array $response, $operation) {
        $httpCode = isset($response['http_code']) ? $response['http_code'] : 500;
        $errorMessage = isset($response['error']) ? $response['error'] : 'Unknown error';
        $context = array('response' => isset($response['response']) ? $response['response'] : array(), 'operation' => $operation);
        $errorDetail = isset($response['response']['message']) ? $response['response']['message'] : $errorMessage;

        // Handle cURL connection errors
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

        // Typesense does not use specific error types like Elasticsearch, so we map based on HTTP code and message
        switch ($httpCode) {
            case 400:
                if (strpos($errorDetail, 'field') !== false) {
                    return new FastSearchError(
                        "Invalid field definition for operation $operation: $errorDetail",
                        'INVALID_FIELDS',
                        $httpCode,
                        $context
                    );
                }
                return new FastSearchError(
                    "Invalid request for operation $operation: $errorDetail",
                    'INVALID_REQUEST',
                    $httpCode,
                    $context
                );
            case 401:
            case 403:
                return new FastSearchError(
                    "Authentication failed for operation $operation: $errorDetail",
                    'AUTHENTICATION_FAILED',
                    $httpCode,
                    $context
                );
            case 404:
                return new FastSearchError(
                    "Index not found for operation $operation: $errorDetail",
                    'INDEX_NOT_FOUND',
                    $httpCode,
                    $context
                );
            case 409:
                return new FastSearchError(
                    "Index already exists for operation $operation: $errorDetail",
                    'INDEX_ALREADY_EXISTS',
                    $httpCode,
                    $context
                );
            case 429:
                return new FastSearchError(
                    "Rate limit exceeded for operation $operation: $errorDetail",
                    'RATE_LIMIT',
                    $httpCode,
                    $context
                );
            case 500:
            case 503:
                return new FastSearchError(
                    "Server error for operation $operation: $errorDetail",
                    'SERVER_ERROR',
                    $httpCode,
                    $context
                );
            default:
                return new FastSearchError(
                    "Unknown error for operation $operation: $errorDetail",
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
    private function executeWithRetry($method, $url, $data = array(), $headers = array(), $queryParams = array(), $rawBody = null) {
        $attempts = 0;
        $maxAttempts = $this->retryAttempts;
        $baseDelayMs = $this->retryDelayMs;
        $retryableCodes = array(408, 429, 503);

        // Add API key header
        if (!empty($this->auth['api_key'])) {
            $headers[] = 'X-TYPESENSE-API-KEY: ' . $this->auth['api_key'];
        }

        while ($attempts < $maxAttempts) {
            $response = HttpHelper::request($method, $url, $data, $this->auth, $headers, $queryParams, $rawBody);

            if ($response['success']) {
                return array(
                    'success' => true,
                    'data' => $response['response'],
                    'error' => null
                );
            }

            // Map Typesense error
            $error = $this->mapTypesenseError($response, strtolower($method) . '_request');

            // Check if error is retryable
            if (in_array($response['http_code'], $retryableCodes) || $error->getErrorCode() === 'RATE_LIMIT' || $error->getErrorCode() === 'CONNECTION') {
                $attempts++;
                if ($attempts < $maxAttempts) {
                    $delayMs = $baseDelayMs * pow(2, $attempts);
                    usleep($delayMs * 1000);
                    continue;
                }
            }

            return array(
                'success' => false,
                'data' => null,
                'error' => $error
            );
        }

        return array(
            'success' => false,
            'data' => null,
            'error' => $this->mapTypesenseError($response, strtolower($method) . '_request')
        );
    }

    /**
     * Returns the driver functions for FastSearch.
     */
    private function getDriver() {
        $driver = array();

        /**
         * Retrieves the schema of a collection.
         */
        $driver['getIndexSchema'] = function ($index, FastSearch $fs) {
            $response = $this->executeWithRetry('GET', "{$this->host}/collections/{$index}");
            if ($response['success'] && isset($response['data']['fields'])) {
                $schema = array();
                foreach ($response['data']['fields'] as $field) {
                    $type = $field['type'];
                    $schema[$field['name']] = $type === 'string' ? 'text' : ($type === 'int32' ? 'integer' : ($type === 'float' ? 'float' : 'boolean'));
                }
                return array('success' => true, 'data' => $schema, 'error' => null);
            }
            return $response;
        };

        /**
         * Configures a collection with the specified schema.
         */
        $driver['configureIndex'] = function ($index, $fields, FastSearch $fs) {
            if ($fields === FastSearch::NO_INDEX) {
                return array('success' => true, 'data' => null, 'error' => null);
            }

            if (!is_array($fields)) {
                return array(
                    'success' => false,
                    'data' => null,
                    'error' => new FastSearchError(
                        "Fields must be an array of field definitions (name => type).",
                        'INVALID_FIELDS',
                        null,
                        array('index' => $index)
                    )
                );
            }
            foreach ($fields as $name => $type) {
                if (!in_array($type, array('string', 'text', 'keyword', 'integer', 'float', 'boolean'))) {
                    return array(
                        'success' => false,
                        'data' => null,
                        'error' => new FastSearchError(
                            "Invalid field type '$type' for field '$name'.",
                            'INVALID_FIELDS',
                            null,
                            array('index' => $index, 'field' => $name, 'type' => $type)
                        )
                    );
                }
            }

            // Check if collection exists
            $response = $this->executeWithRetry('GET', "{$this->host}/collections/{$index}");
            if ($response['success']) {
                return array('success' => true, 'data' => null, 'error' => null);
            }
            if ($response['error'] && $response['error']->getErrorCode() === 'INDEX_NOT_FOUND') {
                // Create collection
                $schema = array(
                    'name' => $index,
                    'fields' => array_map(function ($type, $name) {
                        return array(
                            'name' => $name,
                            'type' => $type === 'string' || $type === 'text' || $type === 'keyword' ? 'string' : ($type === 'integer' ? 'int32' : ($type === 'float' ? 'float' : 'bool')),
                            'facet' => $type === 'string' || $type === 'keyword',
                            'optional' => true // Mark all fields as optional
                        );
                    }, $fields, array_keys($fields))
                );
                $createResponse = $this->executeWithRetry('POST', "{$this->host}/collections", $schema);
                if ($createResponse['success']) {
                    return array('success' => true, 'data' => null, 'error' => null);
                }
                return $createResponse;
            }
            return $response;
        };

        /**
         * Checks if a collection exists.
         */
        $driver['indexExists'] = function ($index, FastSearch $fs) {
            $response = $this->executeWithRetry('GET', "{$this->host}/collections/{$index}");
            return array(
                'success' => true,
                'data' => $response['success'],
                'error' => ($response['error'] && $response['error']->getErrorCode() !== 'INDEX_NOT_FOUND') ? $response['error'] : null
            );
        };

        /**
         * Deletes a collection.
         */
        $driver['deleteIndex'] = function ($index, FastSearch $fs) {
            $response = $this->executeWithRetry('DELETE', "{$this->host}/collections/{$index}");
            if ($response['success'] || ($response['error'] && $response['error']->getErrorCode() === 'INDEX_NOT_FOUND')) {
                return array('success' => true, 'data' => null, 'error' => null);
            }
            return $response;
        };

        /**
         * Indexes a single document.
         */
        $driver['index'] = function ($index, $id, $document, FastSearch $fs) use (&$driver) {
            if (empty($id)) {
                return array(
                    'success' => false,
                    'data' => null,
                    'error' => new FastSearchError(
                        "Document ID cannot be empty.",
                        'INVALID_ID',
                        null,
                        array('index' => $index)
                    )
                );
            }
            // Get index schema to ensure all fields are present
            $schemaResponse = $driver['getIndexSchema']($index, $fs);
            if ($schemaResponse['success']) {
                $schemaFields = $schemaResponse['data'];
                // Fill missing fields with type-appropriate default values
                foreach ($schemaFields as $field => $type) {
                    if (!isset($document[$field]) && $field !== 'id') {
                        switch ($type) {
                            case 'text':
                            case 'keyword':
                                $document[$field] = '';
                                break;
                            case 'integer':
                                $document[$field] = 0;
                                break;
                            case 'float':
                                $document[$field] = 0.0;
                                break;
                            case 'boolean':
                                $document[$field] = false;
                                break;
                            default:
                                $document[$field] = '';
                        }
                    }
                }
            }
            $document['id'] = $id;
            $response = $this->executeWithRetry('POST', "{$this->host}/collections/{$index}/documents", $document);
            if ($response['success']) {
                return array('success' => true, 'data' => null, 'error' => null);
            }
            return $response;
        };

        /**
         * Bulk indexes multiple documents.
         */
        $driver['bulkIndex'] = function ($index, $documents, FastSearch $fs) use (&$driver) {
            if (empty($documents)) {
                return array('success' => true, 'data' => array(), 'error' => null);
            }
            // Get index schema to ensure all fields are present
            $schemaResponse = $driver['getIndexSchema']($index, $fs);
            $schemaFields = $schemaResponse['success'] ? $schemaResponse['data'] : [];
            $bulkDocuments = array();
            foreach ($documents as $id => $document) {
                if (empty($id)) {
                    return array(
                        'success' => false,
                        'data' => null,
                        'error' => new FastSearchError(
                            "Document ID cannot be empty in bulk index.",
                            'INVALID_ID',
                            null,
                            array('index' => $index, 'document_id' => $id)
                        )
                    );
                }
                // Fill missing fields with type-appropriate default values
                if (!empty($schemaFields)) {
                    foreach ($schemaFields as $field => $type) {
                        if (!isset($document[$field]) && $field !== 'id') {
                            switch ($type) {
                                case 'text':
                                case 'keyword':
                                    $document[$field] = '';
                                    break;
                                case 'integer':
                                    $document[$field] = 0;
                                    break;
                                case 'float':
                                    $document[$field] = 0.0;
                                    break;
                                case 'boolean':
                                    $document[$field] = false;
                                    break;
                                default:
                                    $document[$field] = '';
                            }
                        }
                    }
                }
                $document['id'] = $id;
                $bulkDocuments[] = $document;
            }
            $response = $this->executeWithRetry('POST', "{$this->host}/collections/{$index}/documents/import", array('action' => 'create'), array('Content-Type: application/json'), array(), json_encode($bulkDocuments));
            if ($response['success']) {
                $results = array();
                foreach ($documents as $id => $doc) {
                    $results[$id] = true; // Typesense does not return per-document status
                }
                return array('success' => true, 'data' => $results, 'error' => null);
            }
            return $response;
        };

        /**
         * Normalizes search options for Typesense.
         */
        $driver['normalizeOptions'] = function ($options) {
            $normalized = array(
                'num_typos' => isset($options['typo_tolerance']) ? ($options['typo_tolerance'] === false ? 0 : ($options['typo_tolerance'] === 'auto' ? 2 : (int)$options['typo_tolerance'])) : 2,
                'case_sensitive' => isset($options['case_sensitive']) ? $options['case_sensitive'] : false,
                'search_fields' => isset($options['search_fields']) ? $options['search_fields'] : array('name'),
                'return_fields' => isset($options['return_fields']) ? $options['return_fields'] : null,
                'sort' => isset($options['sort']) ? $options['sort'] : array(),
                'highlight' => isset($options['highlight']) ? $options['highlight'] : false,
                'match' => isset($options['match_type']) ? $options['match_type'] : 'any',
                'facets' => isset($options['facets']) ? $options['facets'] : array()
            );

            $allowedOptions = array('typo_tolerance', 'case_sensitive', 'search_fields', 'return_fields', 'sort', 'highlight', 'match_type', 'facets');
            foreach (array_keys($options) as $option) {
                if (!in_array($option, $allowedOptions)) {
                    throw new \InvalidArgumentException("Unsupported option '$option'. Supported options are: " . implode(', ', $allowedOptions));
                }
            }

            return $normalized;
        };

        /**
         * Searches documents with support for filters and facets.
         */
        $driver['search'] = function ($index, $query, $filters, $limit, $offset, $options, FastSearch $fs) {
            if ($limit < 0 || $offset < 0) {
                return array(
                    'success' => false,
                    'data' => null,
                    'error' => new FastSearchError(
                        "Limit and offset must be non-negative.",
                        'INVALID_PARAMETERS',
                        null,
                        array('limit' => $limit, 'offset' => $offset)
                    )
                );
            }
            $queryParams = array(
                'q' => $query,
                'query_by' => implode(',', $options['search_fields']),
                'query_by_weights' => implode(',', array_fill(0, count($options['search_fields']), 1)), // Equal weights for all fields
                'prefix' => 'true', // Enable prefix matching
                'per_page' => $limit,
                'page' => ($offset / $limit) + 1,
                'num_typos' => $options['num_typos']
            );

            // Apply filters
            if (!empty($filters)) {
                $filterExpressions = array();
                foreach ($filters as $field => $value) {
                    if (is_array($value) && (isset($value['gte']) || isset($value['lte']))) {
                        if (isset($value['gte'])) {
                            $filterExpressions[] = "{$field}:>={$value['gte']}";
                        }
                        if (isset($value['lte'])) {
                            $filterExpressions[] = "{$field}:<={$value['lte']}";
                        }
                    } else {
                        $filterExpressions[] = "{$field}:=" . (is_string($value) ? $value : $value);
                    }
                }
                $queryParams['filter_by'] = implode(' && ', $filterExpressions);
            }

            // Apply sort
            if (!empty($options['sort'])) {
                $sortExpressions = array();
                foreach ($options['sort'] as $field => $order) {
                    $sortExpressions[] = "{$field}:" . ($order === 'asc' ? 'asc' : 'desc');
                }
                $queryParams['sort_by'] = implode(',', $sortExpressions);
            }

            // Apply facets
            if (!empty($options['facets'])) {
                $queryParams['facet_by'] = implode(',', $options['facets']);
            }

            // Apply highlight
            if ($options['highlight']) {
                $queryParams['highlight_fields'] = implode(',', $options['search_fields']);
                $queryParams['highlight_start_tag'] = '<mark>';
                $queryParams['highlight_end_tag'] = '</mark>';
            }

            $response = $this->executeWithRetry('GET', "{$this->host}/collections/{$index}/documents/search", array(), array(), $queryParams);
            if (!$response['success']) {
                return $response;
            }

            $results = array();
            if (isset($response['data']['hits'])) {
                foreach ($response['data']['hits'] as $hit) {
                    $result = $hit['document'];
                    if ($options['highlight'] && isset($hit['highlights'])) {
                        $result['highlight'] = array();
                        foreach ($hit['highlights'] as $highlight) {
                            $result['highlight'][$highlight['field']] = $highlight['snippet'];
                        }
                    }
                    $results[] = $result;
                }
            }

            // Process facets
            if (!empty($options['facets']) && isset($response['data']['facet_counts'])) {
                $results['facets'] = array();
                foreach ($response['data']['facet_counts'] as $facet) {
                    $field = $facet['field_name'];
                    $results['facets'][$field] = array_map(function ($count) {
                        return array(
                            'value' => $count['value'],
                            'count' => $count['count']
                        );
                    }, $facet['counts']);
                }
            }

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

            return array('success' => true, 'data' => $results, 'error' => null);
        };

        /**
         * Updates a document.
         */
        $driver['update'] = function ($index, $id, $document, FastSearch $fs) {
            if (empty($id)) {
                return array(
                    'success' => false,
                    'data' => null,
                    'error' => new FastSearchError(
                        "Document ID cannot be empty.",
                        'INVALID_ID',
                        null,
                        array('index' => $index)
                    )
                );
            }
            $document['id'] = $id;
            $response = $this->executeWithRetry('PATCH', "{$this->host}/collections/{$index}/documents/{$id}", $document);
            if ($response['success']) {
                return array('success' => true, 'data' => null, 'error' => null);
            }
            return $response;
        };

        /**
         * Deletes a document.
         */
        $driver['delete'] = function ($index, $id, FastSearch $fs) {
            if (empty($id)) {
                return array(
                    'success' => false,
                    'data' => null,
                    'error' => new FastSearchError(
                        "Document ID cannot be empty.",
                        'INVALID_ID',
                        null,
                        array('index' => $index)
                    )
                );
            }
            $response = $this->executeWithRetry('DELETE', "{$this->host}/collections/{$index}/documents/{$id}");
            if ($response['success'] || ($response['error'] && $response['error']->getErrorCode() === 'INDEX_NOT_FOUND')) {
                return array('success' => true, 'data' => null, 'error' => null);
            }
            return $response;
        };

        /**
         * Clears all documents from a collection.
         */
        $driver['clear'] = function ($index, FastSearch $fs) {
            $response = $this->executeWithRetry('DELETE', "{$this->host}/collections/{$index}/documents");
            if ($response['success']) {
                return array('success' => true, 'data' => null, 'error' => null);
            }
            return $response;
        };

        /**
         * Refreshes a collection (no-op in Typesense as indexing is synchronous).
         */
        $driver['refresh'] = function ($index, FastSearch $fs) {
            $response = $this->executeWithRetry('GET', "{$this->host}/collections/{$index}");
            if ($response['success']) {
                return array('success' => true, 'data' => null, 'error' => null);
            }
            return $response;
        };

        /**
         * Updates collection settings.
         */
        $driver['updateIndexSettings'] = function ($index, $settings, FastSearch $fs) {
            $response = $this->executeWithRetry('PATCH', "{$this->host}/collections/{$index}", $settings);
            if ($response['success']) {
                return array('success' => true, 'data' => null, 'error' => null);
            }
            return $response;
        };

        return $driver;
    }
}