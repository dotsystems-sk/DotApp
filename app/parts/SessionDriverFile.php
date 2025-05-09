<?php

namespace Dotsystems\App\Parts;
use \Dotsystems\App\Parts\Config;

class SessionDriverFile {
    private $dir;
    private $filename;
    private $values;
    private $variables;
    private static $driver = null;

    public static function driver() {
        if (self::$driver === null) {
            self::$driver = new self();
            return self::$driver->getDriver();
        } else {
            return self::$driver->getDriver();
        }
    }

    function __construct() {
        $this->dir = sys_get_temp_dir() . '/dotapp_sessions/';
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0700, true);
        }

        // Validate or generate session ID
        $sessionId = $this->getSessionId();
        $this->filename = $this->dir . $sessionId . '.php';

        // Set cookie with secure session ID
        setcookie('dotapp_session', $sessionId, [
            'expires' => time() + Config::session("lifetime"),
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }

    private function getSessionId() {
        // Validate existing session ID from cookie
        if (isset($_COOKIE['dotapp_session'])) {
            $sessionId = $_COOKIE['dotapp_session'];
            // Ensure session ID contains only alphanumeric characters and is 32 chars long
            if (preg_match('/^[a-zA-Z0-9]{32}$/', $sessionId)) {
                return $sessionId;
            }
        }

        // Generate new session ID
        do {
            $sessionId = bin2hex(random_bytes(32)); // 32-character session ID
            $filePath = $this->dir . $sessionId . '.php';
        } while (file_exists($filePath)); // Ensure no collision

        // Set new session ID in cookie
        setcookie('dotapp_session', $sessionId, [
            'expires' => time() + Config::session("lifetime"), // 1 hour default expiry
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        return $sessionId;
    }

    private function getDriver() {
        $driverFn = [];

        // Function SAVE
        $driverFn['save'] = function ($dsm) {
            $sessionData = [
                'expiry' => time() + 3600, // Extend expiry to 1 hour from now
                'values' => $this->values,
                'variables' => $this->variables
            ];
            
            $content = "<?php exit(); ?>\n" . serialize($sessionData);
            file_put_contents($this->filename, $content, LOCK_EX);
            chmod($this->filename, 0600); // Restrict file permissions
        };

        // Function LOAD
        $driverFn['load'] = function ($dsm) use (&$driverFn) {
            if (file_exists($this->filename)) {
                $content = file_get_contents($this->filename);

                $data = substr($content, strpos($content, "\n") + 1);
                $sessionData = unserialize($data);
                
                if ($sessionData && is_array($sessionData)) {
                    // Check expiry
                    if (isset($sessionData['expiry']) && $sessionData['expiry'] > time()) {
                        $this->values = $sessionData['values'] ?? [];
                        $this->variables = $sessionData['variables'] ?? [];
                        // Extend expiry on load
                        $driverFn['save']($dsm);
                        return;
                    }
                }
            }
            // Initialize empty session if file doesn't exist or is expired
            $this->values = [];
            $this->variables = [];
            $driverFn['save']($dsm);
        };

        

        // Function GET
        $driverFn['get'] = function ($name, $dsm) use (&$driverFn) {
            if (isset($this->variables[$name])) {
                $varid = $this->variables[$name];
                $value = $this->values[$varid];

                if ($value && !is_array($value) && strpos($value, 'O:') === 0) {
                    $value = unserialize($value);
                }

                return $value;
            }

            return null;
        };

        // Function SET
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
            $driverFn['save']($dsm);

            return $this;
        };

        // Function DELETE
        $driverFn['delete'] = function ($dsm) use (&$driverFn) {
            if (file_exists($this->filename)) {
                unlink($this->filename);
            }
            $this->values = [];
            $this->variables = [];
            $driverFn['save']($dsm);
        };

        // Function CLEAR
        $driverFn['clear'] = function ($dsm) use (&$driverFn) {
            $this->values = [];
            $this->variables = [];
            $driverFn['save']($dsm);
        };

        return $driverFn;
    }
}

?>