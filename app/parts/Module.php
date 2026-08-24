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
 * @version   2.0 FREE
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
            // Why: dotapper zadefinuje rezim este pred bootom appky, webovy DACore optimizer ho zasa potrebuje nastavit tu.
            if (!defined('__DOTAPPER_OPTIMIZER__')) {
                define('__DOTAPPER_OPTIMIZER__', 1);
            }
            $moduly = glob(__ROOTDIR__ . "/app/modules/*", GLOB_ONLYDIR); // Get all module directories
            $moduly = is_array($moduly) ? $moduly : [];
            $routyModulov = [];
            $routyListenerov = [];
            $baseLanguageDescriptors = [];
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
                    $baseLanguageDescriptors[$modulName] = $objekt->baseLanguages();

                    // Why: listener moze pocuvat na inych routach a pritom nema zobudit initialize() celeho modulu.
                    $listenerRoutes = $routes;
                    $listenerInit = $modul . '/module.listeners.php';
                    if (file_exists($listenerInit)) {
                        $listenerClass = "Dotsystems\\App\\Modules\\" . $modulName . "\\Listeners";
                        if (!class_exists($listenerClass, false)) {
                            // Why: subor vytvara objekt aj na konci, optimizer konstanta ale zastavi register().
                            $dotApp = DotApp::DotApp();
                            include $listenerInit;
                        }
                        if (!class_exists($listenerClass, false)) {
                            throw new \RuntimeException("Listener class $listenerClass not found");
                        }

                        $listenerObject = new $listenerClass(DotApp::DotApp(), true);
                        $listenerRoutes = $listenerObject->resolvedInitializeRoutes($routes);
                    }
                    $routyListenerov[$modulName] = $listenerRoutes;
                }
            }

            // Why: Compile small base catalogs once so sleeping modules do not read translation JSON at runtime.
            $baseLanguages = self::compileBaseLanguages($baseLanguageDescriptors);
            $phpCode = self::buildOptimizerCode($routyModulov, $routyListenerov, $baseLanguages);
            file_put_contents(__ROOTDIR__ . "/app/modules/modulesAutoLoader.php", $phpCode);
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

    /**
     * Return small locale files needed before this module initializes.
     *
     * An empty array keeps the legacy behavior where only translation files
     * registered by initialize() are available.
     *
     * @return array<int, array{file: string, locale: string}> Base translation descriptors.
     */
    public function baseLanguages() {
        return [];
    }

    /**
     * Normalize safe base-language descriptors owned by one module.
     *
     * Invalid rows are ignored so a broken optional catalog cannot block the
     * application or the route optimizer.
     *
     * @param mixed $descriptors Candidate descriptor rows.
     * @param string $moduleName Module that owns every referenced JSON file.
     * @return array<int, array{file: string, locale: string}> Valid normalized rows.
     */
    public static function validateBaseLanguages($descriptors, $moduleName) {
        if (!is_array($descriptors) || !is_string($moduleName) || $moduleName === '') {
            return [];
        }

        $normalized = [];
        foreach ($descriptors as $descriptor) {
            if (!is_array($descriptor)
                || !isset($descriptor['file'], $descriptor['locale'])
                || !is_string($descriptor['file'])
                || !is_string($descriptor['locale'])) {
                continue;
            }

            $file = trim($descriptor['file']);
            $locale = strtolower(trim($descriptor['locale']));
            $prefix = $moduleName . ':';
            if (strpos($file, $prefix) !== 0
                || preg_match('/^[a-z]{2,3}_[a-z]{2,3}$/', $locale) !== 1) {
                continue;
            }

            $relative = substr($file, strlen($prefix));
            if ($relative === ''
                || substr($relative, 0, 1) === '/'
                || strpos($relative, "\\") !== false
                || strpos($relative, "\0") !== false
                || strpos($relative, ':') !== false
                || preg_match('~(^|/)\.\.(/|$)~', $relative) === 1
                || strtolower(pathinfo($relative, PATHINFO_EXTENSION)) !== 'json') {
                continue;
            }

            $normalized[] = [
                'file' => $file,
                'locale' => $locale
            ];
        }

        return $normalized;
    }

    /**
     * Compile module-owned base JSON files into deterministic locale maps.
     *
     * Modules are merged alphabetically and the first module keeps a key when
     * another module defines the same source text.
     *
     * @param array<string, mixed> $descriptorsByModule Descriptor rows keyed by module name.
     * @param string|null $onlyLocale Optional active locale for non-optimized loading.
     * @return array<string, array<string, string>> Compiled maps keyed by locale.
     */
    public static function compileBaseLanguages($descriptorsByModule, $onlyLocale = null) {
        if (!is_array($descriptorsByModule)) {
            return [];
        }

        $onlyLocale = is_string($onlyLocale) && $onlyLocale !== ''
            ? strtolower($onlyLocale)
            : null;
        ksort($descriptorsByModule, SORT_STRING);
        $moduleMaps = [];

        foreach ($descriptorsByModule as $moduleName => $descriptors) {
            if (!is_string($moduleName) || preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $moduleName) !== 1) {
                continue;
            }

            $moduleMaps[$moduleName] = [];
            $rows = self::validateBaseLanguages($descriptors, $moduleName);
            foreach ($rows as $row) {
                $locale = $row['locale'];
                if ($onlyLocale !== null && $locale !== $onlyLocale) {
                    continue;
                }

                $path = self::resolveBaseLanguagePath($row['file'], $moduleName);
                if ($path === null) {
                    continue;
                }

                $size = filesize($path);
                if ($size === false || $size > 262144) {
                    continue;
                }

                $json = file_get_contents($path);
                if ($json === false) {
                    continue;
                }

                $translations = json_decode($json, true);
                if (!is_array($translations) || json_last_error() !== JSON_ERROR_NONE) {
                    continue;
                }

                // Why: Later files from the same module may refine that module's own base catalog.
                foreach ($translations as $source => $translation) {
                    if ((!is_string($source) && !is_int($source)) || !is_string($translation)) {
                        continue;
                    }
                    $moduleMaps[$moduleName][$locale][strtolower((string) $source)] = $translation;
                }
            }
        }

        return self::mergeBaseLanguageModuleMaps($moduleMaps);
    }

    /**
     * Compile base languages from module classes that are already declared.
     *
     * Optimization-mode instances return before installation and initialize(),
     * so reading descriptors does not wake a module.
     *
     * @param array<int, string> $moduleNames Installed module names.
     * @param string|null $onlyLocale Optional active locale to limit JSON I/O.
     * @return array<string, array<string, string>> Compiled locale maps.
     */
    public static function compileBaseLanguagesForModules($moduleNames, $onlyLocale = null) {
        if (!is_array($moduleNames)) {
            return [];
        }

        sort($moduleNames, SORT_STRING);
        $descriptorsByModule = [];
        foreach ($moduleNames as $moduleName) {
            if (!is_string($moduleName) || preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $moduleName) !== 1) {
                continue;
            }

            $className = "Dotsystems\\App\\Modules\\" . $moduleName . "\\Module";
            if (!class_exists($className, false)) {
                continue;
            }

            // Why: Reuse the optimizer's inert constructor path without including module.init.php again.
            $module = new $className(null, true);
            $descriptorsByModule[$moduleName] = $module->baseLanguages();
        }

        return self::compileBaseLanguages($descriptorsByModule, $onlyLocale);
    }

    /**
     * Merge already decoded module maps with deterministic first-module precedence.
     *
     * @param array<string, array<string, array<string, string>>> $moduleMaps Locale maps keyed by module.
     * @return array<string, array<string, string>> Merged locale maps.
     */
    public static function mergeBaseLanguageModuleMaps($moduleMaps) {
        if (!is_array($moduleMaps)) {
            return [];
        }

        ksort($moduleMaps, SORT_STRING);
        $merged = [];
        foreach ($moduleMaps as $localeMaps) {
            if (!is_array($localeMaps)) {
                continue;
            }
            foreach ($localeMaps as $locale => $translations) {
                if (!is_string($locale) || !is_array($translations)) {
                    continue;
                }
                $locale = strtolower($locale);
                if (!isset($merged[$locale])) {
                    $merged[$locale] = [];
                }
                foreach ($translations as $source => $translation) {
                    if ((!is_string($source) && !is_int($source)) || !is_string($translation)) {
                        continue;
                    }
                    $source = strtolower((string) $source);
                    if (!array_key_exists($source, $merged[$locale])) {
                        $merged[$locale][$source] = $translation;
                    }
                }
            }
        }

        ksort($merged, SORT_STRING);
        foreach ($merged as &$translations) {
            ksort($translations, SORT_STRING);
        }
        unset($translations);

        return $merged;
    }

    /**
     * Resolve one modular JSON path inside its owner's translations directory.
     *
     * @param string $file Modular file descriptor.
     * @param string $moduleName Owning module name.
     * @return string|null Canonical file path or null when containment fails.
     */
    private static function resolveBaseLanguagePath($file, $moduleName) {
        $prefix = $moduleName . ':';
        $relative = substr($file, strlen($prefix));
        $translationsRoot = realpath(__ROOTDIR__ . "/app/modules/" . $moduleName . "/translations");
        if ($translationsRoot === false) {
            return null;
        }

        $candidate = realpath(
            $translationsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative)
        );
        if ($candidate === false || !is_file($candidate) || !is_readable($candidate)) {
            return null;
        }

        $root = strtolower(str_replace("\\", '/', $translationsRoot)) . '/';
        $resolved = strtolower(str_replace("\\", '/', $candidate));
        if (strpos($resolved, $root) !== 0) {
            return null;
        }

        return $candidate;
    }

    /**
     * Build the backward-compatible version 2 optimizer payload.
     *
     * @param array<string, array<int, string>> $modules Module route map.
     * @param array<string, array<int, string>> $listeners Listener route map.
     * @param array<string, array<string, string>> $baseLanguages Optional compiled locale maps.
     * @return string Generated PHP cache source.
     */
    private static function buildOptimizerCode($modules, $listeners, $baseLanguages) {
        // Why: $modules remains the legacy contract and the optional language map does not change version 2.
        $phpCode = "<?php\n"
            . "\$modules = " . var_export($modules, true) . ";\n"
            . "\$listeners = " . var_export($listeners, true) . ";\n";
        if (is_array($baseLanguages) && $baseLanguages !== []) {
            $phpCode .= "\$baseLanguages = " . var_export($baseLanguages, true) . ";\n";
        }
        $phpCode .= "\$modulesAutoLoaderVersion = 2;\n"
            . " ?>";

        return $phpCode;
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
