<?php

/**
 * CLASS NoDI - Performance-First Wrapper
 * * This class provides a minimalist wrapper for callable entities within the DotApp framework.
 * Its primary purpose is to act as a performance bypass flag, instructing the framework's 
 * resolver to skip all Dependency Injection (DI) and Reflection API processes.
 * * By wrapping a closure, method, or function in this class, the framework will execute it 
 * directly, significantly reducing CPU overhead for high-traffic routes and API endpoints.
 * * @package    DotApp Framework
 * @author     Štefan Miščík <info@dotsystems.sk>
 * @company    Dotsystems s.r.o.
 * @version    1.0
 * @license    MIT License
 * @date       2025
 */

namespace Dotsystems\App\Parts;

class NoDI {
    public $callable;

    /**
     * Constructor for the NoDI wrapper.
     * * @param callable|string|array $callable The callable entity to be executed without DI.
     */
    public function __construct($callable) {
        $this->callable = $callable;
    }

    /**
     * Directly executes the wrapped callable using provided arguments.
     * This method bypasses DotApp's internal Reflection-based argument resolution.
     * * @param mixed ...$args Arguments to be passed to the callable.
     * @return mixed The result of the callable execution.
     */
    public function call(...$args) {
        return call_user_func_array($this->callable, $args);
    }
}