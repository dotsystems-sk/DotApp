<?php

/**
 * DotApp Framework - Reactive Module
 * 
 * This class provides backend support for the DotApp Reactive JavaScript library.
 * It handles reactive endpoint registration, HTML attribute processing, rate limiting,
 * and secure communication between frontend and backend.
 * 
 * @package   DotApp Framework
 * @category  Framework Parts
 * @author    Štefan Miščík <stefan@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.0
 * @date      2025
 * @license   MIT License
 * 
 * License Notice:
 * Permission is granted to use, modify, and distribute this code under the MIT License,
 * provided this header is retained in all copies or substantial portions of the file,
 * including author and company information.
 */

/*
    The Reactive class serves as the backend counterpart to the DotApp Reactive
    JavaScript library (dotapp.reactive.js). It enables reactive data binding
    by processing HTML attributes and managing reactive endpoints.
    
    Key Features:
    - Process reactive-* HTML attributes and convert them to secure versions
    - Register and manage reactive endpoints
    - Rate limiting and security (CRC, CSRF)
    - Hook system (before, after, onError, onResponseCode)
    - Integration with DotApp template engine
    
    This class is completely standalone and does not require modifications
    to other DotApp files.
*/

namespace Dotsystems\App\Parts;
use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\Request;
use \Dotsystems\App\Parts\Router;
use \Dotsystems\App\Parts\DSM;

class Reactive {
    private $reactive; // Stores reactive functions and endpoints
    private $dotapp; // Reference to main DotApp object
    public $dotApp; // camelCase compatibility
    public $DotApp; // PascalCase compatibility
    private $key; // Secure key for verifying requests
    private $objects; // Store objects in session
    private $max_keys;
    private $register_functions; // Flag for function registration
    private $register_function; // Current registration
    private $chain;
    private $newlimiters;
    private static $ReactiveOBJ = null;
    
    // Default configuration
    private $defaultConfig = [
        'storage_limit' => 200,
        'default_retries' => 3,
        'default_retry_delay' => 1000,
        'max_keys' => 200
    ];

    /**
     * Constructor: Initializes the reactive object with storage, keys, and renderer
     * 
     * @param object $dotapp Reference to the main DotApp instance
     */
    function __construct($dotapp) {
        self::$ReactiveOBJ = $this;
        $this->newlimiters = array();
        $this->dotapp = $dotapp;
        $this->dotApp = $this->dotapp;
        $this->DotApp = $this->dotapp;
        
        $this->reactive = array();
        $this->reactive['fn'] = array();
        $this->reactive['endpoints'] = array();
        $this->reactive['hooks'] = array();
        
        $this->register_functions = true;
        
        // Initialize storage
        $this->initializeStorage();
        
        // Initialize key
        $this->initializeKey();
        
        // Initialize limits
        $this->initializeLimits();
        
        // Register renderer automatically
        $this->registerRenderer();
        
        $this->clear_chain();
    }

    function __destruct() {
        $this->dotapp->dsm->set('_reactive_key', $this->key);
        $this->dotapp->dsm->set('_reactive_objects', $this->objects);
    }

    /**
     * Initialize storage from session
     * @private
     */
    private function initializeStorage() {
        $stored = $this->dotapp->dsm->get('_reactive_objects');
        if ($stored != null && is_array($stored)) {
            $this->objects = $stored;
        } else {
            $this->objects = array();
        }
    }

    /**
     * Initialize or load security key
     * @private
     */
    private function initializeKey() {
        if ($this->dotapp->dsm->get('_reactive_key') != null) {
            $this->key = $this->dotapp->dsm->get('_reactive_key');
        } else {
            $this->key = $this->dotapp->generate_strong_password(32, "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789@-");
            $this->dotapp->dsm->set('_reactive_key', $this->key);
        }
    }

    /**
     * Initialize rate limiting defaults
     * @private
     */
    private function initializeLimits() {
        if (!isset($this->objects['limits'])) {
            $this->objects['limits'] = array();
        }
        if (!isset($this->objects['limits']['global'])) {
            $this->objects['limits']['global'] = array();
            $this->objects['limits']['global']['minute'] = 0;
            $this->objects['limits']['global']['hour'] = 0;
            $this->objects['limits']['global']['used'] = 0;
        }
        if (!isset($this->objects['limitersLimits'])) {
            $this->objects['limitersLimits'] = array();
        }
        if (!isset($this->objects['settings_order'])) {
            $this->objects['settings_order'] = array();
        }
        if (!isset($this->objects['settings'])) {
            $this->objects['settings'] = array();
        }
        
        $this->max_keys = $this->defaultConfig['max_keys'];
    }

