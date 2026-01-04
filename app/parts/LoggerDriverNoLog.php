<?php
/**
 * CLASS LoggerDriverNoLog - No-Operation Logger Driver
 *
 * Implements a no-operation logging driver for the DotApp framework. This driver
 * discards all log messages, effectively disabling logging without affecting the
 * application's functionality.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.8 FREE
 * @license   MIT License
 * @date      2014 - 2026
 */

namespace Dotsystems\App\Parts;

class LoggerDriverNoLog {
    private static $driver = null;

    public static function driver() {
        if (self::$driver === null) {
            self::$driver = new self();
        }
        return self::$driver->getDriver();
    }

    private function getDriver(): array {
        $driver = [];

        $driver['log'] = function (string $level, string $message, array $context, Logger $logger) {
            // No-op: Do nothing with the log message
        };

        $driver['rotate'] = function (Logger $logger) {
            // No-op: No rotation needed
        };

        $driver['clean'] = function (Logger $logger) {
            // No-op: No cleanup needed
        };

        return $driver;
    }
}
?>
