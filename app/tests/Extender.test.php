<?php
/**
 * Core tests for the Extender replacement registry.
 *
 * Copy this file to app/tests/Extender.test.php after Extender is in app/parts.
 *
 * @package DotApp Framework
 */

namespace Dotsystems\App\Tests;

use Dotsystems\App\Parts\Extender;
use Dotsystems\App\Parts\Tester;

// Why: staging has a sibling Extender.php; after copy to app/tests the class autoloads from app/parts.
if (!class_exists(Extender::class, false)) {
    $stagedExtender = __DIR__ . '/Extender.php';
    if (is_file($stagedExtender)) {
        require_once $stagedExtender;
    }
}

final class ExtenderControllerProbe
{
    /**
     * Accepts DotApp's no-DI controller construction argument.
     *
     * @param mixed $dotApp Active framework instance supplied by stringToCallable().
     */
    public function __construct($dotApp = null)
    {
        unset($dotApp);
    }

    /**
     * Returns an observable value for controller-string dispatch testing.
     *
     * @param mixed $payload Explicit Extender argument.
     * @return array<string, mixed> Stable test payload.
     */
    public function dispatch($payload): array
    {
        return [
            'source' => 'controller-string',
            'payload' => $payload,
        ];
    }
}

Tester::addTest('test_extender_native_callable_exact_return', function () {
    $className = 'Dotsystems\\App\\Tests\\ExtenderProbe\\NativeReturn';
    $methodName = 'run';
    $payload = ['id' => 41, 'zero' => 0, 'flag' => false];
    Extender::extend($className, $methodName, function ($data, $extra) {
        return [$data, $extra, 0, false, null];
    });
    $returned = Extender::call($className, $methodName, $payload, 'extra');
    $passed = $returned === [$payload, 'extra', 0, false, null];

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Native callable receives arguments and returns the exact value' : 'Native callable dispatch or return mismatched',
        'test_name' => 'Test Extender native callable exact return',
        'context' => ['core' => true, 'extender' => true],
    ];
});

Tester::addTest('test_extender_exist_exists_alias', function () {
    $className = 'Dotsystems\\App\\Tests\\ExtenderProbe\\ExistAlias';
    $methodName = 'save';
    $beforeExist = Extender::exist($className, $methodName);
    $beforeExists = Extender::exists($className, $methodName);
    Extender::extend($className, $methodName, function () {
        return true;
    });
    $afterExist = Extender::exist($className, $methodName);
    $afterExists = Extender::exists($className, $methodName);
    $otherExist = Extender::exist($className, 'other');
    $passed = $beforeExist === false
        && $beforeExists === false
        && $afterExist === true
        && $afterExists === true
        && $otherExist === false;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'exist is an alias of exists for registered and missing targets' : 'exist/exists alias behavior mismatched',
        'test_name' => 'Test Extender exist exists alias',
        'context' => ['core' => true, 'extender' => true],
    ];
});

Tester::addTest('test_extender_original_signal_runs_owner_fallback', function () {
    $className = 'Dotsystems\\App\\Tests\\ExtenderProbe\\OriginalFallback';
    $methodName = 'quote';
    Extender::extend($className, $methodName, function () {
        return Extender::original();
    });

    $extensionResult = Extender::call($className, $methodName);
    $originalRan = false;
    if (Extender::isOriginal($extensionResult)) {
        $originalRan = true;
        $finalResult = ['source' => 'original'];
    } else {
        $finalResult = $extensionResult;
    }

    $marker = Extender::original();
    $passed = $originalRan
        && $finalResult === ['source' => 'original']
        && $marker === Extender::original()
        && Extender::isOriginal($marker)
        && Extender::isOriginal(new \stdClass()) === false
        && Extender::isOriginal('__DOTAPP_EXTENDER_ORIGINAL__') === false
        && Extender::isOriginal(['original' => true]) === false;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Unique original signal lets the owner continue its fallback logic' : 'Extender original signal behavior mismatched',
        'test_name' => 'Test Extender original fallback signal',
        'context' => ['core' => true, 'extender' => true],
    ];
});

