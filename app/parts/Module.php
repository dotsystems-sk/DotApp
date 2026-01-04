<?php

/**
 * Abstract class MODULE
 * 
 * This abstract class serves as a base for creating modules within the DotApp framework. 
 * It provides the foundational structure and methods that all modules must implement, 
 * promoting consistency and reusability across different modules. 
 * 
 * By extending this class, developers can create custom modules that integrate seamlessly 
 * into the DotApp architecture, leveraging its core functionalities while adding their own 
 * specific behaviors and features.
 * 
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.8 FREE
 * @license   MIT License
 * @date      2014 - 2026
 * 
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the 
 * following condition: You **must** retain this header in all copies or 
 * substantial portions of the code, including the author and company information.
 */

/*
    Module Class Usage:

    The `module` class is an abstract class that provides a blueprint for all modules 
    within the DotApp framework. Any class extending this abstract class must implement 
    its abstract methods, ensuring that all modules adhere to the required structure.

    Example of a derived class:
    - `class module_<MODULENAME> extends \Dotsystems\App\Parts\module`
    
    Key Features:
    - Defines essential methods that all modules must implement.
    - Facilitates the creation of reusable and consistent modules.
    - Serves as a foundation for module-specific logic and behavior.

    This abstraction allows developers to build powerful extensions for the 
    DotApp framework while maintaining a standardized approach to module development.
*/

namespace Dotsystems\App\Parts;
use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\DI;

abstract class Module {
    use StaticGetSet;
    public const IF_NOT_EXIST = 1;
    public const DELETE = 2;
	/*
		Namiesto INTERFACE ideme do abstract triedy, lebo potrebujeme premenne
	*/
    private static $staticDI;
	private $path;
	public $dotapp;
    public $dotApp;
    public $DotApp;
    public $di;
    public $DI; // Alias pre di, blbuvzdornost.
    public $call; // Alias pre di, blbuvzdornost.
    public $Call; // Alias pre di, blbuvzdornost.
	public $modulename;
	private $moduledata;
    public $initialized;
    private $assetsLoaded;
    protected static $staticModuleName;
    protected static $staticModuleNameLock = false;
    private $settingsCache = null; // Cache for settings to reduce I/O operations
    private $settingsLoaded = false; // Flag to track if settings are loaded
	//public $menu; /* Ci ma modul svoje vlastne menu alebo nie. 0 - Nie, 1 - Ano */
	
	function __construct($dotapp, $optimalizacia = false) {
        $this->initialized = false;
		$this->dotapp = $dotapp;
        $this->dotApp = $this->dotapp;
        $this->DotApp = $this->dotapp;
        $classname = get_class($this);
        $this->assetsLoaded = false;
        $classname = str_replace("Dotsystems\\App\\Modules\\", "", $classname);
		$classnamea = explode("\\", $classname);
		$classname = $classnamea[0];
		$classname = str_replace("module_", "", $classname);
		$this->modulename = $classname;
        static::moduleName($this->modulename);
		$this->path = __ROOTDIR__ . "/app/modules/" . $classname;        
        // Ukoncime nacitavanie dalej
        if ($optimalizacia === true || defined("__DOTAPPER_OPTIMIZER__") || !defined("__DOTAPP_MODULES_CAN_LOAD__")) return;

		$this->di = new DI($this, $dotapp);
        $this->DI = $this->di; // Alias pre di, blbuvzdornost.
        static::$staticDI = $this->di;
        $this->call = $this->di; // Alias pre di, blbuvzdornost.
        $this->Call = $this->di; // Alias pre di, blbuvzdornost.
        $this->installation();
		$dotapp->module_add($this->modulename, $this->di);
        $dotapp->trigger("dotapp.module." . $this->modulename . ".init.start", $this);
        if ($this->initializeConditionAndListener() || defined('__DOTAPPER_RUN__')) {
            $this->dotapp->dotapper['routes_module'] = $this->modulename;
            $this->load();
        }
        $dotapp->trigger("dotapp.module." . $this->modulename . ".init.end", $this);
	}