    /**
     * Register renderer automatically
     * @private
     */
    private function registerRenderer() {
        if ($this->dotapp->router && $this->dotapp->router->renderer) {
            $this->dotapp->router->renderer->addRenderer('reactive', [$this, 'dotReactive']);
        }
    }

    /**
     * Process reactive attributes in HTML code
     * Converts reactive-* attributes to secure versions with encrypted data
     * 
     * @param string $code HTML code to process
     * @return string Processed HTML code with secure reactive attributes
     */
    public function dotReactive($code) {
        // Pattern to find elements with reactive-api attribute
        $pattern = '/<([^>]+)\s+reactive-api=["\']([^"\']+)["\']([^>]*)>/i';
        preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE);
        
        foreach ($matches[0] as $index => $match) {
            $fullMatch = $match[0];
            $tagContent = $matches[1][$index][0];
            $apiUrl = $matches[2][$index][0];
            $remainingAttrs = $matches[3][$index][0];
            
            // Extract all reactive-* attributes
            $attrs = $this->extractReactiveAttributes($remainingAttrs . ' ' . $tagContent);
            
            // Generate unique ID and key
            $endpointId = isset($attrs['id']) ? $attrs['id'] : $this->generate_id();
            $key = $this->generate_id();
            
            // Register endpoint configuration
            $this->registerReactiveEndpoint($endpointId, $key, $apiUrl, $attrs);
            
            // Build replacement attributes
            $replacement = $this->buildSecureAttributes($endpointId, $key, $apiUrl, $attrs);
            
            // Replace the tag
            $newTag = '<' . $tagContent . $replacement . '>';
            $code = str_replace($fullMatch, $newTag, $code);
        }
        
