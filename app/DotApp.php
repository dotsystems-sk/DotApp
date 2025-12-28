<?php

/**
 * DotApp Framework
 * 
 * This is the main class for the DotApp framework, providing core functionality 
 * and serving as the entry point for initializing the framework's components.
 * 
 * @package   DotApp Framework
 * @category  Framework Core
 * @author    Štefan Miščík <stefan@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.7 FREE
 * @date      2014 - 2025
 * @license   MIT License
 * 
 * License Notice:
 * Permission is granted to use, modify, and distribute this code under the MIT License,
 * provided this header is retained in all copies or substantial portions of the file,
 * including author and company information.
 */

namespace Dotsystems\App;

use Dotsystems\App\Parts\Auth;
use Dotsystems\App\Parts\Config;
use Dotsystems\App\Parts\DSM;
use Dotsystems\App\Parts\RouterObj;
use Dotsystems\App\Parts\Renderer;
use Dotsystems\App\Parts\CustomRenderer;
use Dotsystems\App\Parts\Routes;
use Dotsystems\App\Parts\Wrapper;
use Dotsystems\App\Parts\Databaser;
use Dotsystems\App\Parts\RequestObj;
use Dotsystems\App\Parts\Translator;
use Dotsystems\App\Parts\Bridge;
use Dotsystems\App\Parts\Logger;
use Dotsystems\App\Parts\DI;
use Dotsystems\App\Parts\Limiter;
use Dotsystems\App\Parts\Response;
use Dotsystems\App\Parts\Middleware;

global $translator;
$translator = new \stdClass();

class DotApp {
    private static $dotAppForStatic;
    private static $version = 1.7;
    public $auth; // Kvoli fasade AuthObj
    public $dotapper = array();
    public $initialized;
	public $router;
    public $Router; // Alias pre pascalCase
	private $modules;
	public $request;
    public $Request; // Alias pre pascalCase
	public $db;
    public $DB; // Alias pre pascalCase
	private $lang;
    public $CSRF = null;
	private $unprotected;
	private $custom_classes;
	private $module_classes;
    private $module_asked;
	private $translations;
	private $senders_email;
	private $senders_sms;
	private $enc_key;
	private $c_enc_key; // Custom encryption key
	private $debug_data;
	private $dotappdata;
	public $bridge;
    public $Bridge; // Alias pre PascalCase
	public $wrapper; // Wrapper objektov
	private $listeners; // on, off, trigger
	public $dsm; // DotApp Session Manager
    public $DSM; // PascalCase
	private $thendata; // Pri retazeni
    public $logger;
    public $Logger;
    private $middleware;
    private $middlewareStorage = array();
    private $bindings;
    private $instances;
    public $consumption=array();
    public $limiter;
    public $Limiter;
    private $defaultRoutes = false;
    public $renderer;
    public $Renderer;
    public $customRenderer;
    public $proxyServery = ['*'];
    private $runFromCacheBlocked = false;
    
	
	public function __debugInfo() {
        return [
            'publicData' => 'This is just part of dotapp. Nothing to see !'
        ];
    }

    public function middlewareStorage($object) {
        $object->setStorage($this->middlewareStorage,$this);
    }

    // Nastavime alebo ziskame globalny dotapp. Ak niekde inde potrebujeme instanciu dotapp ziskame ju kludne cez DotApp::dotApp();
    public static function dotApp($dotapp = null) {
        if ($dotapp === null) {
            if (self::$dotAppForStatic instanceof DotApp) return self::$dotAppForStatic;
            throw new \Exception("dotApp not found !");
        }

        if ($dotapp instanceof DotApp) {
            self::$dotAppForStatic = $dotapp;
        }        
    }

    public function consumption() {
        $consumption = array();
        $consumption['start_memory'] = $this->consumption['start_memory'];
        $consumption['start_time'] = $this->consumption['start_time'];
        $consumption['end_memory'] = memory_get_usage();
        $consumption['end_time'] = microtime(true);
        $consumption['execution_time'] = $consumption['end_time'] - $consumption['start_time'];
        $consumption['memory_usage'] = $this->formatBytes($consumption['end_memory'] - $consumption['start_memory']);
        $consumption['memory_usage_peak'] = $this->formatBytes(memory_get_peak_usage());
        return $consumption;
    }

    public function isDebugMode() {
        if (defined("DEBUG_MODE")) {
			return DEBUG_MODE;
		}
        return false;
    }
	
	function __construct($custom_key = "") {
        Config::set("app","name_hash",hash('sha256',hash('sha256',Config::get("app","name"))));
        self::dotApp($this);
        $this->dotapper['routes_module'] = "**ROOT**";
        $this->dotapper['routes'] = array();
        $this->db = new \stdClass();
        if ($custom_key === "") {
            $this->c_enc_key = Config::get("app","c_enc_key");
            $this->c_enc_key = $this->key2_upgrade($this->c_enc_key);
        } else {
            $this->c_enc_key = (string)$custom_key;
            Config::set("app","c_enc_key",$this->c_enc_key);
            $this->c_enc_key = $this->key2_upgrade($this->c_enc_key);
        }        
        $this->initialized = date("d.m.Y H:i:s");
        $this->consumption['start_memory'] = memory_get_usage();
        $this->consumption['start_time'] = microtime(true);
        // Pouzivame pre DI
        $this->bindings = array();
        $this->instances = array();
        $this->singleton(DotApp::class, function() {return $this;});
        $this->module_asked = array();
		$this->unprotected['post'] = $_POST;
		$this->unprotected['get'] = $_GET;
        $this->db = new DI(new Databaser($this),$this);
        $this->DB = $this->db;
        $this->db->diSet();
		$this->dsm = new DSM("dotapp");
        $this->DSM = $this->dsm; // Pascalcase
		$this->dsm->load();
        $this->generate_enc_key();
        $this->protect($_COOKIE);
		$this->request = new RequestObj($this);
        $this->Request = $this->request;
        $this->customRenderer = new CustomRenderer($this);
		$this->router = new RouterObj($this);
        $this->Router = $this->router;
        $this->renderer = $this->router->renderer;
        $this->Renderer = $this->router->renderer;
        $this->auth = $this->request->auth; // Kvoli fasade
        // Nastavime funkcie pre limiter tak, aby pouzivali ako ulozisko DSM. Uzivatel si moze pouzit ake chce ak si ich zaregistruje cez singleton napriklad.
        $this->register_limiter_fn();
        $this->builtInMiddleware();
        $this->bind(Response::class, function() {return new Response($this->request);});
        $this->bind(Renderer::class, function() {return new Renderer($this);});
        $this->singleton(RouterObj::class, function() {return $this->router;});
        $this->singleton(RequestObj::class, function() {return $this->request;});
        $this->singleton(Auth::class, function() {return $this->request->auth;});
        $this->logger = Logger::use();
        $this->Logger = $this->logger; // Alias pre pascalcase
        $this->singleton(Logger::class, function() {return $this->logger;});
        

        
        if ($this->dsm->get('_formCSRF') != null) {
            $this->CSRF = $this->dsm->get('_formCSRF');
        } else {
            $this->CSRF = base64_encode(random_bytes(16));
            $this->dsm->set('_formCSRF', $this->CSRF);
        }

		if ($this->dsm->get('_bridge') != null) {
			$this->Bridge = &$this->bridge;
            $this->bridge = $this->dsm->get('_bridge');
		} else {
            $this->Bridge = &$this->bridge; // Musi to byt takto inak by Bridge nebol pristupny v konsturktore Bridge
			$this->bridge = new Bridge($this);
		}
		$this->lang="en_US";
		$this->dotappdata = array();
		$this->set_wrapper();
		new Translator();
        $this->middleware = array();
        $this->listeners = array();
        $this->request->response->headers[base64_decode("WC1Qb3dlcmVkLUJ5")] = base64_decode("ZG90YXBwOyB3d3cuZG90c3lzdGVtcy5zaw==");
        $this->request->response->headers[base64_decode("WC1GcmFtZXdvcms=")] = base64_decode("ZG90YXBw");
        $this->parseRouterCache();
    }

    private function blockRunFromCache() {
        $this->runFromCacheBlocked = true;
    }

    private function runFromCache(&$data) {
        if ($this->runFromCacheBlocked === false) {
            // Najdeme subor a spravime include
        }
    }

    private function parseRouterCache() {
        $routeCache = __ROOTDIR__."/app/runtime/routercache/";
        // Najdeme subor a spravime include
    }

    private function builtInMiddleware() {
        new Middleware("dotapp.global.loader",function($vstup) { return $vstup; });
    }

    /**
     * Registers default getter and setter functions for the rate limiter.
     * 
     * This method initializes the limiter array with default getter and setter functions
     * that use the DotApp Session Manager (DSM) as storage. The getter retrieves limit
     * values by key from the session storage, while the setter stores limit values by key.
     * These functions are used by the rate limiting system to track and enforce request limits.
     * 
     * The limiter can be customized by registering different getter/setter implementations
     * through the singleton pattern if needed.
     * 
     * @return void
     */
    public function register_limiter_fn() {
        $this->limiter = array();

        $this->limiter['getter'] = function($key) {
            $limity = array();
            if ($this->dsm->get('_default_limiter') != null && is_array($this->dsm->get('_default_limiter'))) {
                $limity = $this->dsm->get('_default_limiter');
            }
            
            return isset($limity[$key]) ? $limity[$key] : null;
        };

        $this->limiter['setter'] = function($key, $value) {
            $limity = array();
            if ($this->dsm->get('_default_limiter') != null && is_array($this->dsm->get('_default_limiter'))) {
                $limity = $this->dsm->get('_default_limiter');
            }
            $limity[$key] = $value;
            $this->dsm->set('_default_limiter', $limity);
        };

        $this->Limiter = &$this->limiter;
    }

