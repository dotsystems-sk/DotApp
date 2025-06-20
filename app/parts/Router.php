<?php
namespace Dotsystems\App\Parts;

use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\DI;
use \Dotsystems\App\Parts\Facade;
use \Dotsystems\App\Parts\Bridge;

/*
    Len kvoli obalovaniu aby sme mohli trosku skraslit syntax niektorych tried
*/

class Router extends Facade {
    const STATIC_ROUTE = true;
    const DYNAMIC_ROUTE = false;

    protected static $component = 'router'; 
    protected static $allowedMethods = [
        'any',
        'get',
        'post',
        'put',
        'delete',
        'patch',
        'options',
        'head',
        'trace',
        'match',
		'matched',
        'apiPoint',
        'actual_path',
        'errorHandle',
        'reset',
        'hasRoute',
        'match_url',
        'before',
        'after'
    ];

    protected static $methodAliases = [
        'matchUrl' => 'match_url',
        'actualPath' => 'actual_path'
    ];

    public static function middleware(string $name, $callback=null, ...$args) {
        return DotApp::dotApp()->middleware($name,$callback, ...$args);
    }
	
	public static function bridge(...$args) {
		Bridge::listen(...$args);
	}
}

?>