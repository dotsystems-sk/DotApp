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
 * @version   1.6 FREE
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
use Dotsystems\App\DotApp;

class Request {

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

    public function __set($name, $value) {
        if ($name == "response") throw new \InvalidArgumentException("request->response locked for edit !");
    }
	
	function __construct($dotapp,$rpath=1) {
        $this->dotapp = $dotapp;
        $this->dotApp = $this->dotapp;
        $this->DotApp = $this->dotapp;
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
        $this->dsm->use("default")->load();
        $this->gsLocked = false; // Zamkneme hlavne getters a setters
        $this->auth = new Auth($this->dotapp,$this->dsm);
    }

    public function getDsm() {
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

    public function data() {
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
                    } else {
                        // If not JSON, parse as form data
                        parse_str($input, $data);
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
                    } else {
                        // Parse as form data
                        parse_str($input, $data);
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
    
        // Ensure $data is an array
        return is_array($data) ? $data : [];
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

    public function crcCheck() {
        $method = strtolower($this->getMethod());
        $data = null;
        $crc = null;

        // Získame dáta a CRC podľa HTTP metódy
        switch ($method) {
            case 'get':
                $data = $_GET['data'] ?? null;
                $crc = $_GET['crc'] ?? null;
                break;
            case 'post':
                $data = $_POST['data'] ?? null;
                $crc = $_POST['crc'] ?? null;
                break;
            case 'put':
            case 'patch':
                $input = file_get_contents('php://input');
                parse_str($input, $params);
                $data = $params['data'] ?? null;
                $crc = $params['crc'] ?? null;
                break;
            case 'delete':
                $data = $_DELETE['data'] ?? null; 
                $crc = $_DELETE['crc'] ?? null;
                break;
            default:
                return false;
        }

        // Ak chýbajú dáta alebo CRC, vrátime false
        if ($data === null || $crc === null) {
            return false;
        }

        // Overíme CRC pomocou crc_check z DotApp
        $privateKey = $_SESSION['module_users_ckey'] ?? '';
        return $this->dotapp->crc_check($privateKey, $crc, $data);
    }
	
}



?>