    public function trustProxy(array $proxy) {
        /*
            ['*'] - ver vsetkym
            priklad: ['10.10.1.2','10.10.1.3']
            tuto vlastnost vyuziva objekt Request.
            Akykovek objekt Request bude obsahovat toto pole lebo je spolocne a zdielane nezavisle na instancii requestu
        */
        $this->proxyServery = $proxy;
    }

	/**
	 * Sets the encryption key for the current instance of the dotapp framework.
	 * The provided key is stored as c_enc_key and assigned to thendata, allowing 
	 * for easy retrieval later. This method returns the current instance, enabling 
	 * method chaining with the THEN method for further configuration or operations.
	 *
	 * @param mixed $key The encryption key to be set.
	 * @return $this The current instance for method chaining.
	 */
	public function enc_key($key) {
		$this->c_enc_key = $key;
		return $this;
	}

    public function encKey() {
        return $this->c_enc_key;
    }

	/**
	 * Creates and returns a new instance of the renderer class, passing the 
	 * current instance of dotapp as a parameter. This function is useful for 
	 * initializing a new renderer that can work with the current state of the 
	 * application, facilitating custom rendering logic without directly modifying 
	 * the core framework. Note that this method does not return the current 
	 * instance, thus it does not support chaining.
	 *
	 * @return renderer A new renderer instance.
	 */
	public function new_renderer() {
		return new Renderer($this);
	}

    // Vytvorime a vratime novy renderer...
    public function newRenderer($dotapp=null) {
        return new Renderer($this);
    }

	/**
	 * Initializes and sets a new wrapper for the current instance of the dotapp 
	 * framework. It creates a closure that captures the current instance and 
	 * returns it. This method checks if the calling object is an instance of 
	 * dotapp and if the provided object is an instance of wrapper. If both 
	 * conditions are met, it returns the current instance as part of the wrapper 
	 * functionality. This method also updates thendata to hold the new wrapper 
	 * instance, returning the current instance to support method chaining with 
	 * the THEN method for additional operations.
	 *
	 * @return $this The current instance for method chaining.
	 */
	private function set_wrapper() {
		$dotappasarg = $this;
		$dotappasargf = function() use ($dotappasarg) {
			return($dotappasarg);
		};
		$wrapper_f = function($objekt) use ($dotappasargf) {
			if (get_class($this) == "Dotsystems\App\DotApp" && get_class($objekt) == "Dotsystems\App\Parts\Wrapper") {
				return($dotappasargf());
			}
		};
		$this->wrapper = new wrapper($wrapper_f);
		$this->thendata = $this->wrapper;
		return $this;
	}
	
	/**
	 * Custom error handler for the dotapp framework that formats and logs error messages.
	 * This method constructs a detailed error message containing the error number, file, 
	 * line number, and error string. By returning true, it indicates that the error has 
	 * been handled, preventing the default PHP error handler from being invoked.
	 *
	 * @param int $errno The level of the error.
	 * @param string $errstr The error message.
	 * @param string $errfile The filename where the error occurred.
	 * @param int $errline The line number where the error occurred.
	 * @return bool Always returns true to indicate the error has been handled.
	 */
	public function errhandler($errno, $errstr, $errfile, $errline) {
		$errorMessage = "Error [$errno] in $errfile on line $errline: $errstr";
		//echo "<b>Custom error:</b> $errorMessage<br>";
		return true;
	}

	/**
	 * Checks if the specified data exists in the dotappdata array using a hashed key.
	 * This method utilizes the md5 hash of the provided name to check for the existence 
	 * of the corresponding entry in the dotappdata array, returning true if it exists 
	 * and false otherwise.
	 *
	 * @param string $name The name of the data to check.
	 * @return bool True if the data exists, false otherwise.
	 */
	public function isset_data($name) {
		return(isset($this->dotappdata[md5($name)]));
	}

	/**
	 * Sets the value for a specified name in the dotappdata array, using a hashed key.
	 * This method takes a name and a value, hashes the name using md5, and stores the 
	 * value in the dotappdata array, allowing for easy retrieval later.
	 *
	 * @param string $name The name under which the value will be stored.
	 * @param mixed $value The value to be stored.
	 * @return void
	 */
	public function set_data($name, $value) {
		$this->dotappdata[md5($name)] = $value;
	}

	/**
	 * Retrieves the value associated with the specified name from the dotappdata array, 
	 * using a hashed key. This method checks if the data exists in the dotappdata array 
	 * and returns the value if found; otherwise, it returns false.
	 *
	 * @param string $name The name of the data to retrieve.
	 * @return mixed The value associated with the name, or false if it does not exist.
	 */
	public function get_data($name) {
		if (isset($this->dotappdata[md5($name)])) return($this->dotappdata[md5($name)]); else return(false);
	}

	/**
	 * Registers an email sender with a specified name for use within the dotapp framework.
	 * This method takes a sender name and a sender email address, storing them in the 
	 * senders_email array for later use. It returns the current instance to allow for 
	 * method chaining with the THEN method.
	 *
	 * @param string $sendername The name of the email sender.
	 * @param string $sender The email address of the sender.
	 * @return $this The current instance for method chaining.
	 */
	public function register_email_sender($sendername, $sender) {
		$this->senders_email[$sendername] = $sender;
		return($this);
	}

    public function db() {
        return new databaser();
    }

	
	/**
	 * Registers an SMS sender with a specified name for use within the dotapp framework.
	 * This method takes a sender name and a sender phone number, storing them in the 
	 * senders_sms array for later use. It returns the current instance to allow for 
	 * method chaining with the THEN method.
	 *
	 * @param string $sendername The name of the SMS sender.
	 * @param string $sender The phone number of the sender.
	 * @return $this The current instance for method chaining.
	 */
	public function register_sms_sender($sendername, $sender) {
		$this->senders_sms[$sendername] = $sender;
		return($this);
	}

	/**
	 * Retrieves the email sender associated with the specified name. Each email sender 
	 * must implement the send_email($data) function, where $data should include the 
	 * recipient and sender details. This method returns the corresponding sender 
	 * from the senders_email array.
	 *
	 * @param string $sendername The name of the email sender to retrieve.
	 * @return mixed The email sender associated with the name, or null if not found.
	 */
	public function email_sender($sendername) {
		/*
			Every EMAIL sender must contain the function send_email($data);
			$data['recipient'];
			$data['sender'];
		*/
		return($this->senders_email[$sendername]);
	}

	/**
	 * Retrieves the SMS sender associated with the specified name. Each SMS sender 
	 * must implement the send_sms($number, $text) function. This method returns the 
	 * corresponding sender from the senders_sms array.
	 *
	 * @param string $sendername The name of the SMS sender to retrieve.
	 * @return mixed The SMS sender associated with the name, or null if not found.
	 */
	public function sms_sender($sendername) {
		/* Each SMS sender must contain the function send_sms($number, $text); */
		return($this->senders_sms[$sendername]);
	}

	/**
	 * Adds a new module to the dotapp framework. This process is automatically handled 
	 * by the constructor of \Dotsystems\App\Parts\module. For instance, if a module 
	 * named dotcms is created, it will be automatically added. If the module name is 
	 * "system", it is changed to "nonerpsystem" to avoid conflicts.
	 *
	 * @param string $modulename The name of the module to add.
	 * @param object $module The module object to register.
	 * @return void
	 */
	public function module_add($modulename, &$module) {
		if ($modulename == "system") {
			$modulename = "nonerpsystem";
		}
		$this->modules[$modulename] = $module;
	}

	/**
	 * A shortcut for retrieving the module class. This method returns the library object 
	 * of the specified module, allowing individual modules to share libraries and data 
	 * among themselves.
	 *
	 * @param string $module_name The name of the module to retrieve the class from.
	 * @param string $classname The name of the class to retrieve.
	 * @return object The class object of the specified module.
	 */
	function mclass($module_name, $classname) {
        $this->module($module_name)->load();
		return($this->module_classes[$module_name][$classname]);
	}

	/**
	 * An alias for the mclass method, serving as a shortcut for accessing the module 
	 * library. This alternative naming convention may be easier for some users to remember.
	 *
	 * @param string $module_name The name of the module to retrieve the class from.
	 * @param string $classname The name of the class to retrieve.
	 * @return object The class object of the specified module.
	 */
	function mlibrary($module_name, $classname) {
        $this->module($module_name)->load();
		return($this->mclass($module_name, $classname));
	}

	/**
	 * Retrieves the object of the specified module. This allows one module to access 
	 * another module's functionality. If the module name is "system", it is changed to 
	 * "nonerpsystem" to avoid conflicts.
	 *
	 * @param string $modulename The name of the module to retrieve.
	 * @return mixed The module object associated with the name, or null if not found.
	 */
	public function module($modulename) {
		if ($modulename == "system") {
			$modulename = "nonerpsystem";
		}
        
        if (!in_array($modulename,$this->module_asked)) {
            $this->load_module_listeners($modulename);
            $this->load_module($modulename);
        }
        if (!isSet($this->modules[$modulename])) throw new \InvalidArgumentException("Module ".$modulename." not registered correctly !");
        return($this->modules[$modulename]);
	}

