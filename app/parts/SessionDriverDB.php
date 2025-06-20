<?php
namespace Dotsystems\App\Parts;
use \Dotsystems\App\Parts\Config;
use \Dotsystems\App\Parts\DB;

/**
 * CLASS SessionDriverDB - Batabase-Based Session Driver Implementation
 *
 * This class provides a Database-based session management functionality for the DotApp framework.
 * It handles session operations including initialization, storage, retrieval,
 * and management of session variables using a database with enhanced security
 * and performance features, such as per-sessname record splitting and optimized garbage collection.
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
class SessionDriverDB {
    private $sessionId;
    private $isActive = false;
    private $sessionDataStorage = null;
    private static $driver = null;
    private $constructed = false;

    public static function driver() {
        if (self::$driver === null) {
            self::$driver = new self();
            return self::$driver->getDriver();
        }
        return self::$driver->getDriver();
    }

    function __construct() {
        if ($this->sessionDataStorage === null) {
            $this->sessionDataStorage = [];
            $this->sessionDataStorage['values'] = [];
            $this->sessionDataStorage['variables'] = [];
        }
    }

    public function constructLater() {
        if ($this->constructed === false) {
            // Validate or generate session ID
            $this->sessionId = $this->getSessionId();
            // Start drivera
            $this->setSessionCookie();
            $this->isActive = true;
            // Inicializuj sessionDataStorage
        }
        $this->constructed = true;
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
            $result = null;
            DB::module("RAW")
                ->q(function ($qb) use ($sessionId) {
                    $qb
                    ->select(['COUNT(*)'], Config::get("db","prefix").Config::session("database_table"))
                    ->where('session_id','=',$sessionId);
                })
                ->execute(function($navrat, $db, $debug) use (&$result) {
                    if (isset($navrat[0])) {
                        $navrat = $navrat[0];
                        if (isset($navrat['COUNT(*)'])) {
                            $result = (int)$navrat['COUNT(*)'];
                        }
                    }
                });
                
        } while ($result > 0);

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
            $this->constructLater();
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
            $this->constructLater();
            return $this->isActive ? PHP_SESSION_ACTIVE : PHP_SESSION_NONE;
        };

        // Funkcia SAVE
        $driverFn['save'] = function ($dsm) {
            $this->constructLater();
            if ($this->sessionDataStorage === null) {
                return false;
            }
            $expiry = time() + Config::session("lifetime");
            $values = serialize($this->sessionDataStorage['values'][$dsm->sessname] ?? []);
            $variables = serialize($this->sessionDataStorage['variables'][$dsm->sessname] ?? []);
            $sessionId = $this->sessionId;
            $sessname = $dsm->sessname;

            DB::module("RAW")->q(function ($qb) use ($sessionId, $sessname, $values, $variables, $expiry) {
                $qb->insertInto(Config::db("prefix").Config::session("database_table"),
                    [
                        "session_id" => $sessionId,
                        "sessname" => $sessname,
                        "values" => $values,
                        "variables" => $variables,
                        "expiry" => $expiry
                    ]
                    )
                    ->onDuplicateKeyUpdate([
                        "values" => $values,
                        "variables" => $variables
                    ]);
            })->execute(function($navrat, $db, $debug) {
                $navrat = $navrat;
            });
            return true;
        };

        // Funkcia DESTROY
        $driverFn['destroy'] = function ($dsm) {
            $this->constructLater();
            $sessionId = $this->sessionId;
            $sessname = $dsm->sessname;

            DB::module("RAW")->q(
                function ($qb) use ($sessionId, $sessname) {
                    $qb->delete(Config::db("prefix").Config::session("database_table"))
                        ->where('session_id', '=', $sessionId)
                        ->andWhere('sessname', '=', $sessname);
                }
            )->execute();

            $this->sessionDataStorage['values'][$dsm->sessname] = [];
            $this->sessionDataStorage['variables'][$dsm->sessname] = [];
            $this->isActive = false;
            $this->setSessionCookie(true); // Zmaž cookie
        };

        // Funkcia LOAD
        $driverFn['load'] = function ($dsm) use (&$driverFn) {
            $this->constructLater();
            $result = null;
            if (!isset($this->sessionDataStorage['values'][$dsm->sessname]) && 
                !isset($this->sessionDataStorage['variables'][$dsm->sessname])) {
                $sessionId = $this->sessionId;
                $sessname = $dsm->sessname;
                DB::module("RAW")->q(
                    function ($qb) use ($sessionId, $sessname) {
                        $qb
                        ->select(['values', 'variables'],Config::db("prefix").Config::session("database_table"))
                        ->where('session_id', '=', $sessionId)
                        ->andWhere('sessname', '=', $sessname)
                        ->andWhere('expiry', '>', time());
                    }
                )->execute(function($navrat, $db, $debug) use (&$result) {
                    if (isset($navrat[0])) {
                        $result = $navrat[0];
                    }
                });

                if ($result && is_array($result)) {
                    $this->sessionDataStorage['values'][$dsm->sessname] = unserialize($result['values']) ?? [];
                    $this->sessionDataStorage['variables'][$dsm->sessname] = unserialize($result['variables']) ?? [];
                    $driverFn['save']($dsm); // Aktualizuj expiráciu
                } else {
                    $this->sessionDataStorage['values'][$dsm->sessname] = [];
                    $this->sessionDataStorage['variables'][$dsm->sessname] = [];
                }
            }
            return $this;
        };

        // Funkcia GET
        $driverFn['get'] = function ($name, $dsm) use (&$driverFn) {
            $this->constructLater();
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
            $this->constructLater();
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
            $this->constructLater();
            if (isset($this->sessionDataStorage['variables'][$dsm->sessname][$name])) {
                $varid = $this->sessionDataStorage['variables'][$dsm->sessname][$name];
                unset($this->sessionDataStorage['variables'][$dsm->sessname][$name]);
                unset($this->sessionDataStorage['values'][$dsm->sessname][$varid]);
                $driverFn['save']($dsm);
            }
        };

        // Funkcia CLEAR
        $driverFn['clear'] = function ($dsm) use (&$driverFn) {
            $this->constructLater();
            $this->sessionDataStorage['values'][$dsm->sessname] = [];
            $this->sessionDataStorage['variables'][$dsm->sessname] = [];
            $driverFn['save']($dsm);
        };

        // Funkcia REGENERATE_ID
        $driverFn['regenerate_id'] = function ($deleteOld, $dsm) use (&$driverFn) {
            $this->constructLater();
            $oldSessionId = $this->sessionId;

            // Generuj nové session ID
            do {
                $result = null;
                $newSessionId = bin2hex(random_bytes(32));
                $result = DB::module("RAW")
                ->q(function ($qb) use ($token) {
                    $qb
                    ->select(['COUNT(*)'], Config::get("db","prefix").Config::session("database_table"))
                    ->where('session_id','=',$sessionId);
                })
                ->execute(function($navrat, $db, $debug) use (&$result) {
                    if (isset($navrat[0])) {
                        $navrat = $navrat[0];
                        if (isset($navrat['COUNT(*)'])) {
                            $result = (int)$navrat['COUNT(*)'];
                        }
                    }
                });
            } while ($result > 0);

            $this->sessionId = $newSessionId;

            // Prenes dáta pre každé sessname
            foreach (array_keys($this->sessionDataStorage['values']) as $sessname) {
                $values = serialize($this->sessionDataStorage['values'][$sessname] ?? []);
                $variables = serialize($this->sessionDataStorage['variables'][$sessname] ?? []);
                $expiry = time() + Config::session("lifetime");

                DB::module("RAW")->q(
                    function ($qb) use ($oldSessionId, $sessname, $values, $variables, $expiry) {
                        $qb
                        ->insertInto(Config::db("prefix").Config::session("database_table"))
                        ->values("session_id", $oldSessionId)
                        ->values("sessname", $sessname)
                        ->values("values", $values)
                        ->values("variables", $variables)
                        ->values("expiry", $expiry);
                    }
                )->execute();            }

            // Zmaž staré záznamy, ak je deleteOld true
            if ($deleteOld) {
                DB::module("RAW")->q(
                    function ($qb) use ($oldSessionId) {
                        $qb
                        ->deleteFrom(Config::db("prefix").Config::session("database_table"))
                        ->where('session_id', '=', $oldSessionId);
                    }
                )->execute();
            }

            // Aktualizuj cookie
            $this->setSessionCookie();
        };

        // Funkcia SESSION_ID
        $driverFn['session_id'] = function ($new, $dsm) use (&$driverFn) {
            $this->constructLater();
            if ($new !== null) {
                $oldSessionId = $this->sessionId;
                $result = null;

                DB::model("RAW")->q(
                    function ($qb) use ($new) {
                        $qb
                        ->select(['COUNT(*)'], Config::db("prefix").Config::session("database_table"))
                        ->where('session_id', '=', $new);
                    }
                )->execute(function($navrat, $db, $debug) use (&$result) {
                    if (isset($navrat[0])) {
                        $navrat = $navrat[0];
                        if (isset($navrat['COUNT(*)'])) {
                            $result = (int)$navrat['COUNT(*)'];
                        }
                    }
                });

                if ($result > 0) {
                    throw new \Exception("Session ID $new already exists");
                }

                $this->sessionId = $new;

                // Prenes dáta pre každé sessname
                foreach (array_keys($this->sessionDataStorage['values']) as $sessname) {
                    $values = serialize($this->sessionDataStorage['values'][$sessname] ?? []);
                    $variables = serialize($this->sessionDataStorage['variables'][$sessname] ?? []);
                    $expiry = time() + Config::session("lifetime");

                    DB::modul("RAW")->q(
                        function ($qb) use ($new, $sessname, $values, $variables, $expiry) {
                            $qb
                            ->insertInto(Config::db("prefix").Config::session("database_table"))
                            ->values("session_id", $new)
                            ->values("sessname", $sessname)
                            ->values("values", $values)
                            ->values("variables", $variables)
                            ->values("expiry", $expiry);
                        }
                    )->execute();
                }

                DB::module("RAW")->q(
                    function ($qb) use ($oldSessionId) {
                        $qb
                        ->delete(Config::db("prefix").Config::session("database_table"))
                        ->where('session_id', '=', $oldSessionId);
                    }
                )->execute();

                // Aktualizuj cookie
                $this->setSessionCookie();
            }
            return $this->sessionId;
        };

        // Funkcia GC (Garbage Collection)
        // Volat cez DSM::use()->gc();
        $driverFn['gc'] = function ($dsm) use (&$driverFn) {
            $this->constructLater();
            DB::module("RAW")->q(
                function ($qb) {
                    $qb
                    ->delete(Config::db("prefix").Config::session("database_table"))
                    ->where('expiry', '<', time());
                }
            )->execute();        
        };

        return $driverFn;
    }
}
?>