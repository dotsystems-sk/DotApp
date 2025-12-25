<?php
namespace Dotsystems\App\Parts;

use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\DI;
use \Dotsystems\App\Parts\Facade;
use \Dotsystems\App\Parts\RequestObj;

/*
    Len kvoli obalovaniu aby sme mohli trosku skraslit syntax niektorych tried
*/

class Request extends Facade {
    protected static $component = 'request'; 
    protected static $allowedMethods = [
        'loadBalancing',
        'isSecure',
        'getHost',
        'getPort',
        'getFullUrl',
        'getPath',
        'getMethod',
        'getVars',
        'data',
        'upload',
        'matchData',
        'hookData',
        'body',
        'route',
        'requireAuth',
        'isValidCSRF',
        'invalidateCSRF',
        'crcCheck',
        'formSignatureCheck',
        'form',
        'lock',
        'firewall'
    ];

    public static function middleware(string $name, $callback=null, ...$args) {
        return DotApp::dotApp()->middleware($name,$callback, ...$args);
    }
}

?>