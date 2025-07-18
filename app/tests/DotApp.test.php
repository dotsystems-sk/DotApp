<?php
namespace Dotsystems\App\Tests;

use Dotsystems\App\DotApp;
use Dotsystems\App\Parts\Tester;

// Získanie globálnej inštancie DotApp
$dotApp = DotApp::dotApp();

// Test pre isDebugMode
Tester::addTest('test_isDebugMode', function () use ($dotApp) {
    $result = $dotApp->isDebugMode();
    $expected = defined('DEBUG_MODE') ? DEBUG_MODE : false;

    return [
        'status' => $result === $expected ? 1 : 0,
        'info' => $result === $expected ? 'isDebugMode returns correct value' : "isDebugMode returned $result, expected $expected",
        'test_name' => 'Test isDebugMode',
        'context' => ['core' => true]
    ];
});

// Test pre isset_data, set_data, get_data
Tester::addTest('test_data_operations', function () use ($dotApp) {
    $key = 'test_data';
    $value = 'test_value';
    
    $dotApp->set_data($key, $value);
    $exists = $dotApp->isset_data($key);
    $retrieved = $dotApp->get_data($key);
    $nonExistent = $dotApp->get_data('non_existent_key');

    $passed = $exists === true && $retrieved === $value && $nonExistent === false;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'Data operations work correctly' : 'Data operations failed: exists=' . var_export($exists, true) . ', retrieved=' . var_export($retrieved, true) . ', nonExistent=' . var_export($nonExistent, true),
        'test_name' => 'Test isset_data, set_data, get_data',
        'context' => ['core' => true]
    ];
});

// Test pre protect_data a unprotect_data
Tester::addTest('test_protect_unprotect_data', function () use ($dotApp) {
    $inputs = [
        "<script>alert('xss')</script>",
        "Test with & special chars'\"\\",
        "SELECT * FROM users; --"
    ];

    $allPassed = true;
    $details = [];

    foreach ($inputs as $input) {
        $protected = $dotApp->protect_data($input);
        $unprotected = $dotApp->unprotect_data($protected);
        $passed = $unprotected === $input;
        $allPassed = $allPassed && $passed;
        $details[] = "Input: $input, Protected: $protected, Unprotected: $unprotected, Passed: " . ($passed ? 'Yes' : 'No');
    }

    return [
        'status' => $allPassed ? 1 : 0,
        'info' => $allPassed ? 'protect_data and unprotect_data work correctly' : 'Failed: ' . implode('; ', $details),
        'test_name' => 'Test protect_data and unprotect_data',
        'context' => ['core' => true]
    ];
});

// Test pre encrypt a decrypt
Tester::addTest('test_encrypt_decrypt', function () use ($dotApp) {
    $inputs = [
        "Test string 123",
        "<script>alert('xss')</script>",
        "Special chars &*#@"
    ];
    $key2 = "test_key";
    $wrongKey = "wrong_key";

    $allPassed = true;
    $details = [];

    foreach ($inputs as $input) {
        // Test správneho šifrovania a dešifrovania
        $encrypted = $dotApp->encrypt($input, $key2);
        $decrypted = $dotApp->decrypt($encrypted, $key2);
        $correctKeyPassed = $decrypted === $input;

        // Test nesprávneho kľúča
        $wrongKeyDecrypted = $dotApp->decrypt($encrypted, $wrongKey);
        $wrongKeyPassed = $wrongKeyDecrypted === false;

        $passed = $correctKeyPassed && $wrongKeyPassed;
        $allPassed = $allPassed && $passed;
        $details[] = "Input: $input, Correct key decrypted: " . ($correctKeyPassed ? 'Yes' : 'No') . ", Wrong key decrypted: " . ($wrongKeyPassed ? 'Yes' : 'No');
    }

    return [
        'status' => $allPassed ? 1 : 0,
        'info' => $allPassed ? 'encrypt and decrypt work correctly' : 'Failed: ' . implode('; ', $details),
        'test_name' => 'Test encrypt and decrypt',
        'context' => ['core' => true]
    ];
});

// Test pre encrypta a decrypta
Tester::addTest('test_encrypta_decrypta', function () use ($dotApp) {
    $inputs = [
        ['key' => 'value', 'number' => 123],
        ['xss' => "<script>alert('xss')</script>", 'test' => 'data'],
        ['special' => "&*#@"]
    ];
    $key2 = "test_array_key";
    $wrongKey = "wrong_array_key";

    $allPassed = true;
    $details = [];

    foreach ($inputs as $input) {
        // Test správneho šifrovania a dešifrovania poľa
        $encrypted = $dotApp->encrypta($input, $key2);
        $decrypted = $dotApp->decrypta($encrypted, $key2);
        $correctKeyPassed = $decrypted === $input;

        // Test nesprávneho kľúča
        $wrongKeyDecrypted = $dotApp->decrypta($encrypted, $wrongKey);
        $wrongKeyPassed = $wrongKeyDecrypted === false;

        $passed = $correctKeyPassed && $wrongKeyPassed;
        $allPassed = $allPassed && $passed;
        $details[] = "Input: " . json_encode($input) . ", Correct key decrypted: " . ($correctKeyPassed ? 'Yes' : 'No') . ", Wrong key decrypted: " . ($wrongKeyPassed ? 'Yes' : 'No');
    }

    return [
        'status' => $allPassed ? 1 : 0,
        'info' => $allPassed ? 'encrypta and decrypta work correctly' : 'Failed: ' . implode('; ', $details),
        'test_name' => 'Test encrypta and decrypta',
        'context' => ['core' => true]
    ];
});

