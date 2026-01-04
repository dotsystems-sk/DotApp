<?php
/**
 * CLASS di - Universal Dependency Injection Wrapper
 *
 * This class provides a wrapper for dependency injection, enabling method calls on a wrapped object 
 * or class while resolving dependencies through the DotApp framework's resolver. It supports both 
 * dynamic instance calls and static method calls via an explicitly provided DotApp instance.
 * For chaining, it ensures that if a method returns $this, the wrapper instance is returned instead.
 *
 * Key Features:
 * - Wraps an object or class and delegates method calls to it.
 * - Resolves method dependencies using the DotApp framework's resolver.
 * - Requires an explicit DotApp instance via constructor.
 * - Supports dynamic argument resolution for instance and static method calls.
 * - Ensures method chaining by returning the wrapper instance when $this is returned.
 * - Throws exceptions for undefined methods or unresolvable dependencies.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.3
 * @license   MIT License
 * @date      2014 - 2026
 * POSLAT3
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the 
 * following condition: You **must** retain this header in all copies or 
 * substantial portions of the code, including the author and company information.
 */

namespace Dotsystems\App\Parts;
use Dotsystems\App\DotApp;

class DI {
    private $target; // Object instance (dynamic) or class name (static)
    private $dotapp; // Instance-specific DotApp resolver (required)
    public $classname=null;

    // Konštruktor
    function __construct($target, $dotapp=null) {
        if ($dotapp === null) $dotapp = DotApp::dotApp();
        if ($target instanceof \Closure || (!is_object($target) && !is_string($target))) {
            throw new \InvalidArgumentException("Target must be an object instance or class name string.");
        } 
        if (is_object($target)) {
            $this->classname = get_class($target);
        }
        if ($dotapp === null) {
            throw new \InvalidArgumentException("DotApp instance is required.");
        }
        $this->target = $target;
        $this->dotapp = $dotapp;
    }

    public function getTarget() {
        return ($this->target);
    }

    // Dynamické volania (inštancie)
    public function __call($method, $arguments) {
        if (!is_object($this->target)) {
            throw new \Exception("Cannot call instance method $method on a non-object target. Use callStatic for class names.");
        }

        if (method_exists($this->target, $method)) {
            $reflection = new \ReflectionMethod($this->target, $method);
            $parameters = $reflection->getParameters();
            $resolvedArguments = $this->resolveArguments($parameters, $arguments);
            $result = call_user_func_array([$this->target, $method], $resolvedArguments);
            // Ak metóda vráti $this, vrátime namiesto toho túto inštanciu di
            // Inak by neslo retazenie lebo by sa retaziálo na povodny objekt
            if ($result === $this->target) {
                return $this;
            }
            return $result;
        }

        throw new \Exception("Method $method does not exist in " . get_class($this->target));
    }

    // Volanie statických metód (napr. controller2::call())
    public function callStatic($method, $arguments) {
        if (is_object($this->target)) {
            throw new \Exception("Cannot call static method $method on an object instance.");
        }

        if (!class_exists($this->target)) {
            throw new \Exception("Class $this->target does not exist.");
        }

        if (method_exists($this->target, $method)) {
            $reflection = new \ReflectionMethod($this->target, $method);
            if (!$reflection->isStatic()) {
                throw new \Exception("Method $method in $this->target is not static.");
            }
            $parameters = $reflection->getParameters();
            $resolvedArguments = $this->resolveArguments($parameters, $arguments);
            $result = call_user_func_array([$this->target, $method], $resolvedArguments);
            // Pre statické volania nie je $this relevantné, takže vraciame výsledok priamo
            return $result;
        }

        throw new \Exception("Static method $method does not exist in $this->target");
    }

    // Univerzálna metóda na resolve argumentov
    private function resolveArguments($parameters, $arguments) {
        if ($this->dotapp === null) {
            throw new \Exception("No DotApp resolver available for dependency injection.");
        }

        $resolvedArgs = [];
        foreach ($parameters as $param) {
            if ($param->getType() && !$param->getType()->isBuiltin()) {
                $type = $param->getType()->getName();
                $resolvedArgs[] = $this->dotapp->resolve($type);
            } else {
                $resolvedArgs[] = array_shift($arguments) ?? ($param->isDefaultValueAvailable() ? $param->getDefaultValue() : null);
            }
        }
        return $resolvedArgs;
    }
}
?>
