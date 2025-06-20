<?php
/**
 * CLASS CacheDriverNull - Null Cache Driver Implementation
 *
 * This class provides a null cache driver for the DotApp framework, effectively disabling caching.
 * It implements the same interface as other cache drivers but performs no operations, ensuring
 * compatibility with modules that rely on the Cache class without storing or retrieving any data.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <stefan@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.7 FREE
 * @date      2014 - 2025
 * @license   MIT License
 */

namespace Dotsystems\App\Parts;

class CacheDriverNull {
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
     * Constructor (empty for null driver).
     */
    public function __construct() {
        // No initialization needed
    }

    /**
     * Returns the driver methods as an array of callables.
     *
     * @return array Driver methods
     */
    private function getDriver() {
        $driverFn = [];

        // SAVE: Does nothing, returns true for compatibility
        $driverFn['save'] = function ($key, $data, $lifetime, $context, $cm) {
            return true;
        };

        // LOAD: Always returns null (no data stored)
        $driverFn['load'] = function ($key, $context, $destroy, $cm) {
            return null;
        };

        // EXISTS: Returns false (or null if load=true)
        $driverFn['exists'] = function ($key, $context, $load, $cm) {
            return $load ? null : false;
        };

        // DELETE: Does nothing, returns instance for chaining
        $driverFn['delete'] = function ($key, $context, $cm) {
            // No operation
        };

        // CLEAR: Does nothing, returns instance for chaining
        $driverFn['clear'] = function ($cm) {
            // No operation
        };

        // GC: Does nothing, returns instance for chaining
        $driverFn['gc'] = function ($cm) {
            // No operation
        };

        return $driverFn;
    }
}
?>