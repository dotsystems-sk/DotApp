<?php
/**
 * Core tests for explicit Veto listener returns.
 *
 * @package DotApp Framework
 */

namespace Dotsystems\App\Tests;

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Events;
use Dotsystems\App\Parts\Tester;
use Dotsystems\App\Parts\Veto;

Tester::addTest('test_veto_value_object', function () {
    $veto = new Veto('template.in_use', 'Template is referenced.', [
        'template_id' => 17,
    ]);
    $invalidRejected = false;
    try {
        new Veto('Bad code');
    } catch (\InvalidArgumentException $exception) {
        $invalidRejected = $exception->getMessage() !== '';
    }
    $detailsCopy = $veto->details();
    $detailsCopy['template_id'] = 99;
    $passed = $veto->code() === 'template.in_use'
        && $veto->message() === 'Template is referenced.'
        && $veto->details() === ['template_id' => 17]
        && $detailsCopy === ['template_id' => 99]
        && $invalidRejected;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Veto keeps validated immutable decision details' : 'Veto value object mismatched',
        'test_name' => 'Test Veto value object',
        'context' => ['core' => true, 'events' => true, 'veto' => true],
    ];
});

Tester::addTest('test_trigger_with_veto_stops_on_first_veto', function () {
    $dotApp = DotApp::dotApp();
    $calls = [];
    $dotApp->on('test.veto.short_circuit', function ($result, ...$data) use (&$calls) {
        $calls[] = ['old_false', $result, $data];
        return false;
    });
    $dotApp->on('test.veto.short_circuit', function ($result, ...$data) use (&$calls) {
        $calls[] = ['veto', $result, $data];
        return new Veto('record.in_use', 'A module still references this record.', ['record_id' => 41]);
    });
    $dotApp->on('test.veto.short_circuit', function () use (&$calls) {
        $calls[] = ['must_not_run'];
        return null;
    });

    try {
        $veto = $dotApp->triggerWithVeto('TEST.VETO.SHORT_CIRCUIT', ['id' => 41], 'extra');
    } finally {
        $dotApp->offevent('test.veto.short_circuit');
    }

    $passed = $veto instanceof Veto
        && $veto->code() === 'record.in_use'
        && count($calls) === 2
        && ($calls[0][0] ?? '') === 'old_false'
        && ($calls[0][1] ?? null) === ['id' => 41]
        && ($calls[0][2] ?? null) === ['extra']
        && ($calls[1][0] ?? '') === 'veto';

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Only a Veto object stops later listeners' : 'Veto short-circuit behavior mismatched',
        'test_name' => 'Test triggerWithVeto short circuit',
        'context' => ['core' => true, 'events' => true, 'veto' => true],
    ];
});

Tester::addTest('test_events_facade_returns_null_without_veto', function () {
    $calls = 0;
    Events::on('test.veto.allowed', function () use (&$calls) {
        $calls++;
        return ['legacy' => true];
    });
    Events::on('test.veto.allowed', function () use (&$calls) {
        $calls++;
        return 'legacy';
    });
    Events::on('test.veto.allowed', function () use (&$calls) {
        $calls++;
        return null;
    });

    try {
        $veto = Events::triggerWithVeto('test.veto.allowed', ['id' => 8]);
    } finally {
        Events::offevent('test.veto.allowed');
    }
    $passed = $veto === null && $calls === 3;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Facade returns null when no Veto object exists' : 'Facade no-veto behavior mismatched',
        'test_name' => 'Test Events triggerWithVeto facade',
        'context' => ['core' => true, 'events' => true, 'veto' => true],
    ];
});

