<?php
namespace Dotsystems\App\Parts;
use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\Config;

/**
 * CLASS SessionDriverRedis - Redis-Based Session Driver Implementation
 *
 * This class provides a Redis-based session management functionality for the DotApp framework.
 * It handles session operations including initialization, storage, retrieval,
 * and management of session variables using Redis as the storage backend.
 * The driver bypasses PHP's native $_SESSION, ensuring direct Redis interaction.
 * It implements a singleton pattern for consistent session management.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.7 FREE
 * @license   MIT License
 * @date      2025
 *
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the
 * following condition: You **must** retain this header in all copies or
 * substantial portions of the code, including the author and company information.
 */
class SessionDriverRedis {
    private $redis;
    private $sessionDataStorage = null;
    private $sessionId;
    private $isActive = false;
    private static $driver = null;

    /**
     * Get or create the singleton instance of the Redis session driver.
     *
     * @return array Driver functions
     */
    public static function driver() {
        if (self::$driver === null) {
            self::$driver = new self();
            return self::$driver->getDriver();
        }
        return self::$driver->getDriver();
    }

    /**
     * Constructor: Initialize Redis connection and session.
     */
    function __construct() {
        // Validate required configuration settings
        $requiredConfigs = [
            'redis_host' => Config::session('redis_host'),
            'redis_port' => Config::session('redis_port'),
            'redis_timeout' => Config::session('redis_timeout'),
            'redis_database' => Config::session('redis_database'),
            'redis_prefix' => Config::session('redis_prefix'),
            'cookie_name' => Config::session('cookie_name'),
            'lifetime' => Config::session('lifetime'),
            'path' => Config::session('path'),
            'secure' => Config::session('secure'),
            'httponly' => Config::session('httponly'),
            'samesite' => Config::session('samesite')
        ];

        foreach ($requiredConfigs as $key => $value) {
            if (is_null($value) || $value === '') {
                throw new \Exception("Missing or invalid configuration for '$key' in session settings.");
            }
        }

        // Initialize Redis connection
        $this->redis = new \Redis();
        $host = $requiredConfigs['redis_host'];
        $port = $requiredConfigs['redis_port'];
        $timeout = $requiredConfigs['redis_timeout'];
        $password = Config::session('redis_password'); // Password can be empty
        $database = $requiredConfigs['redis_database'];
        $persistent = Config::session('redis_persistent', false);

        try {
            if ($persistent) {
                $this->redis->pconnect($host, $port, $timeout);
            } else {
                $this->redis->connect($host, $port, $timeout);
            }
            if ($password !== '') {
                $this->redis->auth($password);
            }
            $this->redis->select($database);
        } catch (\RedisException $e) {
            throw new \Exception("Failed to connect to Redis: " . $e->getMessage());
        }

        // Validate or generate session ID
        $this->sessionId = $this->getSessionId();
        // Set session cookie
        $this->setSessionCookie();
        $this->isActive = true;
        // Initialize sessionDataStorage
        if ($this->sessionDataStorage === null) {
            $this->sessionDataStorage = [];
            $this->sessionDataStorage['values'] = [];
            $this->sessionDataStorage['variables'] = [];
        }
    }

