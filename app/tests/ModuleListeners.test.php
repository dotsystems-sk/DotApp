<?php
/**
 * Tests listener route separation and the legacy module-route fallback.
 *
 * @package DotApp Framework
 */

namespace Dotsystems\App\Parts\Tests;

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Listeners;
use Dotsystems\App\Parts\Tester;

Tester::addTest('test_listener_routes_legacy_fallback', function () {
    $listener = new class(null, true) extends Listeners {
        /**
         * Keep the test listener free of event side effects.
         *
         * @param mixed $dotapp Unused DotApp instance.
         * @return void
         */
        public function register($dotapp) {
            unset($dotapp);
        }
    };

    $routes = ['/legacy/*', '/legacy/detail/*'];
    $resolved = $listener->resolvedInitializeRoutes($routes);
    $passed = $resolved === $routes;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Listener uses module routes when no override exists' : 'Legacy listener route fallback failed',
        'test_name' => 'Listener routes keep legacy fallback',
        'context' => ['core' => true, 'listeners' => true]
    ];
});

Tester::addTest('test_listener_routes_override_module_routes', function () {
    $listener = new class(null, true) extends Listeners {
        /**
         * Return a route that differs from the sleeping module route.
         *
         * @return array<int, string> Listener-only route map.
         */
        public function initializeRoutes() {
            return ['/hooks/*'];
        }

        /**
         * Keep the test listener free of event side effects.
         *
         * @param mixed $dotapp Unused DotApp instance.
         * @return void
         */
        public function register($dotapp) {
            unset($dotapp);
        }
    };

    $resolved = $listener->resolvedInitializeRoutes(['/admin/*']);
    $passed = $resolved === ['/hooks/*'];

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Listener override is independent from module routes' : 'Listener override was replaced by module routes',
        'test_name' => 'Listener routes override module routes',
        'context' => ['core' => true, 'listeners' => true]
    ];
});

Tester::addTest('test_listener_routes_reject_invalid_shape', function () {
    $rejected = false;
    try {
        Listeners::validateInitializeRoutes(['/ok', ['nested']], 'InvalidListener');
    } catch (\InvalidArgumentException $exception) {
        $rejected = strpos($exception->getMessage(), 'InvalidListener') !== false;
    }

    return [
        'status' => $rejected ? 1 : 0,
        'info' => $rejected ? 'Invalid listener route maps are rejected' : 'Invalid listener route map was accepted',
        'test_name' => 'Listener routes reject invalid shape',
        'context' => ['core' => true, 'listeners' => true]
    ];
});

Tester::addTest('test_module_route_matcher_handles_wildcard_and_bad_cache', function () {
    $dotApp = DotApp::dotApp();
    $method = new \ReflectionMethod($dotApp, 'moduleRoutesMatch');
    $wildcard = $method->invoke($dotApp, ['*']);
    $invalid = $method->invoke($dotApp, 'not-an-array');
    $passed = $wildcard === true && $invalid === false;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Optimizer matcher handles wildcard and malformed route maps' : 'Optimizer matcher compatibility failed',
        'test_name' => 'Module route matcher handles cache variants',
        'context' => ['core' => true, 'listeners' => true]
    ];
});

Tester::addTest('test_listener_map_supports_old_and_new_optimizer_files', function () {
    $dotApp = DotApp::dotApp();
    $method = new \ReflectionMethod($dotApp, 'moduleListenerRoutes');
    $modules = ['Shop' => ['/shop/*'], 'Legacy' => ['/legacy/*']];
    $listeners = ['Shop' => ['/hooks/*']];

    $oldMap = $method->invoke($dotApp, $modules, null, null);
    $newMap = $method->invoke($dotApp, $modules, $listeners, 2);
    $badNewMap = $method->invoke($dotApp, $modules, 'broken', 2);
    $expectedNewMap = ['Shop' => ['/hooks/*'], 'Legacy' => ['/legacy/*']];
    $passed = $oldMap === $modules && $newMap === $expectedNewMap && $badNewMap === $modules;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Old and new optimizer maps resolve compatibly' : 'Optimizer listener map fallback failed',
        'test_name' => 'Listener map supports optimizer cache versions',
        'context' => ['core' => true, 'listeners' => true]
    ];
});