Tester::addTest('test_trigger_with_veto_propagates_listener_exception', function () {
    $dotApp = DotApp::dotApp();
    $laterRan = false;
    $dotApp->on('test.veto.exception', function () {
        throw new \RuntimeException('veto listener failed');
    });
    $dotApp->on('test.veto.exception', function () use (&$laterRan) {
        $laterRan = true;
    });
    $caught = false;

    try {
        $dotApp->triggerWithVeto('test.veto.exception');
    } catch (\RuntimeException $exception) {
        $caught = $exception->getMessage() === 'veto listener failed';
    } finally {
        $dotApp->offevent('test.veto.exception');
    }
    // Why: listener, ktory zlyhal, uz nezastavi listenerov po nom; vynimka pride volajucemu az po ich dobehnuti.
    $passed = $caught && $laterRan === true;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Listener exception reaches the caller after the later listeners ran' : 'Veto exception behavior mismatched',
        'test_name' => 'Test triggerWithVeto exception propagation',
        'context' => ['core' => true, 'events' => true, 'veto' => true],
    ];
});

Tester::addTest('test_trigger_with_veto_returns_veto_after_listener_exception', function () {
    $dotApp = DotApp::dotApp();
    $calls = [];
    $dotApp->on('test.veto.exception_then_veto', function () use (&$calls) {
        $calls[] = 'throws';
        throw new \RuntimeException('listener before the veto failed');
    });
    $dotApp->on('test.veto.exception_then_veto', function () use (&$calls) {
        $calls[] = 'veto';
        return new Veto('record.in_use', 'A module still references this record.');
    });
    $dotApp->on('test.veto.exception_then_veto', function () use (&$calls) {
        $calls[] = 'must_not_run';
    });
    $thrown = false;
    $veto = null;

    try {
        $veto = $dotApp->triggerWithVeto('test.veto.exception_then_veto', ['id' => 9]);
    } catch (\Throwable $exception) {
        $thrown = true;
    } finally {
        $dotApp->offevent('test.veto.exception_then_veto');
    }
    // Why: veto ma prednost pred vynimkou skorsieho listenera (akcia sa aj tak zastavi) a stale konci dispatch.
    $passed = $thrown === false
        && $veto instanceof Veto
        && $veto->code() === 'record.in_use'
        && $calls === ['throws', 'veto'];

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'A Veto of a later listener wins over an earlier listener exception' : 'Veto after exception behavior mismatched',
        'test_name' => 'Test triggerWithVeto veto after listener exception',
        'context' => ['core' => true, 'events' => true, 'veto' => true],
    ];
});

Tester::addTest('test_trigger_isolates_listener_exceptions', function () {
    $dotApp = DotApp::dotApp();
    $calls = [];
    $payload = ['id' => 12];
    $dotApp->on('test.trigger.isolation', function ($result, ...$data) use (&$calls) {
        $calls[] = ['first', $result, $data];
    });
    $dotApp->on('test.trigger.isolation', function () use (&$calls) {
        $calls[] = ['throws_first'];
        throw new \RuntimeException('first listener failure');
    });
    $dotApp->on('test.trigger.isolation', function ($result, ...$data) use (&$calls) {
        $calls[] = ['after_first_failure', $result, $data];
    });
    $dotApp->on('test.trigger.isolation', function () use (&$calls) {
        $calls[] = ['throws_second'];
        throw new \LogicException('second listener failure');
    });
    $dotApp->on('test.trigger.isolation', function () use (&$calls) {
        $calls[] = ['last'];
    });
    $caught = null;
    // Why: druha vynimka ide do error logu; test ju smeruje do docasneho suboru, aby neznecistil log servera.
    $logFile = tempnam(sys_get_temp_dir(), 'dotapp-trigger-');
    $previousLog = ini_set('error_log', (string) $logFile);

    try {
        $dotApp->trigger('TEST.TRIGGER.ISOLATION', $payload, 'extra');
    } catch (\Throwable $exception) {
        $caught = $exception;
    } finally {
        $dotApp->offevent('test.trigger.isolation');
        if ($previousLog !== false) {
            ini_set('error_log', (string) $previousLog);
        }
    }
    $logged = is_string($logFile) && is_file($logFile) ? (string) file_get_contents($logFile) : '';
    if (is_string($logFile) && is_file($logFile)) {
        unlink($logFile);
    }
    $order = array_map(function ($call) {
        return $call[0];
    }, $calls);
    $passed = $caught instanceof \RuntimeException
        && $caught->getMessage() === 'first listener failure'
        && $order === ['first', 'throws_first', 'after_first_failure', 'throws_second', 'last']
        && ($calls[2][1] ?? null) === $payload
        && ($calls[2][2] ?? null) === ['extra']
        && strpos($logged, 'DotApp trigger(test.trigger.isolation): listener failed with LogicException') !== false
        && strpos($logged, 'second listener failure') === false;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Every listener runs; the first exception reaches the caller afterwards, the second is logged without its message' : 'Trigger isolation mismatched',
        'test_name' => 'Test trigger isolates listener exceptions',
        'context' => ['core' => true, 'events' => true],
    ];
});

