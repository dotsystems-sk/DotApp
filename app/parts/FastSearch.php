<?php
/**
 * CLASS FastSearch - DotApp Search Engine Manager
 *
 * Manages search operations within the DotApp framework, providing a unified interface
 * for indexing, searching, and managing documents across multiple search engines
 * (Elasticsearch, OpenSearch, Meilisearch, Algolia, Typesense). Supports e-commerce
 * use cases with robust, universal APIs and driver-based engine abstraction.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.7 FREE
 * @license   MIT License
 * @date      2014 - 2025
 */

namespace Dotsystems\App\Parts;

use Dotsystems\App\Parts\Config;
use Dotsystems\App\Parts\FastSearchError;

class FastSearch {
    private static $instances = [];
    private $searchName;
    private $driver;
    private $search_manager;

    // Constant for marking no index configuration
    const NO_INDEX = 'DotApp_FastSearch_NoIndex_Unique_9283746501928374650192837465019283746501';

    public function __construct($searchName, $driver) {
        $this->searchName = $searchName;
        $this->driver = $driver;
        self::$instances[$searchName] = $this;
        $this->search_manager['managers'] = [];
        foreach (Config::searchDriver($this->driver) as $way => $wayFn) {
            $this->search_manager['managers'][$this->driver][$way] = $wayFn;
        }
    }

    public function name() {
        return $this->searchName;
    }

    public static function use($searchName = null, $driver = null) {
        if ($searchName === null) {
            $searchName = hash('sha256', 'DotApp Framework null Search :)');
        }
        if ($driver === null) {
            $driver = Config::searchEngines('driver') ?? 'default';
        }
        if (isset(self::$instances[$searchName])) {
            return self::$instances[$searchName];
        }
        return new self($searchName, $driver);
    }

    /**
     * Configures the index with specified fields or marks it as not requiring configuration.
     *
     * @param string $index Name of the search index
     * @param array|string $fields Array of field definitions (name => type) or FastSearch::NO_INDEX
     * @return array ['success' => bool, 'data' => mixed, 'error' => FastSearchError|null]
     */
    public function configureIndex(string $index, $fields = []): array {
        if ($fields !== self::NO_INDEX && !is_array($fields)) {
            return [
                'success' => false,
                'data' => null,
                'error' => new FastSearchError(
                    "Fields must be an array of field definitions (name => type) or FastSearch::NO_INDEX.",
                    'INVALID_FIELDS',
                    null,
                    ['index' => $index]
                )
            ];
        }
        if ($fields !== self::NO_INDEX && empty($fields)) {
            return [
                'success' => false,
                'data' => null,
                'error' => new FastSearchError(
                    "Fields array cannot be empty unless FastSearch::NO_INDEX is used.",
                    'EMPTY_FIELDS',
                    null,
                    ['index' => $index]
                )
            ];
        }
        return call_user_func($this->search_manager['managers'][$this->driver]['configureIndex'], $index, $fields, $this);
    }

    /**
     * Checks if an index exists.
     *
     * @param string $index Name of the search index
     * @return array ['success' => bool, 'data' => bool, 'error' => FastSearchError|null]
     */
    public function indexExists(string $index): array {
        return call_user_func($this->search_manager['managers'][$this->driver]['indexExists'], $index, $this);
    }

    /**
     * Deletes an index.
     *
     * @param string $index Name of the search index
     * @return array ['success' => bool, 'data' => null, 'error' => FastSearchError|null]
     */
    public function deleteIndex(string $index): array {
        return call_user_func($this->search_manager['managers'][$this->driver]['deleteIndex'], $index, $this);
    }

    /**
     * Indexes a single document.
     *
     * @param string $index Name of the search index
     * @param string $id Document ID
     * @param array $document Document data
     * @return array ['success' => bool, 'data' => null, 'error' => FastSearchError|null]
     */
    public function index(string $index, string $id, array $document): array {
        return call_user_func($this->search_manager['managers'][$this->driver]['index'], $index, $id, $document, $this);
    }