    // Vracia pocet modulov - vyuzitelne ak by nejaky modul potreboval vediet ci je sam alebo nie.
    public function modules() {
		return count($this->modules);
	}

    public function moduleExist($module) {
        return $this->modules[$module] ?? null;
    }

	
	/**
	 * Registers a module class within the dotapp framework. This function is invoked when 
	 * a library is loaded in a module, such as $this->load_library("dotcms"). The constructor 
	 * of the library \Dotsystems\App\Parts\library will automatically register the library 
	 * in dotapp. The module name and class instance are stored, allowing for easy access 
	 * to the module's functionality.
	 *
	 * @param string $module_name The name of the module to register.
	 * @param object $class The instance of the module class to register.
	 * @return $this The current instance for method chaining.
	 */
	function register_module_class($module_name, $class, string $classnamestatic="") {
		$classname = get_class($class);
        // Obalenie DI kontajnerom musime extrahovat povodnu triedu
        if (isset($class->classname) && $class->classname != null) {
            $classname = $class->classname;
        }
		$classname = str_replace("Dotsystems\\App\\Modules\\","",$classname);
		$classnamea = explode("\\",$classname);
		$classname = $classnamea[0];
		$classname = str_replace("module_","",$classname);
        if ($classnamestatic !== "") {
            $classname = $classnamestatic;
        }
		$this->module_classes[$module_name][$classname] = $class;
		$this->thendata = $class;
		return $this;
	}

	/**
	 * Retrieves an unprotected POST variable from the request. All POST and GET data are 
	 * automatically protected. This method provides access to unprotected POST data, allowing 
	 * retrieval of specific variables when necessary.
	 *
	 * @param string $premenna The name of the POST variable to retrieve.
	 * @return mixed The value of the specified unprotected POST variable, or null if not found.
	 */
	public function post($premenna) {
		return($this->unprotected['post'][$premenna]);
	}

	/**
	 * Retrieves an unprotected GET variable from the request. Similar to the post() method, 
	 * this method allows access to unprotected GET data. It returns the value of a specific 
	 * variable when needed.
	 *
	 * @param string $premenna The name of the GET variable to retrieve.
	 * @return mixed The value of the specified unprotected GET variable, or null if not found.
	 */
	public function get($premenna) {
		return($this->unprotected['get'][$premenna]);
	}

	/**
	 * Registers a custom class within the dotapp framework. This method is particularly useful 
	 * when creating a library of functions instead of a module. The custom class is passed the 
	 * dotapp instance in its constructor to enable internal functions of the library to access 
	 * the entire application. Example usage:
	 * 
	 * require_once __ROOTDIR__ . '/App/CustomClasses/system.class.php';
	 * $dotapp->register_custom_class("system", new system($dotapp));
	 *
	 * @param string $classname The name to register the custom class under.
	 * @param object $class The instance of the custom class to register.
	 * @return $this The current instance for method chaining.
	 */
	/**
     * @deprecated Legacy method. Use Dependency Injection or Facades instead.
     * Memory inefficient due to eager loading.
     */
	function register_custom_class($classname, $class) {
		$this->thendata = $class;
		$this->custom_classes[$classname] = $class;
		$this->thendata = $class;
		return $this;
	}

	/**
	 * A shortcut for working with custom classes. This method returns the object of the 
	 * specified custom library, allowing access to its functions. Example usage:
	 * 
	 * require_once __ROOTDIR__ . '/App/CustomClasses/system.class.php';
	 * $dotapp->register_custom_class("system", new system($dotapp));
	 * 
	 * Access the library with:
	 * $dotapp->cclass("system")->function_from_library();
	 *
	 * @param string $classname The name of the custom class to retrieve.
	 * @return object The instance of the specified custom class.
	 */
	/**
     * @deprecated Legacy access. Use DI injection in controller method signature instead.
     */
	function cclass($classname) {
		return($this->custom_classes[$classname]);
	}

	
	/*
		Kedze som z vychodu, tak ako inak by to bolo keby som tu nieco nezakomponoval.
		$dotapp->davajhet(); je len aliasom k funkcii run(); Mne sa davajhet viac paci :)
	*/
	
	function davajhet() {
		$this->run();
	}
	
	/**
	 * Executes the main functionality of the application. This method initiates 
	 * the routing process and outputs the result. It serves as the entry point 
	 * for executing the core logic of the framework.
	 */
	function run($request = null) {
        if (defined('__DOTAPPER_RUN__')) return null;
        if (Config::session("rm_autologin") === true) {
            if (Auth::isLogged() === false) {
                Auth::autoLogin();             
            }
        }
        $request = $request ?? $request = $this->router->resolve();
        $this->runRequest($request);
    }

    public function runRequest($request) {
        // Kompatibilita so starsimi verziami dotapp aby ostali funkcne
        if (is_string($request)) {
            echo $request;
            return;
        }
    
        if (is_object($request) && isset($request->response)) {

            if ($request->response->isSent) {
                return;
            }
    
            if ($request->response->redirect) {
                header("Location: {$request->response->redirect}", true, $request->response->status);
                $request->response->isSent = true;
                return;
            }
    
            http_response_code($request->response->status);
    
            foreach ($request->response->headers as $name => $value) {
                header("$name: $value");
            }
    
            foreach ($request->response->cookies as $name => $cookie) {
                setcookie(
                    $name,
                    $cookie["value"],
                    $cookie["expire"],
                    $cookie["path"]
                );
            }
    
            if (isset($request->response->body)) {
                echo $request->response->body;
            }
    
            $request->response->isSent = true;
        }
    }

	/**
	 * Invokes a callback function with the current state of the application 
	 * (stored in $thendata). If the provided callback is callable, it is 
	 * executed with the current data, and the result is stored back in 
	 * $thendata. This allows for method chaining and flexible processing.
	 *
	 * @param callable|string $callback A callable function or a string that 
	 *                                   represents a function name.
	 * @return $this The current instance for method chaining.
	 */
	public function then($callback = "") {
		// DEPRECATED !!!! DO NOT USE !!!!
		if (is_callable($callback)) {
			$this->thendata = $callback($this->thendata);
		}
		return $this;
	}

	/**
         * Registers a listener for a specified event. When the event occurs, 
         * the corresponding callback function will be executed. The method returns 
         * a unique listener ID that can be used for reference or removal of the listener.
         *
         * The function supports three modes:
         * 
         * 1. **Basic event listener:** Registers a listener for a specific event.
         *    Example usage: $listenerid = $dotapp->on("user.registered", function() { echo "Finally someone registered."; });
         *
         * 2. **Route-based event listener:** Registers a listener that is triggered only 
         *    if the current URL matches the specified route pattern.
         *    Example usage: $listenerid = $dotapp->on("/product/*", "product.sold", function() { echo "A product has been sold."; });
         *    The event "product.sold" will be registered only if the current URL matches "/product/*". Otherwise, the function returns without registering the listener.
         *
         * 3. **Method and route-based event listener:** Registers a listener that is triggered only 
         *    if the current HTTP method (e.g., GET, POST) and URL match the specified method and route pattern.
         *    Example usage: $listenerid = $dotapp->on("get", "/product/{id:i}", "product.view", function($params) { echo "Viewing product ID: " . $params['id']; });
         *    The event "product.view" will be registered only if the HTTP method is "GET" and the current URL matches "/product/{id:i}".
         *
         * @param string $eventname The name of the event to listen for (used in modes 1 and 2), or the HTTP method (e.g., "get", "post") in mode 3.
         * @param callable $callback The function to be executed when the event occurs.
         * @param string|null $route (Optional) A route pattern that must match the current URL for the event to be registered (used in modes 2 and 3).
         * @param string|null $method (Optional) An HTTP method that must match the current request method (used in mode 3).
         * @return $this The current instance for method chaining.
         * @throws InvalidArgumentException If the provided callback is not callable or the number of arguments is invalid.
     */
    public function bind(string $key, $resolver): void {
        $this->bindings[$key] = ['resolver' => $this->stringToCallable($resolver), 'shared' => false];
    }

    public function singleton(string $key, $resolver): void {
        $this->bindings[$key] = ['resolver' => $this->stringToCallable($resolver), 'shared' => true];
    }

    public function resolve(string $key) {
        if (!isset($this->bindings[$key])) {
            throw new \Exception("No binding for key: $key");
        }
        $binding = $this->bindings[$key];
        if ($binding['shared']) {
            if (!isset($this->instances[$key])) {
                try {
                    $this->instances[$key] = call_user_func($binding['resolver']);
                } catch(\Exception $e) {
                    $err = $e;
                }
                    
            }
            return $this->instances[$key];
        }
        return call_user_func($binding['resolver']);
    }

    public function middleware(string $name,$callback=null, ...$args) {
        if (!isSet($this->middlewareStorage['middleware'])) {
            $this->middlewareStorage['middleware'] = array();
            $this->middlewareStorage['chains'] = array();
        }
		$originalName = $name;
        if (is_string($name)) $name = array($name);
        $name = json_encode($name);
        if (isset($this->middlewareStorage['middleware'][$name])) {
            return new Middleware($originalName,$callback,...$args);
        } else throw new \Exception("Undefined middleware");        
    }
     
