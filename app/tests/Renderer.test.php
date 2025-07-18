<?php
namespace Dotsystems\App\Parts\Tests;

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Renderer;
use Dotsystems\App\Parts\Tester;

// Získanie globálnej inštancie DotApp a vytvorenie inštancie Renderer
$dotApp = DotApp::dotApp();
$renderer = new Renderer($dotApp);

// Test pre setLayout a getLayoutVar
Tester::addTest('test_setLayout_getLayoutVar', function () use ($renderer) {
    $layout = 'test_layout';
    $varName = 'test_var';
    $varValue = 'test_value';

    $renderer->setLayout($layout);
    $renderer->setLayoutVar($varName, $varValue);
    $result = $renderer->getLayoutVar($varName);
    $vars = $renderer->getLayoutVars();

    $passed = $result === $varValue && isset($vars[$varName]) && $vars[$varName] === $varValue;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'setLayout and getLayoutVar work correctly' : "Failed: result=$result, expected=$varValue, vars=" . var_export($vars, true),
        'test_name' => 'Test setLayout and getLayoutVar',
        'context' => ['core' => true]
    ];
});

// Test pre setView a getViewVar
Tester::addTest('test_setView_getViewVar', function () use ($renderer) {
    $view = 'test_view';
    $varName = 'test_var';
    $varValue = 'test_value';

    $renderer->setView($view);
    $renderer->setViewVar($varName, $varValue);
    $result = $renderer->getViewVar($varName);
    $vars = $renderer->getViewVars();

    $passed = $result === $varValue && isset($vars[$varName]) && $vars[$varName] === $varValue;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'setView and getViewVar work correctly' : "Failed: result=$result, expected=$varValue, vars=" . var_export($vars, true),
        'test_name' => 'Test setView and getViewVar',
        'context' => ['core' => true]
    ];
});

// Test pre minimizeHTML
Tester::addTest('test_minimizeHTML', function () use ($renderer) {
    $inputs = [
        "<div>  Test  </div> <!-- comment --> <p>  Text  </p>" => "<div>Test</div><p>Text</p>",
        "  <span>Hello</span>   <span>World</span>  " => "<span>Hello</span><span>World</span>",
        "<p>\n\tText\n</p>" => "<p>Text</p>"
    ];

    $allPassed = true;
    $details = [];

    foreach ($inputs as $input => $expected) {
        $result = $renderer->minimizeHTML($input);
        $passed = $result === $expected;
        $allPassed = $allPassed && $passed;
        $details[] = "Input: $input, Result: $result, Expected: $expected, Passed: " . ($passed ? 'Yes' : 'No');
    }

    return [
        'status' => $allPassed ? 1 : 0,
        'info' => $allPassed ? 'minimizeHTML works correctly' : 'Failed: ' . implode('; ', $details),
        'test_name' => 'Test minimizeHTML',
        'context' => ['core' => true]
    ];
});

// Test pre minimizeCSS
Tester::addTest('test_minimizeCSS', function () use ($renderer) {
    $inputs = [
        "body {  color:  red;  } /* comment */ div { margin: 10px; }" => "body{color:red}div{margin:10px}",
        ".class {  padding:  5px; \n\n font-size:  12px; }" => ".class{padding:5px;font-size:12px}",
        "p { margin: 0 !important; }" => "p{margin:0 !important}"
    ];

    $allPassed = true;
    $details = [];

    foreach ($inputs as $input => $expected) {
        $result = $renderer->minimizeCSS($input);
        $passed = $result === $expected;
        $allPassed = $allPassed && $passed;
        $details[] = "Input: $input, Result: $result, Expected: $expected, Passed: " . ($passed ? 'Yes' : 'No');
    }

    return [
        'status' => $allPassed ? 1 : 0,
        'info' => $allPassed ? 'minimizeCSS works correctly' : 'Failed: ' . implode('; ', $details),
        'test_name' => 'Test minimizeCSS',
        'context' => ['core' => true]
    ];
});

// Test pre minimizeJS
Tester::addTest('test_minimizeJS', function () use ($renderer) {
    $inputs = [
        "function test() {  return 1;  } // comment \n var x = 2;" => "function test(){return 1}var x=2;",
        "var a = 5; /* multi-line\ncomment */ var b = 10;" => "var a=5;var b=10;",
        "if (true) { console.log('test'); }" => "if(true){console.log('test')}"
    ];

    $allPassed = true;
    $details = [];

    foreach ($inputs as $input => $expected) {
        $result = $renderer->minimizeJS($input);
        $passed = $result === $expected;
        $allPassed = $allPassed && $passed;
        $details[] = "Input: $input, Result: $result, Expected: $expected, Passed: " . ($passed ? 'Yes' : 'No');
    }

    return [
        'status' => $allPassed ? 1 : 0,
        'info' => $allPassed ? 'minimizeJS works correctly' : 'Failed: ' . implode('; ', $details),
        'test_name' => 'Test minimizeJS',
        'context' => ['core' => true]
    ];
});