    /**
     * Generate or validate session ID.
     *
     * @return string Session ID
     */
    private function getSessionId() {
        $cookieName = Config::session('cookie_name');
        $prefix = Config::session('redis_prefix');
        if (isset($_COOKIE[$cookieName])) {
            $sessionId = $_COOKIE[$cookieName];
            // Ensure session ID contains only alphanumeric characters and is 64 chars long
            if (preg_match('/^[a-zA-Z0-9]{64}$/', $sessionId)) {
                // Check if any session data exists for this session ID
                $keys = $this->redis->keys("{$prefix}{$sessionId}:*");
                if (!empty($keys)) {
                    // Log for debugging
                    DotApp::DotApp()->Logger->info("SessionDriverRedis: Reusing session ID '$sessionId' found in Redis with keys: " . implode(', ', $keys));
                    return $sessionId;
                }
                // Log why session ID is not reused
                DotApp::DotApp()->Logger->warning("SessionDriverRedis: Session ID '$sessionId' found in cookie but no matching keys in Redis");
            } else {
                DotApp::DotApp()->Logger->warning("SessionDriverRedis: Invalid session ID format in cookie: '$sessionId'");
            }
        }

        // Generate new session ID
        do {
            $sessionId = bin2hex(random_bytes(32)); // 64-character session ID
        } while (!empty($this->redis->keys("{$prefix}{$sessionId}:*")));
        DotApp::DotApp()->Logger->info("SessionDriverRedis: Generated new session ID: '$sessionId'");

        return $sessionId;
    }

    /**
     * Set or delete session cookie.
     *
     * @param bool $delete Whether to delete the cookie
     * @return bool Success of cookie setting
     */
    private function setSessionCookie($delete = false) {
        $cookieName = Config::session('cookie_name');
        $value = $delete ? '' : $this->sessionId;
        $expires = $delete ? time() - 3600 : time() + Config::session('lifetime');

        if (headers_sent($file, $line)) {
            DotApp::DotApp()->Logger->warning("SessionDriverRedis: Cannot set cookie '$cookieName' - headers already sent in $file:$line");
            return false;
        }

        $result = setcookie(
            $cookieName,
            $value,
            [
                'expires' => $expires,
                'path' => Config::session('path'),
                'secure' => Config::session('secure'),
                'httponly' => Config::session('httponly'),
                'samesite' => Config::session('samesite')
            ]
        );

        if (!$result) {
            DotApp::DotApp()->Logger->error("SessionDriverRedis: Failed to set cookie '$cookieName'");
        }

        return $result;
    }

    /**
     * Get Redis key for a specific sessname.
     *
     * @param string $sessname Session name
     * @return string Redis key
     */
    private function getRedisKey($sessname) {
        $safeSessname = preg_replace('/[^a-zA-Z0-9_]/', '_', $sessname);
        $prefix = Config::session('redis_prefix');
        $key = "{$prefix}{$this->sessionId}:$safeSessname";
        return $key;
    }

