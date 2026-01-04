<?php
/**
 * CORE FUNCTIONALITY TESTS FOR DOTAPP FRAMEWORK
 * Version 1.8
 *
 * Tests for core framework functionality that can be tested without database
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

// Získanie globálnej inštancie DotApp
$dotApp = DotApp::dotApp();

// Test pre základnú inštanciu DotApp
Tester::addTest('test_dotapp_instance', function () use ($dotApp) {
    $passed = $dotApp instanceof DotApp;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'DotApp instance created successfully' : 'DotApp instance creation failed',
        'test_name' => 'Test DotApp instance',
        'context' => ['core' => true, 'instance' => true]
    ];
});

// Test pre základné konštanty
Tester::addTest('test_constants_defined', function () use ($dotApp) {
    $constants = [
        '__ROOTDIR__',
        '__MAINTENANCE__',
        '__DEBUG__',
        '__RENDER_TO_FILE__'
    ];

    $allDefined = true;
    $missing = [];

    foreach ($constants as $const) {
        if (!defined($const)) {
            $allDefined = false;
            $missing[] = $const;
        }
    }

    return [
        'status' => $allDefined ? 1 : 0,
        'info' => $allDefined ? 'All required constants are defined' : 'Missing constants: ' . implode(', ', $missing),
        'test_name' => 'Test required constants',
        'context' => ['core' => true, 'constants' => true]
    ];
});

// Test pre základné vlastnosti DotApp
Tester::addTest('test_dotapp_properties', function () use ($dotApp) {
    $hasProperties = true;
    $missing = [];

    $requiredProperties = [
        'router',
        'request',
        'db',
        'dsm',
        'logger',
        'renderer'
    ];

    foreach ($requiredProperties as $prop) {
        if (!property_exists($dotApp, $prop)) {
            $hasProperties = false;
            $missing[] = $prop;
        }
    }

    return [
        'status' => $hasProperties ? 1 : 0,
        'info' => $hasProperties ? 'All required properties exist' : 'Missing properties: ' . implode(', ', $missing),
        'test_name' => 'Test DotApp properties',
        'context' => ['core' => true, 'properties' => true]
    ];
});

// Test pre základné utility metódy
Tester::addTest('test_utility_methods', function () use ($dotApp) {
    $methods = [
        'isDebugMode',
        'formatBytes',
        'removeNonAlphanumeric',
        'create_alias',
        'generate_strong_password',
        'is_json'
    ];

    $allExist = true;
    $missing = [];

    foreach ($methods as $method) {
        if (!method_exists($dotApp, $method)) {
            $allExist = false;
            $missing[] = $method;
        }
    }

    return [
        'status' => $allExist ? 1 : 0,
        'info' => $allExist ? 'All utility methods exist' : 'Missing methods: ' . implode(', ', $missing),
        'test_name' => 'Test utility methods',
        'context' => ['core' => true, 'methods' => true]
    ];
});

// Test pre statické metódy DotApp
Tester::addTest('test_static_methods', function () use ($dotApp) {
    $staticMethods = [
        'dotApp'
    ];

    $allExist = true;
    $missing = [];

    foreach ($staticMethods as $method) {
        if (!method_exists('Dotsystems\App\DotApp', $method)) {
            $allExist = false;
            $missing[] = $method;
        }
    }

    return [
        'status' => $allExist ? 1 : 0,
        'info' => $allExist ? 'All static methods exist' : 'Missing static methods: ' . implode(', ', $missing),
        'test_name' => 'Test static methods',
        'context' => ['core' => true, 'static' => true]
    ];
});

// Test pre základné konštanty a premenné prostredia
Tester::addTest('test_environment_setup', function () use ($dotApp) {
    try {
        // Test základného prostredia
        $rootDirExists = defined('__ROOTDIR__') && is_dir(__ROOTDIR__);
        $maintenanceDefined = defined('__MAINTENANCE__');
        $debugDefined = defined('__DEBUG__');

        $passed = $rootDirExists && $maintenanceDefined && $debugDefined;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'Environment constants are properly defined' :
                       "Environment failed: rootDir=$rootDirExists, maintenance=$maintenanceDefined, debug=$debugDefined",
            'test_name' => 'Test environment setup',
            'context' => ['core' => true, 'environment' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'Environment setup error: ' . $e->getMessage(),
            'test_name' => 'Test environment setup',
            'context' => ['core' => true, 'environment' => true]
        ];
    }
});

// Test pre základné PHP funkcie (bez duplicit)
Tester::addTest('test_php_functions', function () use ($dotApp) {
    try {
        // Test základných PHP funkcií, ktoré framework používa
        $jsonTest = json_encode(['test' => 'value']);
        $isValidJson = $dotApp->is_json($jsonTest);

        $aliasTest = $dotApp->create_alias('Test String!');
        $hasAlias = !empty($aliasTest);

        $passed = $isValidJson && $hasAlias;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'PHP utility functions work correctly' :
                       "Functions failed: json=$isValidJson, alias=$hasAlias",
            'test_name' => 'Test PHP utility functions',
            'context' => ['core' => true, 'utilities' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'PHP functions error: ' . $e->getMessage(),
            'test_name' => 'Test PHP utility functions',
            'context' => ['core' => true, 'utilities' => true]
        ];
    }
});

// Test pre základné string utility funkcie
Tester::addTest('test_string_utilities', function () use ($dotApp) {
    try {
        // Test základných string funkcií
        $alias = $dotApp->create_alias('Test String with Ümlauts!');
        $aliasValid = !empty($alias) && strpos($alias, 'test-string') !== false;

        $clean = $dotApp->removeNonAlphanumeric('Test!@#123');
        $cleanValid = $clean === 'Test123';

        $strongPass = $dotApp->generate_strong_password(8);
        $passValid = strlen($strongPass) === 8;

        $passed = $aliasValid && $cleanValid && $passValid;

        return [
            'status' => $passed ? 1 : 0,
            'info' => $passed ? 'String utilities work correctly' :
                       "Utilities failed: alias=$aliasValid, clean=$cleanValid, password=$passValid",
            'test_name' => 'Test string utilities',
            'context' => ['core' => true, 'strings' => true]
        ];

    } catch (Exception $e) {
        return [
            'status' => 0,
            'info' => 'String utilities error: ' . $e->getMessage(),
            'test_name' => 'Test string utilities',
            'context' => ['core' => true, 'strings' => true]
        ];
    }
});

?>