    public static function optimize() {
        try {
            define('__DOTAPPER_OPTIMIZER__', 1);
            $moduly = glob(__ROOTDIR__ . "/app/modules/*", GLOB_ONLYDIR); // Get all module directories
            $routyModulov = [];
            foreach ($moduly as $modul) {
                $modulinit = $modul . '/module.init.php';
                $modulName = str_replace("\\", "/", $modul);
                $modulName = explode("/", $modulName);
                $modulName = $modulName[count($modulName) - 1];
                if (file_exists($modulinit)) {
                    $className = "Dotsystems\\App\\Modules\\" . $modulName . "\\Module";
                    if (!class_exists($className, false)) {
                        include $modulinit;
                    }
                    if (!class_exists($className, false)) {
                        throw new \RuntimeException("Module class $className not found");
                    }
                    $objekt = new $className(null, true);
                    $routes = $objekt->initializeRoutes();
                    if (!is_array($routes) || array_filter($routes, fn($item) => !is_string($item)) !== []) {
                        throw new \InvalidArgumentException("initializeRoutes() must return a one-dimensional array of strings in module {$objekt->modulename}");
                    }
                    $routyModulov[$modulName] = $routes;
                }
            }
            file_put_contents(__ROOTDIR__ . "/app/modules/modulesAutoLoader.php", "<?php\n\$modules = " . var_export($routyModulov, true) . ";\n ?>");
            return true;
        } catch (\Exception $e) {
            return $e;
        }
    }

