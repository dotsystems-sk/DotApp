<?php
namespace Dotsystems\App\Parts;
use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\Config;

/**
 * CLASS SessionDriverFile2 - File-Based Session Driver Implementation version 2
 *
 * This class provides a file-based session management functionality for the DotApp framework.
 * It handles session operations including initialization, storage, retrieval,
 * and management of session variables using a file-based storage mechanism with
 * enhanced security and performance features, such as per-sessname file splitting
 * and optimized garbage collection.
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

class SessionDriverFile2 {
    private $dir;
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
        $this->dir = __ROOTDIR__ . Config::session("file_driver_dir2");
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0700, true);
        }
        // Validate or generate session ID
        $this->sessionId = $this->getSessionId();
        // Start drivera
        $this->setSessionCookie();
        $this->isActive = true;
        // Inicializuj sessionDataStorage
        if ($this->sessionDataStorage === null) {
            $this->sessionDataStorage = [];
            $this->sessionDataStorage['values'] = [];
            $this->sessionDataStorage['variables'] = [];
        }
    }

    private function getSessionId() {
        $cookieName = Config::session("cookie_name");
        if (isset($_COOKIE[$cookieName])) {
            $sessionId = $_COOKIE[$cookieName];
            // Ensure session ID contains only alphanumeric characters and is 64 chars long
            if (preg_match('/^[a-zA-Z0-9]{64}$/', $sessionId)) {
                return $sessionId;
            }
        }

        // Generate new session ID
        do {
            $sessionId = bin2hex(random_bytes(32)); // 64-character session ID
            $filePath = $this->dir . "/" . $sessionId . '_*.php';
        } while (glob($filePath));

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

    private function getFilename($sessname) {
        // Validuj sessname pre názov súboru
        $safeSessname = preg_replace('/[^a-zA-Z0-9_]/', '_', $sessname);
        $filename = $this->dir . "/" . $this->sessionId . '_' . $safeSessname . '.php';
        $filename = str_replace("\\\\", "\\", $filename);
        $filename = str_replace("//", "/", $filename);
        return $filename;
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
            $prefix .= "if (time() > $expiry) { unlink(__FILE__); echo \"expired;\";echo __FILE__; }\n";
            $prefix .= "exit();\n";
            $prefix .= "?>\n";

            // Ulož iba dáta pre aktuálne sessname
            $sessionData = [
                'values' => $this->sessionDataStorage['values'][$dsm->sessname] ?? [],
                'variables' => $this->sessionDataStorage['variables'][$dsm->sessname] ?? []
            ];
            $content = $prefix . serialize($sessionData);
            $filename = $this->getFilename($dsm->sessname);
            file_put_contents($filename, $content, LOCK_EX);
            chmod($filename, 0600);
            return true;
        };

        // Funkcia DESTROY
        $driverFn['destroy'] = function ($dsm) use (&$driverFn) {
            $filename = $this->getFilename($dsm->sessname);
            if (file_exists($filename)) {
                unlink($filename);
            }
            $this->sessionDataStorage['values'][$dsm->sessname] = [];
            $this->sessionDataStorage['variables'][$dsm->sessname] = [];
            $this->isActive = false;
            $this->setSessionCookie(true); // Zmaž cookie
        };

        // Funkcia LOAD
        $driverFn['load'] = function ($dsm) use (&$driverFn) {
            if (!isset($this->sessionDataStorage['values'][$dsm->sessname]) && 
                !isset($this->sessionDataStorage['variables'][$dsm->sessname])) {
                $filename = $this->getFilename($dsm->sessname);
                if (file_exists($filename)) {
                    $content = file_get_contents($filename);
                    $data = trim(substr($content, strpos($content, "\n?>") + 3));
                    $sessionData = unserialize($data);
                    if ($sessionData && is_array($sessionData)) {
                        $this->sessionDataStorage['values'][$dsm->sessname] = $sessionData['values'] ?? [];
                        $this->sessionDataStorage['variables'][$dsm->sessname] = $sessionData['variables'] ?? [];
                        $driverFn['save']($dsm); // Aktualizuj expiráciu
                    }
                } else {
                    $this->sessionDataStorage['values'][$dsm->sessname] = [];
                    $this->sessionDataStorage['variables'][$dsm->sessname] = [];
                }
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

            // Generuj nové session ID
            do {
                $newSessionId = bin2hex(random_bytes(32));
                $filePath = $this->dir . "/" . $newSessionId . '_*.php';
            } while (glob($filePath));

            $this->sessionId = $newSessionId;

            // Prenes dáta pre každé sessname
            foreach (array_keys($this->sessionDataStorage['values']) as $sessname) {
                $oldFilename = $this->dir . "/" . $oldSessionId . '_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $sessname) . '.php';
                $newFilename = $this->getFilename($sessname);
                if (file_exists($oldFilename)) {
                    rename($oldFilename, $newFilename);
                }
                $driverFn['save']((object)['sessname' => $sessname]);
            }

            // Zmaž staré súbory, ak je deleteOld true
            if ($deleteOld) {
                foreach (glob($this->dir . "/" . $oldSessionId . '_*.php') as $file) {
                    unlink($file);
                }
            }

            // Aktualizuj cookie
            $this->setSessionCookie();
        };

        // Funkcia SESSION_ID
        $driverFn['session_id'] = function ($new, $dsm) use (&$driverFn) {
            if ($new !== null) {
                $oldSessionId = $this->sessionId;

                $filePath = $this->dir . "/" . $new . '_*.php';
                if (glob($filePath)) {
                    throw new \Exception("Session ID $new already exists");
                }

                $this->sessionId = $new;

                // Prenes dáta pre každé sessname
                foreach (array_keys($this->sessionDataStorage['values']) as $sessname) {
                    $oldFilename = $this->dir . "/" . $oldSessionId . '_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $sessname) . '.php';
                    $newFilename = $this->getFilename($sessname);
                    if (file_exists($oldFilename)) {
                        rename($oldFilename, $newFilename);
                    }
                    $driverFn['save']((object)['sessname' => $sessname]);
                }

                // Zmaž staré súbory
                foreach (glob($this->dir . "/" . $oldSessionId . '_*.php') as $file) {
                    unlink($file);
                }

                // Aktualizuj cookie
                $this->setSessionCookie();
            }
            return $this->sessionId;
        };

        // Funkcia GC (Garbage Collection)
        // Volat cez DSM::use()->gc();
        $driverFn['gc'] = function ($dsm) use (&$driverFn) {
            // Načítaj zoznam všetkých súborov
            $files = glob($this->dir . '/*.php');
            $processedSessionIds = [];

            while (!empty($files)) {
                $file = array_shift($files);
                $basename = basename($file, '.php');
                $sessionId = explode('_', $basename)[0];

                // Preskoč, ak už bolo SESSIONID spracované
                if (in_array($sessionId, $processedSessionIds)) {
                    continue;
                }

                // Spracuj súbor
                ob_start();
                include $file;
                $output = ob_get_clean();

                // Označ SESSIONID ako spracované
                $processedSessionIds[] = $sessionId;

                if (strpos($output, 'expired;') === 0) {
                    // Zmaž všetky súbory pre toto SESSIONID
                    foreach ($files as $index => $relatedFile) {
                        $relatedBasename = basename($relatedFile, '.php');
                        if (strpos($relatedBasename, $sessionId . '_') === 0) {
                            unlink($relatedFile);
                            unset($files[$index]);
                        }
                    }
                    // Zmaž aktuálny súbor (ak ešte existuje)
                    if (file_exists($file)) {
                        unlink($file);
                    }
                } else {
                    // Vylúč nespracované súbory pre toto SESSIONID
                    foreach ($files as $index => $relatedFile) {
                        $relatedBasename = basename($relatedFile, '.php');
                        if (strpos($relatedBasename, $sessionId . '_') === 0) {
                            unset($files[$index]);
                        }
                    }
                }
            }
        };

        return $driverFn;
    }
}
?>
