<?php
/**
 * CLASS LoggerDriverFile - File-Based Logger Driver
 *
 * Implements a file-based logging driver for the DotApp framework. Logs are stored in
 * self-contained log files with rotation based on file count or size. Supports customizable
 * nested subfolders under __ROOTDIR__/app/runtime/logs/ with recursive directory creation.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.8 FREE
 * @license   MIT License
 * @date      2014 - 2026
 */

namespace Dotsystems\App\Parts;

use Dotsystems\App\Parts\Config;

class LoggerDriverFile {
    private $dir;
    private $name;
    private $maxFiles;
    private $maxSize;
    private static $driver = null;

    public static function driver() {
        if (self::$driver === null) {
            self::$driver = new self();
        }
        return self::$driver->getDriver();
    }

    public function __construct() {
        $subfolder = Config::get('logger', 'folder') ?? 'default';
        // Sanitize subfolder to prevent directory traversal and invalid characters
        $subfolder = preg_replace('/[^a-zA-Z0-9_\/-]/', '', $subfolder);
        $subfolder = trim($subfolder, '/\\'); // Remove leading/trailing slashes
        $this->dir = __ROOTDIR__ . "/app/runtime/logs/" . ($subfolder ?: 'default');
        // Normalize path to prevent double slashes and parent directory access
        $this->dir = str_replace(['..', '//'], ['', '/'], $this->dir);
        $this->dir = rtrim($this->dir, '/\\');
        $this->maxFiles = Config::get('logger', 'max_files') ?? 7;
        $this->maxSize = Config::get('logger', 'max_size') ?? 10485760; // 10MB
        $this->name = null;

        // Recursively create directories if they don't exist
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0700, true);
            file_put_contents($this->dir . '/.htaccess', "Deny from all\n");
        }
    }

    private function generateLogFilename(): string {
        $date = (new \DateTime())->format('Y-m-d');
        return $this->dir . "/log_{$date}_" . hash('sha256', $this->name . $date) . ".log";
    }

    private function getDriver(): array {
        $driver = [];

        $driver['log'] = function (string $level, string $message, array $context, Logger $logger) {
            if ($this->name === null) {
                $this->name = $logger->name();
            }
            [$formattedMessage, $metadata] = Logger::formatLog($level, $message, $context);
            $filename = $this->generateLogFilename();
            $lock = fopen($filename, 'a');
            if ($lock && flock($lock, LOCK_EX)) {
                fwrite($lock, $formattedMessage . "\n");
                flock($lock, LOCK_UN);
                fclose($lock);
                chmod($filename, 0600);
            } elseif ($lock) {
                fclose($lock); // Close file if locking fails
            }

            // Check for rotation
            if (file_exists($filename) && filesize($filename) > $this->maxSize) {
                $this->rotate($logger);
            }
        };

        $driver['rotate'] = function (Logger $logger) {
            if ($this->name === null) {
                $this->name = $logger->name();
            }
            $files = glob($this->dir . '/log_*.log');
            usort($files, function ($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            if (count($files) >= $this->maxFiles) {
                for ($i = $this->maxFiles - 1; $i < count($files); $i++) {
                    if (file_exists($files[$i])) {
                        unlink($files[$i]);
                    }
                }
            }
            $filename = $this->generateLogFilename();
            if (file_exists($filename) && filesize($filename) > $this->maxSize) {
                $newFilename = $filename . '.' . time();
                rename($filename, $newFilename);
            }
        };

        $driver['clean'] = function (Logger $logger) {
            if ($this->name === null) {
                $this->name = $logger->name();
            }
            $files = glob($this->dir . '/log_*.log');
            $now = time();
            foreach ($files as $file) {
                if ($now - filemtime($file) > 30 * 86400) { // 30 days
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
            }
        };

        return $driver;
    }
}

?>
