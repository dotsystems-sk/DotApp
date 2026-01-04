<?php
/**
 * CLASS Tester - DotApp Tester
 *
 * This class provides a robust testing class for the DotApp framework. It allows
 * registering test callbacks, loading tests from framework and module directories,
 * and running them to collect results, including memory usage metrics for each test.
 * The tester is designed to be lightweight, requiring no external dependencies, and
 * supports modular test organization. It handles non-existent test directories gracefully.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <stefan@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.8 FREE
 * @date      2014 - 2026
 * @license   MIT License
 */

namespace Dotsystems\App\Parts;

class Tester {
    private static $tests = [];

    /**
     * Adds a test callback with an optional name.
     *
     * @param string|callable $nameOrCallback Test name (if string) or callback (if callable)
     * @param callable|null $callback Callback function that returns an array ['status' => 1|0|2, 'info' => string, ...]
     * @throws \InvalidArgumentException If name is already used or invalid arguments are provided
     */
    public static function addTest($nameOrCallback, $callback = null): void {
        // Handle single parameter (callable only)
        if (is_callable($nameOrCallback) && $callback === null) {
            $callback = $nameOrCallback;
            // Generate a unique test name using md5 and sha256
            $name = md5(uniqid(mt_rand(), true)) . hash('sha256', random_bytes(16));
        } elseif (is_string($nameOrCallback) && is_callable($callback)) {
            $name = $nameOrCallback;
        } else {
            echo 'Invalid arguments: Provide either a string name and callable, or just a callable.';
            throw new \InvalidArgumentException('Invalid arguments: Provide either a string name and callable, or just a callable.');
        }

        // Check for name collision
        if (isset(self::$tests[$name])) {
            echo "Test name '$name' is already registered.";
            throw new \InvalidArgumentException("Test name '$name' is already registered.");
        }

        self::$tests[$name] = $callback;
    }

    /**
     * Runs all registered tests and returns their results, including memory usage metrics.
     *
     * @return array Array containing test results and summary
     */
    public static function run(): array {
        $results = [];
        foreach (self::$tests as $name => $callback) {
            // Run garbage collection to free unused memory
            gc_collect_cycles();
            
            $startTime = microtime(true);
            $startMemory = memory_get_usage();
            
            try {
                $result = $callback();
                $endMemory = memory_get_usage();
                
                if (!is_array($result) || !isset($result['status'])) {
                    $results[$name] = [
                        'status' => 0,
                        'info' => 'Invalid return value: Expected array with status',
                        'test_name' => $name,
                        'duration' => microtime(true) - $startTime,
                        'memory_delta' => ($endMemory - $startMemory) / 1024, // KB
                        'context' => $result['context'] ?? []
                    ];
                } else {
                    $results[$name] = [
                        'status' => $result['status'],
                        'info' => $result['info'] ?? '',
                        'test_name' => $result['test_name'] ?? $name,
                        'duration' => microtime(true) - $startTime,
                        'memory_delta' => ($endMemory - $startMemory) / 1024, // KB
                        'context' => $result['context'] ?? []
                    ];
                }
            } catch (\Exception $e) {
                $endMemory = memory_get_usage();
                
                $results[$name] = [
                    'status' => 0,
                    'info' => 'Exception: ' . $e->getMessage(),
                    'test_name' => $name,
                    'duration' => microtime(true) - $startTime,
                    'memory_delta' => ($endMemory - $startMemory) / 1024, // KB
                    'context' => []
                ];
            }
        }

        return [
            'results' => $results,
            'summary' => [
                'total' => count($results),
                'passed' => count(array_filter($results, fn($r) => $r['status'] === 1)),
                'skipped' => count(array_filter($results, fn($r) => $r['status'] === 2)),
                'failed' => count(array_filter($results, fn($r) => $r['status'] === 0))
            ]
        ];
    }

    /**
     * Loads tests from framework and module directories, skipping non-existent directories.
     *
     * @param bool $loadFrameworkTests Whether to load tests from app/tests
     * @param bool|string $loadModuleTests Whether to load module tests (true = all, false = none, string = specific module)
     * @return void
     */
    public static function loadTests(bool $loadFrameworkTests, $loadModuleTests): void {
        // Recursive function to load PHP files
        $loadPhpFiles = function (string $dir) use (&$loadPhpFiles) {
            // Skip if directory does not exist
            if (!is_dir($dir)) {
                return;
            }
            foreach (scandir($dir) as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                if (is_dir($path)) {
                    $loadPhpFiles($path);
                } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                    require_once $path;
                }
            }
        };

        // Load framework tests if directory exists
        if ($loadFrameworkTests) {
            $frameworkTestsDir = __ROOTDIR__ . '/app/tests';
            if (is_dir($frameworkTestsDir)) {
                $loadPhpFiles($frameworkTestsDir);
            }
        }

        // Load module tests
        if ($loadModuleTests !== false) {
            $modulesDir = __ROOTDIR__ . '/app/modules';
            // Skip if modules directory does not exist
            if (!is_dir($modulesDir)) {
                return;
            }
            if (is_string($loadModuleTests)) {
                // Load tests for a specific module if directory exists
                $moduleTestsDir = $modulesDir . '/' . $loadModuleTests . '/tests';
                if (is_dir($moduleTestsDir)) {
                    $loadPhpFiles($moduleTestsDir);
                }
            } elseif ($loadModuleTests === true) {
                // Load tests for all modules
                foreach (scandir($modulesDir) as $module) {
                    if ($module === '.' || $module === '..' || !is_dir($modulesDir . '/' . $module)) {
                        continue;
                    }
                    $moduleTestsDir = $modulesDir . '/' . $module . '/tests';
                    if (is_dir($moduleTestsDir)) {
                        $loadPhpFiles($moduleTestsDir);
                    }
                }
            }
        }
    }
}

?>