Tester::addTest('test_extender_class_method_case_normalization', function () {
    $className = 'Dotsystems\\App\\Tests\\ExtenderProbe\\CaseTarget';
    $methodName = 'saveItem';
    Extender::extend($className, $methodName, function () {
        return 'normalized';
    });
    $passed = Extender::exists('dotsystems\\app\\tests\\extenderprobe\\casetarget', 'SAVEITEM') === true
        && Extender::exist('\\Dotsystems\\App\\Tests\\ExtenderProbe\\CaseTarget', 'SaveItem') === true
        && Extender::call('DOTSYSTEMS\\APP\\TESTS\\EXTENDERPROBE\\CASETARGET', 'saveitem') === 'normalized';

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Class and method names are matched case-insensitively' : 'Extender case normalization mismatched',
        'test_name' => 'Test Extender class method case normalization',
        'context' => ['core' => true, 'extender' => true],
    ];
});

Tester::addTest('test_extender_key_isolation', function () {
    Extender::extend('Dotsystems\\App\\Tests\\ExtenderProbe\\IsoA', 'run', function () {
        return 'a-run';
    });
    Extender::extend('Dotsystems\\App\\Tests\\ExtenderProbe\\IsoB', 'run', function () {
        return 'b-run';
    });
    Extender::extend('Dotsystems\\App\\Tests\\ExtenderProbe\\IsoA', 'other', function () {
        return 'a-other';
    });
    $passed = Extender::call('Dotsystems\\App\\Tests\\ExtenderProbe\\IsoA', 'run') === 'a-run'
        && Extender::call('Dotsystems\\App\\Tests\\ExtenderProbe\\IsoB', 'run') === 'b-run'
        && Extender::call('Dotsystems\\App\\Tests\\ExtenderProbe\\IsoA', 'other') === 'a-other'
        && Extender::exists('Dotsystems\\App\\Tests\\ExtenderProbe\\IsoA', 'run') === true
        && Extender::exists('Dotsystems\\App\\Tests\\ExtenderProbe\\IsoB', 'other') === false;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Registry keys stay isolated by class and method' : 'Extender key isolation mismatched',
        'test_name' => 'Test Extender key isolation',
        'context' => ['core' => true, 'extender' => true],
    ];
});

Tester::addTest('test_extender_duplicate_registration_rejected', function () {
    $className = 'Dotsystems\\App\\Tests\\ExtenderProbe\\Duplicate';
    $methodName = 'run';
    Extender::extend($className, $methodName, function () {
        return 1;
    });
    $rejected = false;
    try {
        Extender::extend($className, $methodName, function () {
            return 2;
        });
    } catch (\LogicException $exception) {
        $rejected = strpos($exception->getMessage(), 'already registered') !== false;
    }
    $passed = $rejected && Extender::call($className, $methodName) === 1;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'A second extender for the same target is rejected' : 'Duplicate extender registration was accepted',
        'test_name' => 'Test Extender duplicate registration rejection',
        'context' => ['core' => true, 'extender' => true],
    ];
});

Tester::addTest('test_extender_invalid_target_and_handler_rejected', function () {
    $invalidClass = false;
    try {
        Extender::exists('', 'save');
    } catch (\InvalidArgumentException $exception) {
        $invalidClass = $exception->getMessage() !== '';
    }
    $invalidMethod = false;
    try {
        Extender::exists('Dotsystems\\App\\Tests\\ExtenderProbe\\InvalidTarget', 'save-item');
    } catch (\InvalidArgumentException $exception) {
        $invalidMethod = $exception->getMessage() !== '';
    }
    $invalidHandler = false;
    try {
        Extender::extend('Dotsystems\\App\\Tests\\ExtenderProbe\\InvalidHandler', 'run', 123);
    } catch (\InvalidArgumentException $exception) {
        $invalidHandler = strpos($exception->getMessage(), 'callable') !== false;
    }
    $invalidController = false;
    try {
        Extender::extend('Dotsystems\\App\\Tests\\ExtenderProbe\\InvalidController', 'run', 'Not A Valid@');
    } catch (\InvalidArgumentException $exception) {
        $invalidController = $exception->getMessage() !== '';
    }
    $passed = $invalidClass && $invalidMethod && $invalidHandler && $invalidController;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Invalid targets and handlers are rejected before registration' : 'Invalid Extender input was accepted',
        'test_name' => 'Test Extender invalid target handler rejection',
        'context' => ['core' => true, 'extender' => true],
    ];
});