    public function middleware2(string $name,$callback=false, ...$args): \Closure {
		if ($callback !== false) $callback = $this->stringToCallable($callback,...$args);
        return $this->uniware("middleware", $name, $callback, ...$args);
    }

    public function middlewareCall(string $name, ...$args) {
        return $this->uniwareCall("middleware", $name, ...$args);
    }

    private function uniware(string $type, string $name,$callback=false, ...$args): \Closure {
        if (!isset($this->middleware[$type])) $this->middleware[$type] = array();
        // Podelime to na funkcie - string to callable
        if ($callback === false) {
            if (is_callable($this->middleware[$type][$name])) {
                // Ak boli predane argumenty tak zavolame funkciu
                if (!empty($args)) return function() use (&$name,$args,&$type) {
                    return call_user_func($this->middleware[$type][$name],...$args);
                };
                return $this->middleware[$type][$name];
            } else {
                throw new \InvalidArgumentException("Task '".$type."' not defined !");
            }            
        }
        if (is_callable($callback)) {
            $this->middleware[$type][$name] = $callback;
        } else if (is_string($callback)) {
            $this->middleware[$type][$name] = $this->stringToCallable($callback,...$args);
        } else {
            throw new \InvalidArgumentException("Unable to recognize module name, set module name manually ! Syntax: module:controller@function ");
        }
        return $this->middleware[$type][$name];
    }

    private function uniwareCall(string $type, string $name, ...$args) {
        if (is_callable($this->middleware[$type][$name])) {
            return call_user_func($this->middleware[$type][$name],...$args);
        } else {
            throw new \InvalidArgumentException("Task '".$type."' not defined !");
        } 
    }

    

    private function validateFnName($input) {
        $pattern = '/^([a-zA-Z0-9_-]+:)?[\\\\a-zA-Z0-9_-]+@[a-zA-Z_][a-zA-Z0-9_]*$/';
        return preg_match($pattern, $input) === 1;
    }

    public function stringToCallable($callback, ...$argsSend) {

        // Bez DI kontajnera ak pouzijeme noDI wrapper.
        if ($callback instanceof \Dotsystems\App\Parts\NoDI) {
            return function(...$args) use ($callback) {
                return $callback->call(...$args);
            };
        }
        
        // Ak je pole middleware, alebo middleware Chain
        if ($callback instanceof Middleware) {
            $chain = $callback->chain();
            $fn = function() use ($chain) {
                $navratFn = $chain->callAllMiddlewares();
                if ($navratFn instanceof Response) {
                    $this->runRequest($this->request);
                    exit();
                }
                return $navratFn;
            };
            return $fn;
        }

        if (Middleware::instanceOfMiddlewareChain($callback)) {
            $chain = $callback;
            $fn = function() use ($chain) {                
                $navratFn = $chain->callAllMiddlewares();
                if ($navratFn instanceof Response) {
                    $this->runRequest($this->request);
                    exit();
                }
                return $navratFn;
            };
            return $fn;
        }

        // Ak je pole ako vstup
        if (is_array($callback)) {
            // Je pole asociativne? (obsahuje kluce 'module', 'class', 'function') ?
            if (isset($callback['module']) || isset($callback['class']) || isset($callback['function'])) {
                // Asociativne pole
                $module = $callback['module'] ?? null;
                $class = $callback['class'] ?? null;
                $function = $callback['function'] ?? null;
            } else {
                // Indexovane pole
                $count = count($callback);
                if ($count === 3) {
                    // Format: ['module', 'class', 'function']
                    $module = $callback[0] ?? null;
                    $class = $callback[1] ?? null;
                    $function = $callback[2] ?? null;
                } elseif ($count === 2) {
                    // Format: ['class', 'function']
                    $module = null;
                    $class = $callback[0] ?? null;
                    $function = $callback[1] ?? null;
                } else {
                    throw new \InvalidArgumentException("Indexed array must contain 2 or 3 elements (class and function, or module, class, and function)!");
                }
            }
        
            // Vytvorenie textového formátu
            if ($module !== null && $module !== '' && $class !== null && $class !== '' && $function !== null && $function !== '') {
                $callback = "$module:$class@$function";
            } elseif ($class !== null && $class !== '' && $function !== null && $function !== '') {
                $callback = "$class@$function";
            } else {
                throw new \InvalidArgumentException("Callback must contain at least non-empty class and function!");
            }
        }
    
        if (is_string($callback)) {
            // Ak je na konci vykrincnik, tak DI uplne vynechame (ultra rychly call)
            $useDI = true;
            if (substr($callback, -1) === '!') {
                $useDI = false;
                $callback = rtrim($callback, '!');
            }

            if ($this->validateFnName($callback)) {
                $callbackA = explode("@", $callback);
                $funkcia = $callbackA[1];
        
                if (strpos($callbackA[0], ":") !== false) {
                    // Module handling
                    $callback1A = explode(":", $callbackA[0]);
                    if (strpos($callback1A[1], "\\") !== false) {
                        $trieda = '\Dotsystems\App\Modules\\' . $callback1A[0] . '\\' .$callback1A[1];
                    } else {
                        $trieda = '\Dotsystems\App\Modules\\' . $callback1A[0] . '\Controllers\\' .$callback1A[1];
                    }
                } else {                
                    $trieda = $callbackA[0];
                }
         
                return function(...$args) use ($trieda, $funkcia, $argsSend, $useDI) {
                    $trieda = str_replace("\\\\","\\",$trieda);
                    $trieda = str_replace("\\\\","\\",$trieda);
        
                    // Ak mame priznak !, tak robime len cisty call bez reflexie a DI
                    if ($useDI === false) {
                        if (!class_exists($trieda)) throw new \Exception("Class $trieda not found!");
                        $instance = new $trieda($this);
                        return call_user_func_array([$instance, $funkcia], array_merge($argsSend, $args));
                    }

                    return $this->di($trieda, $funkcia, $argsSend, ...$args);
                };
            } else {
                throw new \InvalidArgumentException("Incorrect controller name! Syntax: module:controller@function or array ['module','class','function']");
            }
        }

        // Ak pride closure, posleme ju standardne cez di
        if ($callback instanceof \Closure) {
            return function(...$args) use ($callback, $argsSend) {
                return $this->di(null, $callback, $argsSend, ...$args);
            };
        }
    }

    public function di($trieda, $funkcia, $argsSend, ...$args) {
        $resolvedArgs = [];
        
        if ($funkcia instanceof \Closure) {

            $reflection = new \ReflectionFunction($funkcia);
            $params = $reflection->getParameters();
            
            foreach ($params as $param) {
                if ($param->getType() && !$param->getType()->isBuiltin()) {
                    $type = $param->getType()->getName();
                    if (isset($this->bindings[$type])) {
                        $resolvedArgs[] = $this->resolve($type);
                    } else {
                        throw new \InvalidArgumentException("No binding registered for $type");
                    }
                } else {
                    $resolvedArgs[] = array_shift($args) ?? array_shift($argsSend);
                }
            }
            
            $finalArgs = (!empty($args)) ? array_merge($resolvedArgs, $args) : $resolvedArgs;
            //$funkcia = $funkcia->bindTo($this, get_class($this));
            return call_user_func_array($funkcia, $finalArgs);
        } else {
            class_exists($trieda, true); 
            
            // Reflection for regular method
            $reflection = new \ReflectionMethod($trieda, $funkcia);
            $params = $reflection->getParameters();
            
            foreach ($params as $param) {
                if ($param->getType() && !$param->getType()->isBuiltin()) {
                    $type = $param->getType()->getName();
                    if (isset($this->bindings[$type])) {
                        $resolvedArgs[] = $this->resolve($type);
                    } else {
                        throw new \InvalidArgumentException("No binding registered for $type");
                    }
                } else {
                    /*
                        Predvolane alebo dynamicke parametre
                        Poznamka: Musim doprogramovat parametre ktor vieme predat cez middlewareCall !!!
                        Aby zvladala anonymka aj parametre predavane middleCallom navyse.
                    */
                    $resolvedArgs[] = array_shift($args) ?? array_shift($argsSend);
                }
            }
            
            $finalArgs = (!empty($args)) ? array_merge($resolvedArgs, $args) : $resolvedArgs;
            return call_user_func_array([$trieda, $funkcia], $finalArgs);
        }
    }

