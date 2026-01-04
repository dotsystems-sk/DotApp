<?php
/**
 * CLASS CacheDriverRedis - Redis-Based Cache Driver Implementation
 *
 * This class provides a Redis-based cache management functionality for the DotApp framework.
 * It serves as a drop-in replacement for the file-based cache driver, maintaining full compatibility
 * with the Cache class interface. Data is stored in Redis as JSON strings, with folder and cache name
 * support emulated via key prefixes.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <stefan@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.8 FREE
 * @date      2014 - 2026
 * @license   MIT License
 */

namespace Dotsystems\App\Parts;

use Dotsystems\App\Parts\Config;

class CacheDriverRedis {
    private $redis;
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
     * Constructor initializes Redis connection, prefix, and name.
     */
    public function __construct() {
        $this->prefix = Config::cache("prefix") ?? 'dotapp_';
        $this->name = null;
        $this->connectToRedis();
    }

    /**
     * Establishes connection to Redis server using config settings.
     */
    private function connectToRedis() {
        $this->redis = new \Redis();
        $host = Config::cache('redis_host') ?? '127.0.0.1';
        $port = Config::cache('redis_port') ?? 6379;
        $timeout = Config::cache('redis_timeout') ?? 2;
        $password = Config::cache('redis_password') ?? '';
        $database = Config::cache('redis_database') ?? 0;

        $this->redis->connect($host, $port, $timeout);
        if ($password) {
            $this->redis->auth($password);
        }
        $this->redis->select($database);
    }

    /**
     * Generates a unique Redis key based on folder, cache name, key, and context.
     *
     * @param string $key Cache key
     * @param array $context Additional context
     * @param string $folder Cache folder
     * @return string Generated Redis key
     */
    private function generateRedisKey($key, $context, $folder) {
        $folderHash = md5($folder);
        $normalizedContext = Cache::normalizeData($context);
        $combined = $this->prefix . $key;
        if (!empty($normalizedContext)) {
            $combined .= ':' . json_encode($normalizedContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return "cache:{$folderHash}:{$this->name}:" . md5($combined . $this->name);
    }

    /**
     * Generates a key for centralized memory storage.
     *
     * @param string $key Cache key
     * @param array $context Additional context
     * @param string $folder Cache folder
     * @return string Generated memory key
     */
    private function generateCacheKey($key, $context, $folder) {
        $normalizedContext = Cache::normalizeData($context);
        $combined = $this->prefix . $folder . ':' . $this->name . ':' . $key;
        if (!empty($normalizedContext)) {
            $combined .= ':' . json_encode($normalizedContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return md5($combined);
    }

    /**
     * Returns the driver methods as an array of callables.
     *
     * @return array Driver methods
     */
    private function getDriver() {
        $driverFn = [];

        // SAVE: Stores data in Redis with lifetime
        $driverFn['save'] = function ($key, $data, $lifetime, $context, $cm) {
            if ($this->name === null) {
                $this->name = $cm->name();
            }
            $folder = $cm->folder();
            $redisKey = $this->generateRedisKey($key, $context, $folder);
            $folderHash = md5($folder);
            $storeData = json_encode($data);
            if ($lifetime === null) {
                $lifetime = Config::cache("lifetime") ?? 3600;
            }
            $this->redis->set($redisKey, $storeData, $lifetime);
            $this->redis->sAdd("cache:folder:{$folderHash}:{$this->name}:keys", $redisKey);

            $cacheKey = $this->generateCacheKey($key, $context, $folder);
            Cache::$cacheStorage[$cacheKey] = $data;
            return true;
        };

        // LOAD: Retrieves data from Redis or memory
        $driverFn['load'] = function ($key, $context, $destroy, $cm) {
            if ($this->name === null) {
                $this->name = $cm->name();
            }
            $folder = $cm->folder();
            $redisKey = $this->generateRedisKey($key, $context, $folder);
            $cacheKey = $this->generateCacheKey($key, $context, $folder);

            if (isset(Cache::$cacheStorage[$cacheKey])) {
                $data = Cache::$cacheStorage[$cacheKey];
                if ($destroy) {
                    unset(Cache::$cacheStorage[$cacheKey]);
                }
                return $data;
            }

            $jsonData = $this->redis->get($redisKey);
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
            $redisKey = $this->generateRedisKey($key, $context, $folder);
            $cacheKey = $this->generateCacheKey($key, $context, $folder);

            if (isset(Cache::$cacheStorage[$cacheKey])) {
                if ($load) {
                    return Cache::$cacheStorage[$cacheKey];
                }
                return true;
            }

            $exists = $this->redis->exists($redisKey);
            if ($exists && $load) {
                $jsonData = $this->redis->get($redisKey);
                $data = json_decode($jsonData, true);
                Cache::$cacheStorage[$cacheKey] = $data;
                return $data;
            }
            return (bool) $exists;
        };

        // DELETE: Removes a specific cache entry
        $driverFn['delete'] = function ($key, $context, $cm) {
            if ($this->name === null) {
                $this->name = $cm->name();
            }
            $folder = $cm->folder();
            $redisKey = $this->generateCacheKey($key, $context, $cm->name());
            $folderHash = md5($folder);
            $this->redis->del($redisKey);
            $this->redis->sRem("cache:folder:{$folderHash}:{$this->cacheName}:keys", $redisKey);

            $cacheKey = $this->generateCacheKey($key, $context, $folder);
            unset(Cache::$cacheStorage[$cacheKey]);
        };

        // CLEAR: Removes all data items for the current cache
        $driverFn['clear'] = function ($cm) {
            if ($this->name === null) {
                $this->name = $cm->name();
            }
            $folder = $cm->folder();
            $hashFolder = md5($folder);
            $keys = $this->redis->sMembers("cache:folder:{$folderHash}:{$this->cacheName}:keys");
            if (!empty($keys)) {
                $this->redis->del($keys);
            }
            $this->redis->del("cache:folder:{$folderHash}:{$this->cacheName}:keys");
            Cache::$cacheStorage = [];
        };

        // GC: Garbage collection (not needed for Redis)
        $driverFn['gc'] = function ($cm) {
            if ($this->name === null) {
                $this->name = $cm->name();
            }
            // Redis handles expiration automatically
        };

        return $driverFn;
    }
}
?>
