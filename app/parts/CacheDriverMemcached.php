<?php
/**
 * CLASS CacheDriverMemcached - Memcached Cache Driver Implementation
 *
 * This class provides a Memcached-based cache driver for the DotApp framework, supporting distributed
 * high-performance caching. It implements the same interface as other cache drivers, ensuring
 * compatibility with modules that rely on the Cache class.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <stefan@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.7 FREE
 * @date      2014 - 2025
 * @license   MIT License
 */

namespace Dotsystems\App\Parts;

use Dotsystems\App\Parts\Config;

class CacheDriverMemcached {
    private $memcached;
    private $prefix;
    private $name;
    private static $driver = null;

    /**
     * Returns the driver array with callable methods.
     *
     * @return array Driver methods
     */
    public static function driver() {
        if (self::$driver === null) {
            self::$driver = new self();
            return self::$driver->getDriver();
        }
        return self::$driver->getDriver();
    }

    /**
     * Constructor initializes Memcached connection, prefix, and name.
     */
    public function __construct() {
        $this->prefix = Config::cache("prefix") ?? 'dotapp_';
        $this->name = null;
        $this->connectToMemcached();
    }

    /**
     * Establishes connection to Memcached server using config settings.
     */
    private function connectToMemcached() {
        $this->memcached = new \Memcached();
        $host = Config::cache('memcached_host') ?? '127.0.0.1';
        $port = Config::cache('memcached_port') ?? 11211;
        $this->memcached->addServer($host, $port);
    }

    /**
     * Generates a unique cache key based on folder, cache name, key, and context.
     *
     * @param string $key Cache key
     * @param array $context Additional context
     * @param string $folder Cache folder
     * @return string Generated cache key
     */
    private function generateCacheKey($key, $context, $folder) {
        $folderHash = md5($folder);
        $normalizedContext = Cache::normalizeData($context);
        $combined = $this->prefix . $key;
        if (!empty($normalizedContext)) {
            $combined .= ':' . json_encode($normalizedContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return "cache:{$folderHash}:{$this->name}:" . md5($combined . $this->name);
    }

    /**
     * Returns the driver methods as an array of callables.
     *
     * @return array Driver methods
     */
    private function getDriver() {
        $driverFn = [];

        // SAVE: Stores data in Memcached with lifetime
        $driverFn['save'] = function ($key, $data, $lifetime, $context, $cm) {
            if ($this->name === null) {
                $this->name = $cm->name();
            }
            $folder = $cm->folder();
            $cacheKey = $this->generateCacheKey($key, $context, $folder);
            $storeData = json_encode($data);
            $lifetime = $lifetime ?? Config::cache("lifetime") ?? 3600;
            $this->memcached->set($cacheKey, $storeData, $lifetime);
            Cache::$cacheStorage[$cacheKey] = $data;
            return true;
        };

        // LOAD: Retrieves data from Memcached or memory
        $driverFn['load'] = function ($key, $context, $destroy, $cm) {
            if ($this->name === null) {
                $this->name = $cm->name();
            }
            $folder = $cm->folder();
            $cacheKey = $this->generateCacheKey($key, $context, $folder);

            if (isset(Cache::$cacheStorage[$cacheKey])) {
                $data = Cache::$cacheStorage[$cacheKey];
                if ($destroy) {
                    unset(Cache::$cacheStorage[$cacheKey]);
                }
                return $data;
            }

            $jsonData = $this->memcached->get($cacheKey);
            if ($jsonData === false) {
                unset(Cache::$cacheStorage[$cacheKey]);
                return null;
            }

            $data = json_decode($jsonData, true);
            Cache::$cacheStorage[$cacheKey] = $data;
            if ($destroy) {
                unset(Cache::$cacheStorage[$cacheKey]);
            }
            return $data;
        };

        // EXISTS: Checks if cache exists, optionally loads it
        $driverFn['exists'] = function ($key, $context, $load, $cm) {
            if ($this->name === null) {
                $this->name = $cm->name();
            }
            $folder = $cm->folder();
            $cacheKey = $this->generateCacheKey($key, $context, $folder);

            if (isset(Cache::$cacheStorage[$cacheKey])) {
                if ($load) {
                    return Cache::$cacheStorage[$cacheKey];
                }
                return true;
            }

            $jsonData = $this->memcached->get($cacheKey);
            if ($jsonData === false) {
                return false;
            }

            if ($load) {
                $data = json_decode($jsonData, true);
                Cache::$cacheStorage[$cacheKey] = $data;
                return $data;
            }
            return true;
        };

        // DELETE: Removes a specific cache entry
        $driverFn['delete'] = function ($key, $context, $cm) {
            if ($this->name === null) {
                $this->name = $cm->name();
            }
            $folder = $cm->folder();
            $cacheKey = $this->generateCacheKey($key, $context, $folder);
            $this->memcached->delete($cacheKey);
            unset(Cache::$cacheStorage[$cacheKey]);
        };

        // CLEAR: Clears all cache entries (not folder-specific due to Memcached limitations)
        $driverFn['clear'] = function ($cm) {
            if ($this->name === null) {
                $this->name = $cm->name();
            }
            $this->memcached->flush();
            Cache::$cacheStorage = [];
        };

        // GC: Garbage collection (handled by Memcached internally)
        $driverFn['gc'] = function ($cm) {
            if ($this->name === null) {
                $this->name = $cm->name();
            }
            // Memcached handles expiration automatically
        };

        return $driverFn;
    }
}
?>