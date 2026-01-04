<?php
// Tested with Elasticsearch 9.0.2
namespace Dotsystems\App\Parts;

use Dotsystems\App\Parts\Config;
use Dotsystems\App\Parts\FastSearch;
use Dotsystems\App\Parts\FastSearchError;

/**
 * Elasticsearch Driver for FastSearch
 *
 * Provides a unified interface for search operations within the DotApp framework,
 * supporting indexing, searching, and document management with Elasticsearch.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.8 FREE
 * @license   MIT License
 * @date      2014 - 2026
 */

class FastSearchDriverElasticSearch {
    private $host;
    private $auth;
    private static $driver = null;
    private $retryAttempts;
    private $retryDelayMs;

    public static function driver() {
        if (self::$driver === null) {
            self::$driver = new self();
        }
        return self::$driver->getDriver();
    }

    public function __construct() {
        $this->host = Config::searchEngines('elasticsearch_host') ?? 'https://localhost:9200';
        $this->auth = [
            'username' => Config::searchEngines('elasticsearch_username') ?? '',
            'password' => Config::searchEngines('elasticsearch_password') ?? '',
            'ca_file' => Config::searchEngines('elasticsearch_ca_file') ?? '',
            'ca_fingerprint' => Config::searchEngines('elasticsearch_ca_fingerprint') ?? ''
        ];
        $this->retryAttempts = Config::searchEngines('elasticsearch_retry_attempts') ?? 3;
        $this->retryDelayMs = Config::searchEngines('elasticsearch_retry_delay_ms') ?? 200;
    }