        return $code;
    }

    /**
     * Extract reactive-* attributes from HTML
     * @private
     */
    private function extractReactiveAttributes($attrString) {
        $attrs = array();
        $pattern = '/reactive-(\w+)=["\']([^"\']+)["\']/i';
        preg_match_all($pattern, $attrString, $matches);
        
        for ($i = 0; $i < count($matches[0]); $i++) {
            $name = strtolower($matches[1][$i]);
            $value = $matches[2][$i];
            $attrs[$name] = $value;
        }
        
        return $attrs;
    }

    /**
     * Register reactive endpoint configuration
     * @private
     */
    private function registerReactiveEndpoint($endpointId, $key, $apiUrl, $attrs) {
        $internalID = isset($attrs['id']) ? $attrs['id'] : hash('sha256', md5($apiUrl));
        
        if (!isset($this->objects['settings'][$internalID])) {
            $this->objects['settings'][$internalID] = array();
        }
        
        if (!isset($this->objects['settings'][$internalID]['valid'])) {
            $this->objects['settings'][$internalID]['valid'] = array();
        }
        
        $this->objects['settings'][$internalID]['valid'][$key] = $key;
        $this->clean_array($this->objects['settings'][$internalID]['valid']);
        $this->objects['settings'][$internalID]['api'] = $apiUrl;
        $this->objects['settings'][$internalID]['method'] = isset($attrs['method']) ? strtoupper($attrs['method']) : 'GET';
        $this->objects['settings'][$internalID]['trigger'] = isset($attrs['trigger']) ? $attrs['trigger'] : null;
        $this->objects['settings'][$internalID]['variable'] = isset($attrs['variable']) ? $attrs['variable'] : null;
        $this->objects['settings'][$internalID]['interval'] = isset($attrs['interval']) ? intval($attrs['interval']) : null;
        $this->objects['settings'][$internalID]['template'] = isset($attrs['template']) ? $attrs['template'] : null;
        
        if (!in_array($internalID, $this->objects['settings_order'])) {
            $this->objects['settings_order'][] = $internalID;
        }
        
        $this->clean_old_settings();
        
        if (!in_array($internalID, $this->objects['settings_order'])) {
            $this->objects['settings_order'][] = $internalID;
        }
        
        // Garbage collector
        $this->clean_old_settings();
    }

    /**
     * Build secure attributes for replacement
     * @private
     */
    private function buildSecureAttributes($endpointId, $key, $apiUrl, $attrs) {
        $replacement = '';
        
        // Remove all reactive-* attributes and replace with secure versions
        $replacement .= ' reactive-key="' . htmlspecialchars($this->key, ENT_QUOTES) . '"';
        $replacement .= ' reactive-id="' . htmlspecialchars($key, ENT_QUOTES) . '"';
        $replacement .= ' reactive-endpoint-id="' . htmlspecialchars($endpointId, ENT_QUOTES) . '"';
        
        // Encrypt API URL
        $encryptedApi = $this->dotapp->encrypt($apiUrl, $key);
        $replacement .= ' reactive-data="' . htmlspecialchars($encryptedApi, ENT_QUOTES) . '"';
        
        // Store endpoint ID in data-id
        $encryptedEndpointId = $this->dotapp->encrypt($endpointId, $key);
        $replacement .= ' reactive-data-id="' . htmlspecialchars($encryptedEndpointId, ENT_QUOTES) . '"';
        
        return $replacement;
    }

    /**
     * Clean old settings (garbage collector)
     * @private
     */
    private function clean_old_settings() {
        if (count($this->objects['settings_order']) > $this->max_keys) {
            $toRemove = array_slice($this->objects['settings_order'], 0, count($this->objects['settings_order']) - $this->max_keys);
            foreach ($toRemove as $internalID) {
                unset($this->objects['settings'][$internalID]);
                unset($this->objects['limits'][$internalID]);
                unset($this->objects['limitersLimits'][$internalID]);
            }
            $this->objects['settings_order'] = array_slice($this->objects['settings_order'], -$this->max_keys);
        }
    }

    /**
     * Clean array helper (used for valid keys)
     * @private
     */
    private function clean_array(&$array) {
        try {
            if (is_array($array) && (count($array) > $this->max_keys)) {
                $array = array_slice($array, -$this->max_keys, $this->max_keys);
            }
        } catch (\Exception $e) {
            // Ignore errors
        }
    }

    /**
     * Generate unique ID
     * @return string
     */
    public function generate_id() {
        return $this->dotapp->generate_strong_password(48, "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789") . 
               date("Ymdsisisihhis") . 
               md5(microtime() . rand(5000, 10000));
    }

    /**
     * Clear chain
     */
    public function clear_chain() {
        $this->chain = null;
        return $this;
    }

    /**
     * Create reactive endpoint programmatically
     * 
     * @param string $url API endpoint URL
     * @param array $config Configuration array
     * @return object Endpoint instance with chainable methods
     */
    public function reactive($url, $config = array()) {
        return $this->create($url, $config);
    }

    /**
     * Create reactive endpoint
     * 
     * @param string $url API endpoint URL
     * @param array $config Configuration array
     * @return object Endpoint instance
     */
    public function create($url, $config = array()) {
        $endpointId = isset($config['id']) ? $config['id'] : $this->generate_id();
        $key = $this->generate_id();
        
        $internalID = isset($config['id']) ? $config['id'] : hash('sha256', md5($url));
        
        // Store endpoint configuration
        if (!isset($this->objects['settings'][$internalID])) {
            $this->objects['settings'][$internalID] = array();
        }
        
        $this->objects['settings'][$internalID]['api'] = $url;
        $this->objects['settings'][$internalID]['method'] = isset($config['method']) ? strtoupper($config['method']) : 'GET';
        $this->objects['settings'][$internalID]['trigger'] = isset($config['trigger']) ? $config['trigger'] : null;
        $this->objects['settings'][$internalID]['variable'] = isset($config['variable']) ? $config['variable'] : null;
        $this->objects['settings'][$internalID]['interval'] = isset($config['interval']) ? intval($config['interval']) : null;
        $this->objects['settings'][$internalID]['template'] = isset($config['template']) ? $config['template'] : null;
        
        if (!isset($this->objects['settings'][$internalID]['valid'])) {
            $this->objects['settings'][$internalID]['valid'] = array();
        }
        $this->objects['settings'][$internalID]['valid'][$key] = $key;
        $this->clean_array($this->objects['settings'][$internalID]['valid']);
        
        if (!in_array($internalID, $this->objects['settings_order'])) {
            $this->objects['settings_order'][] = $internalID;
        }
        
        $this->clean_old_settings();
        $this->clean_array($this->objects['settings'][$internalID]['valid']);
        
        if (!isset($this->reactive['endpoints'][$endpointId])) {
            $this->reactive['endpoints'][$endpointId] = array(
                'id' => $endpointId,
                'url' => $url,
                'config' => $config,
                'internalID' => $internalID,
                'hooks' => array(
                    'before' => array(),
                    'after' => array(),
                    'onError' => array(),
                    'onResponseCode' => array()
                )
            );
        }
        
        // Return chainable instance
        return $this->createEndpointInstance($endpointId);
    }

    /**
     * Create endpoint instance for chaining
     * @private
     */
    private function createEndpointInstance($endpointId) {
        $self = $this;
        return new class($self, $endpointId) {
            private $reactive;
            private $endpointId;
            
            public function __construct($reactive, $endpointId) {
                $this->reactive = $reactive;
                $this->endpointId = $endpointId;
            }
            
            public function before($fn) {
                $this->reactive->before($this->endpointId, $fn);
                return $this;
            }
            
            public function after($fn) {
                $this->reactive->after($this->endpointId, $fn);
                return $this;
            }
            
            public function onError($fn) {
                $this->reactive->onError($this->endpointId, $fn);
                return $this;
            }
            
            public function onResponseCode($code, $fn) {
                $this->reactive->onResponseCode($this->endpointId, $code, $fn);
                return $this;
            }
            
            public function refresh() {
                // Manual refresh trigger - would need endpoint URL to fetch
                return $this;
            }
            
            public function destroy() {
                $this->reactive->destroy($this->endpointId);
            }
        };
    }

    /**
     * Get all registered endpoints
     * @return array
     */
    public function getEndpoints() {
        return $this->reactive['endpoints'];
    }

    /**
     * Get specific endpoint by ID
     * @param string $id Endpoint ID
     * @return array|null
     */
    public function getEndpoint($id) {
        return isset($this->reactive['endpoints'][$id]) ? $this->reactive['endpoints'][$id] : null;
    }

    /**
     * Destroy all endpoints
     */
    public function destroyAll() {
        $this->reactive['endpoints'] = array();
        $this->objects['settings'] = array();
        $this->objects['settings_order'] = array();
    }

    /**
     * Destroy specific endpoint
     * @param string $id Endpoint ID
     */
    public function destroy($id) {
        if (isset($this->reactive['endpoints'][$id])) {
            $endpoint = $this->reactive['endpoints'][$id];
            $internalID = $endpoint['internalID'];
            
            unset($this->reactive['endpoints'][$id]);
            unset($this->objects['settings'][$internalID]);
            
            // Remove from order
            $key = array_search($internalID, $this->objects['settings_order']);
            if ($key !== false) {
                unset($this->objects['settings_order'][$key]);
                $this->objects['settings_order'] = array_values($this->objects['settings_order']);
            }
        }
    }

    /**
     * Add before hook for endpoint
     * @param string $endpointId Endpoint ID
     * @param callable $callback Callback function
     */
    public function before($endpointId, $callback) {
        if (!isset($this->reactive['endpoints'][$endpointId])) {
            return;
        }
        
        if (!isset($this->reactive['endpoints'][$endpointId]['hooks']['before'])) {
            $this->reactive['endpoints'][$endpointId]['hooks']['before'] = array();
        }
        
        if (!is_callable($callback)) {
            $callback = $this->dotapp->stringToCallable($callback);
        }
        
        if (is_callable($callback)) {
            $this->reactive['endpoints'][$endpointId]['hooks']['before'][] = $callback;
        }
    }

    /**
     * Add after hook for endpoint
     * @param string $endpointId Endpoint ID
     * @param callable $callback Callback function
     */
    public function after($endpointId, $callback) {
        if (!isset($this->reactive['endpoints'][$endpointId])) {
            return;
        }
        
        if (!isset($this->reactive['endpoints'][$endpointId]['hooks']['after'])) {
            $this->reactive['endpoints'][$endpointId]['hooks']['after'] = array();
        }
        
        if (!is_callable($callback)) {
            $callback = $this->dotapp->stringToCallable($callback);
        }
        
        if (is_callable($callback)) {
            $this->reactive['endpoints'][$endpointId]['hooks']['after'][] = $callback;
        }
    }

    /**
     * Add error handler for endpoint
     * @param string $endpointId Endpoint ID
     * @param callable $callback Callback function
     */
    public function onError($endpointId, $callback) {
        if (!isset($this->reactive['endpoints'][$endpointId])) {
            return;
        }
        
        if (!isset($this->reactive['endpoints'][$endpointId]['hooks']['onError'])) {
            $this->reactive['endpoints'][$endpointId]['hooks']['onError'] = array();
        }
        
        if (!is_callable($callback)) {
            $callback = $this->dotapp->stringToCallable($callback);
        }
        
        if (is_callable($callback)) {
            $this->reactive['endpoints'][$endpointId]['hooks']['onError'][] = $callback;
        }
    }

    /**
     * Add response code handler for endpoint
     * @param string $endpointId Endpoint ID
     * @param int $code HTTP status code
     * @param callable $callback Callback function
     */
    public function onResponseCode($endpointId, $code, $callback) {
        if (!isset($this->reactive['endpoints'][$endpointId])) {
            return;
        }
        
        if (!isset($this->reactive['endpoints'][$endpointId]['hooks']['onResponseCode'])) {
            $this->reactive['endpoints'][$endpointId]['hooks']['onResponseCode'] = array();
        }
        
        if (!isset($this->reactive['endpoints'][$endpointId]['hooks']['onResponseCode'][$code])) {
            $this->reactive['endpoints'][$endpointId]['hooks']['onResponseCode'][$code] = array();
        }
        
        if (!is_callable($callback)) {
            $callback = $this->dotapp->stringToCallable($callback);
        }
        
        if (is_callable($callback)) {
            $this->reactive['endpoints'][$endpointId]['hooks']['onResponseCode'][$code][] = $callback;
        }
    }

    /**
     * Resolve reactive request
     * @param string $url URL to check
     * @return bool
     */
    private function reactive_resolve($url) {
        if (!isset($this->dotapp->router) || !isset($this->dotapp->router->request)) {
            return false;
        }
        
        $request_path = $this->dotapp->router->request->getPath();
        if ($request_path == $url) {
            // Check if it's a reactive request
            if (isset($_SERVER['HTTP_DOTREACTIVE']) && $_SERVER['HTTP_DOTREACTIVE'] === 'true') {
                return true;
            }
            // Also check for reactive header in request
            if (isset($_POST['data']['reactive-id'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Execute reactive endpoint
     * @private
     */
    private function executeReactive() {
        $return_data = array();
        header('X-Answered-By: reactive');
        
        if (!isset($_POST['data'])) {
            $data['status'] = 0;
            $data['error_code'] = 1;
            $data['status_txt'] = "Invalid request!";
            return $this->dotapp->ajax_reply($data, 400);
        }
        
        $postdata = $_POST['data'];
        $reactive_key = $this->dotapp->dsm->get('_reactive_key');
        
        // Verify key
        if (!isset($postdata['reactive-key']) || $reactive_key != $postdata['reactive-key']) {
            $data['status'] = 0;
            $data['error_code'] = 2;
            $data['status_txt'] = "Reactive key does not match!";
            return $this->dotapp->ajax_reply($data, 403);
        }
        
        // Decrypt endpoint ID
        if (!isset($postdata['reactive-id'])) {
            $data['status'] = 0;
            $data['error_code'] = 1;
            $data['status_txt'] = "Missing reactive ID!";
            return $this->dotapp->ajax_reply($data, 400);
        }
        
        $key = $postdata['reactive-id'];
        $encryptedEndpointId = isset($postdata['reactive-data-id']) ? $postdata['reactive-data-id'] : '';
        
        if (empty($encryptedEndpointId)) {
            $data['status'] = 0;
            $data['error_code'] = 1;
            $data['status_txt'] = "Missing endpoint ID!";
            return $this->dotapp->ajax_reply($data, 400);
        }
        
        $endpointId = $this->dotapp->decrypt($encryptedEndpointId, $key);
        $internalID = hash('sha256', md5($endpointId));
        
        // Check if endpoint exists and key is valid
        if (!isset($this->objects['settings'][$internalID]) || 
            !isset($this->objects['settings'][$internalID]['valid'][$key])) {
            $data['status'] = 0;
            $data['error_code'] = 3;
            $data['status_txt'] = "Invalid endpoint or key!";
            return $this->dotapp->ajax_reply($data, 404);
        }
        
        // Check rate limits
        if (!$this->check_rate($internalID)) {
            $data['status'] = 0;
            $data['error_code'] = 4;
            $data['status_txt'] = "Rate limit exceeded!";
            return $this->dotapp->ajax_reply($data, 429);
        }
        
        // Get endpoint configuration
        $endpointConfig = $this->objects['settings'][$internalID];
        $apiUrl = $endpointConfig['api'];
        $method = $endpointConfig['method'];
        
        // Execute before hooks if endpoint is registered
        if (isset($this->reactive['endpoints'][$endpointId])) {
            $endpoint = $this->reactive['endpoints'][$endpointId];
            if (isset($endpoint['hooks']['before'])) {
                foreach ($endpoint['hooks']['before'] as $hook) {
                    try {
                        call_user_func($hook, null, null);
                    } catch (\Exception $e) {
                        // Log error but continue
                    }
                }
            }
        }
        
        // Note: Actual API call would be handled by JavaScript
        // This is just the endpoint registration and validation
        
        $default_data['status'] = 1;
        $return_data['body'] = array('message' => 'Reactive endpoint validated');
        $return_data = array_merge($default_data, $return_data);
        
        // Execute after hooks
        if (isset($this->reactive['endpoints'][$endpointId])) {
            $endpoint = $this->reactive['endpoints'][$endpointId];
            if (isset($endpoint['hooks']['after'])) {
                foreach ($endpoint['hooks']['after'] as $hook) {
                    try {
                        call_user_func($hook, $return_data, null);
                    } catch (\Exception $e) {
                        // Log error but continue
                    }
                }
            }
        }
        
        return $this->dotapp->ajax_reply($return_data, 200);
    }

    /**
     * Check rate limit for endpoint
     * @private
     */
    private function check_rate($internalID) {
        $limits = isset($this->objects['limitersLimits'][$internalID]) ? $this->objects['limitersLimits'][$internalID] : array();
        if (empty($limits)) {
            if (isset($this->dotapp->router->request->response)) {
                $this->dotapp->router->request->response->limiter = false;
            }
            return true;
        }
        
        if (!isset($this->newlimiters[$internalID])) {
            $this->newlimiters[$internalID] = new Limiter(
                $limits,
                $internalID,
                $this->dotapp->limiter['getter'],
                $this->dotapp->limiter['setter']
            );
        }
        
        if (isset($this->dotapp->router->request->response)) {
            $this->dotapp->router->request->response->limiter = $this->newlimiters[$internalID];
        }
        
        if ($this->newlimiters[$internalID]->isAllowed("/dotapp/reactive")) {
            return true;
        }
        return false;
    }

    /**
     * Set global limit per minute
     * @param int $limit
     * @return $this
     */
    public function limitPerMinute($limit) {
        if ($limit < 0) $limit = 0;
        $this->objects['limits']['global']['minute'] = $limit;
        return $this;
    }

    /**
     * Set global limit per hour
     * @param int $limit
     * @return $this
     */
    public function limitPerHour($limit) {
        if ($limit < 0) $limit = 0;
        $this->objects['limits']['global']['hour'] = $limit;
        return $this;
    }

    /**
     * Register function (Bridge-style compatibility)
     * @param string $function_name
     * @param callable $callback
     * @return object
     */
    public function fn($function_name, $callback) {
        $this->chain = $function_name;
        
        if ($this->register_functions === false) {
            return $this->chainMe(null);
        }
        
        if ($this->register_function !== $function_name && $this->register_function !== true) {
            return $this->chainMe(null);
        }
        
        if (!is_callable($callback)) {
            $callback = $this->dotapp->stringToCallable($callback);
        }
        
        if (is_callable($callback)) {
            $this->reactive['fn'][$function_name] = $callback;
        } else {
            throw new \Exception("Callback is not a function!");
        }
        
        return $this->chainMe($function_name);
    }

    /**
     * Chain method for method chaining
     */
    public function chainMe($function_name, $empty = false) {
        $obj = $this;
        return new class($obj, $function_name, $empty) {
            private $parentObj;
            private $fnName;
            private $empty;
            
            public function __construct($parent, $function_name, $empty) {
                $this->parentObj = $parent;
                $this->fnName = $function_name;
                $this->empty = $empty;
            }
            
            public function before($callback) {
                if ($this->empty === true) return $this;
                if (!is_callable($callback)) {
                    $callback = DotApp::DotApp()->stringToCallable($callback);
                }
                if (isset($this->fnName)) {
                    $this->parentObj->before($this->fnName, $callback);
                }
                return $this;
            }
            
            public function after($callback) {
                if ($this->empty === true) return $this;
                if (!is_callable($callback)) {
                    $callback = DotApp::DotApp()->stringToCallable($callback);
                }
                if (isset($this->fnName)) {
                    $this->parentObj->after($this->fnName, $callback);
                }
                return $this;
            }
        };
    }

    /**
     * Before hook (Bridge-style)
     */
    public function before(...$args) {
        if (count($args) == 1) {
            $function_name = $this->chain;
            $callback = $args[0];
        } else {
            $function_name = $args[0];
            $callback = $args[1];
        }
        $this->chain = $function_name;
        
        if ($this->register_functions == false) {
            return $this;
        }
        
        if (!isset($this->reactive['hooks']['before'])) {
            $this->reactive['hooks']['before'] = array();
        }
        if (!isset($this->reactive['hooks']['before'][$function_name])) {
            $this->reactive['hooks']['before'][$function_name] = array();
        }
        
        if (is_callable($callback)) {
            $this->reactive['hooks']['before'][$function_name][] = $callback;
        } else {
            throw new \Exception("Callback is not a function!");
        }
        
        return $this;
    }

    /**
     * After hook (Bridge-style)
     */
    public function after(...$args) {
        if (count($args) == 1) {
            $function_name = $this->chain;
            $callback = $args[0];
        } else {
            $function_name = $args[0];
            $callback = $args[1];
        }
        $this->chain = $function_name;
        
        if ($this->register_functions == false) {
            return $this;
        }
        
        if (!isset($this->reactive['hooks']['after'])) {
            $this->reactive['hooks']['after'] = array();
        }
        if (!isset($this->reactive['hooks']['after'][$function_name])) {
            $this->reactive['hooks']['after'][$function_name] = array();
        }
        
        if (is_callable($callback)) {
            $this->reactive['hooks']['after'][$function_name][] = $callback;
        } else {
            throw new \Exception("Callback is not a function!");
        }
        
        return $this;
    }

    /**
     * Listen for reactive endpoint (Bridge-style)
     * @param string|array $url
     * @param string $function_name
     * @param callable $callback
     * @param bool $static
     * @return object
     */
    public static function listen($url, $function_name, $callback, $static = false) {
        if (Router::matched()) {
            return self::$ReactiveOBJ->chainMe($function_name, true);
        }
        
        if (is_array($url)) {
            foreach ($url as $testUrl) {
                $rozpoznanyReactive = self::$ReactiveOBJ->reactive_resolve($testUrl);
                if ($rozpoznanyReactive === true) break;
            }
        } else {
            $rozpoznanyReactive = self::$ReactiveOBJ->reactive_resolve($url);
        }
        
        if ($rozpoznanyReactive === true) {
            if (strtolower(self::$ReactiveOBJ->register_function) === strtolower($function_name)) {
                Router::post($url, function() {
                    return self::$ReactiveOBJ->executeReactive();
                }, $static);
            } else {
                return self::$ReactiveOBJ->chainMe($function_name, true);
            }
        }
        
        return self::$ReactiveOBJ->fn($function_name, $callback);
    }

    /**
     * Initialize elements (for programmatic use)
     * Scans and processes reactive elements - mainly used by renderer
     */
    public function initializeElements() {
        // This is mainly handled by dotReactive() renderer method
        // Can be extended for programmatic initialization if needed
    }

    /**
     * Create from element attributes (used internally by renderer)
     * @param array $attrs Attributes array
     * @return object|null
     */
    public function createFromElement($attrs) {
        if (!isset($attrs['api'])) {
            return null;
        }
        
        return $this->create($attrs['api'], $attrs);
    }

    /**
     * Check if function exists
     * @param string $function_name
     * @return bool
     */
    public function isfn($function_name) {
        return isset($this->reactive['fn'][$function_name]);
    }

    /**
     * Check if is reactive request
     * @return bool
     */
    public function isReactive() {
        return isset($_SERVER['HTTP_DOTREACTIVE']) && $_SERVER['HTTP_DOTREACTIVE'] === 'true';
    }
}

?>

