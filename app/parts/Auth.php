<?php
namespace Dotsystems\App\Parts;

use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\DI;
use \Dotsystems\App\Parts\Facade;
use \Dotsystems\App\Parts\RequestObj;

/**
 * Facade for AuthObj to simplify access to authentication-related methods.
 */
class Auth extends Facade {

    protected static $component = 'auth';
    protected static $allowedMethods = [
        'tfaTotp',
        'tfaSms',
        'tfaEmail',
        'createUser',
        'confirmTwoFactor',
        'logged',
        'isLogged',
        'loggedStage',
        'userId',
        'username',
        'roles',
        'token',
        'login',
        'autoLogin',
        'logout',
        'hasRole',
        'can',
        'refreshToken',
        'updateActivity',
        'getAuthData',
        'setAttribute',
        'getAttribute',
        'lock',
        'isLocked',
		'attributes'
    ];

}
?>
