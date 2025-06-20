<?php
/**
 * CLASS Logger - DotApp Logging Manager
 *
 * Provides a modern, robust logging system for the DotApp framework, supporting multiple
 * log levels, context injection, and extensible drivers. Designed to handle debug, info,
 * warning, error, and critical logs with features like log rotation and detailed metadata.
 *
 * Key Features:
 * - Supports PSR-3 log levels: emergency, alert, critical, error, warning, notice, info, debug.
 * - Context injection for additional data (e.g., user ID, request ID).
 * - Driver-based architecture for flexible storage (e.g., file, PHP error_log).
 * - Configurable via DotApp's Config class for folder, driver, and log level settings.
 * - Log rotation and cleanup support for file-based drivers.
 * - Thread-safe and performant with centralized instance management.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.7 FREE
 * @license   MIT License
 * @date      2014 - 2025
 */

namespace Dotsystems\App\Parts;

use Dotsystems\App\Parts\Config;

/**
 * Logger class for DotApp framework.
 */
class Logger {
    /** @var array Logger instances */
    private static $instances = [];
    
    /** @var string Logger name */
    private $loggerName;
    
    /** @var string Logging driver */
    private $driver;
    
    /** @var int Minimum log level */
    private $logLevel;
    
    /** @var array Logger manager configuration */
    private $logger_manager;
    
    /** @var array Default context for logs */
    private $defaultContext = [];

    // PSR-3 log levels
    const LEVEL_EMERGENCY = 0;
    const LEVEL_ALERT = 1;
    const LEVEL_CRITICAL = 2;
    const LEVEL_ERROR = 3;
    const LEVEL_WARNING = 4;
    const LEVEL_NOTICE = 5;
    const LEVEL_INFO = 6;
    const LEVEL_DEBUG = 7;

    /** @var array Mapping of log level names to values */
    private static $levels = [
        'emergency' => self::LEVEL_EMERGENCY,
        'alert' => self::LEVEL_ALERT,
        'critical' => self::LEVEL_CRITICAL,
        'error' => self::LEVEL_ERROR,
        'warning' => self::LEVEL_WARNING,
        'notice' => self::LEVEL_NOTICE,
        'info' => self::LEVEL_INFO,
        'debug' => self::LEVEL_DEBUG,
    ];

    /**
     * Constructor for the DotApp Logger.
     *
     * @param string $loggerName Unique name for the logger instance.
     * @param string $driver The logging driver to use.
     */
    public function __construct($loggerName, $driver) {
        $this->loggerName = $loggerName;
        $this->driver = $driver;
        $this->logLevel = isset(self::$levels[Config::get('logger', 'min_level')]) 
            ? self::$levels[Config::get('logger', 'min_level')] 
            : self::LEVEL_INFO;
        self::$instances[$loggerName] = $this;
        $this->logger_manager['managers'] = [];
        foreach (Config::loggerDriver($this->driver) as $way => $wayFn) {
            $this->logger_manager['managers'][$this->driver][$way] = $wayFn;
        }
    }

    /**
     * Retrieves or creates a Logger instance.
     *
     * @param string|null $loggerName Optional logger name (defaults to hashed name).
     * @param string|null $driver Optional driver name (defaults to config).
     * @return Logger
     */
    public static function use($loggerName = null, $driver = null) {
        if ($loggerName === null) {
            $loggerName = hash('sha256', 'DotApp Framework null Logger :)');
        }
        if ($driver === null) {
            $driver = Config::get('logger', 'driver') ?? 'default';
        }
        if (isset(self::$instances[$loggerName])) {
            return self::$instances[$loggerName];
        }
        return new self($loggerName, $driver);
    }

    /**
     * Returns the logger's name.
     *
     * @return string
     */
    public function name() {
        return $this->loggerName;
    }

    /**
     * Sets default context to be included in all logs.
     *
     * @param array $context
     * @return Logger
     */
    public function withContext(array $context) {
        $this->defaultContext = array_merge($this->defaultContext, $context);
        return $this;
    }

    /**
     * Logs a message at the specified level.
     *
     * @param string $level Log level (e.g., 'info', 'error').
     * @param string $message Log message.
     * @param array $context Additional context data.
     * @return Logger
     */
    public function log($level, $message, array $context = array()) {
        if (!isset(self::$levels[$level])) {
            $this->log('warning', "Invalid log level '$level' used.", array('original_level' => $level));
            return $this;
        }
        if (self::$levels[$level] > $this->logLevel) {
            return $this; // Skip logging if level is below minimum
        }
        $context = array_merge($this->defaultContext, $context);
        call_user_func(
            $this->logger_manager['managers'][$this->driver]['log'],
            $level,
            $message,
            $context,
            $this
        );
        return $this;
    }

    /**
     * Logs an emergency message.
     *
     * @param string $message
     * @param array $context
     * @return Logger
     */
    public function emergency($message, array $context = array()) {
        return $this->log('emergency', $message, $context);
    }

    /**
     * Logs an alert message.
     *
     * @param string $message
     * @param array $context
     * @return Logger
     */
    public function alert($message, array $context = array()) {
        return $this->log('alert', $message, $context);
    }

    /**
     * Logs a critical message.
     *
     * @param string $message
     * @param array $context
     * @return Logger
     */
    public function critical($message, array $context = array()) {
        return $this->log('critical', $message, $context);
    }

    /**
     * Logs an error message.
     *
     * @param string $message
     * @param array $context
     * @return Logger
     */
    public function error($message, array $context = array()) {
        return $this->log('error', $message, $context);
    }

    /**
     * Logs a warning message.
     *
     * @param string $message
     * @param array $context
     * @return Logger
     */
    public function warning($message, array $context = array()) {
        return $this->log('warning', $message, $context);
    }

    /**
     * Logs a notice message.
     *
     * @param string $message
     * @param array $context
     * @return Logger
     */
    public function notice($message, array $context = array()) {
        return $this->log('notice', $message, $context);
    }

    /**
     * Logs an info message.
     *
     * @param string $message
     * @param array $context
     * @return Logger
     */
    public function info($message, array $context = array()) {
        return $this->log('info', $message, $context);
    }

    /**
     * Logs a debug message.
     *
     * @param string $message
     * @param array $context
     * @return Logger
     */
    public function debug($message, array $context = array()) {
        return $this->log('debug', $message, $context);
    }

    /**
     * Rotates logs (driver-specific).
     *
     * @return Logger
     */
    public function rotate() {
        call_user_func($this->logger_manager['managers'][$this->driver]['rotate'], $this);
        return $this;
    }

    /**
     * Cleans old logs (driver-specific).
     *
     * @return Logger
     */
    public function clean() {
        call_user_func($this->logger_manager['managers'][$this->driver]['clean'], $this);
        return $this;
    }

    /**
     * Formats the log message with context.
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return array [formatted_message, metadata]
     */
    public static function formatLog($level, $message, array $context) {
        $timestamp = (new \DateTime())->format('Y-m-d\TH:i:s.uP');
        $requestId = isset($context['request_id']) ? $context['request_id'] : uniqid('req_', true);
        $metadata = array(
            'timestamp' => $timestamp,
            'level' => strtoupper($level),
            'request_id' => $requestId,
            'context' => $context,
        );
        $formattedMessage = sprintf(
            '[%s] %s: %s %s',
            $timestamp,
            strtoupper($level),
            $message,
            !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''
        );
        return array($formattedMessage, $metadata);
    }
}
?>