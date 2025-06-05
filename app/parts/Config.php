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

use \Dotsystems\App\DotApp;

// -----------------------------------------------------------------------------
// The configuration must be filled in before creating an instance of DotApp !!!
// -----------------------------------------------------------------------------

class Config {
    private static $settings = [
        'databases' => [
        ],
        'db' => [
            'prefix' => 'dotapp_', // Predpona v databaze
            'driver' => 'pdo', // Nazov default drivera zvoleneho uzivatelom
            'maindb' => 'main' // Nazov hlavnej databazy ak si ju uzivatel pomenoval inak nez main, moduly si to nacitaju
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
        'emailer' => [
        ],
    ];

    private static $sessionDrivers = [];

    public static function router($key,$value=null) {
        if ($value === null) {
            return self::$settings['router'][$key] ?? null;
        } else {
            self::$settings['router'][$key] = $value;
        }        
    }

    public static function email($account,$key,$value=null) {
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
                    throw new \Exception("if key is null, then value must be an array");
                }                
            } else {
                self::$settings['emailer'][$account][$key] = $value;
            }
            
        }        
    }

    public static function session($key,$value=null) {
        if ($value === null) {
            return self::$settings['session'][$key] ?? null;
        } else {
            self::$settings['session'][$key] = $value;
        }        
    }

    public static function addDatabase($name,$host,$username,$password,$database,$charset,$type,$driver) {
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

    public static function bridge($key,$value=null) {
        if ($value === null) {
            return self::$settings['bridge'][$key] ?? null;
        } else {
            self::$settings['bridge'][$key] = $value;
        }        
    }

	public static function db($key,$value=null) {
        if ($value === null) {
            return self::$settings['db'][$key] ?? null;
        } else {
            self::$settings['db'][$key] = $value;
        }        
    }

    public static function totp($key,$value=null) {
        if ($value === null) {
            return self::$settings['totp'][$key] ?? null;
        } else {
            self::$settings['totp'][$key] = $value;
        }        
    }
	
	public static function app($key,$value=null) {
        if ($value === null) {
            return self::$settings['app'][$key] ?? null;
        } else {
            self::$settings['app'][$key] = $value;
        }        
    }

    public static function get($settingName,$key=null) {
        if ($key === null) return self::$settings[$settingName] ?? null;
        return self::$settings[$settingName][$key] ?? null;
    }

    public static function set($settingName,$key,$value=null) {
        if ($value === null) {
            $value=$key;
            self::$settings[$settingName] = $value;
        } else {
            if (!isSet(self::$settings[$settingName])) self::$settings[$settingName] = [];
            if ($key===null) self::$settings[$settingName] = $value; else self::$settings[$settingName][$key] = $value;
        }        
    }

    public static function module($moduleName,$key,$value=null) {
        if ($value === null) {
            // Getter
            return self::$settings['modules'][$moduleName]?? null;
        } else {
            // Setter
            if (!isset(self::$settings['modules'][$moduleName])) self::$settings['modules'][$moduleName] = [];
            self::$settings['modules'][$moduleName] = $value;            
        }        
    }

    public static function sessionDriver($name,$driver=null) {
        if ($driver === null) {
            if (isSet(self::$sessionDrivers[$name])) return self::$sessionDrivers[$name];
            throw new \Exception("Driver ".$name." not defined !");
        } else {
            if (isSet($driver['load']) && isSet($driver['save']) && isSet($driver['get']) && isSet($driver['set']) && isSet($driver['delete']) && isSet($driver['clear'])) {
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
    
}

?>