    /**
     * Bulk indexes multiple documents.
     *
     * @param string $index Name of the search index
     * @param array $documents Array of [id => document] pairs
     * @return array ['success' => bool, 'data' => array, 'error' => FastSearchError|null]
     */
    public function bulkIndex(string $index, array $documents): array {
        return call_user_func($this->search_manager['managers'][$this->driver]['bulkIndex'], $index, $documents, $this);
    }

    /**
     * Searches documents with universal parameters and optional faceting.
     *
     * @param string $index Name of the search index
     * @param string $query Search query
     * @param array $filters Key-value pairs or range filters
     * @param int $limit Maximum number of results
     * @param int $offset Result offset for pagination
     * @param array $options Search options (typo_tolerance, case_sensitive, etc.)
     * @return array ['success' => bool, 'data' => array, 'error' => FastSearchError|null]
     */
    public function search(string $index, string $query, array $filters = [], int $limit = 10, int $offset = 0, array $options = []): array {
        // Validate options
        $allowedOptions = ['typo_tolerance', 'case_sensitive', 'search_fields', 'return_fields', 'sort', 'highlight', 'match_type', 'facets'];
        foreach (array_keys($options) as $option) {
            if (!in_array($option, $allowedOptions)) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => new FastSearchError(
                        "Unsupported option '$option'. Supported options are: " . implode(', ', $allowedOptions),
                        'INVALID_OPTION',
                        null,
                        ['option' => $option]
                    )
                ];
            }
        }

        $normalizedOptions = call_user_func($this->search_manager['managers'][$this->driver]['normalizeOptions'], $options);
        return call_user_func($this->search_manager['managers'][$this->driver]['search'], $index, $query, $filters, $limit, $offset, $normalizedOptions, $this);
    }

    /**
     * Updates a single document.
     *
     * @param string $index Name of the search index
     * @param string $id Document ID
     * @param array $document Document data
     * @return array ['success' => bool, 'data' => null, 'error' => FastSearchError|null]
     */
    public function update(string $index, string $id, array $document): array {
        return call_user_func($this->search_manager['managers'][$this->driver]['update'], $index, $id, $document, $this);
    }

    /**
     * Deletes a single document.
     *
     * @param string $index Name of the search index
     * @param string $id Document ID
     * @return array ['success' => bool, 'data' => null, 'error' => FastSearchError|null]
     */
    public function delete(string $index, string $id): array {
        return call_user_func($this->search_manager['managers'][$this->driver]['delete'], $index, $id, $this);
    }

    /**
     * Clears all documents from an index.
     *
     * @param string $index Name of the search index
     * @return array ['success' => bool, 'data' => null, 'error' => FastSearchError|null]
     */
    public function clear(string $index): array {
        return call_user_func($this->search_manager['managers'][$this->driver]['clear'], $index, $this);
    }

    /**
     * Refreshes an index to make recent changes visible.
     *
     * @param string $index Name of the search index
     * @return array ['success' => bool, 'data' => null, 'error' => FastSearchError|null]
     */
    public function refresh(string $index): array {
        return call_user_func($this->search_manager['managers'][$this->driver]['refresh'], $index, $this);
    }

    /**
     * Retrieves the schema of an index.
     *
     * @param string $index Name of the search index
     * @return array ['success' => bool, 'data' => array, 'error' => FastSearchError|null]
     */
    public function getIndexSchema(string $index): array {
        return call_user_func($this->search_manager['managers'][$this->driver]['getIndexSchema'], $index, $this);
    }

    /**
     * Updates index settings (e.g., analyzers, replicas).
     *
     * @param string $index Name of the search index
     * @param array $settings Settings to update
     * @return array ['success' => bool, 'data' => null, 'error' => FastSearchError|null]
     */
    public function updateIndexSettings(string $index, array $settings): array {
        return call_user_func($this->search_manager['managers'][$this->driver]['updateIndexSettings'], $index, $settings, $this);
    }
}
?>