    private function mapElasticsearchError(array $response, string $operation): FastSearchError {
        $httpCode = $response['http_code'] ?? 500;
        $errorMessage = $response['error'] ?? 'Unknown error';
        $context = ['response' => $response['response'] ?? [], 'operation' => $operation];
        $errorType = $response['response']['error']['type'] ?? null;

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

        switch ($errorType) {
            case 'resource_already_exists_exception':
                return new FastSearchError(
                    "Index already exists for operation $operation: $errorMessage",
                    'INDEX_ALREADY_EXISTS',
                    $httpCode,
                    $context
                );
            case 'index_not_found_exception':
                return new FastSearchError(
                    "Index not found for operation $operation: $errorMessage",
                    'INDEX_NOT_FOUND',
                    $httpCode,
                    $context
                );
            case 'document_missing_exception':
                return new FastSearchError(
                    "Document not found for operation $operation: $errorMessage",
                    'DOCUMENT_NOT_FOUND',
                    $httpCode,
                    $context
                );
            case 'mapper_parsing_exception':
            case 'invalid_type_name_exception':
                return new FastSearchError(
                    "Invalid field definition for operation $operation: $errorMessage",
                    'INVALID_FIELDS',
                    $httpCode,
                    $context
                );
            case 'illegal_argument_exception':
                $reason = $response['response']['error']['reason'] ?? '';
                if (strpos($reason, 'field') !== false) {
                    return new FastSearchError(
                        "Invalid property for operation $operation: $errorMessage",
                        'INVALID_PROPERTY',
                        $httpCode,
                        $context
                    );
                }
                return new FastSearchError(
                    "Invalid parameters for operation $operation: $errorMessage",
                    'INVALID_PARAMETERS',
                    $httpCode,
                    $context
                );
            case 'security_exception':
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
            case 'cluster_block_exception':
            case 'no_shard_available_action_exception':
                return new FastSearchError(
                    "Server error for operation $operation: $errorMessage",
                    'SERVER_ERROR',
                    $httpCode,
                    $context
                );
            case 'version_conflict_engine_exception':
                return new FastSearchError(
                    "Update failed due to version conflict for operation $operation: $errorMessage",
                    'UPDATE_FAILED',
                    $httpCode,
                    $context
                );
            case 'parsing_exception':
                return new FastSearchError(
                    "Invalid request for operation $operation: $errorMessage",
                    'INVALID_REQUEST',
                    $httpCode,
                    $context
                );
            case 'search_phase_execution_exception':
                return new FastSearchError(
                    "Search failed for operation $operation: $errorMessage",
                    'SEARCH_FAILED',
                    $httpCode,
                    $context
                );
            case 'strict_dynamic_mapping_exception':
                return new FastSearchError(
                    "Invalid document properties for operation $operation: $errorMessage",
                    'INVALID_PROPERTIES',
                    $httpCode,
                    $context
                );
            default:
                switch ($httpCode) {
                    case 401:
                    case 403:
                        return new FastSearchError(
                            "Authentication failed for operation $operation: $errorMessage",
                            'AUTHENTICATION_FAILED',
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
                    case 503:
                        return new FastSearchError(
                            "Server unavailable for operation $operation: $errorMessage",
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

            $error = $this->mapElasticsearchError($response, strtolower($method) . '_request');

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
            'error' => $this->mapElasticsearchError($response, strtolower($method) . '_request')
        ];
    }

    private function getDriver(): array {
        $driver = [];

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

            $response = $this->executeWithRetry('HEAD', "{$this->host}/{$index}");
            if ($response['success']) {
                return ['success' => true, 'data' => null, 'error' => null];
            }
            if ($response['error'] && $response['error']->getErrorCode() === 'INDEX_NOT_FOUND') {
                $mappings = [
                    'mappings' => [
                        'properties' => array_map(function ($type) {
                            return ['type' => $type === 'string' ? 'keyword' : $type];
                        }, $fields),
                    ],
                ];
                $createResponse = $this->executeWithRetry('PUT', "{$this->host}/{$index}", $mappings);
                if ($createResponse['success']) {
                    return ['success' => true, 'data' => null, 'error' => null];
                }
                if ($createResponse['error'] && $createResponse['error']->getErrorCode() === 'INDEX_ALREADY_EXISTS') {
                    return ['success' => true, 'data' => null, 'error' => null];
                }
                return $createResponse;
            }
            return $response;
        };

        $driver['indexExists'] = function (string $index, FastSearch $fs): array {
            $response = $this->executeWithRetry('HEAD', "{$this->host}/{$index}");
            return [
                'success' => true,
                'data' => $response['success'],
                'error' => $response['error'] && $response['error']->getErrorCode() !== 'INDEX_NOT_FOUND' ? $response['error'] : null,
            ];
        };

        $driver['deleteIndex'] = function (string $index, FastSearch $fs): array {
            $response = $this->executeWithRetry('DELETE', "{$this->host}/{$index}");
            if ($response['success'] || ($response['error'] && $response['error']->getErrorCode() === 'INDEX_NOT_FOUND')) {
                return ['success' => true, 'data' => null, 'error' => null];
            }
            return $response;
        };

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
            $response = $this->executeWithRetry('PUT', "{$this->host}/{$index}/_doc/{$id}", $document);
            if ($response['success']) {
                return ['success' => true, 'data' => null, 'error' => null];
            }
            return $response;
        };

        $driver['bulkIndex'] = function (string $index, array $documents, FastSearch $fs): array {
            if (empty($documents)) {
                return ['success' => true, 'data' => [], 'error' => null];
            }
            $bulkData = '';
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
                $bulkData .= json_encode(['index' => ['_id' => $id]]) . "\n";
                $bulkData .= json_encode($document) . "\n";
            }
            $response = $this->executeWithRetry('POST', "{$this->host}/{$index}/_bulk", [], ['Content-Type: application/x-ndjson'], [], $bulkData);
            if ($response['success'] && isset($response['data']['items'])) {
                $results = [];
                foreach ($response['data']['items'] as $item) {
                    $id = $item['index']['_id'];
                    $results[$id] = !isset($item['index']['error']);
                }
                return ['success' => true, 'data' => $results, 'error' => null];
            }
            return $response;
        };

        $driver['normalizeOptions'] = function (array $options): array {
            $normalized = [
                'num_typos' => isset($options['typo_tolerance']) ? ($options['typo_tolerance'] === false ? 0 : ($options['typo_tolerance'] === 'auto' ? 2 : (int)$options['typo_tolerance'])) : 2,
                'case_sensitive' => $options['case_sensitive'] ?? false,
                'search_fields' => $options['search_fields'] ?? ['name'],
                'return_fields' => $options['return_fields'] ?? null,
                'sort' => $options['sort'] ?? [],
                'highlight' => $options['highlight'] ?? false,
                'match' => $options['match_type'] ?? 'any',
                'facets' => $options['facets'] ?? [],
            ];

            $allowedOptions = ['typo_tolerance', 'case_sensitive', 'search_fields', 'return_fields', 'sort', 'highlight', 'match_type', 'facets'];
            foreach (array_keys($options) as $option) {
                if (!in_array($option, $allowedOptions)) {
                    throw new \InvalidArgumentException("Unsupported option '$option'. Supported options are: " . implode(', ', $allowedOptions));
                }
            }

            return $normalized;
        };

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
            $body = [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'multi_match' => [
                                    'query' => $query,
                                    'fields' => $options['search_fields'],
                                    'fuzziness' => $options['num_typos'] > 0 ? 'AUTO' : 0,
                                    'type' => $options['match'] === 'phrase' ? 'phrase' : 'best_fields',
                                ],
                            ],
                        ],
                        'filter' => [],
                    ],
                ],
                'size' => $limit,
                'from' => $offset,
            ];

            foreach ($filters as $field => $value) {
                if (is_array($value) && (isset($value['gte']) || isset($value['lte']))) {
                    $body['query']['bool']['filter'][] = [
                        'range' => [
                            $field => array_filter([
                                'gte' => $value['gte'] ?? null,
                                'lte' => $value['lte'] ?? null,
                            ]),
                        ],
                    ];
                } else {
                    $body['query']['bool']['filter'][] = [
                        'term' => [$field => $value],
                    ];
                }
            }

            if (!empty($options['sort'])) {
                $body['sort'] = array_map(function ($order, $field) {
                    return [$field => ['order' => $order]];
                }, array_values($options['sort']), array_keys($options['sort']));
            }

            if ($options['highlight']) {
                $body['highlight'] = [
                    'fields' => array_fill_keys($options['search_fields'], []),
                    'pre_tags' => ['<mark>'],
                    'post_tags' => ['</mark>'],
                ];
            }

            if (!empty($options['facets'])) {
                $body['aggs'] = [];
                foreach ($options['facets'] as $field) {
                    $body['aggs'][$field] = [
                        'terms' => ['field' => $field],
                    ];
                }
            }

            if (isset($options['return_fields'])) {
                $body['_source'] = $options['return_fields'];
            }

            $response = $this->executeWithRetry('POST', "{$this->host}/{$index}/_search", $body);
            if (!$response['success']) {
                return $response;
            }

            $results = [];
            if (isset($response['data']['hits']['hits'])) {
                foreach ($response['data']['hits']['hits'] as $hit) {
                    $result = $hit['_source'];
                    $result['id'] = $hit['_id']; // Include document ID in results
                    if ($options['highlight'] && isset($hit['highlight'])) {
                        $result['highlight'] = $hit['highlight'];
                    }
                    $results[] = $result;
                }
            }

            if (!empty($options['facets']) && isset($response['data']['aggregations'])) {
                $results['facets'] = [];
                foreach ($options['facets'] as $field) {
                    if (isset($response['data']['aggregations'][$field]['buckets'])) {
                        $results['facets'][$field] = array_map(function ($bucket) {
                            return [
                                'value' => $bucket['key'],
                                'count' => $bucket['doc_count'],
                            ];
                        }, $response['data']['aggregations'][$field]['buckets']);
                    }
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

            return ['success' => true, 'data' => $results, 'error' => null];
        };

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
            $response = $this->executeWithRetry('POST', "{$this->host}/{$index}/_update/{$id}", ['doc' => $document]);
            if ($response['success']) {
                return ['success' => true, 'data' => null, 'error' => null];
            }
            return $response;
        };

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
            $response = $this->executeWithRetry('DELETE', "{$this->host}/{$index}/_doc/{$id}");
            if ($response['success'] || ($response['error'] && $response['error']->getErrorCode() === 'DOCUMENT_NOT_FOUND')) {
                return ['success' => true, 'data' => null, 'error' => null];
            }
            return $response;
        };

        $driver['clear'] = function (string $index, FastSearch $fs): array {
            $response = $this->executeWithRetry('POST', "{$this->host}/{$index}/_delete_by_query", ['query' => ['match_all' => new \stdClass()]]);
            if ($response['success']) {
                return ['success' => true, 'data' => null, 'error' => null];
            }
            return $response;
        };

        $driver['refresh'] = function (string $index, FastSearch $fs): array {
            $response = $this->executeWithRetry('POST', "{$this->host}/{$index}/_refresh");
            if ($response['success']) {
                return ['success' => true, 'data' => null, 'error' => null];
            }
            return $response;
        };

        $driver['getIndexSchema'] = function (string $index, FastSearch $fs): array {
            $response = $this->executeWithRetry('GET', "{$this->host}/{$index}/_mapping");
            if ($response['success'] && isset($response['data'][$index]['mappings']['properties'])) {
                $properties = $response['data'][$index]['mappings']['properties'];
                $schema = [];
                foreach ($properties as $field => $config) {
                    $schema[$field] = $config['type'] ?? 'unknown';
                }
                return ['success' => true, 'data' => $schema, 'error' => null];
            }
            return $response;
        };

        $driver['updateIndexSettings'] = function (string $index, array $settings, FastSearch $fs): array {
            $response = $this->executeWithRetry('PUT', "{$this->host}/{$index}/_settings", $settings);
            if ($response['success']) {
                return ['success' => true, 'data' => null, 'error' => null];
            }
            return $response;
        };

        return $driver;
    }
}
?>