Tester::addTest('test_extender_missing_registration', function () {
    $rejected = false;
    try {
        Extender::call('Dotsystems\\App\\Tests\\ExtenderProbe\\Missing', 'run');
    } catch (\LogicException $exception) {
        $rejected = strpos($exception->getMessage(), 'No extender is registered') !== false;
    }

    return [
        'status' => $rejected ? 1 : 0,
        'info' => $rejected ? 'Calling an unregistered target is rejected' : 'Missing Extender registration was accepted',
        'test_name' => 'Test Extender missing registration',
        'context' => ['core' => true, 'extender' => true],
    ];
});

Tester::addTest('test_extender_recursive_call_protection', function () {
    $className = 'Dotsystems\\App\\Tests\\ExtenderProbe\\Recursive';
    $methodName = 'run';
    $recursiveRejected = false;
    Extender::extend($className, $methodName, function () use ($className, $methodName, &$recursiveRejected) {
        try {
            Extender::call(strtoupper($className), strtoupper($methodName));
        } catch (\LogicException $exception) {
            $recursiveRejected = strpos($exception->getMessage(), 'Recursive extender call') !== false;
        }
        return 'after-reentry';
    });
    $returned = Extender::call($className, $methodName);
    $passed = $recursiveRejected && $returned === 'after-reentry';

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Re-entering the same extender target is rejected' : 'Recursive Extender call protection mismatched',
        'test_name' => 'Test Extender recursive call protection',
        'context' => ['core' => true, 'extender' => true],
    ];
});

Tester::addTest('test_extender_exception_propagates_and_cleans_up', function () {
    $className = 'Dotsystems\\App\\Tests\\ExtenderProbe\\Throws';
    $methodName = 'run';
    Extender::extend($className, $methodName, function () {
        throw new \RuntimeException('extender handler failed');
    });
    $caught = false;
    try {
        Extender::call($className, $methodName);
    } catch (\RuntimeException $exception) {
        $caught = $exception->getMessage() === 'extender handler failed';
    }
    $secondWasRecursive = false;
    $secondCaught = false;
    try {
        Extender::call($className, $methodName);
    } catch (\RuntimeException $exception) {
        $secondCaught = $exception->getMessage() === 'extender handler failed';
    } catch (\LogicException $exception) {
        $secondWasRecursive = strpos($exception->getMessage(), 'Recursive extender call') !== false;
    }
    $passed = $caught && $secondCaught && $secondWasRecursive === false;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Handler exceptions propagate and the active-call lock is cleared' : 'Extender exception cleanup mismatched',
        'test_name' => 'Test Extender exception propagation cleanup',
        'context' => ['core' => true, 'extender' => true],
    ];
});

Tester::addTest('test_extender_dotapp_controller_string', function () {
    $className = 'Dotsystems\\App\\Tests\\ExtenderProbe\\ControllerString';
    $methodName = 'run';
    Extender::extend(
        $className,
        $methodName,
        'Dotsystems\\App\\Tests\\ExtenderControllerProbe@dispatch!'
    );
    $returned = Extender::call($className, $methodName, ['id' => 17]);
    $passed = $returned === [
        'source' => 'controller-string',
        'payload' => ['id' => 17],
    ];

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Controller string dispatches through DotApp::call without module side effects' : 'Extender controller string dispatch mismatched',
        'test_name' => 'Test Extender DotApp controller string',
        'context' => ['core' => true, 'extender' => true],
    ];
});
