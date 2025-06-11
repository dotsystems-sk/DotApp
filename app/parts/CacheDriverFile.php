<?php
/**
 * CLASS CacheDriverFile - File-Based Cache Driver Implementation
 *
 * This class provides a file-based cache management functionality for the DotApp framework.
 * It uses self-destructing PHP files for storage, with optimized I/O operations and
 * centralized memory storage for loaded data. Data is stored as JSON instead of serialized.
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

class CacheDriverFile {
    private $dir;
    private $name;
    private $defaultTTL;
    private $prefix;
    private static $driver = null;

    public static function driver() {
        if (self::$driver === null) {
            self::$driver = new self();
            return self::$driver->getDriver();
        }
        return self::$driver->getDriver();
    }

    function __construct() {
        $this->dir = null;
        $this->name = null;
        $this->defaultTTL = Config::cache("lifetime") ?? 3600; // Fallback na prednastavenie, ak je nedokoncene
        $this->prefix = Config::cache("prefix") ?? 'dotapp_'; // Fallback na prednastavenie, ak je nedokoncene
    }

    private function generateCacheFilename($key, $context) {
        $normalizedContext = Cache::normalizeData($context);
        $combined = $this->prefix . $key;
        if (!empty($normalizedContext)) {
            $combined .= ':' . json_encode($normalizedContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $md5Hash = md5($combined.$this->name);
        $sha256Hash = hash('sha256', $combined.$this->name);
        $shaPrefix = substr($sha256Hash, 0, 8);
        return $this->dir . "/cache_{$md5Hash}_{$shaPrefix}.php";
    }

    private function getDriver() {
        $driverFn = [];

        // Funkcia SAVE
        $driverFn['save'] = function ($key, $data, $lifetime, $context, $cm) {
            if ($this->dir === null) {
                $this->dir = $cm->folder();
                $this->name = $cm->name();
            }
            $lifetime = $lifetime ?? $this->defaultTTL;
            $filename = $this->generateCacheFilename($key, $context);
            $expireTime = time() + $lifetime;
            $storeData = json_encode($data); // Ukladáme ako JSON
            $content = "<?php
if (!defined('__ROOTDIR__')) { exit(); }
if (time() > ".$expireTime.") { unlink(__FILE__); return false; }
return ".var_export($storeData, true).";
?>";
            file_put_contents($filename, $content, LOCK_EX);
            chmod($filename, 0600);
            // Ukladáme pôvodné dáta do pamäte
            $cacheKey = basename($filename, '.php');
            Cache::$cacheStorage[$cacheKey] = $data;
            return true;
        };

        // Funkcia LOAD
        $driverFn['load'] = function ($key, $context, $destroy, $cm) {
            if ($this->dir === null) {
                $this->dir = $cm->folder();
                $this->name = $cm->name();
            }
            $filename = $this->generateCacheFilename($key, $context);
            $cacheKey = basename($filename, '.php');
            // Ak existuje v pamäti, vrátime priamo
            if (isset(Cache::$cacheStorage[$cacheKey])) {
                $data = Cache::$cacheStorage[$cacheKey];
                if ($destroy) {
                    unset(Cache::$cacheStorage[$cacheKey]);
                }
                return $data;
            }
            // Načítanie zo súboru
            try {
                $jsonData = include $filename;
                if ($jsonData === false) {
                    unset(Cache::$cacheStorage[$cacheKey]);
                    return null; // Súbor vypršal
                }
                // Dekódujeme JSON
                $data = json_decode($jsonData, true);
                Cache::$cacheStorage[$cacheKey] = $data;
                if ($destroy) {
                    unset(Cache::$cacheStorage[$cacheKey]);
                }
                return $data;
            } catch (\Throwable $e) {
                unset(Cache::$cacheStorage[$cacheKey]);
                return null; // Súbor neexistuje
            }
        };

        // Funkcia EXISTS
        $driverFn['exists'] = function ($key, $context, $load, $cm) use (&$driverFn) {
            if ($this->dir === null) {
                $this->dir = $cm->folder();
                $this->name = $cm->name();
            }
            $filename = $this->generateCacheFilename($key, $context);
            $cacheKey = basename($filename, '.php');

            // Check centralized storage first
            if (isset(Cache::$cacheStorage[$cacheKey])) {
                if ($load) {
                    return Cache::$cacheStorage[$cacheKey];
                }
                return true;
            }

            // Try to include the file
            try {
                $jsonData = include $filename;
                if ($jsonData === false) {
                    unset(Cache::$cacheStorage[$cacheKey]);
                    return false; // File expired
                }

                // Store in centralized storage if loading
                if ($load) {
                    $data = json_decode($jsonData, true);
                    Cache::$cacheStorage[$cacheKey] = $data;
                    return $data;
                }

                return true;
            } catch (\Throwable $e) {
                unset(Cache::$cacheStorage[$cacheKey]);
                return false; // File doesn't exist
            }
        };

        // Funkcia DELETE
        $driverFn['delete'] = function ($key, $context, $cm) {
            if ($this->dir === null) {
                $this->dir = $cm->folder();
                $this->name = $cm->name();
            }
            $filename = $this->generateCacheFilename($key, $context);
            $cacheKey = basename($filename, '.php');
            if (file_exists($filename)) {
                unlink($filename);
            }
            unset(Cache::$cacheStorage[$cacheKey]);
        };

        // Funkcia CLEAR
        $driverFn['clear'] = function ($cm) {
            if ($this->dir === null) $this->dir = $cm->folder();
            foreach (glob($this->dir . '/cache_*.php') as $file) {
                unlink($file);
            }
            Cache::$cacheStorage = [];
        };

        // Funkcia GC
        $driverFn['gc'] = function ($cm) {
            if ($this->dir === null) {
                $this->dir = $cm->folder();
                $this->name = $cm->name();
            }
            foreach (glob($this->dir . '/cache_*.php') as $file) {
                try {
                    include $file; // Triggers self-destruct
                } catch (\Throwable $e) {
                    // Skip invalid files
                }
            }
        };

        return $driverFn;
    }
}
?>