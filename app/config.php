<?php

/**
 * DotApp Framework
 * 
 * Initial configuration file for the DotApp Framework.
 * 
 * @package   DotApp Framework
 * @category  Configuration
 * @author    Štefan Miščík <stefan@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.7 FREE
 * @date      2014 - 2025
 * @license   MIT License
 * 
 * License Notice:
 * Permission is granted to use, modify, and distribute this code under the MIT License,
 * provided this header is retained in all copies or substantial portions of the file,
 * including author and company information.
 */
 
use \Dotsystems\App\Parts\Config;
use \Dotsystems\App\Parts\SessionDriverDefault;
use \Dotsystems\App\Parts\CacheDriverFile;
use \Dotsystems\App\Parts\LoggerDriverDefault;


/*
 *  Global translation function that interacts with the translator system.
 *  It checks if the translator is callable and returns a translated string or the text as is.
 */
function translator($text="",...$args) {
    global $translator;

    if (is_callable($translator) && $translator([]) instanceof \Dotsystems\App\Parts\Translator) {
        if ($text === []) {
            return($translator([]));
        }
        if (isset($text) && ( ! is_array($text) ) ) {
            return($translator([])->translate($text,$args));
        } else return($text);
    } else return($text);
}

/*
 *  Autoload external dependencies from Composer.
 */
require_once __DIR__ . '/vendor/autoload.php';

// Basic Settings that are needed for App to run
if (!__MAINTENANCE__) {
    /* Database setup */
    // <name for your database for selectDB>, host, username, password, database_name, encoding, , type , driver
	// Config::addDatabase("main", "127.0.0.1", "dotsystems", "dotsystems", "dotsystems", "UTF8", "MYSQL", 'pdo');
}
// Default SESSION DRIVER ( using $_SESSION )
Config::session("lifetime",3600);
Config::sessionDriver("default",SessionDriverDefault::driver()); // SessionDriverFile, SessionDriverFile2, SessionDriverDB available as well. Create your own if needed.

Config::cacheDriver("default",CacheDriverFile::driver());

Config::loggerDriver("default",LoggerDriverDefault::driver());

Config::set("app","name",'NameOfYourApplication'); // Set application NAME
Config::set("app","c_enc_key",'YourSuperSecretKey'); // Set encryption key

/*
// Examples of how to set email drivers

Config::email("testAcc","smtp",[
    "host" => "server.address.com",
    "port" => 587,
    "timeout" => 30,
    "secure" => "tls",
    "username" => "no-reply@dotapp.dev",
    "password" => "AAAAAAAA",
    "from" => "no-reply@dotapp.dev"
]);

Config::email("testAcc","imap",[
    "host" => "server.address.com",
    "port" => 993,
    "timeout" => 30,
    "secure" => "ssl",
    "username" => "no-reply@dotapp.dev",
    "password" => "AAAAAAAA"
]);
*/

// Check /app/parts/Config.php for more settings.

/*
 *  Initialize the DotApp framework.
 *  Set a custom encryption key to secure your data. 
 *  IMPORTANT: Replace the value of the encryption key with your own custom value.
 */
 
// The old way of setting encryption key: new \Dotsystems\App\DotApp(md5("YourSuperSecretKey")); - still working but will be removed in future
$dotapp = new \Dotsystems\App\DotApp();
$dotApp = $dotapp; // camelCase pred vypustenim na github


/*
 *  Configure the translator by setting the locale and default language for the system.
 */
translator([])->set_locale("en_US")->set_default_locale("en_US");

if (!__MAINTENANCE__) {
	$dotApp->load_modules();
}

/*
 *  Set a custom error handler for the application.
 */
set_error_handler([$dotApp, 'errhandler']);

/*
 *  Register email and SMS sending services.
 *  The built-in email sender is "dotphpmailer", while SMS senders can be added as needed.
 *  You can register email senders using register_email_sender and SMS senders with register_sms_sender.
 */
?>