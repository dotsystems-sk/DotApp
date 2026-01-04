<?php
namespace Dotsystems\App\Parts;
use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\Config;

/**
 * CLASS SessionDriverFile - File-Based Session Driver Implementation (Single File)
 *
 * This class provides a file-based session management functionality for the DotApp framework.
 * It handles session operations including initialization, storage, retrieval,
 * and management of session variables using a single file per session for storage,
 * with enhanced security features and garbage collection optimized for cron execution.
 * 
 * The driver implements a singleton pattern to ensure only one instance exists
 * throughout the application lifecycle, providing consistent session management.
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

class SessionDriverFile {
    private $dir;
    private $filename;
    private $sessionDataStorage = null;
    private $sessionId;
    private $isActive = false;
    private static $driver = null;

    public static function driver() {
        if (self::$driver === null) {
            self::$driver = new self();
            return self::$driver->getDriver();
        }
        return self::$driver->getDriver();
    }

    function __construct() {
        $this->dir = __ROOTDIR__ . Config::session("file_driver_dir");
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0700, true);
        }
        // Validate or generate session ID
        $this->sessionId = $this->getSessionId();
        // Start drivera
        $this->setSessionCookie();
        $this->isActive = true;
        // --> Start drivera
        $this->filename = $this->dir . "/" . $this->sessionId . '.php';
        $this->filename = str_replace("\\\\", "\\", $this->filename);
        $this->filename = str_replace("//", "/", $this->filename);
    }

    private function getSessionId() {
        $cookieName = Config::session("cookie_name");
        if (isset($_COOKIE[$cookieName])) {
            $sessionId = $_COOKIE[$cookieName];
            // Ensure session ID contains only alphanumeric characters and is 48 chars long
            if (preg_match('/^[a-zA-Z0-9]{64}$/', $sessionId)) {
                return $sessionId;
            }
        }

        // Generate new session ID
        do {
            $sessionId = bin2hex(random_bytes(32)); // 64-character session ID
            $filePath = $this->dir . "/" . $sessionId . '.php';
        } while (file_exists($filePath));

        return $sessionId;
    }

    private function setSessionCookie($delete = false) {
        $cookieName = Config::session("cookie_name");
        $value = $delete ? '' : $this->sessionId;
        $expires = $delete ? time() - 3600 : time() + Config::session("lifetime");

        setcookie(
            $cookieName,
            $value,
            [
                'expires' => $expires,
                'path' => Config::session("path"),
                'secure' => Config::session("secure"),
                'httponly' => Config::session("httponly"),
                'samesite' => Config::session("samesite")
            ]
        );
    }

    private function getDriver() {
        $driverFn = [];

        // Funkcia START
        $driverFn['start'] = function ($dsm) use (&$driverFn) {
            if ($this->isActive) {
                return $this;
            }

            // Nastav cookie
            $this->setSessionCookie();

            // Načítaj dáta
            $driverFn['load']($dsm);

            $this->isActive = true;
            return $this;
        };

        // Funkcia STATUS
        $driverFn['status'] = function ($dsm) {
            return $this->isActive ? PHP_SESSION_ACTIVE : PHP_SESSION_NONE;
        };

        // Funkcia SAVE
        $driverFn['save'] = function ($dsm) {
            if ($this->sessionDataStorage === null) {
                return false;
            }
            // Vytvor prefix s kontrolou __ROOTDIR__ a expirácie
            $expiry = time() + Config::session("lifetime");
            $prefix = "<?php\n";
            $prefix .= "if (!defined('__ROOTDIR__')) { exit(); }\n";
            $prefix .= "if (time() > $expiry) { unlink(__FILE__); }\n";
            $prefix .= "exit();\n";
            $prefix .= "?>\n";

            // Ulož dáta s prefixom
            $serialized = serialize($this->sessionDataStorage);
            $content = $prefix . $serialized;
            $this->sessionDataStorage = unserialize($serialized);
            file_put_contents($this->filename, $content, LOCK_EX);
            chmod($this->filename, 0600);
            return true;
        };

        // Funkcia DESTROY
        $driverFn['destroy'] = function ($dsm) use (&$driverFn) {
            if (file_exists($this->filename)) {
                unlink($this->filename);
            }
            $this->sessionDataStorage['values'][$dsm->sessname] = [];
            $this->sessionDataStorage['variables'][$dsm->sessname] = [];
            $this->isActive = false;
            $this->setSessionCookie(true); // Zmaž cookie
        };

        // Funkcia LOAD
        $driverFn['load'] = function ($dsm) use (&$driverFn) {
            if ($this->sessionDataStorage === null && file_exists($this->filename)) {
                $content = file_get_contents($this->filename);
                $data = trim(substr($content, strpos($content, "\n?>") + 3));
                $this->sessionDataStorage = unserialize($data);
                if ($this->sessionDataStorage && is_array($this->sessionDataStorage)) {
                    $driverFn['save']($dsm); // Aktualizuj expiráciu
                    return $this;
                }
            }
            if ($this->sessionDataStorage === null) {
                $this->sessionDataStorage = [];
                $this->sessionDataStorage['values'] = [];
                $this->sessionDataStorage['variables'] = [];
            }
            return $this;
        };

        // Funkcia GET
        $driverFn['get'] = function ($name, $dsm) use (&$driverFn) {
            if (isset($this->sessionDataStorage['variables'][$dsm->sessname][$name])) {
                $varid = $this->sessionDataStorage['variables'][$dsm->sessname][$name];
                $value = $this->sessionDataStorage['values'][$dsm->sessname][$varid];

                if ($value && !is_array($value) && strpos($value, 'O:') === 0) {
                    $value = unserialize($value);
                }

                return $value;
            }

            return null;
        };

        // Funkcia SET
        $driverFn['set'] = function ($name, $value, $dsm) use (&$driverFn) {
            $varid = md5($name) . md5($name . rand(1000, 2000));

            if (isset($this->sessionDataStorage['variables'][$dsm->sessname][$name])) {
                unset($this->sessionDataStorage['values'][$dsm->sessname][$this->sessionDataStorage['variables'][$dsm->sessname][$name]]);
            }

            $this->sessionDataStorage['variables'][$dsm->sessname][$name] = $varid;

            if (is_object($value)) {
                $value = serialize($value);
            }

            $this->sessionDataStorage['values'][$dsm->sessname][$varid] = $value;
            $driverFn['save']($dsm);

            return $this;
        };

        // Funkcia DELETE
        $driverFn['delete'] = function ($name, $dsm) use (&$driverFn) {
            if (isset($this->sessionDataStorage['variables'][$dsm->sessname][$name])) {
                $varid = $this->sessionDataStorage['variables'][$dsm->sessname][$name];
                unset($this->sessionDataStorage['variables'][$dsm->sessname][$name]);
                unset($this->sessionDataStorage['values'][$dsm->sessname][$varid]);
                $driverFn['save']($dsm);
            }
        };

        // Funkcia CLEAR
        $driverFn['clear'] = function ($dsm) use (&$driverFn) {
            $this->sessionDataStorage['values'][$dsm->sessname] = [];
            $this->sessionDataStorage['variables'][$dsm->sessname] = [];
            $driverFn['save']($dsm);
        };

        // Funkcia REGENERATE_ID
        $driverFn['regenerate_id'] = function ($deleteOld, $dsm) use (&$driverFn) {
            $oldSessionId = $this->sessionId;
            $oldFilename = $this->filename;

            // Generuj nové session ID
            do {
                $newSessionId = bin2hex(random_bytes(16));
                $newFilename = $this->dir . "/" . $newSessionId . '.php';
            } while (file_exists($newFilename));

            $this->sessionId = $newSessionId;
            $this->filename = $newFilename;

            // Prenes dáta
            $driverFn['save']($dsm);

            // Zmaž starý súbor, ak je deleteOld true
            if ($deleteOld && file_exists($oldFilename)) {
                unlink($oldFilename);
            }

            // Aktualizuj cookie
            $this->setSessionCookie();
        };

        // Funkcia SESSION_ID
        $driverFn['session_id'] = function ($new, $dsm) use (&$driverFn) {
            if ($new !== null) {
                $oldSessionId = $this->sessionId;
                $oldFilename = $this->filename;

                $newFilename = $this->dir . "/" . $new . '.php';
                if (file_exists($newFilename)) {
                    throw new \Exception("Session ID $new already exists");
                }

                $this->sessionId = $new;
                $this->filename = $newFilename;

                // Prenes dáta
                $driverFn['save']($dsm);

                // Zmaž starý súbor
                if (file_exists($oldFilename)) {
                    unlink($oldFilename);
                }

                // Aktualizuj cookie
                $this->setSessionCookie();
            }
            return $this->sessionId;
        };

        // Funkcia GC (Garbage Collection) - Spustat pomocou CRON napriklad raz za hodinu...
        // Volat cez DSM::use()->gc();
        $driverFn['gc'] = function ($dsm) use (&$driverFn) {
            // Prehľadaj všetky session súbory a spusti ich kód
            foreach (glob($this->dir . '/*.php') as $file) {
                include $file; // Spustí samo-mazací kód
            }
        };

        return $driverFn;
    }
}
?>
