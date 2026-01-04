<?php
namespace Dotsystems\App\Parts;
use Dotsystems\App\DotApp;
use Response;
 
class Middleware {
    use StaticGetSet;
    private $storage;
    public $name;
    private $chain = null;
    public $dotApp;
    public $dotapp;
    public $DotApp;

    public static function use($name) {
        return self::get($name);
    }
    
    public static function get($name) {
        return new self($name);
    }

    public static function define(string $name,$callback=null, ...$args) {
        return self::set($name,$callback, ...$args);
    }

    public static function register(string $name,$callback=null, ...$args) {
        return self::set($name,$callback, ...$args);
    }

    public static function set(string $name,$callback=null, ...$args) {
        return new self($name,$callback, ...$args);
    }

    function __construct($name,$callback=null, ...$args) {
        $this->dotApp = DotApp::dotApp();
        $this->dotapp = $this->dotApp;
        $this->DotApp = $this->dotApp;
        $this->dotApp->middlewareStorage($this);
        if (is_string($name)) $name = array($name);
        $this->name = json_encode($name);
        $this->dispatchConstruct($this->name,$callback, ...$args);
    }

    public function setStorage(&$storage,$setter) {
        if ($setter instanceof \Dotsystems\App\DotApp) {
			$this->storage = &$storage;
		}		
    }

    private function middlewareExists($midName) {
        $middlewares = json_decode($midName,true);
        foreach ($middlewares as $name) {
            $name = json_encode(array($name));
            if (isset($this->storage['middleware'][$name]) && is_callable($this->storage['middleware'][$name])) {
                // Uz nic.
            } else {
                return false;
            }
        }
        return true;
    }

    private function dispatchConstruct($name,$callback, ...$args) {
        // Getter
        if ($callback === null) {
            if (isset($this->storage['chains'][$name]) && $this->storage['chains'][$name] instanceof MiddlewareChain) return $this->storage['chains'][$name];
            if ($this->middlewareExists($name)) {
                $this->storage['chains'][$name] = new MiddlewareChain($this);
            } else {
                throw new \Exception("Undefined middleware");
            }
            return $this->storage['chains'][$name];            
        } else {
            if (!is_callable($callback)) $callback = $this->dotApp->stringToCallable($callback);
            if (is_callable($callback)) {
                $this->storage['middleware'][$name] = $callback;
                $this->storage['chains'][$name] = new MiddlewareChain($this);
                return $this->storage['chains'][$name];
            } else {
                throw new \Exception("Callback is not callable !");
            }
        }
    }

    public function chain() {
        return $this->storage['chains'][$this->name];
    }

    public function middleware($name=null) {
        if ($name === null) return $this->storage['middleware'][$this->name];
        if (is_string($name)) $name = array($name);
        $name = json_encode($name);
        if (isset($this->storage['middleware'][$name])) {
            return $this->storage['middleware'][$name];
        } else throw new \Exception("Undefined middleware");
    }

    public static function instanceOfMiddlewareChain($obj) {
        return ($obj instanceof MiddlewareChain);
    }

}

class MiddlewareChain {
    private $middleware;

    function __construct($middleware) {
        $this->middleware = $middleware;
    }

    
    public function callAllMiddlewares() {
        $allMiddlewares = json_decode($this->middleware->name,true);
        foreach ($allMiddlewares as $middleware) {
            $middlewareFn = $this->middleware->middleware($middleware);
            $nextFn = function($request) {
                /*
                    We're not doing anything here—we just need to return a Closure instance to keep the pipeline moving :)
                    But the user doesn't have to return a Closure themselves—they can simply call: $next($request)
                    So let's yodel a bit while we're at it... Yo-de-lay-hee-hooo!
                    Nothing to see here, just echoing through the middleware Alps...
                */
                return $request;
            };
            $navratFn = $middlewareFn($this->middleware->dotApp->request,$nextFn);
            if ($navratFn instanceof Response) return $navratFn;
        }
        return $navratFn;
    }

    // Zavolaj middleware s argumentami...
    public function call(...$args) {
        $callFn = $this->middleware->middleware();
        return call_user_func($callFn,$this->middleware->dotApp->request, ...$args);
    }

    public function get($callback) {

    }

    public function group($callback) {
        if (!is_callable($callback)) $callback = $this->middleware->dotApp->stringToCallable($callback);
        if (is_callable($callback)) {
            $navratFn = $this->callAllMiddlewares();
            if ($navratFn instanceof Response) {
                $this->middleware->dotApp->runRequest($this->middleware->dotApp->request);
                exit();
            } else {
                if ($navratFn instanceof \Closure) {
                    call_user_func($callback,$this->middleware->dotApp->request);
                    return $this;
                }
                return $this;
            }
        } else {
            throw new \Exception("Callback is not callable !");
        }        
    }

    public function when($callback) {
        if (!is_callable($callback)) $callback = $this->middleware->dotApp->stringToCallable($callback);
        if (is_callable($callback)) {
            $middlwareReturn = $this->call($this->middleware->dotApp->request);
            call_user_func($callback,$middlwareReturn);
            return $this;
        } else {
            throw new \Exception("Callback is not callable !");
        }
    }

    public function true($callback) {
        if (!is_callable($callback)) $callback = $this->middleware->dotApp->stringToCallable($callback);
        if (is_callable($callback)) {
            $middlwareReturn = $this->call($this->middleware->dotApp->request);
            if (! ($middlwareReturn === false || $middlwareReturn === true)) throw new \Exception("Middleware msut return TRUE or FALSe only!");
            if ($middlwareReturn === true) call_user_func($callback,$middlwareReturn);
            return $this;
        } else {
            throw new \Exception("Callback is not callable !");
        }
    }

    public function false($callback) {
        if (!is_callable($callback)) $callback = $this->middleware->dotApp->stringToCallable($callback);
        if (is_callable($callback)) {
            $middlwareReturn = $this->call($this->middleware->dotApp->request);
            if (! ($middlwareReturn === false || $middlwareReturn === true)) throw new \Exception("Middleware msut return TRUE or FALSe only!");
            if ($middlwareReturn === false) call_user_func($callback,$middlwareReturn);
            return $this;
        } else {
            throw new \Exception("Callback is not callable !");
        }
    }

}
?>
