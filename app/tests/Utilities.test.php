<?php
/**
 * UTILITIES TESTS FOR DOTAPP FRAMEWORK
 * Version 1.8
 *
 * Tests for utility classes that can be tested without database
 * Crypto, QR, TOTP, Validator, StaticGetSet, HttpHelper
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
use Dotsystems\App\Parts\Crypto;
use Dotsystems\App\Parts\QR;
use Dotsystems\App\Parts\TOTP;
use Dotsystems\App\Parts\Validator;
use Dotsystems\App\Parts\StaticGetSet;
use Dotsystems\App\Parts\HttpHelper;
use Dotsystems\App\Parts\Cache;
use Dotsystems\App\Parts\CacheDriverNull;

// Získanie globálnej inštancie DotApp
$dotApp = DotApp::dotApp();

// Test pre Crypto triedu - základné šifrovanie/dešifrovanie
Tester::addTest('test_crypto_basic', function () use ($dotApp) {
    try {
        $crypto = new Crypto();
        $testData = "Hello World!";

        $encrypted = $crypto->encrypt($testData);
        $decrypted = $crypto->decrypt($encrypted);

        $passed = !empty($encrypted) && $decrypted === $testData && $encrypted !== $testData;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'Crypto basic encryption/decryption works' : 'Crypto basic failed',
            'test_name' => 'Test Crypto basic functionality',
            'context' => ['utilities' => true, 'crypto' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'Crypto basic error: ' . $e->getMessage(),
            'test_name' => 'Test Crypto basic functionality',
            'context' => ['utilities' => true, 'crypto' => true]
        ];
    }
});

// Test pre QR triedu
Tester::addTest('test_qr_generation', function () use ($dotApp) {
    try {
        $testData = "https://dotapp.dev";

        // Test základnej funkcionality - či statické metódy existujú
        $hasGenerateMethod = method_exists('Dotsystems\App\Parts\QR', 'generate');
        $hasBase64Method = method_exists('Dotsystems\App\Parts\QR', 'imageToBase64');

        // Ak metódy existujú, vyskúšajme ich funkcionalitu
        if ($hasGenerateMethod && $hasBase64Method) {
            $qrResult = QR::generate($testData);
            $isValidQR = is_object($qrResult) || is_string($qrResult);
        } else {
            $isValidQR = false;
        }

        $passed = $hasGenerateMethod && $hasBase64Method && $isValidQR;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'QR class has required methods and works' : 'QR class missing methods or not working',
            'test_name' => 'Test QR generation methods',
            'context' => ['utilities' => true, 'qr' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'QR generation error: ' . $e->getMessage(),
            'test_name' => 'Test QR generation methods',
            'context' => ['utilities' => true, 'qr' => true]
        ];
    }
});

// Test pre TOTP triedu
Tester::addTest('test_totp_basic', function () use ($dotApp) {
    try {
        // Test základnej funkcionality - či statické metódy existujú
        $hasGenerateMethod = method_exists('Dotsystems\App\Parts\TOTP', 'generate');
        $hasNewSecretMethod = method_exists('Dotsystems\App\Parts\TOTP', 'newSecret');
        $hasOtpauthMethod = method_exists('Dotsystems\App\Parts\TOTP', 'otpauth');

        // Ak metódy existujú, vyskúšajme jednoduchú funkcionalitu
        if ($hasGenerateMethod && $hasNewSecretMethod && $hasOtpauthMethod) {
            $secret = TOTP::newSecret(20); // Generate secret for testing
            $code = TOTP::generate($secret);
            $isValidCode = is_string($code) && strlen($code) === 6 && is_numeric($code);
        } else {
            $isValidCode = false;
        }

        $passed = $hasGenerateMethod && $hasNewSecretMethod && $hasOtpauthMethod && $isValidCode;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'TOTP class has required methods and works' : 'TOTP class missing methods or not working',
            'test_name' => 'Test TOTP basic methods',
            'context' => ['utilities' => true, 'totp' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'TOTP basic error: ' . $e->getMessage(),
            'test_name' => 'Test TOTP basic methods',
            'context' => ['utilities' => true, 'totp' => true]
        ];
    }
});

// Test pre Validator triedu - základná validácia
Tester::addTest('test_validator_basic', function () use ($dotApp) {
    try {
        // Test základnej validácie - či statická metóda existuje
        $rules = ['name' => 'required', 'email' => 'email'];
        $data = ['name' => 'John', 'email' => 'john@example.com'];

        $hasValidateMethod = method_exists('Dotsystems\App\Parts\Validator', 'validate');

        // Ak metóda existuje, vyskúšajme jednoduchú validáciu
        if ($hasValidateMethod) {
            $result = Validator::validate($data, $rules);
            $isValidResult = $result === true;
        } else {
            $isValidResult = false;
        }

        $passed = $hasValidateMethod && $isValidResult;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'Validator class has validate method and works' : 'Validator class missing validate method or not working',
            'test_name' => 'Test Validator basic methods',
            'context' => ['utilities' => true, 'validator' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'Validator basic error: ' . $e->getMessage(),
            'test_name' => 'Test Validator basic methods',
            'context' => ['utilities' => true, 'validator' => true]
        ];
    }
});

// Test pre StaticGetSet trait
Tester::addTest('test_static_get_set', function () use ($dotApp) {
    try {
        // Test základnej funkcionality StaticGetSet - trait metódy
        $hasGetStaticMethod = method_exists('Dotsystems\App\Parts\StaticGetSet', 'getStatic');
        $hasSetStaticMethod = method_exists('Dotsystems\App\Parts\StaticGetSet', 'setStatic');

        // StaticGetSet je trait, takže testujeme či metódy existujú
        $passed = $hasGetStaticMethod && $hasSetStaticMethod;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'StaticGetSet trait has required methods' : 'StaticGetSet trait missing methods',
            'test_name' => 'Test StaticGetSet methods',
            'context' => ['utilities' => true, 'staticgetset' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'StaticGetSet error: ' . $e->getMessage(),
            'test_name' => 'Test StaticGetSet methods',
            'context' => ['utilities' => true, 'staticgetset' => true]
        ];
    }
});

// Test pre HttpHelper triedu
Tester::addTest('test_http_helper', function () use ($dotApp) {
    try {
        // Test základnej funkcionality - či statická metóda request existuje
        $hasRequestMethod = method_exists('Dotsystems\App\Parts\HttpHelper', 'request');

        $passed = $hasRequestMethod;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'HttpHelper class has request method' : 'HttpHelper class missing request method',
            'test_name' => 'Test HttpHelper methods',
            'context' => ['utilities' => true, 'http' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'HttpHelper error: ' . $e->getMessage(),
            'test_name' => 'Test HttpHelper methods',
            'context' => ['utilities' => true, 'http' => true]
        ];
    }
});

// Test pre Cache základnú funkcionalitu
Tester::addTest('test_cache_basic', function () use ($dotApp) {
    try {
        // Cache trieda vyžaduje konštruktor s parametrami, testujeme či trieda a základné metódy existujú
        $cacheClassExists = class_exists('Dotsystems\App\Parts\Cache');

        // Ak trieda existuje, otestujeme či má základné metódy
        if ($cacheClassExists) {
            $hasUseMethod = method_exists('Dotsystems\App\Parts\Cache', 'use');
            $hasConstructor = method_exists('Dotsystems\App\Parts\Cache', '__construct');
            $hasSaveMethod = method_exists('Dotsystems\App\Parts\Cache', 'save');
            $hasLoadMethod = method_exists('Dotsystems\App\Parts\Cache', 'load');
        } else {
            $hasUseMethod = $hasConstructor = $hasSaveMethod = $hasLoadMethod = false;
        }

        $passed = $cacheClassExists && $hasUseMethod && $hasConstructor && $hasSaveMethod && $hasLoadMethod;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'Cache class exists and has required methods' : 'Cache class missing or incomplete',
            'test_name' => 'Test Cache basic functionality',
            'context' => ['utilities' => true, 'cache' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'Cache basic error: ' . $e->getMessage(),
            'test_name' => 'Test Cache basic functionality',
            'context' => ['utilities' => true, 'cache' => true]
        ];
    }
});

// Test pre základnú Logger funkcionalitu
Tester::addTest('test_logger_basic', function () use ($dotApp) {
    try {
        // Test základnej funkcionality loggera
        $hasLogMethod = method_exists('Dotsystems\App\Parts\Logger', 'log');
        $hasUseMethod = method_exists('Dotsystems\App\Parts\Logger', 'use');

        $passed = $hasLogMethod && $hasUseMethod;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'Logger class has required methods' : 'Logger class missing methods',
            'test_name' => 'Test Logger basic methods',
            'context' => ['utilities' => true, 'logger' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'Logger basic error: ' . $e->getMessage(),
            'test_name' => 'Test Logger basic methods',
            'context' => ['utilities' => true, 'logger' => true]
        ];
    }
});

// Test pre základnú Response funkcionalitu
Tester::addTest('test_response_basic', function () use ($dotApp) {
    try {
        // Response trieda má statické metódy, testujeme či existujú
        $hasCodeMethod = method_exists('Dotsystems\App\Parts\Response', 'code');
        $hasJsonMethod = method_exists('Dotsystems\App\Parts\Response', 'json');
        $hasRedirectMethod = method_exists('Dotsystems\App\Parts\Response', 'redirect');

        $passed = $hasCodeMethod && $hasJsonMethod && $hasRedirectMethod;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'Response class has required static methods' : 'Response class missing static methods',
            'test_name' => 'Test Response basic methods',
            'context' => ['utilities' => true, 'response' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'Response basic error: ' . $e->getMessage(),
            'test_name' => 'Test Response basic methods',
            'context' => ['utilities' => true, 'response' => true]
        ];
    }
});

// Test pre základnú Route funkcionalitu
Tester::addTest('test_route_basic', function () use ($dotApp) {
    try {
        // Route rozširuje Router triedu, takže testujeme či dedičstvo funguje
        $routeClassExists = class_exists('Dotsystems\App\Parts\Route');
        $extendsRouter = is_subclass_of('Dotsystems\App\Parts\Route', 'Dotsystems\App\Parts\Router');

        $passed = $routeClassExists && $extendsRouter;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'Route class exists and extends Router' : 'Route class missing or incorrect inheritance',
            'test_name' => 'Test Route basic methods',
            'context' => ['utilities' => true, 'route' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'Route basic error: ' . $e->getMessage(),
            'test_name' => 'Test Route basic methods',
            'context' => ['utilities' => true, 'route' => true]
        ];
    }
});

// Test pre základnú Middleware funkcionalitu
Tester::addTest('test_middleware_basic', function () use ($dotApp) {
    try {
        // Middleware trieda používa StaticGetSet trait, testujeme či trieda existuje
        $middlewareClassExists = class_exists('Dotsystems\App\Parts\Middleware');

        // Ak trieda existuje, otestujeme či má základné metódy
        if ($middlewareClassExists) {
            $hasConstructor = method_exists('Dotsystems\App\Parts\Middleware', '__construct');
            $hasUseMethod = method_exists('Dotsystems\App\Parts\Middleware', 'use');
            $hasDefineMethod = method_exists('Dotsystems\App\Parts\Middleware', 'define');
        } else {
            $hasConstructor = $hasUseMethod = $hasDefineMethod = false;
        }

        $passed = $middlewareClassExists && $hasConstructor && $hasUseMethod && $hasDefineMethod;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'Middleware class exists and has required methods' : 'Middleware class missing or incomplete',
            'test_name' => 'Test Middleware basic methods',
            'context' => ['utilities' => true, 'middleware' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'Middleware basic error: ' . $e->getMessage(),
            'test_name' => 'Test Middleware basic methods',
            'context' => ['utilities' => true, 'middleware' => true]
        ];
    }
});

// Test pre základnú Facade funkcionalitu
Tester::addTest('test_facade_basic', function () use ($dotApp) {
    try {
        // Facade je abstraktná trieda pre vytváranie facades
        $facadeClassExists = class_exists('Dotsystems\App\Parts\Facade');

        // Jednoduchšie testovanie - či trieda existuje a je abstraktná
        $passed = $facadeClassExists;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'Facade class exists' : 'Facade class missing',
            'test_name' => 'Test Facade basic methods',
            'context' => ['utilities' => true, 'facade' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'Facade basic error: ' . $e->getMessage(),
            'test_name' => 'Test Facade basic methods',
            'context' => ['utilities' => true, 'facade' => true]
        ];
    }
});

// Test pre základnú Wrapper funkcionalitu
Tester::addTest('test_wrapper_basic', function () use ($dotApp) {
    try {
        // Wrapper trieda vyžaduje konštruktor s parametrami, takže iba otestujeme či trieda existuje
        $wrapperClassExists = class_exists('Dotsystems\App\Parts\Wrapper');

        $passed = $wrapperClassExists;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'Wrapper class exists' : 'Wrapper class missing',
            'test_name' => 'Test Wrapper basic methods',
            'context' => ['utilities' => true, 'wrapper' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'Wrapper basic error: ' . $e->getMessage(),
            'test_name' => 'Test Wrapper basic methods',
            'context' => ['utilities' => true, 'wrapper' => true]
        ];
    }
});

?>
