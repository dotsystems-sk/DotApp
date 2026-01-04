<?php
/**
 * COMPONENTS TESTS FOR DOTAPP FRAMEWORK
 * Version 1.8
 *
 * Tests for component classes that can be tested without database
 * Router, Email, SMS, RouterObj, Request, etc.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.8 FREE
 * @date      2014 - 2026
 * @license   MIT License
 */

namespace Dotsystems\App\Parts\Tests;

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Tester;
use Dotsystems\App\Parts\Router;
use Dotsystems\App\Parts\RouterObj;
use Dotsystems\App\Parts\Request;
use Dotsystems\App\Parts\RequestObj;
use Dotsystems\App\Parts\Email;
use Dotsystems\App\Parts\Sms;
use Dotsystems\App\Parts\SmsProvider;
use Dotsystems\App\Parts\Limiter;
use Dotsystems\App\Parts\Translator;

// Získanie globálnej inštancie DotApp
$dotApp = DotApp::dotApp();

// Test pre Router triedu - základné routing
Tester::addTest('test_router_basic', function () use ($dotApp) {
    try {
        // Router je facade trieda, testujeme či je správne definovaná
        $isFacade = is_subclass_of('Dotsystems\App\Parts\Router', 'Dotsystems\App\Parts\Facade');
        $hasComponent = property_exists('Dotsystems\App\Parts\Router', 'component') ||
                       defined('Dotsystems\App\Parts\Router::$component');

        // Skúsiť zavolať statickú metódu cez facade (bez skutočného routingu)
        $canCallStatic = false;
        try {
            // Toto by malo fungovať cez __callStatic
            $reflection = new \ReflectionClass('Dotsystems\App\Parts\Router');
            $canCallStatic = $reflection->hasMethod('__callStatic');
        } catch (Exception $e) {
            $canCallStatic = false;
        }

        $passed = $isFacade && $canCallStatic;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'Router facade class is properly configured' : 'Router facade class not configured correctly',
            'test_name' => 'Test Router facade configuration',
            'context' => ['components' => true, 'router' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'Router facade error: ' . $e->getMessage(),
            'test_name' => 'Test Router facade configuration',
            'context' => ['components' => true, 'router' => true]
        ];
    }
});

// Test pre RouterObj triedu
Tester::addTest('test_router_obj_basic', function () use ($dotApp) {
    try {
        $routerObj = $dotApp->router;

        // Test základnej funkcionality RouterObj - či objekt existuje
        $isObject = is_object($routerObj);
        $hasClass = get_class($routerObj) === 'Dotsystems\App\Parts\RouterObj';

        $passed = $isObject && $hasClass;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'RouterObj instance exists and is correct type' : 'RouterObj instance not available',
            'test_name' => 'Test RouterObj instance',
            'context' => ['components' => true, 'routerobj' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'RouterObj instance error: ' . $e->getMessage(),
            'test_name' => 'Test RouterObj instance',
            'context' => ['components' => true, 'routerobj' => true]
        ];
    }
});

// Test pre Request triedu (facade)
Tester::addTest('test_request_basic', function () use ($dotApp) {
    try {
        // Request je facade trieda
        $isFacade = is_subclass_of('Dotsystems\App\Parts\Request', 'Dotsystems\App\Parts\Facade');
        $classExists = class_exists('Dotsystems\App\Parts\Request');

        $passed = $isFacade && $classExists;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'Request facade class exists' : 'Request facade class not configured',
            'test_name' => 'Test Request facade class',
            'context' => ['components' => true, 'request' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'Request facade error: ' . $e->getMessage(),
            'test_name' => 'Test Request facade class',
            'context' => ['components' => true, 'request' => true]
        ];
    }
});

// Test pre RequestObj triedu
Tester::addTest('test_request_obj_basic', function () use ($dotApp) {
    try {
        $requestObj = $dotApp->request;

        // Test či RequestObj inštancia existuje
        $isObject = is_object($requestObj);
        $hasClass = get_class($requestObj) === 'Dotsystems\App\Parts\RequestObj';

        $passed = $isObject && $hasClass;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'RequestObj instance exists' : 'RequestObj instance not available',
            'test_name' => 'Test RequestObj instance',
            'context' => ['components' => true, 'requestobj' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'RequestObj instance error: ' . $e->getMessage(),
            'test_name' => 'Test RequestObj instance',
            'context' => ['components' => true, 'requestobj' => true]
        ];
    }
});

// Test pre Email triedu
Tester::addTest('test_email_basic', function () use ($dotApp) {
    try {
        // Test či Email trieda existuje
        $classExists = class_exists('Dotsystems\App\Parts\Email');

        $passed = $classExists;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'Email class exists' : 'Email class not found',
            'test_name' => 'Test Email class existence',
            'context' => ['components' => true, 'email' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'Email class error: ' . $e->getMessage(),
            'test_name' => 'Test Email class existence',
            'context' => ['components' => true, 'email' => true]
        ];
    }
});

// Test pre SMS triedu
Tester::addTest('test_sms_basic', function () use ($dotApp) {
    try {
        // Test či Sms trieda existuje
        $classExists = class_exists('Dotsystems\App\Parts\Sms');

        $passed = $classExists;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'Sms class exists' : 'Sms class not found',
            'test_name' => 'Test Sms class existence',
            'context' => ['components' => true, 'sms' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'Sms class error: ' . $e->getMessage(),
            'test_name' => 'Test Sms class existence',
            'context' => ['components' => true, 'sms' => true]
        ];
    }
});