    /**
     * Registers a listener for a specified event. When the event occurs,
    * the corresponding callback function will be executed. The method returns
    * a unique listener ID that can be used for reference or removal of the listener.
    *
    * The function supports three modes:
    *
    * 1. **Basic event listener:** Registers a listener for a specific event.
    *    Example usage: $listenerid = $dotapp->on("user.registered", function() { echo "Finally someone registered."; });
    *
    * 2. **Route-based event listener:** Registers a listener that is triggered only
    *    if the current URL matches the specified route pattern.
    *    Example usage: $listenerid = $dotapp->on("/product/*", "product.sold", function() { echo "A product has been sold."; });
    *    The event "product.sold" will be registered only if the current URL matches "/product/*". Otherwise, the function returns without registering the listener.
    *
    * 3. **Method and route-based event listener:** Registers a listener that is triggered only
    *    if the current HTTP method (e.g., GET, POST) and URL match the specified method and route pattern.
    *    Example usage: $listenerid = $dotapp->on("get", "/product/{id:i}", "product.view", function($params) { echo "Viewing product ID: " . $params['id']; });
    *    The event "product.view" will be registered only if the HTTP method is "GET" and the current URL matches "/product/{id:i}".
    *
    * @param string $eventname The name of the event to listen for (used in modes 1 and 2), or the HTTP method (e.g., "get", "post") in mode 3.
    * @param callable $callback The function to be executed when the event occurs.
    * @param string|null $route (Optional) A route pattern that must match the current URL for the event to be registered (used in modes 2 and 3).
    * @param string|null $method (Optional) An HTTP method that must match the current request method (used in mode 3).
    * @return $this The current instance for method chaining.
    * @throws InvalidArgumentException If the provided callback is not callable or the number of arguments is invalid.
    */
    public function on(...$args) {
        // Aliasy som zaviedol kvoli tomu ze niekto moze z ineho frameworku byt zvyknuty na ine nazvy. Tak ak je taky odvazny nech si ich tu nastrka kolko chce.
        // Akurat ze po update jadra tu najde prd makovy.
        $aliasy = [
            'dotapp.middleware' => 'dotapp.router.resolve'
        ];

        switch (count($args)) {
            case 2:
                $eventname = $args[0];
                $eventname = strtolower($eventname); // Nech je case insensitivny
                $eventname = $aliasy[$eventname] ?? $eventname;
                $callback = $args[1];
				$callback = $this->stringToCallable($callback);
                if (is_callable($callback)) {
                    $listenerid = (isset($this->listeners['listenersids'][$eventname]) && is_array($this->listeners['listenersids'][$eventname])) ? count($this->listeners['listenersids'][$eventname]) : 0;
                    $listenerid = $eventname . $listenerid . rand(100000, 200000) . md5(rand(100000, 200000));
                    $this->listeners['listenersids'][$eventname][] = $listenerid;
                    $this->listeners['listeners'][$listenerid] = $callback;
                } else {
                    throw new \InvalidArgumentException("Incorrect input! Second argument must be a function!");
                }
                $this->thendata = $listenerid;
                return $this->offhandler($listenerid);
                break;
            case 3:
                $route = $args[0];
                $eventname = $args[1];
                $eventname = strtolower($eventname);
                $eventname = $aliasy[$eventname] ?? $eventname;
                $callback = $args[2];
				$callback = $this->stringToCallable($callback);
                
                // Routa nesedi, nepridame 
                if ($this->router->match_url($route, $this->router->request->getPath()) === false) {
                    return false;
                }
                
                if (is_callable($callback)) {
                    $listenerid = (isset($this->listeners['listenersids'][$eventname]) && is_array($this->listeners['listenersids'][$eventname])) ? count($this->listeners['listenersids'][$eventname]) : 0;
                    $listenerid = $eventname . $listenerid . rand(100000, 200000) . md5(rand(100000, 200000));
                    $this->listeners['listenersids'][$eventname][] = $listenerid;
                    $this->listeners['listeners'][$listenerid] = $callback;
                } else {
                    throw new \InvalidArgumentException("Incorrect input! Third argument must be a function!");
                }
                $this->thendata = $listenerid;
                return $this->offhandler($listenerid);
                break;
            case 4:
                    $method = strtolower($args[0]);
                    $route = $args[1];
                    $eventname = $args[2];
                    $eventname = strtolower($eventname);
                    $eventname = $aliasy[$eventname] ?? $eventname;
                    $callback = $args[3];
					$callback = $this->stringToCallable($callback);
                    
                    // Metoda nesedi, nepridame 
                    if ($this->router->request->getMethod() !== $method) {
                        return false;
                    }

                    // Routa nesedi, nepridame 
                    if ($this->router->match_url($route, $this->router->request->getPath()) === false) {
                        return false;
                    }
                    
                    if (is_callable($callback)) {
                        $listenerid = (isset($this->listeners['listenersids'][$eventname]) && is_array($this->listeners['listenersids'][$eventname])) ? count($this->listeners['listenersids'][$eventname]) : 0;
                        $listenerid = $eventname . $listenerid . rand(100000, 200000) . md5(rand(100000, 200000));
                        $this->listeners['listenersids'][$eventname][] = $listenerid;
                        $this->listeners['listeners'][$listenerid] = $callback;
                    } else {
                        throw new \InvalidArgumentException("Incorrect input! Forth argument must be a function!");
                    }
                    $this->thendata = $listenerid;
                    return $this->offhandler($listenerid);
                    break;
            default:
                throw new \InvalidArgumentException("Incorrect number of inputs! 2 or 3 expected");
                break;
        }
        
    }

    /**
     * Creates and returns an anonymous class instance for handling listener removal.
     *
     * This method generates an anonymous class with a reference to the DotApp instance
     * and the listener ID. The anonymous class provides an `off` method to remove the
     * associated listener.
     *
     * @param string $listenerid The unique ID of the listener to be removed.
     * @return object An instance of an anonymous class with an `off` method for listener removal.
     */
    private function offhandler($listenerid) {
        return new class($this, $listenerid) {
            private $dotapp;
            public $id;
    
            public function __construct($dotapp, $listenerid) {
                $this->dotapp = $dotapp;
                $this->id = $listenerid;
            }
    
            public function off() {
                $this->dotapp->off($this->id);
            }
        };
    }

	/**
	 * Stops a specific listener by its listener ID, which is returned 
	 * by the `on` function. This allows for the removal of individual 
	 * event listeners when they are no longer needed.
	 *
	 * Example usage:
	 * $dotapp->off($listenerid);
	 *
	 * @param string $listenerid The unique ID of the listener to be removed.
	 * @return $this The current instance for method chaining.
	 */
	private function off($listenerid) {
        // Pre spatnu kompatibilitu
		unset($this->listeners['listeners'][$listenerid]);
		$this->thendata = $listenerid;
		return $this;
	}

	/**
	 * Stops all listeners associated with a specific event. This method 
	 * allows for the removal of all listeners tied to an event name, 
	 * effectively cleaning up event handling for that event.
	 *
	 * Example usage:
	 * $dotapp->offevent($eventname);
	 *
	 * @param string $eventname The name of the event for which listeners 
	 *                          should be removed.
	 * @return $this The current instance for method chaining.
	 */
	public function offevent($eventname) {
		foreach ($this->listeners['listenersids'][$eventname] as $key => $listenerid) {
			unset($this->listeners['listeners'][$listenerid]);
			unset($this->listeners['listenersids'][$eventname][$key]);
		}
		return $this;
	}

	/**
	 * Triggers an event by its name, executing all associated listeners 
	 * with the provided data. The method passes the specified data and 
	 * any additional parameters to the callback functions registered 
	 * for the event.
	 *
	 * @param string $eventname The name of the event to be triggered.
	 * @param array $data An array of data to be passed to the listener 
	 *                    callbacks.
	 * @param mixed ...$otherdata Additional data to be passed to the 
	 *                            listener callbacks.
	 * @return $this The current instance for method chaining.
	 */
	public function trigger($eventname, $result = null, ...$data) {
        $eventname = strtolower($eventname);
        if (isset($this->listeners['listenersids']) && isset($this->listeners['listenersids'][$eventname])) {
			foreach ($this->listeners['listenersids'][$eventname] as $key => $listenerid) {
				if (isset($this->listeners['listeners'][$listenerid]) && is_callable($this->listeners['listeners'][$listenerid])) {
					$callback = $this->listeners['listeners'][$listenerid];
					call_user_func($callback, $result, ...$data);
				} else {
					unset($this->listeners['listenersids'][$eventname][$key]);
				}
			}
		}
		return $result;
	}

    /**
         * Checks if a listener is registered for a given event.
         * 
         * This method verifies whether an event listener exists for the specified event name.
         * It returns `true` if at least one listener is registered, otherwise `false`.
         *
         * Example usage:
         * if ($dotapp->hasListener("user.registered")) {
         *     echo "A listener is registered for this event.";
         * }
         *
         * @param string $eventname The name of the event to check.
         * @return bool Returns `true` if the event has a registered listener, otherwise `false`.
    */

    public function hasListener($eventname) {
        if (isset($this->listeners['listenersids']) && isset($this->listeners['listenersids'][$eventname]) ) return true;
        return false;
    }


	/**
	 * Protects variables against SQL injection and XSS attacks by 
	 * replacing certain characters with safe alternatives. This method 
	 * can handle both single variables and arrays recursively.
	 *
	 * Example usage:
	 * $dotapp->protect($input);
	 *
	 * @param mixed &$vstup The variable (or array) to be protected. 
	 *                      The variable is passed by reference to 
	 *                      allow modifications directly.
	 * @return $this The current instance for method chaining.
	 */
	function protect(&$vstup) {
		if (is_array($vstup)) {
			foreach ($vstup as $name => $hodnota) {
				if (!is_array($vstup[$name])) {
					$vstup[$name] = $this->protect_data($vstup[$name]);
				} else {
					$this->protect($vstup[$name]);
				}
			}
		} else if (isSet($vstup) && is_string($vstup)) {
            $vstup = $this->protect_data($vstup);
        }
		return $this;
	}

