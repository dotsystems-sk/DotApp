<?php
namespace Dotsystems\App\Parts;

use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\DI;

/*
    Trieda na obalovanie komponentov pre jednoduchsiu syntax
*/
class Facade {
    protected static $component;
    protected static $allowedMethods = []; // Povolene metody pre fasadu, ['*'] znamena povolit vsetky metody
    protected static $methodAliases = []; // Mapovanie aliasov na skutocne nazvy metod (napr. routerApp -> router_app)

    public static function __callStatic($method, $args) {
        $component = DotApp::dotApp()->{static::$component};

        // Skontrolujeme ci je metoda alias
        $actualMethod = isset(static::$methodAliases[$method]) ? static::$methodAliases[$method] : $method;

        // Ak je $allowedMethods ['*'], povolime vsetky existujuce metody
        if (static::$allowedMethods !== ['*']) {
            // Je metoda (alebo jej alias) povolena?
            if (!in_array($actualMethod, static::$allowedMethods)) {
                throw new \BadMethodCallException("Method '{$method}' (resolved as '{$actualMethod}') not allowed in " . static::class);
            }
        }

        // Existuje metoda v komponente?
        if (!method_exists($component, $actualMethod)) {
            throw new \BadMethodCallException("Method '{$actualMethod}' does not exist in component " . get_class($component));
        }

        // Zavolame metodu s argumentmi
        return $component->$actualMethod(...$args);
    }
}
