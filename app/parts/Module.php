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
	//public $menu; /* Ci ma modul svoje vlastne menu alebo nie. 0 - Nie, 1 - Ano */
	
	function __construct($dotapp,$optimalizacia = false) {
        $this->initialized = false;
		$this->dotapp = $dotapp;
        $this->dotApp = $this->dotapp;
        $this->DotApp = $this->dotapp;
        if ( $optimalizacia === true || defined("__DOTAPPER_OPTIMIZER__") || !defined("__DOTAPP_MODULES_CAN_LOAD__") ) return;
        $classname = get_class($this);
        $this->assetsLoaded = false;
        // New PascalCase
        $classname = str_replace("Dotsystems\\App\\Modules\\","",$classname);
		$classnamea = explode("\\",$classname);
		$classname = $classnamea[0];
		$classname = str_replace("module_","",$classname);
		$this->modulename = $classname;
		$this->path = __ROOTDIR__."/app/modules/".$classname;
		$this->di = new DI($this,$dotapp);
        $this->DI = $this->di; // Alias pre di, blbuvzdornost.
        self::$staticDI = $this->di;
        $this->call = $this->di; // Alias pre di, blbuvzdornost.
        $this->Call = $this->di; // Alias pre di, blbuvzdornost.
        $this->installation();
		$dotapp->module_add($this->modulename,$this->di);
        self::moduleName($this->modulename);
        $dotapp->trigger("dotapp.module.".$this->modulename.".init.start",$this);
        if ($this->initializeConditionAndListener() || defined('__DOTAPPER_RUN__')) {
            $this->dotapp->dotapper['routes_module'] = $this->modulename;
            $this->load();
        }
        $dotapp->trigger("dotapp.module.".$this->modulename.".init.end",$this);
	}

    public static function optimize() {
        try {
            define('__DOTAPPER_OPTIMIZER__',1);
            $moduly = glob(__ROOTDIR__."/app/modules/*", GLOB_ONLYDIR); // Get all module directories
            $routyModulov = [];
            foreach ($moduly as $modul) {
                $modulinit = $modul.'/module.init.php';
                $modulName = str_replace("\\","/",$modul);
                $modulName = explode("/",$modulName);
                $modulName = $modulName[count($modulName)-1];
                if (file_exists($modulinit)) {
                    $className = "Dotsystems\\App\\Modules\\".$modulName."\\Module";
                    if (!class_exists($className, false)) {
                        include $modulinit;
                    }
                    if (!class_exists($className, false)) {
                        throw new \RuntimeException("Module class $className not found");
                    }
                    $objekt = new $className(null,true);
                    $routes = $objekt->initializeRoutes();
                    if (!is_array($routes) || array_filter($routes, fn($item) => !is_string($item)) !== []) {
                        throw new \InvalidArgumentException("initializeRoutes() must return a one-dimensional array of strings in module {$this->modulename}");
                    }
                    $routyModulov[$modulName] = $routes;
                }
            }
            file_put_contents(__ROOTDIR__ . "/app/modules/modulesAutoLoader.php", "<?php\n\$modules = " . var_export($routyModulov, true) . ";\n ?>");
            return (true);
        } catch (\Exception $e) {
            return $e;
        }
    }

    /**
     * Retrieves or sets settings for the module.
     *
     * This function allows you to either retrieve module settings or update them.
     * - If an array is provided as input, it will update the entire settings file with the provided array.
     * - If two arguments are provided (key and value), it will update a specific setting in the settings file.
     * - If a string is provided, it will return the value of that specific setting.
     * - If no input is provided (null), it will return all settings as an array.
     *
     * @param string|array|null $input The setting key (string), an array of settings to update, or null to retrieve all settings.
     * @param mixed $value Optional value to set for a specific key (used when $input is a string).
     *
     * @return mixed|bool|null If updating settings (array or key-value input), returns true on success, false on failure.
     *                         If retrieving a specific setting (string input), returns the value of the setting or null if not found.
     *                         If retrieving all settings (null input), returns an associative array of all settings or an empty array if the settings file does not exist.
     */
    public function settings($input = null, $value = null) {
        $settingsFile = $this->path . "/settings.php";

        // Setter: If input is an array, update the entire settings file
        if (is_array($input)) {
            $content = "<?php\nreturn " . var_export($input, true) . ";\n?>";
            try {
                file_put_contents($settingsFile, $content);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }

        // Setter: If input is a string and value is provided, update a specific setting
        if (is_string($input) && $value !== null) {
            $settings = file_exists($settingsFile) ? include $settingsFile : [];
            if (!is_array($settings)) {
                $settings = [];
            }
            $settings[$input] = $value;
            $content = "<?php\nreturn " . var_export($settings, true) . ";\n?>";
            try {
                file_put_contents($settingsFile, $content);
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }

        // Getter: Load settings file if it exists
        if (file_exists($settingsFile)) {
            $settings = include $settingsFile;
            if (!is_array($settings)) {
                return is_string($input) ? null : [];
            }

            // If input is a string, return the specific setting
            if (is_string($input)) {
                return isset($settings[$input]) ? $settings[$input] : null;
            }

            // If input is null, return all settings
            return $settings;
        }

        // If the settings file does not exist, return null for string input or empty array for null input
        return is_string($input) ? null : [];
    }

    public static function moduleName($name=null) {
        if ($name === null) {
            return self::$staticModuleName;
        } else {
            if (self::$staticModuleNameLock === false) {
                self::$staticModuleName = $name;
                self::$staticModuleNameLock = true;
                return true;
            }            
            return false;
        }
    }

    public function load() {
        if (!$this->initialized) {
            $this->initialized = true;
            $this->dotapp->trigger("dotapp.module.".$this->modulename.".loading",$this);
            $this->load_libraries();
            $this->initialize($this->dotapp);            
            $this->dotapp->trigger("dotapp.module.".$this->modulename.".loaded",$this);
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
        if ($this->dotapp->hasListener("dotapp.module.".$this->modulename.".init.condition")) {
            $result = $this->dotapp->trigger("dotapp.module.".$this->modulename.".init.condition", $result, $this) ?? $result;
        }
        return($result);
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
        $instance = new static(DotApp::DotApp(),true);
        return $instance->autoInitializeCondition();
    }

    public static function di($method, ...$arguments) {
        self::call($method, $arguments);
    }
    
    public static function call($method, ...$arguments) {
        if (strpos($method,"@") === false) {
            if (method_exists(static::class, $method)) {
                return self::$staticDI->callStatic($method, ...$arguments);
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
                foreach($navrat as $route) {
                    if ($this->dotApp->router->match_url($route) !== false ) {
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
		if (file_exists($this->path."/install.php")) {
			$dotapp = $this->dotapp;
            $dotapp->trigger("dotapp.module.".$this->modulename.".install",$this);
			require_once $this->path."/install.php";
			rename($this->path."/install.php", $this->path."/installed_".md5(time().rand(100,999).rand(100,999))."_install.php");
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
		require_once $this->path . '/Libraries/'.$file.".php";
	}

    public function assets($request,$file) {
        if ($this->assetsLoaded === false) {

            if ( is_dir($file) ) {
                $request->response->status = 403;
                return(null);
            }

            if ( !is_file($file) || !is_readable($file) ) {
                $request->response->status = 404;
                return(null);
            }

            /* Ideme poslat subor ak je najdeny */ 
            $mimeType = mime_content_type($file);
            if ($mimeType === false) {
                $mimeType = 'application/octet-stream'; // Fallback pre neznáme typy
            }

            $request->response->headers['Content-Type'] =  $mimeType;
            $request->response->headers['Cache-Control'] =  'public, max-age=31536000';
            $request->response->headers['Last-Modified'] =  gmdate('D, d M Y H:i:s', filemtime($file));

            foreach ($request->response->headers as $name => $value) {
                header("$name: $value");
            }
            
            readfile($file);
            $this->assetsLoaded = true;
            exit();
        }
        // V tejto funkcii si definujeme co chceme robit so subormi assets, ci ich chceme vkladat ci nie...
        // Automaticky definovana funkcia to vyriesi za nas, ale ak chce uzivatel mat kontrolu tak si moze funkciu prepisat.
        
    }

    public function isSetData($name) {
		return($this->isset_data($name));
	}
	
	public function isset_data($name) {
		return(isset($this->moduledata[md5($name)]));
	}
	
	public function setData($name,$value) {
        return($this->set_data($name,$value));
    }
    
    public function set_data($name,$value) {
		$this->moduledata[md5($name)] = $value;
        return $this;
	}
	
    public function getData($name) {
        return($this->get_data($name));
    }

	public function get_data($name) {
		if (isSet($this->moduledata[md5($name)])) return($this->moduledata[md5($name)]); else return(false);
	}
    
	abstract function initialize($dotapp);
	
}

?>