<?php

namespace Dotsystems\App\Parts;

class Config {
    private static $settings = [
        'session' => [
            'driver' => 'default', // Možnosti: default, php, database, redis, file
            'lifetime' => 3600, // Expiracie v sekundach
            'cookie_name' => 'dotapp_session',
            'secure' => true, // Používať iba HTTPS
            'httponly' => true, // Ochrana pred XSS
            'samesite' => 'Strict', // Ochrana pred CSRF
            'database_table' => 'dotapp_sessions', // Pre databázové session
            'redis_prefix' => 'session:', // Pre Redis
        ],
        'database' => [
            'host' => 'localhost',
            'name' => 'mydb',
            'user' => 'root',
            'password' => ''
        ],
        // Ďalšie konfigurácie...
    ];

    private static $sessionDrivers = [];

    public static function session($key,$value=null) {
        if ($value === null) {
            return self::$settings['session'][$key] ?? null;
        } else {
            self::$settings['session'][$key] = $value;
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