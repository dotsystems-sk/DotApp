<?php
namespace Dotsystems\App\Parts;
use \Dotsystems\App\DotApp;
use Middleware;
 
class ModuleMiddleware {
    protected static $staticModuleName = null;
    
    public static function moduleName($name = null) {
        if (self::$staticModuleName === null) {
            // Ziskame nazov modulu z classname
            $fqmn = self::class;
            $pattern = '/^Dotsystems\\\\App\\\\Modules\\\\([^\\\\]+)\\\\.+$/';
            if (preg_match($pattern, $fqmn, $matches)) {
                self::$staticModuleName = $matches[1]; // Set extracted module name
            } else {
                throw new RuntimeException("Unable to extract module name from class: $fqmn");
            }
        }
        return self::$staticModuleName;
    }
   
    public static function middleware($name,$callback=null, ...$args) {
        return new Middleware($name,$callback, ...$args);
    }

}


?>