	/**
	 * Removes the protection from variables, reverting them back to 
	 * their original form. This method is intended to be used after 
	 * data has been processed and needs to be displayed or used 
	 * in its original state.
	 *
	 * Example usage:
	 * $dotapp->unprotect($input);
	 *
	 * @param mixed &$vstup The variable (or array) to be unprotected. 
	 *                      The variable is passed by reference to 
	 *                      allow modifications directly.
	 * @return $this The current instance for method chaining.
	 */
	function unprotect(&$vstup) {
        if (isSet($vstup) && is_array($vstup)) {
            foreach ($vstup as $name => $hodnota) {
                if (!is_array($vstup[$name])) {
                    $vstup[$name] = $this->unprotect_data($vstup[$name]);
                } else {
                    $this->unprotect($vstup[$name]);
                }
            }
        } else if (isSet($vstup) && is_string($vstup)) {
            $vstup = $this->unprotect_data($vstup);
        }
		return $this;
	}

	/**
	 * Protects a given piece of data by replacing certain characters 
	 * with their corresponding HTML entity codes. This is crucial 
	 * for preventing XSS attacks and ensuring data integrity 
	 * when storing user input.
	 *
	 * @param string $data The data to be protected.
	 * @return string The protected data with special characters 
	 *                replaced by safe alternatives.
	 */
	function protect_data($data) {
		$map = [
            '#' => '&#35;',
            '\\x' => '&#92;x',
            '\\' => '&#92;',            
            '$' => '&#36;',
            '"' => '&#34;',
            "'" => '&#39;',
            ',' => '&#44;',
            ';' => '&#59;',
            '%' => '&#37;',
            '*' => '&#42;',
            '<' => '&#60;',
            '=' => '&#61;',
            '>' => '&#62;',
            '(' => '&#40;',
            ')' => '&#41;',
            '&' => '&#38;',
            '^' => '&#94;',
            '`' => '&#96;',
            '~' => '&#126;',
            '!' => '&#33;',
            '{' => '&#123;',
            '}' => '&#125;',
        ];
        

		$data = strtr($data, $map);
		$data = addslashes($data);
		return $data;
	}

	/**
	 * Reverts a protected variable back to its original form. This 
	 * method is particularly useful when displaying data that 
	 * was previously stored in a protected format, such as HTML code.
	 * It ensures that the data is safe for output while retaining 
	 * its original meaning.
	 *
	 * Example usage:
	 * $originalData = $dotapp->unprotect_data($protectedData);
	 *
	 * @param string $data The protected data to be unprotected.
	 * @param int $plus Optional parameter to modify spaces to plus 
	 *                  signs if set to 1.
	 * @return string The unprotected data in its original form.
	 */
	function unprotect_data($data, $plus = 0) {
		$search = [
			"&#35;", "&#36;", "&#34;", "&#39;", "&#44;", "&#59;", 
			"&#37;", "&#42;", "&#60;", "&#61;", "&#62;", "&#40;", 
			"&#41;", "&#38;", "&#94;", "&#96;", "&#126;", "&#33;", 
			"&#92;", "&#123;", "&#125;"
		];
		
		$replace = [
			"#", "$", '"', "'", ",", ";", 
			"%", "*", "<", "=", ">", "(", 
			")", "&", "^", "`", "~", "!", 
			"\\", "{", "}"
		];

		$data = str_replace($search, $replace, $data);

		if ($plus == 1) {
			$data = str_replace(" ", "+", $data);
		}

		return $data;
	}

	
	/**
	 * Escapes special characters in a string by converting them to 
	 * hexadecimal representation, except for alphanumeric characters 
	 * and some selected characters (single quote, double quote, 
	 * and backslash). This is useful for safely handling user input 
	 * or data that may include special characters.
	 *
	 * @param string $value The input string to escape.
	 * @return string The escaped string with special characters 
	 *                represented in hexadecimal format.
	 */
	function escape($value) {
		$return = '';
		for($i = 0; $i < strlen($value); ++$i) {
			$char = $value[$i];
			$ord = ord($char);
			// Only keep certain characters and escape others
			if($char !== "'" && $char !== "\"" && $char !== '\\' && $ord >= 32 && $ord <= 126) {
				$return .= $char;
			} else {
				$return .= '\\x' . dechex($ord);
			}
		}
		return $return;
	}

	/**
	 * Automatically loads all modules present in the application 
	 * by scanning the "modules" directory and including their 
	 * initialization scripts. It also loads the current language 
	 * settings for modules that support localization.
	 *
	 * Example usage:
	 * $dotapp->load_modules();
	 *
	 * @return $this The current instance for method chaining.
	 */
	function load_modules() {
        if ($this->hasListener("dotapp.load_modules.override")) {
            $this->trigger("dotapp.load_modules.override",$this,__ROOTDIR__."/app/modules/");
        } else {
            // Optimalizovanie nacitavanie, vhodne pri velkom mnosztve modulov...
            if (file_exists(__ROOTDIR__."/app/modules/modulesAutoLoader.php") && !defined("__DOTAPPER_RUN__")) {
                $dotapp = $this; // Reference to the current instance
                $izolovane = function() use ($dotapp) {
                    define('__DOTAPP_MODULES_AUTOLOADER__',1);
                    include(__ROOTDIR__."/app/modules/modulesAutoLoader.php");                    
                    $nacitaj = [];
                    foreach ($modules as $modul => $routes) {
                        $matched = false;
                        foreach ($routes as $route) {
                            if ($route === "*") {
                                $matched = true;
                                break;
                            } else {
                                if ($dotapp->router->match_url($route) !== false) {
                                    $matched = true;
                                    break;
                                }
                            }
                        }

                        if ($matched === true) {
                            $nacitaj[] = $modul;
                            $this->load_module_listeners($modul);
                        }                        
                    }

                    define('__DOTAPP_MODULES_CAN_LOAD__',1);
                    foreach ($nacitaj as $modul) {
                        $this->load_module($modul);
                    }
                    $this->thendata = ""; // Clear thendata after loading modules
                    /*  Ak boli nacitane vsetky moduly. Moze sa hodit ak sa modul ptorebuje rozhodnut ci on vygeneruje korenovy router
                        Napriklad na dotapp.modules.loaded si modul overi ci uz je obsadena routa napriklad get / a ak nie je, sam ju vygeneruje alebo presmeruje.
                    */
    
                    $dotapp->trigger("dotapp.modules.loaded",$this->module_asked);
                };
                $izolovane();
            } else {
                $moduly = glob(__ROOTDIR__."/app/modules/*", GLOB_ONLYDIR); // Get all module directories
                $dotapp = $this; // Reference to the current instance
                foreach ($moduly as $modul) {
                    $this->load_module_listeners($modul);
                }
                
                define('__DOTAPP_MODULES_CAN_LOAD__',1);
                foreach ($moduly as $modul) {
                    $this->load_module($modul);
                }
                $this->thendata = ""; // Clear thendata after loading modules
                /*  Ak boli nacitane vsetky moduly. Moze sa hodit ak sa modul ptorebuje rozhodnut ci on vygeneruje korenovy router
                    Napriklad na dotapp.modules.loaded si modul overi ci uz je obsadena routa napriklad get / a ak nie je, sam ju vygeneruje alebo presmeruje.
                */

                $dotapp->trigger("dotapp.modules.loaded",$this->module_asked);
            }
        }
        if ($this->defaultRoutes === false ) $this->defaultRoutes = new Routes($this);
		return $this; // Return the current instance for method chaining
	}

    function load_module($modul) {
        $modul = str_replace(__ROOTDIR__."/app/modules/","",$modul);
        $moduleName = $modul;
        if (in_array($modul,$this->module_asked)) {
            return false;
        }
        $dotapp = $this;
        $dotApp = $this;
        $DotApp = $this;
        $this->module_asked[] = $modul;
        $modul = __ROOTDIR__."/app/modules/".$modul;
        $modulinit = $modul.'/module.init.php'; // Path to module initialization script
        $modullng = $modul.'/translations/'.$this->lang.'.php'; // Path to module language file
        $modullng2 = $modul.'/Translations/'.$this->lang.'.php'; // Path to module language file
            
        // Include the initialization script if it exists and was not used before
        $modulClassName = "Dotsystems\\App\\Modules\\".$moduleName."\\Module";
        if (!class_exists($modulClassName,false)) {
            if (file_exists($modulinit)) {
                include $modulinit;
            }
        } else {
            new $modulClassName($this);
        }        
                
        // Include the language file if it exists
        if (file_exists($modullng)) {
            include $modullng;
        }

        if (file_exists($modullng2)) {
            include $modullng2;
        }
    }

    function load_module_listeners($modul) {
        $modul = str_replace(__ROOTDIR__."/app/modules/","",$modul);
        $modulinit = __ROOTDIR__."/app/modules/".$modul.'/module.listeners.php'; // Aktivujeme listeners
      
        // Include the initialization script if it exists
        if (file_exists($modulinit)) {
            $dotapp = $this;
            $dotApp = $this;
            $DotApp = $this;
            include $modulinit;
        }
    }

