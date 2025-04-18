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
 * @version   1.6 FREE
 * @license   MIT License
 * @date      2014 - 2025
 * 
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the 
 * following condition: You **must** retain this header in all copies or 
 * substantial portions of the code, including the author and company information.
 */

namespace Dotsystems\App\Parts;
use Dotsystems\App\Parts\DI;

abstract class Controller {
    public static $di; // Statická DI inštancia pre volanie funkciia cez kontajner

    // Zabezpecime kontajner DI
    private static function ensureDi() {
        if (!isset(self::$di)) {
            global $dotapp236365b0b1631351e99daf046d18d2bc;
            if (!isset($dotapp236365b0b1631351e99daf046d18d2bc)) {
                throw new \Exception("Global DotApp instance not found in \$dotapp236365b0b1631351e99daf046d18d2bc.");
            }
            self::$di = new DI(static::class, $dotapp236365b0b1631351e99daf046d18d2bc);
        }
    }
	
	// Ak by sme potrebovali pristup k dotappu v controlleri
	public static function dotapp() {
		global $dotapp236365b0b1631351e99daf046d18d2bc;
		if (!isset($dotapp236365b0b1631351e99daf046d18d2bc)) {
            throw new \Exception("Global DotApp instance not found in \$dotapp236365b0b1631351e99daf046d18d2bc.");
        }
		return $dotapp236365b0b1631351e99daf046d18d2bc;
	}

    // Alias ku call, lepsie zapamtatelne ze ide o injection
    public static function di($method, $arguments = []) {
        self::call($method, $arguments);
    }
    
    public static function call($method, $arguments = []) {
        self::ensureDi();

        if (method_exists(static::class, $method)) {
            return self::$di->callStatic($method, $arguments);
        }

        throw new \Exception("Static method $method does not exist in " . static::class);
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
				return self::$di->callStatic($targetMethod, [$request]);
			}
		}

		// Skúsime volať error404, ak existuje
		if (method_exists(static::class, 'error404')) {
			return self::$di->callStatic('error404', [$request]);
		}

		// Predvolená odpoveď, ak error404 neexistuje
		http_response_code(404);
		return "API: Resource '$resource' not found or method '$method' not supported in " . static::class;
	}
	
	public static function api($request) {
		// Alias kratsi. 
		return self::apiDispatch($request);
	}
	
    public function __debugInfo() {
        return [
            'publicData' => 'This is just part of dotapp. Nothing to see !'
        ];
    }
}

?>