// Test pre escapePHP
Tester::addTest('test_escapePHP', function () use ($renderer) {
    $inputs = [
        "<?php echo 'test'; ?>" => "",
        "<?xml version='1.0' ?><tag></tag>" => "<?xml version='1.0' ?><tag></tag>",
        "<script language='php'>echo 'test';</script>" => "",
        "<% echo 'test'; %>" => ""
    ];

    $allPassed = true;
    $details = [];

    foreach ($inputs as $input => $expected) {
        $result = $renderer->escapePHP($input);
        $passed = $result === $expected;
        $allPassed = $allPassed && $passed;
        $details[] = "Input: $input, Result: $result, Expected: $expected, Passed: " . ($passed ? 'Yes' : 'No');
    }

    return [
        'status' => $allPassed ? 1 : 0,
        'info' => $allPassed ? 'escapePHP works correctly' : 'Failed: ' . implode('; ', $details),
        'test_name' => 'Test escapePHP',
        'context' => ['core' => true]
    ];
});

// Test pre addRenderer a getRenderer
Tester::addTest('test_addRenderer_getRenderer', function () use ($renderer, $dotApp) {
    $rendererName = 'test_renderer';
    $customRenderer = function ($code) { return $code . '<!-- Test -->'; };
    
    $renderer->addRenderer($rendererName, $customRenderer);
    $retrievedRenderer = $renderer->getRenderer($rendererName);
    
    $testCode = '<div>Test</div>';
    $expected = $testCode . '<!-- Test -->';
    $result = $retrievedRenderer($testCode);
    
    $passed = $result === $expected;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'addRenderer and getRenderer work correctly' : "Failed: result=$result, expected=$expected",
        'test_name' => 'Test addRenderer and getRenderer',
        'context' => ['core' => true]
    ];
});

// Test pre renderWith
Tester::addTest('test_renderWith', function () use ($renderer, $dotApp) {
    $rendererName = 'test_renderer';
    $customRenderer = function ($code) { return $code . '<!-- Custom Render -->'; };
    
    $renderer->addRenderer($rendererName, $customRenderer);
    $testCode = '<div>Test</div>';
    $result = $renderer->renderWith($rendererName, $testCode);
    $expected = $testCode . '<!-- Custom Render -->';
    
    $passed = $result === $expected;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'renderWith works correctly' : "Failed: result=$result, expected=$expected",
        'test_name' => 'Test renderWith',
        'context' => ['core' => true]
    ];
});

// Test pre addBlock
Tester::addTest('test_addBlock', function () use ($renderer, $dotApp) {
    $blockName = 'test_block';
    $blockFn = function ($innerContent, $blockVariables, $variables) {
        return "<div>$innerContent</div>";
    };
    
    $renderer->addBlock($blockName, $blockFn);
    $code = "{{ block:$blockName }}Test Content{{ /block:$blockName }}";
    $result = $renderer->renderWith('dotapp.block', $code);
    $expected = "<div>Test Content</div>";
    
    $passed = $result === $expected;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'addBlock works correctly' : "Failed: result=$result, expected=$expected",
        'test_name' => 'Test addBlock',
        'context' => ['core' => true]
    ];
});

// Test pre customRenderers
Tester::addTest('test_customRenderers', function () use ($renderer, $dotApp) {
    $rendererName = 'test_renderer';
    $customRenderer = function ($code) { return $code . '<!-- Custom -->'; };
    
    $renderer->addRenderer($rendererName, $customRenderer);
    $renderers = $renderer->customRenderers();
    
    $passed = is_array($renderers) && isset($renderers[$rendererName]) && is_callable($renderers[$rendererName]);

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'customRenderers returns correct renderers' : "Failed: renderers=" . var_export($renderers, true),
        'test_name' => 'Test customRenderers',
        'context' => ['core' => true]
    ];
});

// Test pre updateLayoutContentData
Tester::addTest('test_updateLayoutContentData', function () use ($renderer, $dotApp) {
    $inputs = [
        '{{ var: $testVar }}' => '<?php echo $testVar; ?>',
        '{{_ "Hello" }}' => '<?php echo $translator("Hello"); ?>',
        '{{ if $condition }}Content{{ /if }}' => '<?php if ($condition): ?>Content<?php endif; ?>'
    ];

    $allPassed = true;
    $details = [];

    foreach ($inputs as $input => $expected) {
        $result = $renderer->renderCode($input,[],false);
        
        $passed = $result === $expected;
        $allPassed = $allPassed && $passed;
        $details[] = "Input: $input, Result: $result, Expected: $expected, Passed: " . ($passed ? 'Yes' : 'No');
    }

    return [
        'status' => $allPassed ? 1 : 0,
        'info' => $allPassed ? 'updateLayoutContentData processes patterns correctly' : 'Failed: ' . implode('; ', $details),
        'test_name' => 'Test updateLayoutContentData',
        'context' => ['core' => true]
    ];
});

?>