	/**
	 * Removes all non-alphanumeric characters from a string using 
	 * a regular expression. This can be useful for sanitizing 
	 * input or ensuring that a string contains only letters and 
	 * numbers.
	 *
	 * @param string $inputString The input string to be sanitized.
	 * @return string The sanitized string containing only 
	 *                alphanumeric characters.
	 */
	function removeNonAlphanumeric($inputString) {
		return preg_replace('/[^a-zA-Z0-9]/', '', $inputString); // Replace non-alphanumeric characters with an empty string
	}

	
	/**
	 * Performs a CRC (Cyclic Redundancy Check) validation on the 
	 * received data in API requests. It verifies the integrity of 
	 * the data by comparing the calculated CRC value on the server 
	 * side with the CRC value sent from the client side. If the 
	 * values match, it indicates that the data has not been tampered 
	 * with.
	 *
	 * This function is recommended for enhancing security against 
	 * manipulation of data such as IDs in API requests.
	 *
	 * Usage on the client side (JavaScript):
	 * 
	 * var data = {};
	 * data['f'] = "article-delete";
	 * data['data-id'] = $(button).attr("data-id");
	 * 
	 * $(btnloader).load('/Modules/dotcms/api/v1/', {
	 *     data: Object.assign({}, data),
	 *     g_datah: g_datah(data) // Calls the function to calculate CRC
	 * });
	 *
	 * On the server side (PHP), the function is used as follows:
	 * 
	 * if ($this->dotapp->crc_check($_SESSION['module_users_ckey'], $_POST['g_datah'], $_POST['data'])) {
	 *     // Data is valid and can be processed
	 * } else {
	 *     // Data manipulation detected
	 *     SELF::something_wrong();
	 * }
	 *
	 * @param string $key The session key used for CRC generation.
	 * @param string $crc The CRC value sent from the client side.
	 * @param mixed $data The data to validate, typically from $_POST.
	 * @return int Returns 1 if the CRC matches; otherwise, returns 0.
	 */
	public function crc_check($key, $crc, $data) {
		$resultString = $this->generateOrderedJson($data);
		$resultString = preg_replace('/[^\x00-\x7F]/', '', $resultString);
		$resultString = $key . $resultString;		
		$resultString = $this->unprotect_data($resultString);
		$resultString = trim($this->removeNonAlphanumeric($resultString));
        $resultMd5 = md5($resultString);

		$this->debug_data("crc_check","crc serverside: ".$resultMd5." crc js side: ".$crc);
		$this->debug_data("crc_check_strings","server string: ".$resultString);
		
		if ($crc == $resultMd5) {
			return 1; // CRC is valid
		}
		return 0; // CRC is invalid
	}

	/**
	 * Generates a JSON string from the given data array with 
	 * ordered keys to ensure consistent CRC calculations. The 
	 * ordering of keys is crucial for accurate CRC validation.
	 *
	 * @param mixed $data The data to be converted to JSON format.
	 * @return string A JSON string representation of the ordered data.
	 */
	function generateOrderedJson($data) {

		$orderFn = function($obj) use (&$orderFn) {
			if (!is_array($obj)) {
				return $obj;
			}

			ksort($obj);

			foreach ($obj as $key => $value) {
				$obj[$key] = $orderFn($value);
			}

			return $obj;
		};

		$orderedData = $orderFn($data);
		$jsonString = json_encode($orderedData, JSON_UNESCAPED_UNICODE);
		return $jsonString;
	}

