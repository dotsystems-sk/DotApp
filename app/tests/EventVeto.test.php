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
    $passed = $caught && $laterRan === false;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Listener exception propagates and stops dispatch' : 'Veto exception behavior mismatched',
        'test_name' => 'Test triggerWithVeto exception propagation',
        'context' => ['core' => true, 'events' => true, 'veto' => true],
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
