<?php

/**
 * Abstract class LISTENERS
 * 
 * This abstract class defines the core structure for event listeners within the DotApp framework. 
 * It provides essential methods for handling event-based communication, ensuring modularity and 
 * reusability across different components. 
 * 
 * By extending this class, developers can implement custom event listeners that respond to 
 * triggers within the application, facilitating seamless interaction between various modules. 
 * This approach enables a robust and flexible event-driven architecture.
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




namespace Dotsystems\App\Parts;
use \Dotsystems\App\DotApp;

abstract class Listeners {
	/*
		Namiesto INTERFACE ideme do abstract triedy, lebo potrebujeme premenne
	*/
	public $dotapp;
	public $modulename;
	
	function __construct($dotapp, $optimalizacia = false) {
		$classname = get_class($this);
        // New PascalCase
        $classname = str_replace("Dotsystems\\App\\Modules\\","",$classname);
		$classnamea = explode("\\",$classname);
		$classname = $classnamea[0];
		$classname = str_replace("module_","",$classname);
		$this->modulename = $classname;
		$this->dotapp = $dotapp;

        // Why: pri optimalizacii potrebujeme iba precitat routy, register() by spustil listener pocas buildu cache.
        if ($optimalizacia === true || defined("__DOTAPPER_OPTIMIZER__")) {
            return;
        }

        // Why: stary listener mohol byt vytvoreny skor ako DotApp, bez instancie nema co registrovat.
        if (!is_object($this->dotapp)) {
            return;
        }

        $this->dotapp->dotapper['routes_module'] = $this->modulename;
        if (!defined("__DOTAPP_MODULES_AUTOLOADER__")) {
            // Why: bez vygenerovanej mapy si listener rozhodne sam, ci patri na aktualnu URL.
            if ($this->willInitialize()) {
                $this->register($dotapp);
            }
        } else {
            // Why: optimalizovany loader uz listener pustil iba ked sedela jeho samostatna mapa.
            $this->register($dotapp);
        }		
	}

    /**
     * Routy na ktorych sa ma nacitat iba listener cast modulu.
     *
     * Null zachovava spatnu kompatibilitu a pouzije Module::initializeRoutes().
     * Novy modul moze vratit vlastne pole bez toho, aby sa spustil jeho initialize().
     *
     * @return array<int, string>|null Listener routy alebo null pre fallback na modul.
     */
    public function initializeRoutes() {
        return null;
    }

    /**
     * Vrati samostatne listener routy alebo stare modulove routy ako fallback.
     *
     * @param array<int, string> $moduleRoutes Routy z Module::initializeRoutes().
     * @return array<int, string> Routy zapisane do listener mapy optimalizatora.
     */
    public function resolvedInitializeRoutes($moduleRoutes) {
        $routes = $this->initializeRoutes();
        if ($routes === null) {
            $routes = $moduleRoutes;
        }

        return self::validateInitializeRoutes($routes, $this->modulename);
    }

    /**
     * Overi jednorozmerne pole rout, rovnako prisne ako optimalizator modulov.
     *
     * @param mixed $routes Hodnota vratena initializeRoutes().
     * @param string $modulename Nazov modulu do chybovej hlasky.
     * @return array<int, string> Overene routy.
     * @throws \InvalidArgumentException Pri inom tvare alebo nestandardnej hodnote.
     */
    public static function validateInitializeRoutes($routes, $modulename = '') {
        if (!is_array($routes) || array_filter($routes, fn($item) => !is_string($item)) !== []) {
            throw new \InvalidArgumentException(
                "Listeners::initializeRoutes() must return a one-dimensional array of strings in module ".$modulename
            );
        }

        return array_values($routes);
    }

    /**
     * Rozhodne ci sa ma listener registrovat v rezime bez modulesAutoLoader.php.
     *
     * @return bool True ked sedi nova listener routa alebo stary modulovy fallback.
     */
    public function willInitialize() {
        $routes = $this->initializeRoutes();
        if ($routes === null) {
            // Why: starsie moduly nemaju listener initializeRoutes(), preto zachovame povodne rozhodovanie modulu aj initializeCondition().
            $intializerClass = "Dotsystems\\App\\Modules\\".$this->modulename."\\Module";
            if (!class_exists($intializerClass, false)) {
                $pathToModuleInit = __ROOTDIR__."/app/modules/".$this->modulename."/module.init.php";
                $dotApp = $this->dotapp;
                include $pathToModuleInit;
            }

            return $intializerClass::willInitilaize() === true;
        }

        $routes = self::validateInitializeRoutes($routes, $this->modulename);
        if ($routes === ['*']) {
            return true;
        }

        foreach ($routes as $route) {
            if ($this->dotapp->router->match_url($route) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function call($method, ...$arguments) {
        $fn = DotApp::DotApp()->stringToCallable($method);
        return $fn(...$arguments);
    }
	
	public function __debugInfo() {
        return [
            'publicData' => 'This is just part of dotapp. Nothing to see !'
        ];
    }

	abstract function register($dotapp);
	
}

?>
