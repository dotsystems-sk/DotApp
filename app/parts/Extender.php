<?php
/**
 * Explicit opt-in replacement registry for framework extension points.
 *
 * Copy this file to app/parts/Extender.php to activate its PSR-4 namespace.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   2.0 FREE
 * @license   MIT License
 * @date      2014 - 2026
 *
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the
 * following condition: You must retain this header in all copies or
 * substantial portions of the code, including the author and company information.
 */

namespace Dotsystems\App\Parts;

use Dotsystems\App\DotApp;

final class Extender
{
    /** @var array<string, callable|string> */
    private static $extensions = [];

    /** @var array<string, bool> */
    private static $activeCalls = [];

    /** @var object|null */
    private static $originalSignal;

    /**
     * Registers one replacement handler for an explicitly extendable method.
     *
     * Controller strings use the standard Module:Controller@method! syntax.
     * Native PHP callables are invoked directly without DotApp dependency injection.
     *
     * @param string $className Fully qualified class name of the extension point.
     * @param string $methodName Method name of the extension point.
     * @param callable|string $handler Native callable or DotApp controller string.
     * @return void
     * @throws \InvalidArgumentException When the target or handler is invalid.
     * @throws \LogicException When the target already has a registered extender.
     */
    public static function extend(string $className, string $methodName, $handler): void
    {
        $key = self::targetKey($className, $methodName);
        self::validateHandler($handler);

        // Why: silently replacing an extender would make behavior depend on module boot order.
        if (array_key_exists($key, self::$extensions)) {
            throw new \LogicException(
                "An extender is already registered for {$className}::{$methodName}."
            );
        }

        self::$extensions[$key] = $handler;
    }

    /**
     * Reports whether a target method has a registered replacement.
     *
     * @param string $className Fully qualified class name of the extension point.
     * @param string $methodName Method name of the extension point.
     * @return bool True when exactly one replacement is registered.
     * @throws \InvalidArgumentException When the target identifier is invalid.
     */
    public static function exists(string $className, string $methodName): bool
    {
        return array_key_exists(self::targetKey($className, $methodName), self::$extensions);
    }

    /**
     * Provides the singular-name alias proposed by the original public API.
     *
     * @param string $className Fully qualified class name of the extension point.
     * @param string $methodName Method name of the extension point.
     * @return bool True when exactly one replacement is registered.
     * @throws \InvalidArgumentException When the target identifier is invalid.
     */
    public static function exist(string $className, string $methodName): bool
    {
        return self::exists($className, $methodName);
    }

    /**
     * Returns the unique signal telling an extension point to run its original logic.
     *
     * @return object Request-local identity marker; never expose it as an HTTP response.
     */
    public static function original()
    {
        if (self::$originalSignal === null) {
            // Why: an object identity cannot collide with a legitimate scalar or array handler result.
            self::$originalSignal = new class {
            };
        }

        return self::$originalSignal;
    }

    /**
     * Reports whether a replacement returned the exact original-logic signal.
     *
     * @param mixed $result Replacement return value.
     * @return bool True only for the marker created by original().
     */
    public static function isOriginal($result): bool
    {
        return is_object($result) && $result === self::original();
    }

    /**
     * Invokes the registered replacement and returns its result unchanged.
     *
     * @param string $className Fully qualified class name of the extension point.
     * @param string $methodName Method name of the extension point.
     * @param mixed ...$arguments Explicit arguments exposed by the extension point.
     * @return mixed Replacement return value.
     * @throws \InvalidArgumentException When the target identifier is invalid.
     * @throws \LogicException When no replacement exists or the target re-enters itself.
     * @throws \Throwable Any exception raised by the replacement handler.
     */
    public static function call(string $className, string $methodName, ...$arguments)
    {
        $key = self::targetKey($className, $methodName);

        if (!array_key_exists($key, self::$extensions)) {
            throw new \LogicException(
                "No extender is registered for {$className}::{$methodName}."
            );
        }

        // Why: a replacement pointing back to its own extension point must fail instead of exhausting the stack.
        if (isset(self::$activeCalls[$key])) {
            throw new \LogicException(
                "Recursive extender call detected for {$className}::{$methodName}."
            );
        }

        self::$activeCalls[$key] = true;

        try {
            $handler = self::$extensions[$key];

            // Why: controller strings keep DotApp's existing parser and optional DI behavior.
            if (is_string($handler) && strpos($handler, '@') !== false) {
                return DotApp::call($handler, ...$arguments);
            }

            return call_user_func_array($handler, $arguments);
        } finally {
            unset(self::$activeCalls[$key]);
        }
    }

    /**
     * Builds a case-insensitive registry key without autoloading the target class.
     *
     * @param string $className Fully qualified class name supplied by the caller.
     * @param string $methodName Method name supplied by the caller.
     * @return string Canonical class and method key.
     * @throws \InvalidArgumentException When either identifier is malformed.
     */
    private static function targetKey(string $className, string $methodName): string
    {
        $className = ltrim(trim($className), '\\');
        $methodName = trim($methodName);

        if (
            $className === ''
            || preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*[A-Za-z_][A-Za-z0-9_]*$/', $className) !== 1
        ) {
            throw new \InvalidArgumentException('Extender class name must be a valid fully qualified PHP class name.');
        }

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $methodName) !== 1) {
            throw new \InvalidArgumentException('Extender method name must be a valid PHP method name.');
        }

        // Why: PHP resolves class and method names case-insensitively, so the registry must do the same.
        return strtolower($className . '::' . $methodName);
    }

    /**
     * Rejects ambiguous handlers before they enter the request-local registry.
     *
     * @param mixed $handler Candidate native callable or DotApp controller string.
     * @return void
     * @throws \InvalidArgumentException When the handler cannot be invoked.
     */
    private static function validateHandler($handler): void
    {
        if (is_string($handler) && strpos($handler, '@') !== false) {
            // Why: reuse the canonical controller parser so Extender does not invent a second route grammar.
            DotApp::dotApp()->stringToCallable($handler);
            return;
        }

        if (!is_callable($handler)) {
            throw new \InvalidArgumentException(
                'Extender handler must be a native PHP callable or a DotApp controller string.'
            );
        }
    }
}
