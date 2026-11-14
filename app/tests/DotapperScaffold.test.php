<?php
/**
 * Tests the DotApper module initializer scaffold contract.
 *
 * @package DotApp Framework
 */

namespace Dotsystems\App\Parts\Tests;

use Dotsystems\App\Parts\Tester;

Tester::addTest('test_dotapper_scaffold_adds_inactive_base_languages_example', function () {
    $source = file_get_contents(__ROOTDIR__ . '/dotapper.php');
    $usesTemplateHelper = is_string($source)
        && substr_count($source, '$moduleInitBody = $this->moduleInitTemplate();') === 2
        && substr_count($source, '$file_body = $moduleInitBody;') === 2;

    $dotApper = new \Dotsystems\DotApper\DotApper([]);
    $method = new \ReflectionMethod($dotApper, 'moduleInitTemplate');
    $method->setAccessible(true);
    $template = $method->invoke($dotApper);
    $generated = str_replace('#modulename', 'ScaffoldProbe', $template);

    $keepsMethodInactive = strpos($generated, "\t\t// public function baseLanguages() {") !== false
        && strpos($generated, "\t\t//\treturn [") !== false
        && strpos($generated, "\t\t// }") !== false
        && strpos($generated, "\t\tpublic function baseLanguages() {") === false;
    $documentsFallback = strpos($generated, 'Keep this method commented, or return [],') !== false
        && strpos($generated, 'menu language can change when the module wakes.') !== false;
    $passed = $usesTemplateHelper && $keepsMethodInactive && $documentsFallback;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed
            ? 'Both module generators add an inactive baseLanguages example'
            : 'DotApper baseLanguages scaffold wiring is incomplete',
        'test_name' => 'DotApper adds inactive baseLanguages scaffold',
        'context' => ['core' => true, 'dotapper' => true]
    ];
});

Tester::addTest('test_dotapper_scaffold_keeps_module_placeholder', function () {
    $source = file_get_contents(__ROOTDIR__ . '/dotapper.php');
    $passed = is_string($source)
        && strpos($source, "#modulename:menu_sk.json") !== false
        && strpos($source, "'locale' => 'sk_sk'") !== false;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed
            ? 'Base-language scaffold keeps the module and locale descriptors'
            : 'Base-language scaffold descriptor is missing',
        'test_name' => 'DotApper keeps base-language descriptor placeholders',
        'context' => ['core' => true, 'dotapper' => true]
    ];
});
