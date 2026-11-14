<?php
/**
 * Tests module base-language compilation and loader compatibility.
 *
 * @package DotApp Framework
 */

namespace Dotsystems\App\Parts\Tests;

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Module;
use Dotsystems\App\Parts\Tester;
use Dotsystems\App\Parts\Translator;

require_once __DIR__ . '/BaseLanguageProbe.fixture.php';

Tester::addTest('test_base_languages_default_and_inert_module_peek', function () {
    $defaultModule = new class(null, true) extends Module {
        /**
         * Keep the anonymous module inert.
         *
         * @param mixed $dotapp Unused DotApp instance.
         * @return void
         */
        public function initialize($dotapp)
        {
            unset($dotapp);
        }
    };

    \Dotsystems\App\Modules\BaseLanguageProbe\Module::$initializeCalls = 0;
    $compiled = Module::compileBaseLanguagesForModules(['BaseLanguageProbe'], 'sk_sk');
    $passed = $defaultModule->baseLanguages() === []
        && $compiled === []
        && \Dotsystems\App\Modules\BaseLanguageProbe\Module::$initializeCalls === 0;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed
            ? 'Default base languages stay empty and optimization peeks do not initialize modules'
            : 'Default base-language or inert peek behavior failed',
        'test_name' => 'Base languages default to an inert empty catalog',
        'context' => ['core' => true, 'translations' => true]
    ];
});

Tester::addTest('test_base_language_descriptor_validation', function () {
    $rows = Module::validateBaseLanguages([
        ['file' => 'Shop:menu_sk.json', 'locale' => 'SK_SK'],
        ['file' => 'DACore:sk_sk.json', 'locale' => 'sk_sk'],
        ['file' => 'Shop:../module.init.php', 'locale' => 'sk_sk'],
        ['file' => 'Shop:menu_sk.php', 'locale' => 'sk_sk'],
        ['file' => 'Shop:menu_sk.json', 'locale' => ['sk_sk']],
        ['missing' => 'Shop:menu_sk.json']
    ], 'Shop');

    $expected = [
        ['file' => 'Shop:menu_sk.json', 'locale' => 'sk_sk']
    ];
    $passed = $rows === $expected;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed
            ? 'Only owned JSON descriptors with normalized locales are accepted'
            : 'Unsafe or malformed base-language descriptor was accepted',
        'test_name' => 'Base-language descriptors enforce ownership and JSON paths',
        'context' => ['core' => true, 'translations' => true]
    ];
});

Tester::addTest('test_base_language_conflict_uses_first_module_alphabetically', function () {
    $merged = Module::mergeBaseLanguageModuleMaps([
        'Zulu' => [
            'sk_sk' => ['shared label' => 'Zulu', 'zulu label' => 'Zulu only']
        ],
        'Alpha' => [
            'sk_sk' => ['shared label' => 'Alpha', 'alpha label' => 'Alpha only']
        ]
    ]);

    $passed = ($merged['sk_sk']['shared label'] ?? null) === 'Alpha'
        && ($merged['sk_sk']['alpha label'] ?? null) === 'Alpha only'
        && ($merged['sk_sk']['zulu label'] ?? null) === 'Zulu only';

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed
            ? 'The first module alphabetically keeps conflicting base translations'
            : 'Base-language conflict precedence is not deterministic',
        'test_name' => 'Base-language conflicts use alphabetical module precedence',
        'context' => ['core' => true, 'translations' => true]
    ];
});

Tester::addTest('test_base_language_compiler_reads_only_requested_locale', function () {
    $compiled = Module::compileBaseLanguages([
        'SMSFarm' => [
            ['file' => 'SMSFarm:sk_sk.json', 'locale' => 'sk_sk'],
            ['file' => 'SMSFarm:missing_en.json', 'locale' => 'en_us']
        ]
    ], 'sk_SK');

    $passed = isset($compiled['sk_sk']['sms farm'])
        && !isset($compiled['en_us']);

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed
            ? 'Non-optimized compilation reads only the active locale descriptors'
            : 'Base-language active-locale compilation failed',
        'test_name' => 'Base-language compiler limits non-optimized locale I/O',
        'context' => ['core' => true, 'translations' => true]
    ];
});

Tester::addTest('test_base_language_optimizer_payload_stays_version_two', function () {
    $method = new \ReflectionMethod(Module::class, 'buildOptimizerCode');
    $method->setAccessible(true);
    $modules = ['Shop' => ['/shop/*']];
    $listeners = ['Shop' => ['/shop/*']];

    $withoutBase = $method->invoke(null, $modules, $listeners, []);
    $withBase = $method->invoke(null, $modules, $listeners, [
        'sk_sk' => ['shop' => 'Obchod']
    ]);
    $passed = strpos($withoutBase, '$modulesAutoLoaderVersion = 2;') !== false
        && strpos($withBase, '$modulesAutoLoaderVersion = 2;') !== false
        && strpos($withoutBase, '$baseLanguages = ') === false
        && strpos($withBase, '$baseLanguages = ') !== false;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed
            ? 'The optional compiled language section preserves loader version 2'
            : 'Base-language optimizer payload changed the compatibility version',
        'test_name' => 'Base-language optimizer payload remains version two',
        'context' => ['core' => true, 'translations' => true, 'optimizer' => true]
    ];
});

Tester::addTest('test_active_base_languages_apply_after_main_translations', function () {
    $previousLocale = Translator::getLocale();
    Translator::setLocale('zz_zz');
    Translator::loadLocaleFile('/app/tests/BaseLanguageMain.fixture.json', 'zz_zz');

    $dotApp = DotApp::dotApp();
    $method = new \ReflectionMethod($dotApp, 'applyActiveBaseLanguages');
    $method->setAccessible(true);
    $method->invoke($dotApp, [
        'zz_zz' => [
            'base runtime shared key' => 'Base translation',
            'base runtime new key' => 'Base-only translation',
            '404' => 'Base numeric translation'
        ],
        'yy_yy' => [
            'base runtime inactive key' => 'Inactive translation'
        ]
    ]);

    $shared = Translator::trans('Base Runtime Shared Key');
    $mainOnly = Translator::trans('Base Runtime Main-Only Key');
    $new = Translator::trans('Base Runtime New Key');
    $numeric = Translator::trans('404');
    $inactiveLoaded = Translator::has('Base Runtime Inactive Key', 'yy_yy');

    // Why: Re-queue the main file to model a module that wakes after the initial overlay.
    Translator::loadLocaleFile('/app/tests/BaseLanguageMain.fixture.json', 'zz_zz');
    $reapply = new \ReflectionMethod($dotApp, 'reapplyActiveBaseLanguages');
    $reapply->setAccessible(true);
    $reapply->invoke($dotApp);
    $afterLateWake = Translator::trans('Base Runtime Shared Key');
    Translator::setLocale($previousLocale);

    $passed = $shared === 'Base translation'
        && $mainOnly === 'Main-only translation'
        && $new === 'Base-only translation'
        && $numeric === 'Base numeric translation'
        && $inactiveLoaded === false
        && $afterLateWake === 'Base translation';

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed
            ? 'Only the active base locale is applied after main translations'
            : 'Active base-language runtime precedence or memory filtering failed',
        'test_name' => 'Active base languages override main translations only for active locale',
        'context' => ['core' => true, 'translations' => true]
    ];
});
