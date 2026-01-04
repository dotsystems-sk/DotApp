<?php
// Tested with Algolia Online Web API + account
namespace Dotsystems\App\Parts;

use Dotsystems\App\Parts\Config;
use Dotsystems\App\Parts\FastSearch;
use Dotsystems\App\Parts\FastSearchError;

/**
 * Algolia Driver for FastSearch
 *
 * Provides a unified interface for search operations within the DotApp framework,
 * supporting indexing, searching, and document management with Algolia.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.8 FREE
 * @license   MIT License
 * @date      2014 - 2026
 */

class FastSearchDriverAlgolia {
    private $appId;
    private $searchApiKey;
    private $writeApiKey;
    private $caFile;
    private $caFingerprint;
    private static $driver = null;
    private $retryAttempts;
    private $retryDelayMs;
    private $waitForTask;
    private $waitMaxAttempts;
    private $waitDelayMs;

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
        $this->appId = Config::searchEngines('algolia_app_id') ?: '';
        $this->searchApiKey = Config::searchEngines('algolia_search_api_key') ?: '';
        $this->writeApiKey = Config::searchEngines('algolia_write_api_key') ?: '';
        $this->caFile = Config::searchEngines('algolia_ca_file') ?: '';
        $this->caFingerprint = Config::searchEngines('algolia_ca_fingerprint') ?: '';
        $this->retryAttempts = Config::searchEngines('algolia_retry_attempts') ?: 3;
        $this->retryDelayMs = Config::searchEngines('algolia_retry_delay_ms') ?: 200;
        $this->waitForTask = Config::searchEngines('algolia_wait_for_task', false);
        $this->waitMaxAttempts = Config::searchEngines('algolia_wait_max_attempts', 10);
        $this->waitDelayMs = Config::searchEngines('algolia_wait_delay_ms', 500);

