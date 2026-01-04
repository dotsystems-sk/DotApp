<?php

/**
 * CLASS Config
 *
 * This class manages configuration settings for the DotApp framework,
 * providing a centralized and flexible way to store and retrieve application,
 * database, and session configurations. It supports dynamic setting and retrieval
 * of configuration values, as well as session driver management.
 *
 * Key Features:
 * - Centralized storage of configuration settings for database, application, and sessions.
 * - Support for nested configuration retrieval and modification using get() and set() methods.
 * - Session configuration management with options for lifetime, security, and storage drivers.
 * - Custom session driver registration and validation for flexible session handling.
 * - Secure handling of sensitive data like encryption keys.
 * - Dynamic configuration section handling via __callStatic() for module extensibility.
 * - Custom handler registration via fn() for advanced module configuration.
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

namespace Dotsystems\App\Parts;

use \Dotsystems\App\DotApp;

// -----------------------------------------------------------------------------
// The configuration must be filled in before creating an instance of DotApp !!!
// -----------------------------------------------------------------------------

class Config
{
    public const IF_NOT_EXIST = true;

    private static $settings = [
        'databases' => [],
        'searchEngines' => [
            'driver' => 'default',
            // Elasticsearch
            'elasticsearch_host' => 'https://127.0.0.1:9200',
            'elasticsearch_username' => '', // Username (if authentication is enabled)
            'elasticsearch_password' => '', // Password (if authentication is enabled)
            'elasticsearch_ca_fingerprint' => '', // SHA-256 fingerprint of the CA certificate (optional)
            'elasticsearch_ca_file' => '', // Path to the CA certificate file (optional, e.g., '/path/to/ca.crt')

            // OpenSearch
            'opensearch_host' => 'https://127.0.0.1:9200',
            'opensearch_username' => '', // Username
            'opensearch_password' => '', // Password
            'opensearch_ca_fingerprint' => '', // SHA-256 fingerprint of the CA certificate
            'opensearch_ca_file' => '', // Path to the CA certificate file

            // Meilisearch
            'meilisearch_host' => 'http://127.0.0.1:7700',
            'meilisearch_api_key' => '', // API key for authentication
            'meilisearch_ca_fingerprint' => '', // SHA-256 fingerprint of the CA certificate (optional)
            'meilisearch_ca_file' => '', // Path to the CA certificate file (optional)

            // Algolia
            'algolia_app_id' => '', // Application ID from Algolia dashboard
            'algolia_search_api_key' => '', // Search API Key (read-only)
            'algolia_write_api_key' => '', // Write API Key (for indexing, updates, deletes)
            'algolia_wait_for_task' => true, // Wait for task to complete (true) or return immediately (false)
            'algolia_ca_fingerprint' => '', // SHA-256 fingerprint of the CA certificate (optional)
            'algolia_ca_file' => '', // Path to the CA certificate file (optional)

            // Typesense
            'typesense_host' => 'http://localhost:8108', // URL to the Typesense instance
            'typesense_api_key' => '', // API key for authentication
            'typesense_ca_fingerprint' => '', // SHA-256 fingerprint of the CA certificate (optional)
            'typesense_ca_file' => '', // Path to the CA certificate file (optional)
        ],
        'db' => [
            'prefix' => 'dotapp_', // Predpona v databaze
            'driver' => 'pdo', // Nazov default drivera zvoleneho uzivatelom
            'maindb' => 'main', // Nazov hlavnej databazy ak si ju uzivatel pomenoval inak nez main, moduly si to nacitaju
            'cache' => false, // Allow cache for queries?
        ],
        'dotapp' => [
            'version' => '1.6',
        ],
        'app' => [
            'name' => 'dotApp123456',
            'name_hash' => '', // Do not fill this!
            'c_enc_key' => 'K9xP7mW3qT2rY6vL8cF4hD5aE0zJ1nB2X7bP9qRtY2mW4kZjN6vL8cF3hD5aE0xQ', // Set the encryption key here, or use Config::set("app","c_enc_key","YOUR KEY");
            'version' => '1.0',
        ],
        'session' => [
            'driver' => 'default', // default - preconfigured
            'lifetime' => 3600, // Expiration in seconds
            'rm_always_use' => false, // Always use REMEMBER ME (true)? Or leave it to the user (false)
            'rm_autologin' => false, // Should autologin be performed automatically (true), or handled by the user (false)?
            'rm_lifetime' => 2592000, // Remember me lifetime, default: 30 days
            'cookie_name' => 'dotapp_session',
            'path' => "/",
            'secure' => false, // Use only HTTPS
            'httponly' => true, // Protection against XSS
            'samesite' => 'Strict', // Protection against CSRF
            'database_use' => false, // Use database-stored sessions for logout?
            'database_table' => 'users_sessions', // For database sessions, <db[prefix]>users_sessions
            'redis_host' => '127.0.0.1', // Redis host
            'redis_port' => 6379, // Redis port
            'redis_timeout' => 2, // Timeout for Redis connection
            'redis_password' => '', // Timeout for Redis connection
            'redis_persistent' => false, // Persistent connection to Redis?
            'redis_database' => 0, // Database number for Redis
            'redis_prefix' => 'session:', // For Redis
            'file_driver_dir' => '/app/runtime/SessionDriverFile', // Directory setting for storing SessionDriverFile
            'file_driver_dir2' => '/app/runtime/SessionDriverFile2', // Directory setting for storing SessionDriverFile2
        ],
        'cache' => [
            'use' => false,
            'driver' => 'default',
            'folder' => '/',
            'lifetime' => 36000,
            'prefix' => 'dotapp_',
            'gc_probability' => 1,
            'redis_host' => '127.0.0.1',
            'redis_port' => 6379,
            'redis_timeout' => 2,
            'redis_password' => '',
            'redis_database' => 0,
            'memcached_host' => '127.0.0.1',
            'memcached_port' => 11211,
        ],
        'totp' => [
            'issuer' => 'DotApp',
            'algorithm' => 'SHA256',
            'digits' => 6,
            'period' => 30,
        ],
        'modules' => [],
        'bridge' => [
            'storage_limit' => 200, // Kolko zaznamov sa bude uchovavat per session
        ],
        'router' => [
            'match_cache' => true,
        ],
        'emailer' => [],
        'logger' => [
            'driver' => 'default', // default or file
            'log_levels' => ['emergency', 'alert', 'critical', 'error', 'warning'], // Default log levels to process
            'folder' => 'default', // Subfolder for file driver
            'max_files' => 7, // Max log files before rotation (file driver)
            'max_size' => 10485760, // Max file size in bytes (10MB, file driver)
            'core_log_enabled' => false, // Enable core logging, if false -> logging is available only via hook dotapp.log
        ],
    ];

    private static $sessionDrivers = [];
    private static $cacheDrivers = [];
    private static $searchDrivers = [];
    private static $loggerDrivers = [];
    private static $customHandlers = [];

    /* Robust way for custom configuration handling either by registering callable or using autmatic configuration key creation */

    public static function fn($name, $handler, $sectionKey = null)
    {
        if (!is_callable($handler)) {
            throw new \Exception("Handler must be callable.");
        }

        self::$customHandlers[$name] = $handler;

        if ($sectionKey !== null) {
            if (!isset(self::$settings[$sectionKey])) {
                self::$settings[$sectionKey] = [];
            }
        }
    }

    public static function __callStatic($name, $arguments)
    {
        if (isset(self::$customHandlers[$name])) {
            return call_user_func_array(self::$customHandlers[$name], $arguments);
        }

        $section = $name;

        if (!isset(self::$settings[$section])) {
            self::$settings[$section] = [];
        }

        $key = $arguments[0] ?? null;
        $value = $arguments[1] ?? null;

        if ($value === null) {
            // Getter
            if ($key === null) {
                return self::$settings[$section] ?? null;
            }
            return self::$settings[$section][$key] ?? null;
        } else {
            // Setter
            if ($key === null) {
                if (is_array($value)) {
                    self::$settings[$section] = $value;
                } else {
                    throw new \Exception("If key is null, value must be an array for section '$section'.");
                }
            } else {
                self::$settings[$section][$key] = $value;
            }
        }
    }

    /* Built in config functions */

    public static function logger($key, $value = null)
    {
        if ($value === null) {
            return self::$settings['logger'][$key] ?? null;
        } else {
            self::$settings['logger'][$key] = $value;
        }
    }

    public static function router($key, $value = null)
    {
        if ($value === null) {
            return self::$settings['router'][$key] ?? null;
        } else {
            self::$settings['router'][$key] = $value;
        }
    }

    public static function email($account, $key, $value = null)
    {
        if ($value === null) {
            if ($key === null) {
                return self::$settings['emailer'][$account] ?? null;
            }
            return self::$settings['emailer'][$account][$key] ?? null;
        } else {
            if ($key === null) {
                if (is_array($value)) {
                    self::$settings['emailer'][$account] = $value;
                } else {
                    throw new \Exception("If key is null, then value must be an array");
                }
            } else {
                self::$settings['emailer'][$account][$key] = $value;
            }
        }
    }

    public static function searchEngines($key, $value = null)
    {
        if ($value === null) {
            return self::$settings['searchEngines'][$key] ?? null;
        } else {
            self::$settings['searchEngines'][$key] = $value;
        }
    }

    public static function session($key, $value = null)
    {
        if ($value === null) {
            return self::$settings['session'][$key] ?? null;
        } else {
            self::$settings['session'][$key] = $value;
        }
    }

    public static function addDatabase($name, $host, $username, $password, $database, $charset, $type, $driver)
    {
        self::$settings['databases'][$name] = [
            'type' => $type,
            'host' => $host,
            'username' => $username,
            'password' => $password,
            'database' => $database,
            'charset' => $charset,
            'driver' => $driver,
        ];
    }

    public static function cache($key, $value = null)
    {
        if ($value === null) {
            return self::$settings['cache'][$key] ?? null;
        } else {
            self::$settings['cache'][$key] = $value;
        }
    }

    public static function bridge($key, $value = null)
    {
        if ($value === null) {
            return self::$settings['bridge'][$key] ?? null;
        } else {
            self::$settings['bridge'][$key] = $value;
        }
    }

    public static function db($key, $value = null)
    {
        if ($value === null) {
            return self::$settings['db'][$key] ?? null;
        } else {
            self::$settings['db'][$key] = $value;
        }
    }

    public static function totp($key, $value = null)
    {
        if ($value === null) {
            return self::$settings['totp'][$key] ?? null;
        } else {
            self::$settings['totp'][$key] = $value;
        }
    }

    public static function app($key, $value = null)
    {
        if ($value === null) {
            return self::$settings['app'][$key] ?? null;
        } else {
            self::$settings['app'][$key] = $value;
        }
    }

    public static function get($settingName, $key = null)
    {
        if ($key === null) return self::$settings[$settingName] ?? null;
        return self::$settings[$settingName][$key] ?? null;
    }

    public static function set($settingName, $key, $value = null)
    {
        if ($value === null) {
            $value = $key;
            self::$settings[$settingName] = $value;
        } else {
            if (!isset(self::$settings[$settingName])) self::$settings[$settingName] = [];
            if ($key === null) self::$settings[$settingName] = $value;
            else self::$settings[$settingName][$key] = $value;
        }
    }

    /**
     * Static method to get or set configuration values specific to a module.
     *
     * This method allows retrieving or updating configuration entries stored
     * under the `modules` section of the global configuration array. It supports:
     *
     * - Getting the whole module configuration
     * - Getting a specific key from a module
     * - Setting a specific key or whole module configuration
     * - Optionally setting a value only if it has not been defined yet (default-value pattern)
     *
     * @param string $moduleName         Name of the module.
     * @param string|null $key           Optional key within the module. If null, returns the whole module configuration.
     * @param mixed|null $value          Optional value to set. If null, the function acts as a getter.
     * @param bool $onlyIfNotExist       Optional flag to only set the value **if it does not exist already**.
     *                                   Use Config::IF_NOT_EXIST for readability.
     *
     * @return mixed|null Returns the requested configuration value, or null if not set.
     *
     * ### Usage examples:
     *
     * // GET: Retrieve entire module configuration
     * Config::module("auth");
     *
     * // GET: Retrieve a specific key from module
     * Config::module("auth", "enable2FA");
     *
     * // SET: Set value unconditionally
     * Config::module("auth", "enable2FA", true);
     *
     * // SET-IF-NOT-EXISTS: Only set if the value is not already defined
     * Config::module("auth", "maxLoginAttempts", 5, Config::IF_NOT_EXIST);
     *
     * // ⚠️ IMPORTANT NOTE on IF_NOT_EXIST behavior:
     * // If a configuration key already exists, it will NOT be overwritten,
     * // even if a new value is passed.
     *
     * // Example:
     * // Let's say in config we already have:
     * // "uploadLimit" => 700
     *
     * Config::module("media", "uploadLimit", 100, Config::IF_NOT_EXIST);
     *
     * // Result:
     * // The value remains 700 because it was already defined — the new value 100 is ignored.
     */

    public static function module($moduleName, $key = null, $value = null, $onlyIfNotExist = false)
    {
        if ($value === null) {
            // Getter
            if (!isset(self::$settings['modules'][$moduleName])) {
                return null;
            }

            if ($key === null) {
                return self::$settings['modules'][$moduleName]; // Celý modul
            } else {
                return self::$settings['modules'][$moduleName][$key] ?? null; // Konkrétny kľúč
            }
        } else {
            // Setter / or 'set if not exist'
            if (!isset(self::$settings['modules'][$moduleName])) {
                self::$settings['modules'][$moduleName] = [];
            }

            if ($key === null) {
                // Nastavenie celého modulu
                self::$settings['modules'][$moduleName] = $value;
                return $value;
            } else {
                if ($onlyIfNotExist) {
                    if (!isset(self::$settings['modules'][$moduleName][$key])) {
                        self::$settings['modules'][$moduleName][$key] = $value;
                    }
                    return self::$settings['modules'][$moduleName][$key];
                } else {
                    self::$settings['modules'][$moduleName][$key] = $value;
                    return $value;
                }
            }
        }
    }



    public static function sessionDriver($name, $driver = null)
    {
        if ($driver === null) {
            if (isset(self::$sessionDrivers[$name])) return self::$sessionDrivers[$name];
            throw new \Exception("Driver " . $name . " not defined !");
        } else {
            if (isset($driver['load']) && isset($driver['save']) && isset($driver['get']) && isset($driver['set']) && isset($driver['delete']) && isset($driver['clear'])) {
                if (is_callable($driver['load']) && is_callable($driver['save']) && is_callable($driver['get']) && is_callable($driver['set']) && is_callable($driver['delete']) && is_callable($driver['clear'])) {
                    self::$sessionDrivers[$name] = $driver;
                } else {
                    throw new \Exception("All driver functions must be callable !");
                }
            } else {
                throw new \Exception("Incompatible driver !");
            }
        }
    }

    public static function cacheDriver($name, $driver = null)
    {
        if ($driver === null) {
            if (isset(self::$cacheDrivers[$name])) {
                return self::$cacheDrivers[$name];
            }
            throw new \Exception("Cache driver " . $name . " not defined!");
        } else {
            if (
                isset($driver['save']) &&
                isset($driver['load']) &&
                isset($driver['exists']) &&
                isset($driver['delete']) &&
                isset($driver['clear']) &&
                isset($driver['gc'])
            ) {
                if (
                    is_callable($driver['save']) &&
                    is_callable($driver['load']) &&
                    is_callable($driver['exists']) &&
                    is_callable($driver['delete']) &&
                    is_callable($driver['clear']) &&
                    is_callable($driver['gc'])
                ) {
                    self::$cacheDrivers[$name] = $driver;
                } else {
                    throw new \Exception("All cache driver functions must be callable!");
                }
            } else {
                throw new \Exception("Incompatible cache driver!");
            }
        }
    }

    public static function searchDriver($name, $driver = null)
    {
        if ($driver === null) {
            if (isset(self::$searchDrivers[$name])) {
                return self::$searchDrivers[$name];
            }
            throw new \Exception("Search driver " . $name . " not defined!");
        } else {
            if (
                isset($driver['index']) &&
                isset($driver['search']) &&
                isset($driver['update']) &&
                isset($driver['delete']) &&
                isset($driver['clear']) &&
                is_callable($driver['index']) &&
                is_callable($driver['search']) &&
                is_callable($driver['update']) &&
                is_callable($driver['delete']) &&
                is_callable($driver['clear'])
            ) {
                self::$searchDrivers[$name] = $driver;
            } else {
                throw new \Exception("Incompatible search driver!");
            }
        }
    }

    public static function loggerDriver($name, $driver = null)
    {
        if ($driver === null) {
            if (isset(self::$loggerDrivers[$name])) {
                return self::$loggerDrivers[$name];
            }
            throw new \Exception("Logger driver " . $name . " not defined!");
        } else {
            if (
                isset($driver['log']) &&
                isset($driver['rotate']) &&
                isset($driver['clean']) &&
                is_callable($driver['log']) &&
                is_callable($driver['rotate']) &&
                is_callable($driver['clean'])
            ) {
                self::$loggerDrivers[$name] = $driver;
            } else {
                throw new \Exception("Incompatible logger driver!");
            }
        }
    }

    public static function firewallFn($firewallFunction = null)
    {
        return RequestObj::firewallFn($firewallFunction);
    }
}
