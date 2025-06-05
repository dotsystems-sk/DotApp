<?php
namespace Dotsystems\App\Parts;

/**
 * Class Logger
 * 
 * A simple logging utility for recording application events to a file.
 * Supports two modes: rewriting the log file or appending to it.
 * The log file and mode are set via the logFile() method.
 * 
 * This class provides a lightweight and flexible way to log events in the DotApp framework,
 * allowing developers to track application behavior with customizable log levels and context data.
 * 
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.0
 * @license   MIT License
 * @date      2014-2025
 * 
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the 
 * following condition: You **must** retain this header in all copies or 
 * substantial portions of the code, including the author and company information.
 */
 
class Logger {
    /** @var string Directory where log files are stored */
    private $cachedir = __ROOTDIR__ . "/App/runtime/logs/";

    /** @var string|null Full path to the log file, null if not set */
    private $logfile;

    /** @var int|null Mode of logging (0 = rewrite, 1 = append), null if not set */
    private $mode;

    /**
     * Logger constructor.
     *
     * Initializes an empty logger instance. Use logFile() to set the log file and mode.
     */
    public function __construct() {
        $this->logfile = false;
    }

    /**
     * Validates the log filename.
     *
     * Ensures the filename contains only alphanumeric characters, underscores,
     * or hyphens. The .log extension is added automatically and not required in input.
     *
     * @param string $filename The filename to validate
     * @return bool True if valid, false otherwise
     */
    private function isValidFilename($filename) {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $filename);
    }

    /**
     * Sets the log file and mode for logging.
     *
     * Configures the logger with a filename and mode, storing the file in the cache directory.
     * Returns the logger instance for method chaining.
     *
     * @param string $logfile Name of the log file (without path or .log extension)
     * @param int    $mode    Logging mode: 0 = rewrite, 1 = append (default: 1)
     * @return self Returns the current instance for chaining
     * @throws InvalidArgumentException If the filename or mode is invalid
     */
    public function logFile(string $logfile, int $mode = 1) {
        if ($this->isValidFilename($logfile)) {
            $this->logfile = $this->cachedir . $logfile.".log";

            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $callingClass = $trace[1]['class'] ?? null;            
            if (strpos($callingClass,'Dotsystems\App\Modules') === 0) {
                $callingClassA = explode("_",$callingClass);
                if ($this->isValidFilename($callingClassA[1])) $this->logfile = $this->cachedir . $callingClassA[1] . "_" . $logfile.".log";
            }
            
        } else {
            throw new InvalidArgumentException("Incorrect filename!");
        }

        if ($mode == 0 || $mode == 1) {
            $this->mode = $mode;
        } else {
            throw new InvalidArgumentException("Unknown mode!");
        }

        return $this;
    }

    /**
     * Logs a message to the file with a specified level and optional data.
     *
     * Supported log levels:
     * - 0: INFO – General application information
     * - 1: DEBUG – Debugging details
     * - 2: WARNING – Potential issues
     * - 3: ERROR – Application errors
     * - 4: CRITICAL – Critical errors requiring immediate attention
     *
     * @param string $logText The message to log
     * @param int    $level   The log level (0-4)
     * @param array  $data    Optional context data to include in the log
     * @throws InvalidArgumentException If the log file is not set or level is invalid
     */
    public function log(string $logText, int $level=0, array $data = []) {
        if (!$this->logfile) {
            throw new \InvalidArgumentException("Set log filename!");
        }

        $levelT = [
            0 => "INFO",
            1 => "DEBUG",
            2 => "WARNING",
            3 => "ERROR",
            4 => "CRITICAL"
        ];

        if (isset($levelT[$level])) {
            $data_write = "[ " . date("Y-m-d H:i:s") . " ] [" . $levelT[$level] . "] - " . $logText;
            if (!empty($data)) {
                $data_write .= " Data: " . json_encode($data);
            }
            // Rewrite mode (0) overwrites the file, append mode (1) adds a newline
            ($this->mode == 0) 
                ? file_put_contents($this->logfile, $data_write, LOCK_EX) 
                : file_put_contents($this->logfile, $data_write . "\n", FILE_APPEND | LOCK_EX);
        } else {
            throw new \InvalidArgumentException("Unknown level!");
        }
    }
}
?>