        if (empty($this->appId)) {
            throw new \Exception('Algolia Application ID is required.');
        }
    }

    /**
     * Generates the appropriate host URL based on the operation type.
     *
     * @param string $operation Operation type ('search' or 'write')
     * @return string Host URL
     */
    private function getHost($operation) {
        if ($operation === 'search') {
            return "https://{$this->appId}-dsn.algolia.net";
        }
        return "https://{$this->appId}.algolia.net";
    }

    /**
     * Maps Algolia error messages to FastSearch error codes.
     *
     * @param array $response HTTP response from Algolia
     * @param string $operation Operation that failed
     * @return FastSearchError
     */
    private function mapAlgoliaError($response, $operation) {
        $httpCode = isset($response['http_code']) ? $response['http_code'] : 500;
        $errorMessage = isset($response['error']) ? $response['error'] : 'Unknown error';
        $context = ['response' => isset($response['response']) ? $response['response'] : [], 'operation' => $operation];
        $algoliaMessage = isset($response['response']['message']) ? $response['response']['message'] : '';

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

        // Algolia-specific error handling
        if (strpos($algoliaMessage, 'Index does not exist') !== false) {
            return new FastSearchError(
                "Index not found for operation $operation: $algoliaMessage",
                'INDEX_NOT_FOUND',
                $httpCode,
                $context
            );
        }
        if (strpos($algoliaMessage, 'ObjectID does not exist') !== false) {
            return new FastSearchError(
                "Document not found for operation $operation: $algoliaMessage",
                'DOCUMENT_NOT_FOUND',
                $httpCode,
                $context
            );
        }
        if (strpos($algoliaMessage, 'Invalid Application-ID or API key') !== false) {
            return new FastSearchError(
                "Authentication failed for operation $operation: $algoliaMessage",
                'AUTHENTICATION_FAILED',
                $httpCode,
                $context
            );
        }
        if (strpos($algoliaMessage, 'Invalid attribute') !== false || strpos($algoliaMessage, 'Attribute') !== false) {
            return new FastSearchError(
                "Invalid fields for operation $operation: $algoliaMessage",
                'INVALID_FIELDS',
                $httpCode,
                $context
            );
        }
        if (strpos($algoliaMessage, 'Invalid parameter') !== false) {
            return new FastSearchError(
                "Invalid parameters for operation $operation: $algoliaMessage",
                'INVALID_PARAMETERS',
                $httpCode,
                $context
            );
        }

        // HTTP-based fallback
        switch ($httpCode) {
            case 400:
                return new FastSearchError(
                    "Invalid request for operation $operation: $algoliaMessage",
                    'INVALID_REQUEST',
                    $httpCode,
                    $context
                );
            case 401:
            case 403:
                return new FastSearchError(
                    "Authentication failed for operation $operation: $algoliaMessage",
                    'AUTHENTICATION_FAILED',
                    $httpCode,
                    $context
                );
            case 404:
                return new FastSearchError(
                    "Resource not found for operation $operation: $algoliaMessage",
                    'INDEX_NOT_FOUND',
                    $httpCode,
                    $context
                );
            case 429:
                return new FastSearchError(
                    "Rate limit exceeded for operation $operation: $algoliaMessage",
                    'RATE_LIMIT',
                    $httpCode,
                    $context
                );
            case 500:
            case 503:
                return new FastSearchError(
                    "Server error for operation $operation: $algoliaMessage",
                    'SERVER_ERROR',
                    $httpCode,
                    $context
                );
            default:
                return new FastSearchError(
                    "Unknown error for operation $operation: $algoliaMessage",
                    'UNKNOWN',
                    $httpCode,
                    $context
                );
        }
    }

    /**
     * Executes an HTTP request with retry logic for transient errors.
     *
     * @param string $method HTTP method
     * @param string $endpoint Target endpoint (relative to host)
     * @param string $operationType 'search' or 'write'
     * @param array $data Data to send
     * @param array $headers Additional HTTP headers
     * @param array $queryParams Query parameters
     * @param string|null $rawBody Raw body
     * @return array Response array
     */
    private function executeWithRetry($method, $endpoint, $operationType, $data = [], $headers = [], $queryParams = [], $rawBody = null) {
        $attempts = 0;
        $maxAttempts = $this->retryAttempts;
        $baseDelayMs = $this->retryDelayMs;
        $retryableCodes = [408, 429, 503];

        // Select host and API key based on operation type
        $host = $this->getHost($operationType);
        $apiKey = $operationType === 'search' ? $this->searchApiKey : $this->writeApiKey;
        $url = "$host$endpoint";

        // Add Algolia authentication headers
        $headers[] = 'X-Algolia-Application-Id: ' . $this->appId;
        $headers[] = 'X-Algolia-API-Key: ' . $apiKey;

        // Add SSL verification if configured
        $auth = [
            'ca_file' => $this->caFile,
            'ca_fingerprint' => $this->caFingerprint
        ];

        while ($attempts < $maxAttempts) {
            $response = HttpHelper::request($method, $url, $data, $auth, $headers, $queryParams, $rawBody);

            if ($response['success']) {
                return [
                    'success' => true,
                    'data' => $response['response'],
                    'error' => null
                ];
            }

            $error = $this->mapAlgoliaError($response, strtolower($method) . '_request');

            if (in_array($response['http_code'], $retryableCodes) || $error->getErrorCode() === 'RATE_LIMIT' || $error->getErrorCode() === 'CONNECTION') {
                $attempts++;
                if ($attempts < $maxAttempts) {
                    $delayMs = $baseDelayMs * pow(2, $attempts);
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
            'error' => $this->mapAlgoliaError($response, strtolower($method) . '_request')
        ];
    }

    /**
     * Waits for an Algolia task to complete.
     *
     * @param string $index Index name
     * @param int $taskID Task ID returned by Algolia
     * @return array Response array
     */
    private function waitForTask($index, $taskID) {
        $attempts = 0;
        while ($attempts < $this->waitMaxAttempts) {
            $response = $this->executeWithRetry('GET', "/1/indexes/{$index}/task/{$taskID}", 'write');
            if (!$response['success']) {
                return $response;
            }
            if (isset($response['data']['status']) && $response['data']['status'] === 'published') {
                return ['success' => true, 'data' => null, 'error' => null];
            }
            $attempts++;
            usleep($this->waitDelayMs * 1000); // Wait before next attempt
        }
        return [
            'success' => false,
            'data' => null,
            'error' => new FastSearchError(
                "Task {$taskID} for index {$index} did not complete within {$this->waitMaxAttempts} attempts.",
                'SERVER_ERROR',
                null,
                ['index' => $index, 'taskID' => $taskID]
            )
        ];
    }

    /**
     * Returns the driver functions for FastSearch.
     */
    private function getDriver() {
        $driver = [];

        /**
         * Configures an index with the specified schema.
         */
        $driver['configureIndex'] = function ($index, $fields, FastSearch $fs) {
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
                    )
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
                        )
                    ];
                }
            }

            $response = $this->executeWithRetry('GET', "/1/indexes/{$index}", 'write');
            if ($response['success']) {
                $settings = [
                    'searchableAttributes' => array_keys($fields),
                    'attributesForFaceting' => array_keys($fields)
                ];
                $updateResponse = $this->executeWithRetry('PUT', "/1/indexes/{$index}/settings", 'write', $settings);
                if (!$updateResponse['success']) {
                    return $updateResponse;
                }
                if ($this->waitForTask && isset($updateResponse['data']['taskID'])) {
                    return $this->waitForTask($index, $updateResponse['data']['taskID']);
                }
                return ['success' => true, 'data' => null, 'error' => null];
            }
            if ($response['error'] && $response['error']->getErrorCode() === 'INDEX_NOT_FOUND') {
                $createResponse = $this->executeWithRetry('PUT', "/1/indexes/{$index}", 'write', []);
                if ($createResponse['success']) {
                    $settings = [
                        'searchableAttributes' => array_keys($fields),
                        'attributesForFaceting' => array_keys($fields)
                    ];
                    $updateResponse = $this->executeWithRetry('PUT', "/1/indexes/{$index}/settings", 'write', $settings);
                    if ($updateResponse['success']) {
                        if ($this->waitForTask && isset($updateResponse['data']['taskID'])) {
                            return $this->waitForTask($index, $updateResponse['data']['taskID']);
                        }
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
        $driver['indexExists'] = function ($index, FastSearch $fs) {
            $response = $this->executeWithRetry('GET', "/1/indexes/{$index}", 'search');
            return [
                'success' => true,
                'data' => $response['success'],
                'error' => $response['error'] && $response['error']->getErrorCode() !== 'INDEX_NOT_FOUND' ? $response['error'] : null
            ];
        };

        /**
         * Deletes an index.
         */
        $driver['deleteIndex'] = function ($index, FastSearch $fs) {
            $response = $this->executeWithRetry('DELETE', "/1/indexes/{$index}", 'write');
            if ($response['success']) {
                if ($this->waitForTask && isset($response['data']['taskID'])) {
                    return $this->waitForTask($index, $response['data']['taskID']);
                }
                return ['success' => true, 'data' => null, 'error' => null];
            }
            if ($response['error'] && $response['error']->getErrorCode() === 'INDEX_NOT_FOUND') {
                return ['success' => true, 'data' => null, 'error' => null];
            }
            return $response;
        };

        /**
         * Indexes a single document.
         */
        $driver['index'] = function ($index, $id, $document, FastSearch $fs) {
            if (empty($id)) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => new FastSearchError(
                        "Document ID cannot be empty.",
                        'INVALID_ID',
                        null,
                        ['index' => $index]
                    )
                ];
            }
            $document['objectID'] = $id;
            $response = $this->executeWithRetry('PUT', "/1/indexes/{$index}/{$id}", 'write', $document);
            if ($response['success']) {
                if ($this->waitForTask && isset($response['data']['taskID'])) {
                    return $this->waitForTask($index, $response['data']['taskID']);
                }
                return ['success' => true, 'data' => ['taskID' => $response['data']['taskID']], 'error' => null];
            }
            return $response;
        };

        /**
         * Bulk indexes multiple documents.
         */
        $driver['bulkIndex'] = function ($index, $documents, FastSearch $fs) {
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
                        )
                    ];
                }
                $document['objectID'] = $id;
                $bulkDocuments[] = ['action' => 'addObject', 'body' => $document];
            }
            $response = $this->executeWithRetry('POST', "/1/indexes/{$index}/batch", 'write', ['requests' => $bulkDocuments]);
            if ($response['success']) {
                $results = [];
                foreach ($documents as $id => $doc) {
                    $results[$id] = true; // Algolia doesn't provide per-document status
                }
                if ($this->waitForTask && isset($response['data']['taskID'])) {
                    $waitResponse = $this->waitForTask($index, $response['data']['taskID']);
                    if (!$waitResponse['success']) {
                        return $waitResponse;
                    }
                    return ['success' => true, 'data' => $results, 'error' => null];
                }
                return ['success' => true, 'data' => array_merge($results, ['taskID' => $response['data']['taskID']]), 'error' => null];
            }
            return $response;
        };

        /**
         * Normalizes search options for Algolia.
         */
        $driver['normalizeOptions'] = function ($options) {
            $normalized = [
                'num_typos' => isset($options['typo_tolerance']) ? ($options['typo_tolerance'] === false ? 0 : ($options['typo_tolerance'] === 'auto' ? 2 : (int)$options['typo_tolerance'])) : 2,
                'case_sensitive' => isset($options['case_sensitive']) ? $options['case_sensitive'] : false,
                'search_fields' => isset($options['search_fields']) ? $options['search_fields'] : ['*'],
                'return_fields' => isset($options['return_fields']) ? $options['return_fields'] : null,
                'sort' => isset($options['sort']) ? $options['sort'] : [],
                'highlight' => isset($options['highlight']) ? $options['highlight'] : false,
                'match' => isset($options['match_type']) ? $options['match_type'] : 'any',
                'facets' => isset($options['facets']) ? $options['facets'] : [],
            ];

            $allowedOptions = ['typo_tolerance', 'case_sensitive', 'search_fields', 'return_fields', 'sort', 'highlight', 'match_type', 'facets'];
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
                return [
                    'success' => false,
                    'data' => null,
                    'error' => new FastSearchError(
                        "Limit and offset must be non-negative.",
                        'INVALID_PARAMETERS',
                        null,
                        ['limit' => $limit, 'offset' => $offset]
                    )
                ];
            }
            $body = [
                'query' => $query,
                'hitsPerPage' => $limit,
                'page' => floor($offset / $limit),
                'attributesToRetrieve' => $options['return_fields'] ? $options['return_fields'] : ['*'],
                'typoTolerance' => $options['num_typos'] > 0 ? 'true' : 'false',
            ];

            if (!empty($filters)) {
                $filterExpressions = [];
                foreach ($filters as $field => $value) {
                    if (is_array($value) && (isset($value['gte']) || isset($value['lte']))) {
                        if (isset($value['gte'])) {
                            $filterExpressions[] = "$field:{$value['gte']} TO *";
                        }
                        if (isset($value['lte'])) {
                            $filterExpressions[] = "$field:* TO {$value['lte']}";
                        }
                    } else {
                        $filterExpressions[] = "$field:$value";
                    }
                }
                $body['filters'] = implode(' AND ', $filterExpressions);
            }

            if (!empty($options['sort'])) {
                $body['sort'] = [];
                foreach ($options['sort'] as $field => $order) {
                    $body['sort'][] = $field . ':' . ($order === 'asc' ? 'asc' : 'desc');
                }
            }

            if ($options['highlight']) {
                $body['attributesToHighlight'] = $options['search_fields'];
                $body['highlightPreTag'] = '<mark>';
                $body['highlightPostTag'] = '</mark>';
            }

            if (!empty($options['facets'])) {
                $body['facets'] = $options['facets'];
            }

            $response = $this->executeWithRetry('POST', "/1/indexes/{$index}/query", 'search', $body);
            if (!$response['success']) {
                return $response;
            }

            $results = [];
            if (isset($response['data']['hits'])) {
                foreach ($response['data']['hits'] as $hit) {
                    $result = [];
                    // Rename objectID to id for consistency with other drivers
                    if (isset($hit['objectID'])) {
                        $result['id'] = $hit['objectID'];
                        unset($hit['objectID']);
                    }
                    // Copy all other fields
                    $result = array_merge($result, $hit);
                    // Handle highlight data
                    if ($options['highlight'] && isset($hit['_highlightResult'])) {
                        $result['highlight'] = [];
                        foreach ($hit['_highlightResult'] as $field => $highlight) {
                            if (isset($highlight['value'])) {
                                $result['highlight'][$field] = $highlight['value'];
                            }
                        }
                    }
                    $results[] = $result;
                }
            }

            if (!empty($options['facets']) && isset($response['data']['facets'])) {
                $results['facets'] = [];
                foreach ($options['facets'] as $field) {
                    if (isset($response['data']['facets'][$field])) {
                        $results['facets'][$field] = [];
                        foreach ($response['data']['facets'][$field] as $value => $count) {
                            $results['facets'][$field][] = [
                                'value' => $value,
                                'count' => $count
                            ];
                        }
                    }
                }
            }

            if ($options['case_sensitive'] && !empty($results)) {
                $results = array_filter($results, function ($hit) use ($query, $options) {
                    foreach ($options['search_fields'] as $field) {
                        if (isset($hit[$field])) {
                            $distance = levenshtein($hit[$field], $query);
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
        $driver['update'] = function ($index, $id, $document, FastSearch $fs) {
            if (empty($id)) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => new FastSearchError(
                        "Document ID cannot be empty.",
                        'INVALID_ID',
                        null,
                        ['index' => $index]
                    )
                ];
            }
            $document['objectID'] = $id;
            $response = $this->executeWithRetry('PUT', "/1/indexes/{$index}/{$id}", 'write', $document);
            if ($response['success']) {
                if ($this->waitForTask && isset($response['data']['taskID'])) {
                    return $this->waitForTask($index, $response['data']['taskID']);
                }
                return ['success' => true, 'data' => ['taskID' => $response['data']['taskID']], 'error' => null];
            }
            return $response;
        };

        /**
         * Deletes a document.
         */
        $driver['delete'] = function ($index, $id, FastSearch $fs) {
            if (empty($id)) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => new FastSearchError(
                        "Document ID cannot be empty.",
                        'INVALID_ID',
                        null,
                        ['index' => $index]
                    )
                ];
            }
            $response = $this->executeWithRetry('DELETE', "/1/indexes/{$index}/{$id}", 'write');
            if ($response['success']) {
                if ($this->waitForTask && isset($response['data']['taskID'])) {
                    return $this->waitForTask($index, $response['data']['taskID']);
                }
                return ['success' => true, 'data' => ['taskID' => $response['data']['taskID']], 'error' => null];
            }
            if ($response['error'] && $response['error']->getErrorCode() === 'DOCUMENT_NOT_FOUND') {
                return ['success' => true, 'data' => null, 'error' => null];
            }
            return $response;
        };

        /**
         * Clears all documents from an index.
         */
        $driver['clear'] = function ($index, FastSearch $fs) {
            $response = $this->executeWithRetry('POST', "/1/indexes/{$index}/clear", 'write', []);
            if ($response['success']) {
                if ($this->waitForTask && isset($response['data']['taskID'])) {
                    return $this->waitForTask($index, $response['data']['taskID']);
                }
                return ['success' => true, 'data' => ['taskID' => $response['data']['taskID']], 'error' => null];
            }
            return $response;
        };

        /**
         * Refreshes an index (no-op in Algolia as indexing is asynchronous).
         */
        $driver['refresh'] = function ($index, FastSearch $fs) {
            $response = $this->executeWithRetry('GET', "/1/indexes/{$index}", 'search');
            if ($response['success']) {
                return ['success' => true, 'data' => null, 'error' => null];
            }
            return $response;
        };

        /**
         * Retrieves the schema of an index.
         */
        $driver['getIndexSchema'] = function ($index, FastSearch $fs) {
            $response = $this->executeWithRetry('GET', "/1/indexes/{$index}/settings", 'search');
            if ($response['success']) {
                $schema = [];
                if (isset($response['data']['searchableAttributes'])) {
                    foreach ($response['data']['searchableAttributes'] as $field) {
                        $schema[$field] = 'text'; // Algolia does not expose field types
                    }
                }
                return ['success' => true, 'data' => $schema, 'error' => null];
            }
            return $response;
        };

        /**
         * Updates index settings.
         */
        $driver['updateIndexSettings'] = function ($index, $settings, FastSearch $fs) {
            $response = $this->executeWithRetry('PUT', "/1/indexes/{$index}/settings", 'write', $settings);
            if ($response['success']) {
                if ($this->waitForTask && isset($response['data']['taskID'])) {
                    return $this->waitForTask($index, $response['data']['taskID']);
                }
                return ['success' => true, 'data' => ['taskID' => $response['data']['taskID']], 'error' => null];
            }
            return $response;
        };

        return $driver;
    }
}
?>
