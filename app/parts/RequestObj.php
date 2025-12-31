<?php
/**
 * Class RequestObj
 * 
 * This class is utilized within the router class to handle incoming HTTP requests. 
 * It processes request data, such as parameters, headers, and body content, enabling 
 * the router to effectively manage routing and execute the appropriate handlers based 
 * on the request information.
 * 
 * The request class provides an interface for accessing various aspects of the HTTP 
 * request, making it easier to work with data throughout the application.
 * 
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.7 FREE
 * @license   MIT License
 * @date      2014 - 2025
 * 
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the 
 * following condition: You **must** retain this header in all copies or 
 * substantial portions of the code, including the author and company information.
 */

/*
    RequestObj Class Usage:

    The request class is essential for managing HTTP requests within the DotApp framework.
    It serves as a bridge between incoming requests and the routing system, allowing for 
    seamless data handling.

    Example:
    - Get current path:
      `$dotapp->router->request->getPath();`
    
    The request class ensures that the router has the necessary information to route 
    requests correctly and execute the designated handlers.
*/

namespace Dotsystems\App\Parts;
use \Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Config;
use Dotsystems\App\Parts\Input;

class RequestObj {

    public $dotapp;
    public $dotApp;
    public $DotApp;

    private $reqVars;
    private $route;
    private $path;
    private $removeScriptPath;
    private $matchdata; // Stores variables for URLs with dynamic parameters
    private $hookdata; // Stores variables for hooks (before/after) if called separately; same as matchdata when chained
    private $dsm; // Session manager for requests
    public $response;
    private $gsLocked;
    public $auth;
    private $CSRF;
    public $data = null;
    private $origData = null;
    private $formValid = null;
    private $formData = array();
    
    // Properties for load balancing
    private $trustedProxies;
    private $forwardedHeaders = [
        'for' => 'HTTP_X_FORWARDED_FOR',
        'proto' => 'HTTP_X_FORWARDED_PROTO',
        'host' => 'HTTP_X_FORWARDED_HOST',
        'port' => 'HTTP_X_FORWARDED_PORT',
    ];
    private $isSecure = null;
    private $host = null;
    private $port = null;
    private $isLoadBalanced = false;
    private $originalServerData = []; // Preserves original server values

    private static $firewallFn;

    public function __set($name, $value) {
        if ($name == "response") throw new \InvalidArgumentException("request->response locked for edit !");
    }
	
    function __construct($dotapp, $rpath = 1) {
        $this->dotapp = DotApp::dotApp();
        $this->dotApp = $this->dotapp;
        $this->DotApp = $this->dotapp;
        $this->trustedProxies = &$this->dotApp->proxyServery;
        $this->removeScriptPath = $rpath;
        $this->path = false;
        $this->route = false;
        $this->matchdata = array();
        $this->hookdata = array();
        $this->response = new \stdClass();
        $this->response->limiter = false;
        $this->response->body = "";
        $this->response->status = 200;
        $this->response->headers = ["Content-Type" => "text/html"];
        $this->response->contentType = "text/html";
        $this->response->redirect = null;
        $this->response->cookies = [];
        $this->response->isSent = false;
        $this->response->data = [];
        $this->dsm = new DSM("dotapp.request");
        $this->dsm->load();
        $this->gsLocked = false; // Locks getters and setters
        $this->auth = new AuthObj($this->dotapp, $this->dsm);
        $this->CSRF = $this->dsm->get('_CSRF') ?? array();
        $this->initializeProxyHeaders();
        $this->checkAppFirewall();
        $this->data();
    }

    private function checkAppFirewall() {
        $rules = Config::app("firewall");
        if (is_array($rules)) {
            $firewallOK = self::firewall($rules,$_SERVER['REMOTE_ADDR']);
        }
        if ($firewallOK === false) {
            http_response_code(403);
            exit();
            die();
        }
    }