	/**
	 * Sorts the keys of an array in place, recursively sorting 
	 * any nested arrays. This function is useful for ensuring 
	 * consistent key ordering when generating CRCs or 
	 * processing data.
	 *
	 * @param array &$array The array to sort by keys.
	 */
	function array_key_sort(&$array) {
		ksort($array);
		foreach ($array as &$value) {
			if (is_array($value)) {
				$this->array_key_sort($value);
			}
		}
	}

	
	/* Sluzi ak debugujeme kolko pamate zabral skript a podobne */
	public function formatBytes($bytes, $precision = 2) {
		$units = ['B', 'KB', 'MB', 'GB', 'TB'];

		$bytes = max($bytes, 0);
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024));
		$pow = min($pow, count($units) - 1);

		$bytes /= pow(1024, $pow);

		return round($bytes, $precision) . ' ' . $units[$pow];
	}
	
	/**
	 * Sends a standardized AJAX reply with the given data. The 
	 * function converts the data to JSON format, encodes it in 
	 * base64, and includes execution statistics if debugging is 
	 * enabled. The response contains a status code indicating 
	 * success (status = 1) or failure (status = 0). If the 
	 * operation fails, an error message is also included for 
	 * debugging purposes.
	 *
	 * This approach allows for uniform handling of API responses, 
	 * making it easy to debug and modify scripts in JavaScript.
	 *
	 * Example usage in JavaScript:
	 * 
	 * try {
	 *     var odpoved = obj2array(JSON.parse(atob($(api_html_odpoved))));
	 *     if (isSet(odpoved)) {
	 *         if (odpoved['status'] == 1) {
	 *             // All OK, proceed with logic
	 *         } else {
	 *             if (isSet(odpoved['status_txt'])) alert(odpoved['status_txt']);
	 *         }
	 *     }
	 * } 
	 *
	 * @param mixed $data The data to be returned in the AJAX response.
	 * @param int $status Optional HTTP status code. Defaults to 0.
	 * @return string Base64-encoded JSON string containing the response data.
	 */

    public function ajaxReply($data, $status = 0) {
        return $this->ajax_reply($data, $status);
    }

	public function ajax_reply($data, $status = 0) {
		if ($status > 0) {
            http_response_code($status);
            $this->request->response->status = $status;
        }
		global $start_time;
		global $memoryStart;
		
		$end_time = microtime(true);
		$execution_time = ($end_time - $start_time);
		
		$memoryEnd = memory_get_usage();
		$memoryUsage = $memoryEnd - $memoryStart;
        
        if (__DEBUG__) {
		// Adding memory and execution time to the response data
		$data['memoryusage'] = "Memory used by the script: " . $this->formatBytes($memoryUsage);
		$data['memoryusage_peak'] = "Peak memory usage: " . $this->formatBytes(memory_get_peak_usage());
		$data['execution_time'] = $execution_time . " s";

		// Add debug data if in debug mode
		
			if (is_array($this->debug_data)) {
				if (isSet($data['debug'])) {
					$dmem = $data['debug'];
					$data['debug'] = array();
					$data['debug']['debug'] = $dmem;
				}
				foreach ($this->debug_data as $dkey => $dval) {
					$data['debug'][$dkey] = $dval;
				}
			}
		}

		// Encode the data to JSON and then to base64
		return base64_encode(json_encode($data));
	}

	/**
	 * Adds debug data to the AJAX response. This function stores 
	 * debug information that can be returned in the response when 
	 * debugging is enabled. When debugging is turned off, this 
	 * information will not be sent.
	 *
	 * @param string $key The key for the debug data.
	 * @param mixed $data The debug data to store.
	 */
	public function debug_data($key, $data) {
		$this->debug_data[$key] = $data;
	}

	/**
	 * Recursively converts all keys in an associative array to 
	 * lowercase. This function is useful for ensuring consistent 
	 * key casing when processing data from various sources.
	 *
	 * @param array $array The array whose keys will be converted to lowercase.
	 * @return array The array with all keys converted to lowercase.
	 */
	public function lowercase_arraykeys($array) {
		if (!is_array($array)) {
			return $array;
		}

		$lowercasedArray = array();
		foreach ($array as $key => $value) {
			$lowercasedArray[strtolower($key)] = $this->lowercase_arraykeys($value);
		}

		return $lowercasedArray;
	}

	
	/*
    Generates a key that is subsequently used for 
    encrypting and decrypting data. If a valid encryption key 
    already exists, it will use that key; otherwise, it 
    generates a new random key.
	*/
	function generate_enc_key() {
		if ($this->dsm->get('_enc_key') != null && strlen($this->dsm->get('_enc_key')) > 30) {
			$this->enc_key = $this->dsm->get('_enc_key');
			// Key is OK
		} else {
			$this->enc_key = bin2hex(openssl_random_pseudo_bytes(128));
			$this->dsm->set('_enc_key', $this->enc_key);
		}
		return $this;
	}

	/*
		Encrypts an array of data. This function works similarly 
		to the encrypt function but accepts an array as input, 
		converting it to a JSON string before encryption.
		
		Parameters:
		- $array: An array of data to be encrypted.
		- $key2: An optional additional key for encryption.
		
		Returns:
		- A base64-encoded string containing the IV and encrypted data.
	*/
	function encrypta($array, $key2 = "") {
		$text = json_encode($array);
		return $this->encrypt($text, $key2);
	}

    public function subtractKey(string $encoded, string $key) {
        try {
            $productStr = base64_decode($encoded, true);
            if ($productStr === false) {
                return false;
            }
            $product = gmp_init($productStr, 10);
            $base64Key = base64_encode($key);
            $hexKey = '';
            for ($i = 0; $i < strlen($base64Key); $i++) {
                $hexKey .= sprintf('%02x', ord($base64Key[$i]));
            }
            $bigKey = gmp_init($hexKey, 16);
            $bigStr = gmp_div($product, $bigKey);
            $hexStr = gmp_strval($bigStr, 16);
            $hexStr = strlen($hexStr) % 2 === 0 ? $hexStr : '0' . $hexStr;
            $base64Str = '';
            for ($i = 0; $i < strlen($hexStr); $i += 2) {
                $byte = hexdec(substr($hexStr, $i, 2));
                $base64Str .= chr($byte);
            }
            $decoded = base64_decode($base64Str, true);
            return $decoded !== false ? $decoded : false;
        } catch (\Exception $e) {
            return false;
        }
    }

	/*
		Encrypts a string of data using a composite key. The 
		encryption key is made up of a predefined static part 
		(__ENC_KEY__), a randomly generated key from generate_enc_key(), 
		and an optional additional key ($key2). This helps prevent 
		value substitution attacks when multiple IDs are encrypted 
		with the same key.
		
		Parameters:
		- $text: The plaintext string to encrypt.
		- $key2: An optional additional key for encryption.
		
		Returns:
		- A base64-encoded string containing the IV and encrypted data.
	*/
	function encrypt($text, $key2 = "", $onlykey2 = false) {
		$key2 = $this->key2_upgrade($key2);
		$userkey = "";
		
		if (isset($this->c_enc_key)) {
			$userkey = $this->c_enc_key;
            $userkey = hash('sha256',$userkey);
		}
		
		$key = $userkey . $this->enc_key . $key2;
        if ($onlykey2 === true) $key = hash('sha256',$userkey.$key2);
		$key = hash('sha256', $key, true);
		$cipher = "aes-256-cbc";
		$ivlen = openssl_cipher_iv_length($cipher);
		$iv = openssl_random_pseudo_bytes($ivlen);
		$encrypted = openssl_encrypt($text, $cipher, $key, 0, $iv);
		
		return base64_encode($iv . $encrypted);
	}

	/*
		Decrypts the encrypted data if the correct key is provided. 
		If the key is incorrect, it returns false.
		
		Example usage:
		- $id_webu = $this->dotapp->encrypt(45, "dotcms.websites.id");
		- $this->dotapp->decrypt($id_webu, "dotcms.websites"); // Returns false due to incorrect key2
		- $this->dotapp->decrypt($id_webu, "dotcms.websites.id"); // Returns 45
		
		Parameters:
		- $text: The base64-encoded string containing the IV and encrypted data.
		- $key2: An optional additional key used for decryption.
		
		Returns:
		- The decrypted plaintext string, or false if decryption fails.
	*/
	function decrypt($text, $key2 = "", $onlykey2 = false) {
		$text = $this->unprotect_data($text, 1);
		$key2 = $this->key2_upgrade($key2);
		$userkey = "";
		
		if (isset($this->c_enc_key)) {
			$userkey = $this->c_enc_key;
            $userkey = hash('sha256',$userkey);
		}
		
		$key = $userkey . $this->enc_key . $key2;
        if ($onlykey2 === true) $key = hash('sha256',$userkey.$key2);
		$key = hash('sha256', $key, true);
		$cipher = "aes-256-cbc";
		$text = base64_decode($text);
		$ivlen = openssl_cipher_iv_length($cipher);
		$iv = substr($text, 0, $ivlen);
		
		if (strlen($iv) !== $ivlen) {
			return false;
		}
		
		$encrypted = substr($text, $ivlen);
		$decrypted = openssl_decrypt($encrypted, $cipher, $key, 0, $iv);
		
		if ($decrypted === false) {
			return false;
		}
		
		/*
			Occasionally, a minor code change may result in a 
			decryption failure that returns unexpected characters. 
			Therefore, we check if the decrypted text consists 
			solely of valid characters; if not, we return false as well.
		*/
		if (!preg_match('/^[\p{L}\p{N}\p{P}\p{S}\s]+$/u', $decrypted)) {
			return false;
		}
		
		return $decrypted;
	}

	
	/*
		To iste ako decrypt ale sluzi na desifrovanie pola zasifrovaneho pomocou encrypta()
	*/
	
	function decrypta($text,$key2="") {
		$text = $this->unprotect_data($text,1);
		$dekryptovane = $this->decrypt($text,$key2);
		if ($dekryptovane === false) return($dekryptovane);
		return(json_decode($dekryptovane,true));
	}
	
	/*
		Sluzi len na zosilnenie bezpecnosti sifrovacieho kluca
	*/
	function key2_upgrade($key2="") {
		$key2upgrade = "";
        $add_key = md5($key2);
        $add_key2 = hash('sha256',$key2.$add_key);
        $add_key2 = hash('sha256',$key2.$add_key.$add_key2);
		if ($key2 != "") $key2upgrade = md5($add_key.$key2.$add_key2).md5($key2.$add_key.$add_key2).md5($key2.$add_key2.$add_key);
		return($key2upgrade);
	}
	
	/*
		Kontroluje ci zadany vstup je platny JSON
	*/
	
	function is_json($string) {
		json_decode($string);
		return (json_last_error() == JSON_ERROR_NONE);
	}
	
	/* 
		Normalizuje znaky. Takze prevedie nemecke znaky cinske znaky a podobne na ascii ekvivalenty.
		Vhodne na odstranenie diakritiky pri generovani aliasu.
	*/
	
	function normalize_string($text) {
		$transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII; NFD', $text);
		$normalized = normalizer_normalize($transliterated, \Normalizer::FORM_KD);
		$normalized = preg_replace('/[^a-zA-Z0-9\-]/', ' ', $normalized);
		return($normalized);
	}
	
	/*
		Vytvori ALIAS na zaklade daneho vstupneho textu.
	*/
	
	function create_alias($text) {
		$alias = $this->normalize_string($text);
		$alias = strtolower($alias);
		$alias = str_replace(" ","-",$alias);
		$alias = str_replace("--","-",$alias);
		$alias = str_replace("--","-",$alias);
		$alias = str_replace("--","-",$alias);
		$alias = trim($alias, '-');
		return $alias;
	}
	
	
	/*
		Navrhne silne heslo pozadovanej dlzky $length
	*/
    function generateStrongPassword($length,$allCharacters="") {
        return $this->generate_strong_password($length,$allCharacters);
    }

	function generate_strong_password($length,$allCharacters="") {
		$startfrom = 1;
        $password = '';
		if ($allCharacters == "") {
			$uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$lowercase = 'abcdefghijklmnopqrstuvwxyz';
			$numbers = '0123456789';
			$specialCharacters = '!@#$%^&*()-_=+[]{}|;:,.<>?';			
			$allCharacters = $uppercase . $lowercase . $numbers . $specialCharacters;

			$password = '';
			$password .= $uppercase[rand(0, strlen($uppercase) - 1)];
			$password .= $lowercase[rand(0, strlen($lowercase) - 1)];
			$password .= $numbers[rand(0, strlen($numbers) - 1)];
			$password .= $specialCharacters[rand(0, strlen($specialCharacters) - 1)];
			$startfrom = 4;
		}		
		
		for ($i = $startfrom; $i < $length; $i++) {
			$password .= $allCharacters[rand(0, strlen($allCharacters) - 1)];
		}
		
		$password = str_shuffle($password);
		
		return $password;
	}

    /**
     * Enhances the strength of a password by applying a key upgrade.
     * This function is used to further secure the password before hashing.
     *
     * @param string $pass The password to be made stronger.
     * @return string The upgraded (more secure) password.
     */
    private function makePasswordStrongerAgain($pass) {
        /* Vnutro bolo odstranene aby nebolo naviazane na kluc a pri zmene zabezpecenia nedoslo k*/
        $pass = $this->key2_upgrade($pass);
        return $pass;
    }

    /**
     * Generates a password hash using bcrypt.
     *
     * @param string $pass The password to be hashed.
     * @return string The generated password hash.
     */
    public function generatePasswordHash($pass) {
        $passhash = password_hash($this->makePasswordStrongerAgain($pass), PASSWORD_BCRYPT, ['cost' => 8]);
        return($passhash);
    }

    /**
     * Verifies that a password matches a hash using bcrypt.
     *
     * @param string $pass The password to be verified.
     * @param string $hash The hash to compare the password against.
     * @return bool Returns true if the password matches the hash, false otherwise.
     */
    public function verifyPassword($pass, $hash) {
        return password_verify($this->makePasswordStrongerAgain($pass), $hash);
    }
	
	/*
		Ak potrebujeme spravit POST request. Napriklad nechceme riesit CORS ale potrebujeme nieco spustit.
		Tak postneme nejaky kluc a spustime URL.

		V dotappe vyuzivam pri synchronizacii udajov medzi 2 aplikaciami ktore bezia na inom serveri.
	*/
    /**
     * Sends a POST request to a specified URL with the given data.
    *
    * This function uses curl to send a POST request to the specified URL
    * with the provided data. It returns the response from the URL or false
    * if an error occurred during the request.
    *
    * @param string $url The URL to send the POST request to.
    * @param array $postData An associative array of data to send in the POST request.
    * @return mixed The response from the URL on success, or false on failure.
    */
    function post_request($url, $postData) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            return(false);
        }

        curl_close($ch);
        return $response;
    }
	
	/*
		Aby som si bol isty ze nejaka URl je zarucene v spravnom formate... 
		Tak pouzijeme tuto funckiu.
	*/
	
	function repair_url($url) {
        // Odstráni výkričníky a nahradí viacnásobné lomítka jedným (okrem ://)
        $url = preg_replace(['/!+/', '/(?<=\w)\/+/'], ['', '/'], $url);
        return $url;
    }
	
}

?>