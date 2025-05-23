<?php

namespace Dotsystems\App\Parts;
use \Dotsystems\App\Parts\Config;

/**
 * CLASS SessionDriverDefault - Default Session Driver Implementation
 *
 * This class provides the default session management functionality for the DotApp framework.
 * It handles session operations including initialization, storage, retrieval,
 * and management of session variables using PHP's native session mechanism with
 * additional security and organization features.
 * 
 * The driver implements a singleton pattern to ensure only one instance exists
 * throughout the application lifecycle, providing consistent session management.
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

class SessionDriverDefault {
    private $values;
    private $variables;
    private $sessname;
    private static $driver=null;

    /**
     * Returns the session driver instance using the Singleton pattern.
     * 
     * This method ensures that only one instance of the session driver exists
     * throughout the application lifecycle. On first call, it creates a new
     * instance and returns its driver functions. On subsequent calls, it returns
     * the existing instance's driver functions.
     * 
     * @return array An array of anonymous functions that handle session operations
     *               including save, load, get, set, delete, and clear.
     */
    public static function driver() {
        if (self::$driver === null) {
            self::$driver = new self();
            return self::$driver->getDriver();
        } else  {
            return self::$driver->getDriver();
        }
    }

    /**
     * Initializes the session driver and configures session parameters.
     * 
     * Starts a PHP session if one is not already active, initializes internal
     * storage arrays, and sets a secure session cookie with appropriate parameters.
     */
    function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            @ini_set('session.gc_maxlifetime', Config::session("lifetime"));
            session_start();
        }

        $this->variables = [];
        $this->values = [];

		if (!isset($_COOKIE[Config::session("cookie_name")])) {
			setcookie(Config::session("cookie_name"), session_id(), [
				'expires' => time() + Config::session("lifetime"),
				'path' => Config::session("path"),
				'secure' => Config::session("secure"),
				'httponly' => Config::session("httponly"),
				'samesite' => Config::session("samesite")
			]);
		}
        
    }

    /**
     * Creates and returns an array of session management functions.
     * 
     * Defines anonymous functions for session operations including saving, loading,
     * retrieving, setting, deleting, and clearing session data. These functions
     * provide the core functionality of the session driver.
     * 
     * @return array An associative array of anonymous functions for session management:
     *               - save: Saves the current session state to $_SESSION
     *               - load: Loads session data from $_SESSION
     *               - get: Retrieves a specific variable from the session
     *               - set: Sets a variable in the session
     *               - delete: Removes session data
     *               - clear: Clears session data
     */
    private function getDriver() {

        // Funkcia SAVE
        $driverFn['start'] = function ($dsm) {
            session_start();
        };

        // Funkcia SAVE
        $driverFn['status'] = function ($dsm) {
            return session_status();
        };

        // Funkcia SAVE
        $driverFn['save'] = function ($dsm) {
            $save_session = [];
            $save_session['values'] = $this->values[$dsm->sessname];
            $save_session['variables'] = $this->variables[$dsm->sessname];
            $_SESSION[$dsm->sessname] = $save_session;
        };

        // Funkcia SAVE
        $driverFn['destroy'] = function ($dsm) use (&$driverFn) {
            $this->values[$dsm->sessname] = [];
            $this->variables[$dsm->sessname] = [];
            unset($_SESSION[$dsm->sessname]);
            $driverFn['save']($dsm);
        };

        // Funkcia LOAD
        $driverFn['load'] = function ($dsm) use (&$driverFn) {
            if (isset($_SESSION[$dsm->sessname])) {
                $construct_session = $_SESSION[$dsm->sessname];
                $this->values[$dsm->sessname] = $construct_session['values'];
                $this->variables[$dsm->sessname] = $construct_session['variables'];
            } else {
                $this->values[$dsm->sessname] = [];
                $this->variables[$dsm->sessname] = [];
                $driverFn['save']($dsm); // Save the initial empty state
            }                
        };        

        // Funkcia GET
        $driverFn['get'] = function ($name,$dsm) use (&$driverFn) {
            if (isset($this->variables[$dsm->sessname][$name])) {
                $varid = $this->variables[$dsm->sessname][$name];
                $value = $this->values[$dsm->sessname][$varid];

                if ($value && !is_array($value) && strpos($value, 'O:') === 0) {
                    $value = unserialize($value);
                }

                return $value; // Return the retrieved value
            }

            return null; // Return null if the variable does not exist
        };


        // Funkcia SET
        $driverFn['set'] = function ($name, $value, $dsm) use (&$driverFn) {
            $varid = md5($name) . md5($name . rand(1000, 2000));
        
            if (isset($this->variables[$dsm->sessname][$name])) {
                unset($this->values[$dsm->sessname][$this->variables[$dsm->sessname][$name]]);
            }

            $this->variables[$dsm->sessname][$name] = $varid;

            if (is_object($value)) {
                $value = serialize($value);
            }

            $this->values[$dsm->sessname][$varid] = $value;
            $driverFn['save']($dsm); // Save the session state after setting a value

            return $this; // For chaining
        };

        $driverFn['delete'] = function ($name, $dsm) use (&$driverFn) {
            if (isset($this->variables[$dsm->sessname][$name])) {
                $varid = $this->variables[$dsm->sessname][$name];
                unset($this->variables[$dsm->sessname][$name]);
                unset($this->values[$dsm->sessname][$varid]);
                $driverFn['save']($dsm);
            }
        };

        $driverFn['clear'] = function ($dsm) use (&$driverFn) {
            $this->values[$dsm->sessname] = [];
            $this->variables[$dsm->sessname] = [];
            $driverFn['save']($dsm);
        };

        $driverFn['regenerate_id'] = function ($deleteOld, $dsm) {
            session_regenerate_id($deleteOld);
        };

        $driverFn['session_id'] = function ($new, $dsm) {
            if ($new === null) return session_id();
            return session_id($new);
        };

        $driverFn['gc'] = function ($dsm) {
            // Pouzivame session takze sa pouzije jeho garbace collector
        };

        return $driverFn;
    }
}

?>