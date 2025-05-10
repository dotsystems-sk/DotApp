<?php

namespace Dotsystems\App\Parts;
use \Dotsystems\App\Parts\Config;

class SessionDriverDefault {
    private $values;
    private $variables;
    private $sessname;
    private static $driver=null;

    public static function driver() {
        if (self::$driver === null) {
            self::$driver = new self();
            return self::$driver->getDriver();
        } else  {
            return self::$driver->getDriver();
        }
    }

    function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            @ini_set('session.gc_maxlifetime', Config::session("lifetime"));
            session_start();
        }

        $saessid = session_id();
        setcookie('dotapp_session', session_id(), [
            'expires' => time() + Config::session("lifetime"),
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }

    private function getDriver() {

        // Funkcia SAVE
        $driverFn['save'] = function ($dsm) {
            $save_session = [];
            $save_session['values'] = $this->values;
            $save_session['variables'] = $this->variables;
            $_SESSION[$dsm->sessname] = $save_session;
        };

        // Funkcia LOAD
        $driverFn['load'] = function ($dsm) use (&$driverFn) {
            if (isset($_SESSION[$dsm->sessname])) {
                $construct_session = $_SESSION[$dsm->sessname];
                $this->values = $construct_session['values'];
                $this->variables = $construct_session['variables'];
            } else {
                $this->values = [];
                $this->variables = [];
                $driverFn['save']($dsm); // Save the initial empty state
            }                
        };        

        // Funkcia GET
        $driverFn['get'] = function ($name,$dsm) use (&$driverFn) {
            if (isset($this->variables[$name])) {
                $varid = $this->variables[$name];
                $value = $this->values[$varid];

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
        
            if (isset($this->variables[$name])) {
                unset($this->values[$this->variables[$name]]);
            }

            $this->variables[$name] = $varid;

            if (is_object($value)) {
                $value = serialize($value);
            }

            $this->values[$varid] = $value;
            $driverFn['save']($dsm); // Save the session state after setting a value

            return $this; // For chaining
        };

        $driverFn['delete'] = function ($dsm) use (&$driverFn) {
            if (isset($_SESSION[$dsm->sessname])) {
                $construct_session = $_SESSION[$dsm->sessname];
                $this->values = $construct_session['values'];
                $this->variables = $construct_session['variables'];
            } else {
                $this->values = [];
                $this->variables = [];
                $driverFn['save']($dsm); // Save the initial empty state
            }                
        };

        $driverFn['clear'] = function ($dsm) use (&$driverFn) {
            if (isset($_SESSION[$dsm->sessname])) {
                $construct_session = $_SESSION[$dsm->sessname];
                $this->values = $construct_session['values'];
                $this->variables = $construct_session['variables'];
            } else {
                $this->values = [];
                $this->variables = [];
                $driverFn['save']($dsm); // Save the initial empty state
            }                
        };

        return $driverFn;
    }
}

?>