// Test pre generatePasswordHash a verifyPassword
Tester::addTest('test_generatePasswordHash_verifyPassword', function () use ($dotApp) {
    $passwords = [
        "Password123!",
        "Test@456",
        "Complex#Pass$789"
    ];

    $allPassed = true;
    $details = [];

    foreach ($passwords as $password) {
        // Test správneho hashovania a overenia
        $hash = $dotApp->generatePasswordHash($password);
        $verified = $dotApp->verifyPassword($password, $hash);
        $correctPassPassed = $verified === true;

        // Test nesprávneho hesla
        $wrongPassVerified = $dotApp->verifyPassword($password . "wrong", $hash);
        $wrongPassPassed = $wrongPassVerified === false;

        $passed = $correctPassPassed && $wrongPassPassed;
        $allPassed = $allPassed && $passed;
        $details[] = "Password: $password, Correct pass verified: " . ($correctPassPassed ? 'Yes' : 'No') . ", Wrong pass verified: " . ($wrongPassPassed ? 'Yes' : 'No');
    }

    return [
        'status' => $allPassed ? 1 : 0,
        'info' => $allPassed ? 'generatePasswordHash and verifyPassword work correctly' : 'Failed: ' . implode('; ', $details),
        'test_name' => 'Test generatePasswordHash and verifyPassword',
        'context' => ['core' => true]
    ];
});

// Test pre is_json
Tester::addTest('test_is_json', function () use ($dotApp) {
    $validJson = '{"key":"value"}';
    $invalidJson = '{"key":"value"';
    
    $validResult = $dotApp->is_json($validJson);
    $invalidResult = $dotApp->is_json($invalidJson);

    $passed = $validResult === true && $invalidResult === false;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'is_json validates JSON correctly' : "is_json failed: valid=$validResult, invalid=$invalidResult",
        'test_name' => 'Test is_json',
        'context' => ['core' => true]
    ];
});

// Test pre create_alias
Tester::addTest('test_create_alias', function () use ($dotApp) {
    $inputs = [
        "Hello World! Český Text" => "hello-world-cesky-text",
        "Test & Special Chars" => "test-special-chars",
        "Multi---Dash" => "multi-dash"
    ];

    $allPassed = true;
    $details = [];

    foreach ($inputs as $input => $expected) {
        $result = $dotApp->create_alias($input);
        $passed = $result === $expected;
        $allPassed = $allPassed && $passed;
        $details[] = "Input: $input, Result: $result, Expected: $expected, Passed: " . ($passed ? 'Yes' : 'No');
    }

    return [
        'status' => $allPassed ? 1 : 0,
        'info' => $allPassed ? 'create_alias generates correct alias' : 'Failed: ' . implode('; ', $details),
        'test_name' => 'Test create_alias',
        'context' => ['core' => true]
    ];
});

// Test pre removeNonAlphanumeric
Tester::addTest('test_removeNonAlphanumeric', function () use ($dotApp) {
    $inputs = [
        "Hello!@# World_123" => "HelloWorld123",
        "Test &*#@ Special" => "TestSpecial",
        "123-456!" => "123456"
    ];

    $allPassed = true;
    $details = [];

    foreach ($inputs as $input => $expected) {
        $result = $dotApp->removeNonAlphanumeric($input);
        $passed = $result === $expected;
        $allPassed = $allPassed && $passed;
        $details[] = "Input: $input, Result: $result, Expected: $expected, Passed: " . ($passed ? 'Yes' : 'No');
    }

    return [
        'status' => $allPassed ? 1 : 0,
        'info' => $allPassed ? 'removeNonAlphanumeric works correctly' : 'Failed: ' . implode('; ', $details),
        'test_name' => 'Test removeNonAlphanumeric',
        'context' => ['core' => true]
    ];
});

// Test pre generate_strong_password
Tester::addTest('test_generate_strong_password', function () use ($dotApp) {
    $length = 12;
    $password = $dotApp->generate_strong_password($length);
    
    $hasUpper = preg_match('/[A-Z]/', $password);
    $hasLower = preg_match('/[a-z]/', $password);
    $hasNumber = preg_match('/[0-9]/', $password);
    $hasSpecial = preg_match('/[!@#$%^&*()\-_=+[\]{}|;:,.<>?]/', $password);
    $correctLength = strlen($password) === $length;

    $passed = $hasUpper && $hasLower && $hasNumber && $hasSpecial && $correctLength;

    return [
        'status' => $passed ? 1 : 0,
        'info' => $passed ? 'generate_strong_password creates valid password' : "Password failed: length=$correctLength, upper=$hasUpper, lower=$hasLower, number=$hasNumber, special=$hasSpecial",
        'test_name' => 'Test generate_strong_password',
        'context' => ['core' => true]
    ];
});

?>