<?php
/**
 * CLASS Cache - DotApp Cache Manager
 *
 * This class manages cache operations within the DotApp framework. It provides a unified
 * interface for storing, retrieving, and managing cache data using different drivers
 * (e.g., file, Redis). The cache manager delegates operations to the configured driver,
 * ensuring seamless integration regardless of the backend.
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

class Cache {
    private static $instances = []; // Available Cache instances
    private $driver;
    private $cacheName;
    private $cache_manager;
    private static $cacheStorage = []; // Centralized storage for loaded cache data

    /**
     * Constructor for the DotApp Cache Manager.
     *
     * Initializes the cache manager with a specific cache name.
     * Sets up the cache driver based on configuration.
     *
     * @param string $cacheName The name of the cache instance.
     */
    public function __construct($cacheName) {
        $this->cache_manager["managers"] = [];
        $this->cacheName = $cacheName;
        self::$instances[$cacheName] = $this;
        $this->driver = Config::cache("driver");
        foreach (Config::cacheDriver($this->driver) as $way => $wayFn) {
            $this->cache_manager['managers'][$this->driver][$way] = $wayFn;
        }
    }

    /**
     * Creates or retrieves a Cache instance for the specified cache name.
     *
     * @param string|null $cacheName The name of the cache instance (optional).
     * @return Cache The Cache instance.
     */
    public static function use($cacheName = null) {
        if ($cacheName === null) {
            $cacheName = hash('sha256', "DotApp Framework null Cache :)");
        }
        if (isset(self::$instances[$cacheName])) {
            return self::$instances[$cacheName];
        } else {
            $instance = new self($cacheName);
            return $instance;
        }
    }

    /**
     * Saves data to cache.
     *
     * @param string $key Cache key.
     * @param mixed $data Data to cache.
     * @param int|null $lifetime Cache lifetime in seconds.
     * @param array $context Additional context (e.g., user_id).
     * @return $this
     */
    public function save($key, $data, $lifetime = null, $context = []) {
        call_user_func($this->cache_manager['managers'][$this->driver]["save"], $key, $data, $lifetime, $context, $this);
        return $this;
    }

    /**
     * Loads data from cache.
     *
     * @param string $key Cache key.
     * @param array $context Additional context.
     * @param bool $destroy Destroy data from memory after loading.
     * @return mixed|null Cached data or null.
     */
    public function load($key, $context = [], $destroy = false) {
        return call_user_func($this->cache_manager['managers'][$this->driver]["load"], $key, $context, $destroy, $this);
    }

    /**
     * Checks if cache exists and optionally loads it.
     *
     * @param string $key Cache key.
     * @param array $context Additional context.
     * @param bool $load Load data if exists.
     * @return bool Cache exists.
     */
    public function exists($key, $context = [], $load = false) {
        return call_user_func($this->cache_manager['managers'][$this->driver]["exists"], $key, $context, $load, $this);
    }

    /**
     * Deletes specific cache entry.
     *
     * @param string $key Cache key.
     * @param array $context Additional context.
     * @return $this
     */
    public function delete($key, $context = []) {
        call_user_func($this->cache_manager['managers'][$this->driver]["delete"], $key, $context, $this);
        return $this;
    }

    /**
     * Clears all cache data.
     *
     * @return $this
     */
    public function clear() {
        call_user_func($this->cache_manager['managers'][$this->driver]["clear"], $this);
        return $this;
    }

    /**
     * Runs garbage collection.
     *
     * @return $this
     */
    public function gc() {
        call_user_func($this->cache_manager['managers'][$this->driver]["gc"], $this);
        return $this;
    }

    /**
     * Normalizes data by sorting array keys recursively.
     *
     * @param mixed $data Input data.
     * @return mixed Normalized data.
     */
    public static function normalizeData($data) {
        if (is_array($data)) {
            ksort($data);
            foreach ($data as &$value) {
                $value = self::normalizeData($value);
            }
        } elseif (is_object($data)) {
            $data = (array) $data;
            ksort($data);
            foreach ($data as &$value) {
                $value = self::normalizeData($value);
            }
        }
        return $data;
    }
}

class Cache_OLD {

	public $parentobj;
	public $cachedir = __ROOTDIR__."/app/runtime/cache/";
	
	function __construct($parent) {
        $this->parendobj = $parent;
    }
	
	function cachePageExists($name) {
		if (file_exists($this->cachedir.$name.".php")) {
			return(true);
		} else return(false);
	}
	
	function cachePageSave($name,$renderedpage) {
		file_put_contents($this->cachedir.$name.".php",$renderedpage);
	}
	
	function cachePageRead($name,$data) {
		ob_start();
			$dotapp = $this->parentobj->dotapp;
			include $this->cachedir.$name.".php";
		return ob_get_clean();
	}
	
	function cacheCssExists($name) {
		if (file_exists($this->cachedir.$name.".php")) {
			return(true);
		} else return(false);
	}
	
	function cacheCssSave($name,$renderedpage) {
		file_put_contents($this->cachedir.$name.".php",$renderedpage);
	}
	
	function cacheCssRead($name,$data) {
		ob_start();
			$dotapp = $this->parentobj->dotapp;
			include $this->cachedir.$name.".php";
		return ob_get_clean();
	}	
	
}


?>