Tester::addTest('test_trigger_without_exception_keeps_result', function () {
    $dotApp = DotApp::dotApp();
    $calls = 0;
    $payload = ['id' => 13];
    $dotApp->on('test.trigger.plain', function () use (&$calls) {
        $calls++;
        return 'ignored';
    });
    $dotApp->on('test.trigger.plain', function () use (&$calls) {
        $calls++;
    });

    try {
        $returned = $dotApp->trigger('test.trigger.plain', $payload);
    } finally {
        $dotApp->offevent('test.trigger.plain');
    }
    $passed = $returned === $payload && $calls === 2;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Without an exception trigger() behaves as before and returns the given result' : 'Plain trigger behavior changed',
        'test_name' => 'Test trigger without exception',
        'context' => ['core' => true, 'events' => true],
    ];
});

Tester::addTest('test_legacy_trigger_ignores_veto_object', function () {
    $dotApp = DotApp::dotApp();
    $laterRan = false;
    $payload = ['id' => 5];
    $dotApp->on('test.veto.legacy_trigger', function () {
        return new Veto('ignored.by_trigger');
    });
    $dotApp->on('test.veto.legacy_trigger', function () use (&$laterRan) {
        $laterRan = true;
    });

    try {
        $returned = $dotApp->trigger('test.veto.legacy_trigger', $payload);
    } finally {
        $dotApp->offevent('test.veto.legacy_trigger');
    }
    $passed = $returned === $payload && $laterRan === true;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Legacy trigger still ignores every listener return' : 'Legacy trigger compatibility changed',
        'test_name' => 'Test legacy trigger ignores Veto',
        'context' => ['core' => true, 'events' => true, 'veto' => true],
    ];
});

Tester::addTest('test_veto_event_reaches_catchall', function () {
    $dotApp = DotApp::dotApp();
    $traced = [];
    $subscription = $dotApp->on('dotapp.catchall', function ($result, $eventname, ...$data) use (&$traced) {
        $traced[] = [$result, $eventname, $data];
    });

    try {
        $veto = $dotApp->triggerWithVeto('test.veto.catchall', ['id' => 3], 'detail');
    } finally {
        // Why: subscription->off() je v tomto starom jadre chybne naviazane na private metodu, test odstrani iba vlastne id.
        $subscriptionId = (new \ReflectionObject($subscription))->getProperty('id')->getValue($subscription);
        $off = new \ReflectionMethod($dotApp, 'off');
        $off->setAccessible(true);
        $off->invoke($dotApp, $subscriptionId);
    }
    $passed = $veto === null
        && count($traced) === 1
        && ($traced[0][0] ?? null) === ['id' => 3]
        && ($traced[0][1] ?? '') === 'test.veto.catchall'
        && ($traced[0][2] ?? null) === ['detail'];

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Catchall traces veto events once' : 'Veto catchall trace mismatched',
        'test_name' => 'Test veto event catchall trace',
        'context' => ['core' => true, 'events' => true, 'veto' => true],
    ];
});