// Test pre SmsProvider interface
Tester::addTest('test_sms_provider_basic', function () use ($dotApp) {
    try {
        // Test či SmsProvider interface existuje
        $interfaceExists = interface_exists('Dotsystems\App\Parts\SmsProvider');

        $passed = $interfaceExists;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'SmsProvider interface exists' : 'SmsProvider interface not found',
            'test_name' => 'Test SmsProvider interface existence',
            'context' => ['components' => true, 'smsprovider' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'SmsProvider interface error: ' . $e->getMessage(),
            'test_name' => 'Test SmsProvider interface existence',
            'context' => ['components' => true, 'smsprovider' => true]
        ];
    }
});

// Test pre Limiter triedu
Tester::addTest('test_limiter_basic', function () use ($dotApp) {
    try {
        $limiter = $dotApp->limiter;

        // Test základnej funkcionality limiter
        $hasGetter = isset($limiter['getter']) && is_callable($limiter['getter']);
        $hasSetter = isset($limiter['setter']) && is_callable($limiter['setter']);

        $passed = $hasGetter && $hasSetter;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'Limiter has getter and setter functions' : 'Limiter missing getter/setter',
            'test_name' => 'Test Limiter basic functionality',
            'context' => ['components' => true, 'limiter' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'Limiter basic error: ' . $e->getMessage(),
            'test_name' => 'Test Limiter basic functionality',
            'context' => ['components' => true, 'limiter' => true]
        ];
    }
});

// Test pre Translator triedu
Tester::addTest('test_translator_basic', function () use ($dotApp) {
    try {
        // Test či Translator trieda existuje
        $classExists = class_exists('Dotsystems\App\Parts\Translator');

        $passed = $classExists;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'Translator class exists' : 'Translator class not found',
            'test_name' => 'Test Translator class existence',
            'context' => ['components' => true, 'translator' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'Translator class error: ' . $e->getMessage(),
            'test_name' => 'Test Translator class existence',
            'context' => ['components' => true, 'translator' => true]
        ];
    }
});

// Test pre základnú Controller funkcionalitu
Tester::addTest('test_controller_basic', function () use ($dotApp) {
    try {
        // Test či Controller trieda existuje
        $classExists = class_exists('Dotsystems\App\Parts\Controller');

        $passed = $classExists;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'Controller class exists' : 'Controller class not found',
            'test_name' => 'Test Controller class existence',
            'context' => ['components' => true, 'controller' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'Controller class error: ' . $e->getMessage(),
            'test_name' => 'Test Controller class existence',
            'context' => ['components' => true, 'controller' => true]
        ];
    }
});

// Test pre základnú Module funkcionalitu
Tester::addTest('test_module_basic', function () use ($dotApp) {
    try {
        // Test či Module trieda existuje
        $classExists = class_exists('Dotsystems\App\Parts\Module');

        $passed = $classExists;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'Module class exists' : 'Module class not found',
            'test_name' => 'Test Module class existence',
            'context' => ['components' => true, 'module' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'Module class error: ' . $e->getMessage(),
            'test_name' => 'Test Module class existence',
            'context' => ['components' => true, 'module' => true]
        ];
    }
});

// Test pre základnú Auth funkcionalitu
Tester::addTest('test_auth_basic', function () use ($dotApp) {
    try {
        // Test či Auth trieda existuje
        $classExists = class_exists('Dotsystems\App\Parts\Auth');

        $passed = $classExists;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'Auth class exists' : 'Auth class not found',
            'test_name' => 'Test Auth class existence',
            'context' => ['components' => true, 'auth' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'Auth class error: ' . $e->getMessage(),
            'test_name' => 'Test Auth class existence',
            'context' => ['components' => true, 'auth' => true]
        ];
    }
});

// Test pre základnú Api funkcionalitu
Tester::addTest('test_api_basic', function () use ($dotApp) {
    try {
        // Test či Api trieda existuje
        $classExists = class_exists('Dotsystems\App\Parts\Api');

        $passed = $classExists;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'Api class exists' : 'Api class not found',
            'test_name' => 'Test Api class existence',
            'context' => ['components' => true, 'api' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'Api class error: ' . $e->getMessage(),
            'test_name' => 'Test Api class existence',
            'context' => ['components' => true, 'api' => true]
        ];
    }
});

// Test pre základnú Bridge funkcionalitu
Tester::addTest('test_bridge_basic', function () use ($dotApp) {
    try {
        $bridge = $dotApp->bridge;

        // Test či Bridge inštancia existuje
        $isObject = is_object($bridge);
        $hasClass = get_class($bridge) === 'Dotsystems\App\Parts\Bridge';

        $passed = $isObject && $hasClass;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'Bridge instance exists' : 'Bridge instance not available',
            'test_name' => 'Test Bridge instance',
            'context' => ['components' => true, 'bridge' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'Bridge instance error: ' . $e->getMessage(),
            'test_name' => 'Test Bridge instance',
            'context' => ['components' => true, 'bridge' => true]
        ];
    }
});

// Test pre základnú DSM funkcionalitu (DotApp Session Manager)
Tester::addTest('test_dsm_basic', function () use ($dotApp) {
    try {
        $dsm = $dotApp->dsm;

        // Test či DSM inštancia existuje
        $isObject = is_object($dsm);
        $hasClass = get_class($dsm) === 'Dotsystems\App\Parts\DSM';

        $passed = $isObject && $hasClass;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'DSM instance exists' : 'DSM instance not available',
            'test_name' => 'Test DSM instance',
            'context' => ['components' => true, 'dsm' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'DSM instance error: ' . $e->getMessage(),
            'test_name' => 'Test DSM instance',
            'context' => ['components' => true, 'dsm' => true]
        ];
    }
});

?>
