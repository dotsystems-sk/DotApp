<?php
namespace Dotsystems\App\Parts;
use \Dotsystems\App\DotApp;
use Middleware;
 
class ModuleMiddleware {
   
    public static function middleware($name,$callback=null, ...$args) {
        return new Middleware($name,$callback, ...$args);
    }

}


?>