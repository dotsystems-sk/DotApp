<?php
/**
 * CLASS LoggerDriverDefault - PHP error_log Based Logger Driver
 *
 * Implements a logging driver for the DotApp framework using PHP's built-in error_log
 * function. Logs messages to the system's default error log destination (e.g., server logs).
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.8 FREE
 * @license   MIT License
 * @date      2014 - 2026
 */

namespace Dotsystems\App\Parts;

class LoggerDriverDefault {
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
            [$formattedMessage, $metadata] = Logger::formatLog($level, $message, $context);
            error_log($formattedMessage);
        };

        $driver['rotate'] = function (Logger $logger) {
            // No rotation needed for error_log
        };

        $driver['clean'] = function (Logger $logger) {
            // No cleanup needed for error_log
        };

        return $driver;
    }
}

?>
