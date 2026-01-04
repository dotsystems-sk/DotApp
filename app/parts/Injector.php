<?php
/**
 * Class Injector
 *
 * This class provides a static interface for dependency injection operations
 * within the DotApp framework. It simplifies the management of dependencies
 * by offering methods to register singleton and bind resolvers, facilitating
 * efficient dependency resolution throughout the application.
 *
 * The Injector class acts as a facade around the core dependency injection
 * methods of the DotApp framework, making it easier to configure and manage
 * dependencies in a centralized manner.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.8 FREE
 * @license   MIT License
 * @date      2014 - 2026
 *
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the
 * following condition: You **must** retain this header in all copies or
 * substantial portions of the code, including the author and company information.
 */

/*
    Injector Class Usage:

    The Injector class is essential for managing dependencies within the DotApp framework.
    It provides methods for registering singleton and bind resolvers, allowing for
    flexible dependency injection configurations.

    Example:
    - Register a singleton dependency:
      `Injector::singleton(DotApp::class, function() {return $this;});`
    - Bind a dependency:
      `Injector::bind(Response::class, function() {return new Response(DotApp::dotApp()->request);});`
*/

namespace Dotsystems\App\Parts;
use \Dotsystems\App\DotApp;

class Injector {

    public static function singleton(string $key, callable $resolver) {
        return DotApp::dotAapp()->singleton($key, $resolver);
    }

    public static function bind(string $key, callable $resolver) {
        return DotApp::dotAapp()->bind($key, $resolver);
    }
}

?>