    /**
     * Retrieves or sets settings for the module.
     *
     * This function allows you to either retrieve module settings, update them, or delete a specific setting, using an in-memory cache to reduce I/O operations.
     * - If an array is provided as input, it updates the entire settings file and cache with the provided array.
     * - If a string key and value are provided, it updates a specific setting in the cache and file based on the mode.
     * - If a string key and mode is DELETE, it removes the specific setting from the cache and file.
     * - If a string is provided, it returns the value of that specific setting from the cache.
     * - If no input is provided (null), it returns all settings from the cache as an array.
     *
     * @param string|array|null $input The setting key (string), an array of settings to update, or null to retrieve all settings.
     * @param mixed $value Optional value to set for a specific key (used when $input is a string and $mode is 0 or 1).
     * @param int $mode Optional mode to control setting behavior:
     *                  - 0: Set the value unconditionally (default).
     *                  - Module::IF_NOT_EXIST (1): Set the value only if the key does not exist.
     *                  - Module::DELETE (2): Delete the specified key from settings.
     *
     * @return mixed|bool|null If updating or deleting settings, returns true on success, false on failure.
     *                        If retrieving a specific setting (string input), returns the value of the setting or null if not found.
     *                        If retrieving all settings (null input), returns an associative array of all settings or an empty array if no settings exist.
     *
     * ### Usage examples:
     *
     * // GET: Retrieve all settings
     * $module->settings();
     *
     * // GET: Retrieve a specific setting
     * $module->settings("enable2FA");
     *
     * // SET: Set value unconditionally
     * $module->settings("enable2FA", true);
     *
     * // SET-IF-NOT-EXISTS: Only set if the value is not already defined
     * $module->settings("maxLoginAttempts", 5, Module::IF_NOT_EXIST);
     *
     * // DELETE: Remove a specific setting
     * $module->settings("enable2FA", null, Module::DELETE);
     *
     * // Example for IF_NOT_EXIST:
     * // If settings already have: "uploadLimit" => 700
     * $module->settings("uploadLimit", 100, Module::IF_NOT_EXIST);
     * // Result: The value remains 700 because it was already defined — the new value 100 is ignored.
     *
     * // Example for DELETE:
     * $module->settings("uploadLimit", null, Module::DELETE);
     * // Result: The "uploadLimit" key is removed from settings.
     */
    public function settings($input = null, $value = null, $mode = 0) {
        $settingsFile = $this->path . "/settings.php";

        // Load settings into cache if not already loaded
        if (!$this->settingsLoaded && file_exists($settingsFile)) {
            $settings = include $settingsFile;
            $this->settingsCache = is_array($settings) ? $settings : [];
            $this->settingsLoaded = true;
        } elseif (!$this->settingsLoaded) {
            $this->settingsCache = [];
            $this->settingsLoaded = true;
        }

        // Setter: If input is an array, update the entire settings file and cache
        if (is_array($input)) {
            $this->settingsCache = $input;
            $content = "<?php\nreturn " . var_export($input, true) . ";\n?>";
            try {
                file_put_contents($settingsFile, $content);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }

        // Handle string input for setting, deleting, or getting a specific key
        if (is_string($input)) {
            // DELETE mode: Remove the specified key
            if ($mode === self::DELETE) {
                if (isset($this->settingsCache[$input])) {
                    unset($this->settingsCache[$input]);
                    $content = "<?php\nreturn " . var_export($this->settingsCache, true) . ";\n?>";
                    try {
                        file_put_contents($settingsFile, $content);
                        return true;
                    } catch (\Exception $e) {
                        return false;
                    }
                }
                return true; // Key doesn't exist, so deletion is effectively successful
            }

            // Setter: Update a specific setting
            if ($value !== null) {
                if ($mode === self::IF_NOT_EXIST) {
                    if (!isset($this->settingsCache[$input])) {
                        $this->settingsCache[$input] = $value;
                        $content = "<?php\nreturn " . var_export($this->settingsCache, true) . ";\n?>";
                        try {
                            file_put_contents($settingsFile, $content);
                            return true;
                        } catch (\Exception $e) {
                            return false;
                        }
                    }
                    return isset($this->settingsCache[$input]) ? $this->settingsCache[$input] : null;
                } else {
                    $this->settingsCache[$input] = $value;
                    $content = "<?php\nreturn " . var_export($this->settingsCache, true) . ";\n?>";
                    try {
                        file_put_contents($settingsFile, $content);
                        return true;
                    } catch (\Exception $e) {
                        return false;
                    }
                }
            }

            // Getter: Return the specific setting from cache
            return isset($this->settingsCache[$input]) ? $this->settingsCache[$input] : null;
        }

        // Getter: If input is null, return all settings from cache
        return $this->settingsCache;
    }

    public static function moduleName($name = null) {
        if ($name === null) {
            return static::$staticModuleName;
        } else {
            if (static::$staticModuleNameLock === false) {
                static::$staticModuleName = $name;
                static::$staticModuleNameLock = true;
                return true;
            }            
            return false;
        }
    }

    public function load() {
        if (!$this->initialized) {
            $this->initialized = true;
            $this->dotapp->trigger("dotapp.module." . $this->modulename . ".loading", $this);
            $this->load_libraries();
            $this->initialize($this->dotapp);            
            $this->dotapp->trigger("dotapp.module." . $this->modulename . ".loaded", $this);
        }
        if (defined("__DOTAPPER_RUN__")) {
			$routes = $this->initializeRoutes();
			if (!is_array($routes) || array_filter($routes, fn($item) => !is_string($item)) !== []) {
				throw new \InvalidArgumentException("initializeRoutes() must return a one-dimensional array of strings in module {$this->modulename}");
			}
			$this->dotApp->dotapper['optimizeModules'][$this->modulename] = $routes;
		}
    }
	
	public function __debugInfo() {
        return [
            'publicData' => 'This is just part of dotapp. Nothing to see !'
        ];
    }
	
    public function load_libraries() {
		// Nechane kvoli spatnej kompatibilite starsich modulov
        $this->construct2();
	}

    // Nechane kvoli spatnej kompatibilite starsich modulov
	public function construct2() {
		
	}

    public function initializeConditionAndListener() {
        $result = $this->autoInitializeCondition();
        if ($this->dotapp->hasListener("dotapp.module." . $this->modulename . ".init.condition")) {
            $result = $this->dotapp->trigger("dotapp.module." . $this->modulename . ".init.condition", $result, $this) ?? $result;
        }
        return $result;
    }

    public function initializeCondition($routeMatch) {
        // Zadefinujeme si aku logiku chceme ako podmienku na to, aby bol modul inicializovany.
        // Napriklad nejaky URL match aby sa nenacitavala logika ak sa routy netykaju modulu.
        // Defaultne vracia stale TRUE aby sa inicializacia vykonala.
        return $routeMatch;
    }

    public function initializeRoutes() {
        // Zadefinujeme si aku logiku chceme ako podmienku na to, aby bol modul inicializovany.
        // Napriklad nejaky URL match aby sa nenacitavala logika ak sa routy netykaju modulu.
        // Defaultne vracia stale TRUE aby sa inicializacia vykonala.
        return ['*'];
    }

    public static function willInitilaize() {
        $instance = new static(DotApp::DotApp(), true);
        return $instance->autoInitializeCondition();
    }

    public static function di($method, ...$arguments) {
        static::call($method, $arguments);
    }
    
    public static function call($method, ...$arguments) {
        if (strpos($method, "@") === false) {
            if (method_exists(static::class, $method)) {
                return static::$staticDI->callStatic($method, ...$arguments);
            }
    
            throw new \Exception("Static method $method does not exist in " . static::class);
        } else {
            $fn = DotApp::DotApp()->stringToCallable($method);
            return $fn(...$arguments);
        }
    }

    public function autoInitializeCondition() {
        // Presunuli sme to tu. Ak funkcia vrati TRUE, tak povolime vsetky moduly. 
        // Ak funkcia vrati pole, pouzije sa ako pole pre match router URL a ak niektora routa sedi, modul sa incializuje
        $navrat = $this->initializeRoutes();
        $predajDalej = false;
        if ($navrat === ['*']) {
            $predajDalej = true;
        } else {
            if (is_array($navrat)) {
                $predajDalej = false;
                foreach ($navrat as $route) {
                    if ($this->dotApp->router->match_url($route) !== false) {
                        $predajDalej = true;
                        break;
                    }
                }
            }
        }        

        $navrat = $this->initializeCondition($predajDalej);
        if ($navrat === true) return true;
        if ($navrat === false) return false;
    }
	
	/*
		Modul sa instaluje uplne jednoducho. Skopiruje sa do priecinka modules a obsahuje skript install.php
		Skript install.php sa spusti ak existuje, vykona co ma a potom sa premenuje na nespustitelny nazov.
		Skript install.php ma za ulohu vytvorit zaznamy opravneni pre modul users pripadne vytvorit polozky v menu administracie.
		( nie je povinne ale ak je to modul pre dotapp tak je na nic ak nema polozky v menu )
	*/
	public function installation() {
		if (file_exists($this->path . "/install.php")) {
			$dotapp = $this->dotapp;
            $dotapp->trigger("dotapp.module." . $this->modulename . ".install", $this);
			require_once $this->path . "/install.php";
			rename($this->path . "/install.php", $this->path . "/installed_" . md5(time() . rand(100, 999) . rand(100, 999)) . "_install.php");
		}
	}
	
	// camelCase alias
	public function loadLibrary($file) {
		$this->load_library($file);
	}
	
	public function load_library($file) {
		$dotapp = $this->dotapp;
		/*
			Aby bol $dotapp viditelny pre vlozenu kniznicu. Naprikald pre kniznicu dotapp na konci vytrvarame jej objekt
			new dotcms($this->modulename,$dotapp); a prave tu vyuzijeme $dotapp
			
			Kazda kniznica vytvara svoj objekt.
		*/
		require_once $this->path . '/Libraries/' . $file . ".php";
	}

    public function assets($request, $file) {
        $request->response->status = 404;
		return null;
		
		// V tejto funkcii si definujeme co chceme robit so subormi assets, ci ich chceme vkladat ci nie...
        // Automaticky definovana funkcia to vyriesi za nas, ale ak chce uzivatel mat kontrolu tak si moze funkciu prepisat.
    }

    public function isSetData($name) {
		return $this->isset_data($name);
	}
	
	public function isset_data($name) {
		return isset($this->moduledata[md5($name)]);
	}
	
	public function setData($name, $value) {
        return $this->set_data($name, $value);
    }
    
    public function set_data($name, $value) {
		$this->moduledata[md5($name)] = $value;
        return $this;
	}
	
    public function getData($name) {
        return $this->get_data($name);
    }

	public function get_data($name) {
		if (isset($this->moduledata[md5($name)])) return $this->moduledata[md5($name)]; else return false;
	}
    
	abstract function initialize($dotapp);
}
?>