    /**
     * Get driver functions.
     *
     * @return array Driver functions
     */
    private function getDriver() {
        $driverFn = [];

        // START function
        $driverFn['start'] = function ($dsm) use (&$driverFn) {
            if ($this->isActive) {
                return $this;
            }

            $this->setSessionCookie();
            $driverFn['load']($dsm);
            $this->isActive = true;
            DotApp::DotApp()->Logger->info("SessionDriverRedis: Started session for sessname '{$dsm->sessname}' with ID '{$this->sessionId}'");
            return $this;
        };

        // STATUS function
        $driverFn['status'] = function ($dsm) {
            $status = $this->isActive ? PHP_SESSION_ACTIVE : PHP_SESSION_NONE;
            return $status;
        };

        // SAVE function
        $driverFn['save'] = function ($dsm) {
            if ($this->sessionDataStorage === null) {
                DotApp::DotApp()->Logger->error("SessionDriverRedis: Cannot save - sessionDataStorage is null for sessname '{$dsm->sessname}'");
                return false;
            }

            $sessionData = [
                'values' => $this->sessionDataStorage['values'][$dsm->sessname] ?? [],
                'variables' => $this->sessionDataStorage['variables'][$dsm->sessname] ?? []
            ];

            $key = $this->getRedisKey($dsm->sessname);
            $data = serialize($sessionData);
            $lifetime = Config::session('lifetime');

            try {
                $this->redis->setex($key, $lifetime, $data);
                return true;
            } catch (\RedisException $e) {
                DotApp::DotApp()->Logger->error("SessionDriverRedis: Failed to save session to Redis for key '$key': " . $e->getMessage());
                throw new \Exception("Failed to save session to Redis: " . $e->getMessage());
            }
        };

        // DESTROY function
        $driverFn['destroy'] = function ($dsm) use (&$driverFn) {
            $key = $this->getRedisKey($dsm->sessname);
            try {
                $this->redis->del($key);
                DotApp::DotApp()->Logger->info("SessionDriverRedis: Destroyed session for key '$key'");
            } catch (\RedisException $e) {
                DotApp::DotApp()->Logger->error("SessionDriverRedis: Failed to destroy session in Redis for key '$key': " . $e->getMessage());
                throw new \Exception("Failed to destroy session in Redis: " . $e->getMessage());
            }

            $this->sessionDataStorage['values'][$dsm->sessname] = [];
            $this->sessionDataStorage['variables'][$dsm->sessname] = [];
            $this->isActive = false;
            $this->setSessionCookie(true);
        };

        // LOAD function
        $driverFn['load'] = function ($dsm) use (&$driverFn) {
            if (!isset($this->sessionDataStorage['values'][$dsm->sessname]) &&
                !isset($this->sessionDataStorage['variables'][$dsm->sessname])) {
                $key = $this->getRedisKey($dsm->sessname);
                try {
                    $data = $this->redis->get($key);
                    if ($data !== false) {
                        $sessionData = unserialize($data);
                        if ($sessionData && is_array($sessionData)) {
                            $this->sessionDataStorage['values'][$dsm->sessname] = $sessionData['values'] ?? [];
                            $this->sessionDataStorage['variables'][$dsm->sessname] = $sessionData['variables'] ?? [];
                            $driverFn['save']($dsm); // Update TTL
                        }
                    } else {
                        $this->sessionDataStorage['values'][$dsm->sessname] = [];
                        $this->sessionDataStorage['variables'][$dsm->sessname] = [];
                    }
                } catch (\RedisException $e) {
                    DotApp::DotApp()->Logger->error("SessionDriverRedis: Failed to load session from Redis for key '$key': " . $e->getMessage());
                    throw new \Exception("Failed to load session from Redis: " . $e->getMessage());
                }
            }
            return $this;
        };

        // GET function
        $driverFn['get'] = function ($name, $dsm) {
            if (isset($this->sessionDataStorage['variables'][$dsm->sessname][$name])) {
                $varid = $this->sessionDataStorage['variables'][$dsm->sessname][$name];
                $value = $this->sessionDataStorage['values'][$dsm->sessname][$varid] ?? null;

                if ($value && !is_array($value) && strpos($value, 'O:') === 0) {
                    $value = unserialize($value);
                }

                return $value;
            }

            return null;
        };

        // SET function
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

        // DELETE function
        $driverFn['delete'] = function ($name, $dsm) use (&$driverFn) {
            if (isset($this->sessionDataStorage['variables'][$dsm->sessname][$name])) {
                $varid = $this->sessionDataStorage['variables'][$dsm->sessname][$name];
                unset($this->sessionDataStorage['variables'][$dsm->sessname][$name]);
                unset($this->sessionDataStorage['values'][$dsm->sessname][$varid]);
                $driverFn['save']($dsm);
            }
        };

        // CLEAR function
        $driverFn['clear'] = function ($dsm) use (&$driverFn) {
            $this->sessionDataStorage['values'][$dsm->sessname] = [];
            $this->sessionDataStorage['variables'][$dsm->sessname] = [];
            $driverFn['save']($dsm);
            DotApp::DotApp()->Logger->info("SessionDriverRedis: Cleared session data for sessname '{$dsm->sessname}'");
        };

        // REGENERATE_ID function
        $driverFn['regenerate_id'] = function ($deleteOld, $dsm) use (&$driverFn) {
            $oldSessionId = $this->sessionId;
            $prefix = Config::session('redis_prefix');

            // Generate new session ID
            do {
                $newSessionId = bin2hex(random_bytes(32));
            } while (!empty($this->redis->keys("{$prefix}{$newSessionId}:*")));

            $this->sessionId = $newSessionId;

            // Transfer data for each sessname
            foreach (array_keys($this->sessionDataStorage['values']) as $sessname) {
                $oldKey = "{$prefix}{$oldSessionId}:" . preg_replace('/[^a-zA-Z0-9_]/', '_', $sessname);
                $newKey = $this->getRedisKey($sessname);
                try {
                    if ($this->redis->exists($oldKey)) {
                        $data = $this->redis->get($oldKey);
                        $lifetime = Config::session('lifetime');
                        $this->redis->setex($newKey, $lifetime, $data);
                        if ($deleteOld) {
                            $this->redis->del($oldKey);
                        }
                    }
                    $driverFn['save']((object)['sessname' => $sessname]);
                } catch (\RedisException $e) {
                    DotApp::DotApp()->Logger->error("SessionDriverRedis: Failed to regenerate session ID from '$oldKey' to '$newKey': " . $e->getMessage());
                    throw new \Exception("Failed to regenerate session ID in Redis: " . $e->getMessage());
                }
            }

            // Update cookie
            $this->setSessionCookie();
            DotApp::DotApp()->Logger->info("SessionDriverRedis: Regenerated session ID from '$oldSessionId' to '$newSessionId'");
        };

        // SESSION_ID function
        $driverFn['session_id'] = function ($new, $dsm) use (&$driverFn) {
            $prefix = Config::session('redis_prefix');
            if ($new !== null) {
                $oldSessionId = $this->sessionId;

                if (!empty($this->redis->keys("{$prefix}{$new}:*"))) {
                    DotApp::DotApp()->Logger->error("SessionDriverRedis: Session ID '$new' already exists");
                    throw new \Exception("Session ID $new already exists");
                }

                $this->sessionId = $new;

                // Transfer data for each sessname
                foreach (array_keys($this->sessionDataStorage['values']) as $sessname) {
                    $oldKey = "{$prefix}{$oldSessionId}:" . preg_replace('/[^a-zA-Z0-9_]/', '_', $sessname);
                    $newKey = $this->getRedisKey($sessname);
                    try {
                        if ($this->redis->exists($oldKey)) {
                            $data = $this->redis->get($oldKey);
                            $lifetime = Config::session('lifetime');
                            $this->redis->setex($newKey, $lifetime, $data);
                            $this->redis->del($oldKey);
                        }
                        $driverFn['save']((object)['sessname' => $sessname]);
                    } catch (\RedisException $e) {
                        DotApp::DotApp()->Logger->error("SessionDriverRedis: Failed to set session ID from '$oldKey' to '$newKey': " . $e->getMessage());
                        throw new \Exception("Failed to set session ID in Redis: " . $e->getMessage());
                    }
                }

                // Update cookie
                $this->setSessionCookie();
                DotApp::DotApp()->Logger->info("SessionDriverRedis: Set new session ID '$new'");
            }
            return $this->sessionId;
        };

        // GC (Garbage Collection) function
        $driverFn['gc'] = function ($dsm) {
            $prefix = Config::session('redis_prefix');
            try {
                $keys = $this->redis->keys("{$prefix}*");
                foreach ($keys as $key) {
                    // Redis handles expiration via SETEX TTL, but check for negative TTL
                    if ($this->redis->ttl($key) < 0) {
                        $this->redis->del($key);
                    }
                }
                DotApp::DotApp()->Logger->info("SessionDriverRedis: Garbage collection completed, checked " . count($keys) . " keys");
            } catch (\RedisException $e) {
                DotApp::DotApp()->Logger->error("SessionDriverRedis: Failed to perform garbage collection in Redis: " . $e->getMessage());
                throw new \Exception("Failed to perform garbage collection in Redis: " . $e->getMessage());
            }
        };

        return $driverFn;
    }
}
?>