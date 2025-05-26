<?php
/**
 * Class REQUEST
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
    Request Class Usage:

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

class RequestObj {

    public $dotapp;
    public $dotApp;
    public $DotApp;

	private $reqVars;
    private $route;
    private $path;
	private $removeScriptPath;
    private $matchdata; // Ak volame URL s dynamickymi parametrami, tu su premenne
    private $hookdata; // Ak neretazime metodu ale volame after alebo before samostatne, tak tu su premenne pre hook. Ak retazime matchdata a hookdata budu rovnake
    private $dsm; // Session manager pre REQUESTY
    public $response;
    private $gsLocked;
    public $auth;
    private $CSRF;
    public $data = null;
    private $origData = null;
    private $formValid = null;
    private $formData = array();
    
    // Vlastnosti pre load balancing
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
    private $originalServerData = []; // Zachová pôvodné hodnoty

    public function __set($name, $value) {
        if ($name == "response") throw new \InvalidArgumentException("request->response locked for edit !");
    }
	
	function __construct($dotapp,$rpath=1) {
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
        $this->gsLocked = false; // Zamkneme hlavne getters a setters
        $this->auth = new AuthObj($this->dotapp,$this->dsm);
        $this->CSRF = $this->dsm->get('_CSRF') ?? array();
        $this->initializeProxyHeaders();
        $this->data();
    }

    /*
        Ak slapeme po dialnici load ballancera
    */
    protected function initializeProxyHeaders() {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Zachovaj pôvodné hodnoty
        $this->originalServerData = [
            'REMOTE_ADDR' => $remoteAddr,
            'HTTPS' => $_SERVER['HTTPS'] ?? null,
            'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? null,
            'SERVER_PORT' => $_SERVER['SERVER_PORT'] ?? null,
        ];

        if ($this->isTrustedProxy($remoteAddr)) {
            // Detekcia load balanceru
            $this->isLoadBalanced = !empty($_SERVER[$this->forwardedHeaders['for']]) ||
                                    !empty($_SERVER[$this->forwardedHeaders['proto']]) ||
                                    !empty($_SERVER[$this->forwardedHeaders['host']]) ||
                                    !empty($_SERVER[$this->forwardedHeaders['port']]);

            // Aktualizuj REMOTE_ADDR
            if ($forwardedFor = $_SERVER[$this->forwardedHeaders['for']] ?? '') {
                $_SERVER['REMOTE_ADDR'] = trim(explode(',', $forwardedFor)[0]);
            }

            // Aktualizuj HTTPS
            $proto = $_SERVER[$this->forwardedHeaders['proto']] ?? '';
            if ($proto === 'https') {
                $_SERVER['HTTPS'] = 'on';
                $_SERVER['SERVER_PROTOCOL'] = 'HTTPS/1.1';
            } else {
                $_SERVER['HTTPS'] = 'off';
                $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
            }
            $this->isSecure = ($proto === 'https');

            // Aktualizuj HTTP_HOST
            if ($host = $_SERVER[$this->forwardedHeaders['host']] ?? '') {
                $_SERVER['HTTP_HOST'] = $host;
            }
            $this->host = $_SERVER['HTTP_HOST'] ?? '';

            // Aktualizuj SERVER_PORT
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
                error_log("Warning: X-Forwarded-For detected from untrusted proxy $remoteAddr");
            }
        }
    }

    protected function isTrustedProxy($ip) {
        if ($this->trustedProxies === ['*']) {
            return true; // Ak pouzivame load ballancer tak sa snazme vyhnut *
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

    /*
        Zakladne GETTER A SETTER sa zamknu na zapis
    */
    public function matchData($data=false) {
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
        // Overíme, či je callback callable
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException("Callback musí byť callable!");
        }

        // Pole na uloženie informácií o súboroch
        $files = array();

        // Spracujeme súbory z $_FILES
        if (!empty($_FILES)) {
            foreach ($_FILES as $fieldName => $fileInfo) {
                // Ak je súbor pole (multiple upload), spracujeme každý súbor samostatne
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
                    // Jednotlivý súbor
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

        // Zavoláme callback s poľom súborov
        try {
            call_user_func($callback, $files);
        } catch (\Exception $e) {
            throw new \RuntimeException("Chyba pri spracovaní callbacku: " . $e->getMessage());
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

    public function lock() {
        $this->gsLocked = true;
        return $this;
    }
    // ----> Potialto su potom zamknute

    public function route($route=false) {
        if ($route !== false) {
            $this->route = (string) $route;
        } else {
            return $this->route;
        }        
    }

    public function hookData($data=false) {
        if ($data !== false) {
            $this->hookdata = (array) $data;
        } else {
            return $this->hookdata;
        }  
    }

    public function body($data=false) {
        if ($data !== false) {
            $this->response->body = (string) $data;
        } else {
            return $this->response->body;
        }  
    }

    // Potrbeuje byt autantifikovany
    public function requireAuth($returnData=false) {
        if ($returnData && $this->auth->isLogged()) {
            return $this->auth->getAuthData();
        } 
        if (!$returnData) return $this->auth->isLogged();
        /*
            Inak NULL, aby sa dalo robit v controlleroch easy ze if ($premenna = $request->requireAuth(true)) {//logika s prihlasenym uzivatelom};
        */
        return null;        
    }
	
	public function getPath() {
        if (is_string($this->path)) return($this->path);
		$path = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
		$path = str_replace("\\",'/',$path);
		if ($this->removeScriptPath) {
			$scriptName = dirname($_SERVER['SCRIPT_NAME']);
			$scriptName = str_replace("\\",'/',$scriptName);
			$position = strpos($path, $scriptName);
			$escapedScriptName = preg_quote($scriptName, '/');
			$path = "/".preg_replace('/^' . $escapedScriptName . '/', '', $path, 1);
		}
		$sugetpremenne = strpos($path, '?' );		
		if ($sugetpremenne === false) {
            $this->path = $path;
			return $path;
		} else {
			$patha = explode("?",$path);
			$this->path = $patha[0];
            $this->reqVars = $patha[1];            
			return $patha[0];
		}
	}
	
	public function getMethod() {
        if (defined('__DOTAPPER_RUN__')) {
            $method = "get";
        } else $method = strtolower($_SERVER['REQUEST_METHOD']);
        
        /* 
            Povolene HTTP metody - kedze mame funkcie ako apiDispatch a podobne ktore automatizuju veci na zaklade method a path,
            tak aby uzivatel nemohol zavolat podvrhnutim metody funkciu ktoru nechceme tu mu to zakazeme.
            napriklad ak by pouzil metodu stiahni a resource okno, mohol by zavolat metodu stiahniOkno ktoru inak uzivatel nechce aby bola pristupna.
        */

        $allowedMethods = ['get', 'post', 'put', 'delete', 'patch', 'head', 'options'];
        
        // Kontrola, či je metóda povolená
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
        if (in_array(md5($token),$this->CSRF)) return false;
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
        // Získame dáta z metódy data()
        $dataArray = $this->data(true);
        
        // Extrahujeme 'data' a 'crc' z dát
        $data = $dataArray['data'] ?? null;
        $crc = $dataArray['crc'] ?? null;

        // Overíme CRC pomocou crc_check z DotApp
        $privateKey = $_SESSION['module_users_ckey'] ?? $this->dotApp->encKey();
        $result = $this->dotapp->crc_check($privateKey, $crc, $data);
    
        // Ak chýbajú dáta alebo CRC, vrátime false
        if ($data === null || $crc === null) {
            if ($this->dotApp->isDebugMode()) {
                error_log("crcCheck failed: Missing data or crc");
            }
            return false;
        }
        
        // Validácia CSRF tokenov
        if ($this->dotApp->hasListener("dotapp.invalidate.csrf.url.token")) {
            return $this->dotApp->trigger("dotapp.invalidate.csrf.url.token", $this);
        } else {
            if (is_string($data)) {
                $decoded = json_decode($data, true);
                $data = $decoded ?? $data;
            }
            if (!is_array($data)) {
                if ($this->dotApp->isDebugMode()) {
                    error_log("crcCheck failed: Data is not a valid array");
                }
                return false;
            }
    
            $data['dotapp-security-data-csrf-random-token'] = $this->dotapp->unprotect_data($data['dotapp-security-data-csrf-random-token'] ?? '');
            if ($this->isValidCSRF($data['dotapp-security-data-csrf-random-token'])) {
                // Validácia CSRF
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
                        error_log("crcCheck failed: Invalid referer");
                    }
                    return false;
                }
            } else {
                if ($this->dotApp->isDebugMode()) {
                    error_log("crcCheck failed: Invalid CSRF token");
                }
                return false;
            }
        }
        
        if (!$result && $this->dotApp->isDebugMode()) {
            error_log("crcCheck failed: CRC validation failed");
        }
        
        return $result;
    }

    public function formSignatureCheck() {
        if ($this->formValid === true) return(true);

        // Smernik na data, takze pracujeme s realnymi datami nie vo vlastnom scoope
        $data = $this->data();
        if ( (!isset($data['dotapp-secure-auto-fnname-public']) || !isset($data['dotapp-secure-auto-fnname-action']) || !isset($data['dotapp-secure-auto-fnname-method']) || !isset($data['dotapp-secure-auto-fnname-public'])) && isSet($data['data']) && is_array($data['data'])) {
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
            // Tak je jasne ze je to metoda...
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
                if ( (!isset($data['dotapp-secure-auto-fnname-public']) || !isset($data['dotapp-secure-auto-fnname-action']) || !isset($data['dotapp-secure-auto-fnname-method']) || !isset($data['dotapp-secure-auto-fnname-public'])) && isSet($data['data']) && is_array($data['data'])) {
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
                        return $success($this,$name);
                    } else {
                        throw new \Exception("Success is not callable !");
                    }                    
                }
            }
        } else {
            if ($error === null) throw new \Exception("Signature is invalid !");
            if (!is_callable($error)) $error = $this->dotApp->stringToCallable($error);
            if (is_callable($error)) {
                return $error($this,$name);
            } else {
                throw new \Exception("Error callback is not callable ! Signature is also invalid !");
            }
        }
    }
	
}



?>