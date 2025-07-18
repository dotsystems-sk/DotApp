<?php
/**
 * DotApp Framework
 * 
 * ABSTRACT CLASS Controller2
 *
 * This abstract class serves as a base for static controllers within the DotApp framework.
 * It enables static method calls with dependency injection handled automatically via the di class.
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

namespace Dotsystems\App\Parts;
use \Dotsystems\App\Parts\DI;
use \Dotsystems\App\DotApp;

abstract class Controller {
    public static $di; // Statická DI inštancia pre volanie funkciia cez kontajner
    protected static $staticModuleName = null;

    public static function moduleName($name = null) {
        if (static::$staticModuleName === null) {
            // Ziskame nazov modulu z classname
            $fqmn = static::class;
            $pattern = '/^Dotsystems\\\\App\\\\Modules\\\\([^\\\\]+)\\\\.+$/';
            if (preg_match($pattern, $fqmn, $matches)) {
                static::$staticModuleName = $matches[1]; // Set extracted module name
            } else {
                throw new \RuntimeException("Unable to extract module name from class: $fqmn");
            }
        }
        return static::$staticModuleName;
    }

    // Zabezpecime kontajner DI
    private static function ensureDi() {
        if (!isset(static::$di)) {
            $dotApp = DotApp::dotApp();
            if (!isset($dotApp)) {
                throw new \Exception("Global DotApp instance not found.");
            }
            static::$di = new DI(static::class, $dotApp);
        }
    }
	
	// Ak by sme potrebovali pristup k dotappu v controlleri
	public static function dotApp() {
        $dotApp = DotApp::dotApp();
		if (!isset($dotApp)) {
            throw new \Exception("Global DotApp instance not found.");
        }
		return $dotApp;
	}

    // Alias ku call, lepsie zapamtatelne ze ide o injection
    public static function di($method, ...$arguments) {
        static::call($method, $arguments);
    }
    
    public static function call($method, ...$arguments) {
        static::ensureDi();

        if (strpos($method,"@") === false) {
            if (method_exists(static::class, $method)) {
                return static::$di->callStatic($method, ...$arguments);
            }
    
            throw new \Exception("Static method $method does not exist in " . static::class);
        } else {
            $fn = DotApp::DotApp()->stringToCallable($method);
            return $fn(...$arguments);
        }
        
    }
	
	// Alias pre api
	public static function apiDispatch($request) {
		$method = strtolower($request->getMethod());
		$resource = $request->matchData()['resource'] ?? null;
		$id = $request->matchData()['id'] ?? null;
		
		// Zostavíme názov metódy: <httpMetóda><Resource>
		if ($resource) {
			$targetMethod = $method . ucfirst($resource);
			if (method_exists(static::class, $targetMethod)) {
				return static::$di->callStatic($targetMethod, [$request]);
			}
		}

		// Skúsime volať error404, ak existuje
		if (method_exists(static::class, 'error404')) {
			return static::$di->callStatic('error404', [$request]);
		}

		// Predvolená odpoveď, ak error404 neexistuje
		http_response_code(404);
		return "API: Resource '$resource' not found or method '$method' not supported in " . static::class;
	}
	
	public static function api($request) {
		// Alias kratsi. 
		return static::apiDispatch($request);
	}
	
    public function __debugInfo() {
        return [
            'publicData' => 'This is just part of dotapp. Nothing to see !'
        ];
    }

}

?>