    /**
     * Initializes proxy headers for load-balanced environments.
     */
    protected function initializeProxyHeaders() {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Preserve original server values
        $this->originalServerData = [
            'REMOTE_ADDR' => $remoteAddr,
            'HTTPS' => $_SERVER['HTTPS'] ?? null,
            'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? null,
            'SERVER_PORT' => $_SERVER['SERVER_PORT'] ?? null,
        ];

        if ($this->isTrustedProxy($remoteAddr)) {
            // Detect load balancer
            $this->isLoadBalanced = !empty($_SERVER[$this->forwardedHeaders['for']]) ||
                                    !empty($_SERVER[$this->forwardedHeaders['proto']]) ||
                                    !empty($_SERVER[$this->forwardedHeaders['host']]) ||
                                    !empty($_SERVER[$this->forwardedHeaders['port']]);

            // Update REMOTE_ADDR
            if ($forwardedFor = $_SERVER[$this->forwardedHeaders['for']] ?? '') {
                $_SERVER['REMOTE_ADDR'] = trim(explode(',', $forwardedFor)[0]);
            }

            // Update HTTPS
            $proto = $_SERVER[$this->forwardedHeaders['proto']] ?? '';
            if ($proto === 'https') {
                $_SERVER['HTTPS'] = 'on';
                $_SERVER['SERVER_PROTOCOL'] = 'HTTPS/1.1';
            } else {
                $_SERVER['HTTPS'] = 'off';
                $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
            }
            $this->isSecure = ($proto === 'https');

            // Update HTTP_HOST
            if ($host = $_SERVER[$this->forwardedHeaders['host']] ?? '') {
                $_SERVER['HTTP_HOST'] = $host;
            }
            $this->host = $_SERVER['HTTP_HOST'] ?? '';

            // Update SERVER_PORT
            if ($port = $_SERVER[$this->forwardedHeaders['port']] ?? '') {
                $_SERVER['SERVER_PORT'] = $port;
            }
            $this->port = $_SERVER['SERVER_PORT'] ?? '';
        } else {
            $this->isLoadBalanced = false;
            $this->isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
            $this->host = $_SERVER['HTTP_HOST'] ?? '';
            $this->port = $_SERVER['SERVER_PORT'] ?? '';

            if (!empty($_SERVER[$this->forwardedHeaders['for']]) && $this->dotApp->isDebugMode()) {
                DotApp::DotApp()->Logger->warning("X-Forwarded-For detected from untrusted proxy", ['remote_addr' => $remoteAddr]);
            }
        }
    }

    /**
     * Checks if the given IP is a trusted proxy.
     *
     * @param string $ip The IP address to check.
     * @return bool True if the IP is trusted, false otherwise.
     */
    protected function isTrustedProxy($ip) {
        if ($this->trustedProxies === ['*']) {
            return true; // Warning: Avoid using '*' in production for load balancers
        }
        return in_array($ip, $this->trustedProxies, true);
    }

    public function loadBalancing() {
        return $this->isLoadBalanced;
    }

    public function getOriginalRemoteAddr() {
        return $this->originalServerData['REMOTE_ADDR'] ?? null;
    }

    public function getOriginalHttps() {
        return $this->originalServerData['HTTPS'] ?? null;
    }

    public function getOriginalHost() {
        return $this->originalServerData['HTTP_HOST'] ?? null;
    }

    public function getOriginalPort() {
        return $this->originalServerData['SERVER_PORT'] ?? null;
    }

    public function isSecure() {
        return $this->isSecure;
    }

    public function getHost() {
        return $this->host;
    }

    public function getPort() {
        return $this->port;
    }

    public function getFullUrl() {
        $protocol = $this->isSecure() ? 'https' : 'http';
        $host = $this->getHost();
        $path = $this->getPath();
        $query = $this->getVars() ? '?' . $this->getVars() : '';
        return "$protocol://$host$path$query";
    }

    public function &getDsm() {
        return $this->dsm;
    }

    public function unprotect($vstup) {
        $this->dotapp->unprotect($vstup);
    }
	
    public function __debugInfo() {
        return [
            'publicData' => 'This is just part of dotapp. Nothing to see !'
        ];
    }

    /**
     * Gets or sets match data for dynamic URL parameters.
     * Locked for editing if gsLocked is true.
     *
     * @param mixed $data Data to set, or false to get current data.
     * @return mixed Current match data if getting, void if setting.
     */
    public function matchData($data = false) {
        if ($data !== false) {
            if ($this->gsLocked) {
                throw new \InvalidArgumentException("request->matchData locked for edit!");
            }
            $this->matchdata = (array) $data;
        } else {
            return $this->matchdata;            
        }  
    }

    public function upload($callback) {
        // Verify that callback is callable
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException("Callback must be callable!");
        }

        // Array to store file information
        $files = array();

        // Process files from $_FILES
        if (!empty($_FILES)) {
            foreach ($_FILES as $fieldName => $fileInfo) {
                // Handle multiple file uploads
                if (is_array($fileInfo['name'])) {
                    $fileCount = count($fileInfo['name']);
                    for ($i = 0; $i < $fileCount; $i++) {
                        if ($fileInfo['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                            $files[] = array(
                                'field' => $fieldName,
                                'name' => $fileInfo['name'][$i],
                                'type' => $fileInfo['type'][$i],
                                'size' => $fileInfo['size'][$i],
                                'tmp_name' => $fileInfo['tmp_name'][$i],
                                'error' => $fileInfo['error'][$i],
                                'extension' => strtolower(pathinfo($fileInfo['name'][$i], PATHINFO_EXTENSION))
                            );
                        }
                    }
                } else {
                    // Handle single file upload
                    if ($fileInfo['error'] !== UPLOAD_ERR_NO_FILE) {
                        $files[] = array(
                            'field' => $fieldName,
                            'name' => $fileInfo['name'],
                            'type' => $fileInfo['type'],
                            'size' => $fileInfo['size'],
                            'tmp_name' => $fileInfo['tmp_name'],
                            'error' => $fileInfo['error'],
                            'extension' => strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION))
                        );
                    }
                }
            }
        }

        // Call the callback with the array of files
        try {
            call_user_func($callback, $files);
        } catch (\Exception $e) {
            throw new \RuntimeException("Error processing callback: " . $e->getMessage());
        }

        return $this;
    }

    public function &data($orig = false) {
        if ($this->data !== null && $orig === false) return $this->data;
        if ($this->data !== null && $orig === true) return $this->origData;
        
        $method = $this->getMethod(); // Use existing getMethod() to get the HTTP method
        $data = [];
    
        switch ($method) {
            case 'get':
                $data = $_GET ?? [];
                break;
    
            case 'post':
                $data = $_POST ?? [];
                break;
    
            case 'put':
            case 'patch':
                // Parse raw input (e.g., JSON or form data)
                $input = file_get_contents('php://input');
                if (!empty($input)) {
                    // Try parsing as JSON first
                    $jsonData = json_decode($input, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $data = $jsonData;
                        DotApp::DotApp()->protect($data);
                    } else {
                        // If not JSON, parse as form data
                        parse_str($input, $data);
                        DotApp::DotApp()->protect($data);
                    }
                }
                break;
    
            case 'delete':
                // DELETE requests may include data in the body
                $input = file_get_contents('php://input');
                if (!empty($input)) {
                    // Try parsing as JSON
                    $jsonData = json_decode($input, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $data = $jsonData;
                        DotApp::DotApp()->protect($data);
                    } else {
                        // Parse as form data
                        parse_str($input, $data);
                        DotApp::DotApp()->protect($data);
                    }
                }
                // Note: $_DELETE is not natively supported in PHP, but check if it's manually populated
                if (!empty($_DELETE)) {
                    $data = array_merge($data, $_DELETE);
                }
                break;
    
            case 'head':
            case 'options':
            default:
                // These methods typically don't carry data
                $data = [];
                break;
        }

        if (is_array($data)) {
            $this->origData = $data;
            $this->dotApp->protect($data);
            $this->data = $data;
            return $this->data;
        }

        return [];
    }

    public function query($orig = false) {
        $data = $_GET ?? [];
        if ($orig === true) {
            return $data;
        }
        $this->dotApp->protect($data);
        return $data;
    }

    public function lock() {
        $this->gsLocked = true;
        return $this;
    }

    public function route($route = false) {
        if ($route !== false) {
            $this->route = (string) $route;
        } else {
            return $this->route;
        }        
    }

    public function hookData($data = false) {
        if ($data !== false) {
            $this->hookdata = (array) $data;
        } else {
            return $this->hookdata;
        }  
    }

    public function body($data = false) {
        if ($data !== false) {
            $this->response->body = (string) $data;
        } else {
            return $this->response->body;
        }  
    }

    /**
     * Checks if the user is authenticated and optionally returns auth data.
     *
     * @param bool $returnData If true, returns auth data if authenticated; otherwise, returns null.
     * @return mixed True if authenticated (when $returnData is false), auth data if $returnData is true, or null if not authenticated.
     */
    public function requireAuth($returnData = false) {
        if ($returnData && $this->auth->isLogged()) {
            return $this->auth->getAuthData();
        } 
        if (!$returnData) return $this->auth->isLogged();
        // Returns null for easy controller logic: if ($data = $request->requireAuth(true)) { /* authenticated user logic */ }
        return null;        
    }
	
    public function getPath() {
        if (is_string($this->path)) return $this->path;
        $path = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $path = str_replace("\\", '/', $path);
        if ($this->removeScriptPath) {
            $scriptName = dirname($_SERVER['SCRIPT_NAME']);
            $scriptName = str_replace("\\", '/', $scriptName);
            $position = strpos($path, $scriptName);
            $escapedScriptName = preg_quote($scriptName, '/');
            $path = "/" . preg_replace('/^' . $escapedScriptName . '/', '', $path, 1);
        }
        $sugetpremenne = strpos($path, '?' );		
        if ($sugetpremenne === false) {
            $this->path = $path;
            return $path;
        } else {
            $patha = explode("?", $path);
            $this->path = $patha[0];
            $this->reqVars = $patha[1];            
            return $patha[0];
        }
    }
	
    public function getMethod() {
        if (defined('__DOTAPPER_RUN__')) {
            $method = "get";
        } else {
            $method = strtolower($_SERVER['REQUEST_METHOD']);
        }
        
        // Allowed HTTP methods to prevent unauthorized method calls
        $allowedMethods = ['get', 'post', 'put', 'delete', 'patch', 'head', 'options'];
        
        // Check if the method is allowed
        if (!in_array($method, $allowedMethods) && !defined('__DOTAPPER_RUN__')) {
            http_response_code(405); // Method Not Allowed
            echo "Method '$method' is not allowed. Use only standard HTTP methods: " . implode(', ', $allowedMethods);
            exit;
        }
        
        return $method;
    }
	
    public function getVars() {
        return $this->reqVars;
    }

    public function isValidCSRF($token) {
        if (in_array(md5($token), $this->CSRF)) return false;
        return true;
    }

    public function invalidateCSRF($token) {
        $this->CSRF[] = md5($token);
    
        if (count($this->CSRF) > 500) {
            $this->CSRF = array_slice($this->CSRF, -500);
        }
    
        $this->dsm->set('_CSRF', $this->CSRF);
    }

    public function crcCheck() {
        // Get data from data() method
        $dataArray = $this->data(true);
        
        // Extract 'data' and 'crc' from the data
        $data = $dataArray['data'] ?? null;
        $crc = $dataArray['crc'] ?? null;

        // Verify CRC using crc_check from DotApp
        $privateKey = $_SESSION['module_users_ckey'] ?? $this->dotApp->encKey();
        $result = $this->dotapp->crc_check($privateKey, $crc, $data);
    
        // If data or crc is missing, log warning and return false
        if ($data === null || $crc === null) {
            if ($this->dotApp->isDebugMode()) {
                DotApp::DotApp()->Logger->warning("crcCheck failed: Missing data or crc", [
                    'data' => $data,
                    'crc' => $crc
                ]);
            }
            return false;
        }
        
        // Validate CSRF tokens
        if ($this->dotApp->hasListener("dotapp.invalidate.csrf.url.token")) {
            return $this->dotApp->trigger("dotapp.invalidate.csrf.url.token", $this);
        } else {
            if (is_string($data)) {
                $decoded = json_decode($data, true);
                $data = $decoded ?? $data;
            }
            if (!is_array($data)) {
                if ($this->dotApp->isDebugMode()) {
                    DotApp::DotApp()->Logger->warning("crcCheck failed: Data is not a valid array", [
                        'data' => $data
                    ]);
                }
                return false;
            }
    
            $data['dotapp-security-data-csrf-random-token'] = $this->dotapp->unprotect_data($data['dotapp-security-data-csrf-random-token'] ?? '');
            if ($this->isValidCSRF($data['dotapp-security-data-csrf-random-token'])) {
                // Validate CSRF
                $data['dotapp-security-data-csrf-random-token-key'] = $this->dotapp->unprotect_data($data['dotapp-security-data-csrf-random-token-key'] ?? '');
                $data['dotapp-security-data-csrf-token-tab'] = $this->dotapp->unprotect_data($data['dotapp-security-data-csrf-token-tab'] ?? '');
                $this->invalidateCSRF($data['dotapp-security-data-csrf-random-token']);
                $rb = $this->dotApp->subtractKey(
                    $data['dotapp-security-data-csrf-random-token'],
                    $data['dotapp-security-data-csrf-random-token-key']
                );
                $ref = $this->dotapp->decrypt(
                    $data['dotapp-security-data-csrf-token-tab'],
                    DSM::use()->session_id() . $rb
                );
                
                if (!($ref === ($_SERVER['HTTP_REFERER'] ?? ''))) {
                    if ($this->dotApp->isDebugMode()) {
                        DotApp::DotApp()->Logger->warning("crcCheck failed: Invalid referer", [
                            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
                            'decrypted_referer' => $ref
                        ]);
                    }
                    return false;
                }
            } else {
                if ($this->dotApp->isDebugMode()) {
                    DotApp::DotApp()->Logger->warning("crcCheck failed: Invalid CSRF token", [
                        'csrf_token' => $data['dotapp-security-data-csrf-random-token'] ?? ''
                    ]);
                }
                return false;
            }
        }
        
        if (!$result && $this->dotApp->isDebugMode()) {
            DotApp::DotApp()->Logger->warning("crcCheck failed: CRC validation failed", [
                'crc' => $crc,
                'data' => $data
            ]);
        }
        
        return $result;
    }

    public function formSignatureCheck() {
        if ($this->formValid === true) return true;

        // Reference to data, working with real data, not a local scope
        $data = $this->data();
        if ( (!isset($data['dotapp-secure-auto-fnname-public']) || !isset($data['dotapp-secure-auto-fnname-action']) || !isset($data['dotapp-secure-auto-fnname-method']) || !isset($data['dotapp-secure-auto-fnname-public'])) && isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }

        $decryptKey = $this->dotApp->decrypt($data['dotapp-secure-auto-fnname-public']);
        if ($decryptKey === false) return false;
        $this->formValid = true;
        return true;
    }

    public function form($methodOrName, $nameOrSuccess, $success = null, $error = null, $rewriteAction = null) {
        if (func_num_args() === 2) {
            $name = $methodOrName;
            $success = $nameOrSuccess;
        } else {
            // Method is specified
            if (is_array($methodOrName)) {
                $method = $methodOrName;
                $name = $nameOrSuccess;

                if (is_array($method)) {
                    if (!in_array(strtolower($this->getMethod()), array_map('strtolower', $method))) {
                        return false;
                    }
                } else {
                    if (strtolower($method) !== strtolower($this->getMethod())) {
                        return false;
                    }
                }
            } else {
                if (func_num_args() === 3) {
                    $name = $methodOrName;
                    $error = $success;
                    $success = $nameOrSuccess;
                }
            }            
        }
    
        if ($this->formSignatureCheck()) {
            if (!empty($this->formData)) {
                $decryptKey = $this->formData['key'];
                $action = $this->formData['action'];
                $method = $this->formData['method'];
                $fnname = $this->formData['fnname'];
            } else {
                $data = $this->data();
                if ( (!isset($data['dotapp-secure-auto-fnname-public']) || !isset($data['dotapp-secure-auto-fnname-action']) || !isset($data['dotapp-secure-auto-fnname-method']) || !isset($data['dotapp-secure-auto-fnname-public'])) && isset($data['data']) && is_array($data['data'])) {
                    $data = $data['data'];
                }
                $decryptKey = $this->dotApp->decrypt($data['dotapp-secure-auto-fnname-public']);
                $fnname = $this->dotApp->decrypt($data['dotapp-secure-auto-fnname'], $decryptKey);
                $action = $this->dotApp->decrypt($data['dotapp-secure-auto-fnname-action'], $decryptKey);
                $method = $this->dotApp->decrypt($data['dotapp-secure-auto-fnname-method'], $decryptKey);
                $this->formData['key'] = $decryptKey;
                $this->formData['action'] = $action;
                $this->formData['method'] = $method;
                $this->formData['fnname'] = $fnname;
            }
    
            if ($name == $fnname) {
                if ($rewriteAction !== null) $action = (string)$rewriteAction;
                if (strtolower($method) === $this->getMethod() && ($action === $this->getPath() || $action === $this->getPath()."?".$this->reqVars)) {
                    if (!is_callable($success)) $success = $this->dotApp->stringToCallable($success);
                    if (is_callable($success)) {
                        return $success($this, $name);
                    } else {
                        throw new \Exception("Success is not callable !");
                    }                    
                }
            }
        } else {
            if ($error === null) throw new \Exception("Signature is invalid !");
            if (!is_callable($error)) $error = $this->dotApp->stringToCallable($error);
            if (is_callable($error)) {
                return $error($this, $name);
            } else {
                throw new \Exception("Error callback is not callable ! Signature is also invalid !");
            }
        }
    }

    /**
     * Evaluates IP address rules against a firewall function.
     *
     * This function checks if the given IP address matches any rule in the provided associative array.
     * The rules can be defined as IP addresses or ranges using CIDR notation (e.g., '192.168.1.0/24').
     * Each rule corresponds to an action ('allow' or 'drop') that determines whether the IP address is allowed or denied.
     *
     * @param array $array An associative array containing IP address rules and their corresponding actions.
     * @param string $ip The IP address to be evaluated against the rules.
     * @param bool $default The default behavior if no rule matches the given IP address.
     *                       By default, the default behavior is to allow the IP address (true).
     *
     * @return bool Returns true if the IP address is allowed (action is 'allow' or 1),
     *              or false if the IP address is denied (action is 'drop' or 0).
     *              If no rule matches the IP address, it returns the default behavior.
     *
     * @throws InvalidArgumentException If a provided firewall function is not callable.
     *
     * @example
     * // Set a custom firewall function
     * $firewallRules = [
     *     '192.168.1.0/24' => 'allow',
     *     '192.168.2.0/24' => 'drop',
     *     '0.0.0.0/0' => 'allow',
     * ];
     *
     * $customFirewallFn = function($array, $ip, $default = true) {
     *     // Implement your custom firewall logic here
     *     // ...
     *     return $default; // Return the default behavior if no rule matches
     * };
     *
     * Request::firewallFn($customFirewallFn);
     *
     * // Use the custom firewall function
     * $result = Request::firewall($firewallRules, '192.168.1.100'); // Returns true (allowed by first rule)
     * $result = Request::firewall($firewallRules, '192.168.2.100'); // Returns false (dropped by second rule)
     * $result = Request::firewall($firewallRules, '10.0.0.1'); // Returns true (allowed by third rule)
     * $result = Request::firewall([], '10.0.0.1', false); // Returns false (no rules, default deny)
     */
    public function firewall($array, $ip, $default = true) {
        if (!is_callable(static::$firewallFn)) {
            $this->setDefaultFirewallFunction();
        }
        return call_user_func(static::$firewallFn, $array, $ip, $default);
    }

    /**
     * Sets the default firewall function for the Request class.
     * This function is used to evaluate firewall rules and determine if an IP address is allowed or denied.
     *
     * @return void
     *
     * ### Usage:
     * This function is called internally when the Request class is instantiated.
     * It sets the default firewall function to be used for IP address evaluation.
     *
     * ### Firewall Rules:
     * The firewall rules are defined as an associative array, where each key represents an IP address or range,
     * and the corresponding value represents the action to take ('allow' or 'drop').
     *
     * ### IP Address Matching:
     * The function checks if the given IP address matches any rule by comparing it with the IP addresses or ranges in the array.
     * It supports both exact IP matches and IP range matches using CIDR notation (e.g., '192.168.1.0/24').
     *
     * ### Default Behavior:
     * If no rule matches the given IP address, the function returns the default behavior specified by the $default parameter.
     * By default, the default behavior is to allow the IP address (true).
     *
     * ### Example:
     * ```php
     * $firewallRules = [
     *     '192.168.1.0/24' => 'allow',
     *     '192.168.2.0/24' => 'drop',
     *     '0.0.0.0/0' => 'allow',
     * ];
     *
     * // Check IP address
     * $result = $request->firewall($firewallRules, '192.168.1.100'); // Returns true (allowed by first rule)
     * $result = $request->firewall($firewallRules, '192.168.2.100'); // Returns false (dropped by second rule)
     * $result = $request->firewall($firewallRules, '10.0.0.1'); // Returns true (allowed by third rule)
     * $result = $request->firewall([], '10.0.0.1', false); // Returns false (no rules, default deny)
     * ```
     */
    private function setDefaultFirewallFunction() {
        static::$firewallFn = function($array, $ip, $default = true) {
            foreach ($array as $rule => $action) {
                // Check if the IP matches the rule
                if (strpos($rule, '/') !== false) {
                    // Handle IP range (CIDR notation, e.g., '192.168.1.0/24')
                    list($subnet, $mask) = explode('/', $rule);
                    $subnet = ip2long($subnet);
                    $ipLong = ip2long($ip);
                    if ($subnet === false || $ipLong === false) {
                        continue; // Skip invalid IP or subnet
                    }
                    $mask = ~((1 << (32 - $mask)) - 1);
                    if (($ipLong & $mask) === ($subnet & $mask)) {
                        return $action === 'allow' || $action === 1;
                    }
                } else {
                    // Handle exact IP match
                    if ($ip === $rule) {
                        return $action === 'allow' || $action === 1;
                    }
                }
            }
            // Default: Return the default behavior if no rule matches
            return $default;
        };
    }

    /**
     * Sets or retrieves the custom firewall function for the Request class.
     *
     * This function allows you to define your own logic for evaluating IP address rules.
     * By default, the Request class uses its own internal firewall function to check IP addresses against rules.
     *
     * @param callable|null $firewallFunction The custom firewall function to be used.
     *                                       If null is provided, the function will return the currently set firewall function.
     *
     * @return callable|null The currently set firewall function, or null if no custom function is set.
     *
     * @throws InvalidArgumentException If a provided firewall function is not callable.
     *
     * @example
     * // Set a custom firewall function
     * $firewallRules = [
     *     '192.168.1.0/24' => 'allow',
     *     '192.168.2.0/24' => 'drop',
     *     '0.0.0.0/0' => 'allow',
     * ];
     *
     * $customFirewallFn = function($array, $ip, $default = true) {
     *     // Implement your custom firewall logic here
     *     // ...
     *     return $default; // Return the default behavior if no rule matches
     * };
     *
     * Request::firewallFn($customFirewallFn);
     *
     * // Use the custom firewall function
     * $result = Request::firewall($firewallRules, '192.168.1.100'); // Returns true (allowed by first rule)
     * $result = Request::firewall($firewallRules, '192.168.2.100'); // Returns false (dropped by second rule)
     * $result = Request::firewall($firewallRules, '10.0.0.1'); // Returns true (allowed by third rule)
     * $result = Request::firewall([], '10.0.0.1', false); // Returns false (no rules, default deny)
     */
    public static function firewallFn($firewallFunction = null) {
        if ($firewallFunction !== null) {
            if (!is_callable($firewallFunction)) {
                throw new \InvalidArgumentException("Provided firewall function must be callable.");
            }
            static::$firewallFn = $firewallFunction;
        }
        return static::$firewallFn;
    }

    /**
     * Robust validation of input group from request data.
     * Rehydrates the Input object from encrypted state in request.
     *
     * @param string $groupName Expected group name (e.g. 'login')
     * @return bool|array Returns TRUE if valid, or Error Array if invalid.
     */
    public function validateInput(string $groupName) {
        // Len alias...
        return self::validateInputs($groupName);
    }
    /**
     * Validuje vstupy zo šifrovanej skupiny.
     * Predpokladá, že bezpečnosť prenosu (CRC) si užívateľ vyriešil volaním $request->crcCheck()
     * ak to považoval za nutné.
     *
     * @param string $groupName Názov skupiny (napr. 'moj_formular')
     * @return bool|array TRUE pri úspechu, inak pole s chybami.
     */
    public function validateInputs(string $groupName) {
        $requestData = $this->data(true);
        $payload = [];

        // Detekcia zabalených dát (DotApp JS load() wrapper)
        // Ak sú dáta v kľúči 'data', rozbalíme ich, aby Input videl kľúče priamo.
        if (isset($requestData['data'])) {
            if (is_array($requestData['data'])) {
                $payload = $requestData['data'];
            } elseif (is_string($requestData['data'])) {
                $decoded = json_decode($requestData['data'], true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }
        } 
        
        // Ak dáta nie sú v 'data' (klasický HTML POST), berieme root requestData
        if (empty($payload)) {
             $payload = $requestData;
        }

        // Načítanie objektu Input (overuje podpis pravidiel DotAppInputGroupKey)
        $form = Input::loadFromRequest($payload);

        if (!$form) {
            return [
                'status' => 0,
                'error_code' => 403,
                'status_txt' => 'Security Error: Invalid form state.',
                'errors' => ['_security' => 'Invalid form signature.']
            ];
        }

        if ($form->getGroupName() !== $groupName) {
             return [
                'status' => 0,
                'error_code' => 403,
                'status_txt' => 'Security Error: Group mismatch.',
                'errors' => ['_security' => 'Group mismatch.']
            ];
        }

        // Samotná validácia hodnôt podľa pravidiel (min, max, email atď.)
        if ($form->validate()) {
            return true;
        } else {
            return [
                'status' => 0,
                'error_code' => 422,
                'status_txt' => 'Validation Failed',
                'errors' => $form->getErrors()
            ];
        }
    }
}
?>