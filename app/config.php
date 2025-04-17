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
 * @version   1.6 FREE
 * @date      2014 - 2025
 * @license   MIT License
 * 
 * License Notice:
 * Permission is granted to use, modify, and distribute this code under the MIT License,
 * provided this header is retained in all copies or substantial portions of the file,
 * including author and company information.
 */

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

/*
 *  Initialize the DotApp framework.
 *  Set a custom encryption key to secure your data. 
 *  IMPORTANT: Replace the value of the encryption key with your own custom value.
 */
$dotapp = new \Dotsystems\App\DotApp(md5("YourSuperSecretKey")); // SET THIS KEY TO YOUR OWN VALUE !!!
$dotApp = $dotapp; // camelCase pred vypustenim na github


/*
 *  Configure the translator by setting the locale and default language for the system.
 */
translator([])->set_locale("en_US")->set_default_locale("en_US");

/*
 *  Set the driver and login credentials for the database connection.
 *  Database operations will be performed using the "main" database.
 */
if (!__MAINTENANCE__) {
    /* Database setup - uncomment and set */
	// Select driver. For using MODULES like system and users, use PDO driver.
	// Shared modules expect PDO as the standard driver for compatibility within the framework and community.
	
		//$dotApp->db->driver("pdo");

	// Define database credentials and select the main database.
	// The main database is named 'main' as a convention for shared modules to ensure interoperability.
	
		//$dotApp->db->add("main", "127.0.0.1", "dotApp", "dotApp", "dotApp", "UTF8", "MYSQL")->select_db("main");

	// Example with a different driver for non-shared modules.
	// You can use this approach if you are building modules purely for yourself.
	
		//$dotApp->db->driver("mysqli");
		//$dotApp->db->add("main", "127.0.0.1", "dotsystems", "dotsystems", "dotsystems", "UTF8", "MYSQL")->select_db("main");

	// Note: If you are creating modules solely for your own use and you know what you’ll be doing with them,
	// and you don’t plan to share them with the community, you have the freedom to set it up however you like.
	// You can use the mysqli driver or name your database something other than 'main'.
	// However, for the framework and community standards, using PDO and naming the database 'main'
	// is a MUST to ensure all modules are interoperable, shareable, and usable by everyone.
	// Following this convention allows seamless collaboration and module compatibility across the community.

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

/*
 *  Default built-in PHP mailer service registration.
 */
require_once __ROOTDIR__ . "/app/custom.classes/dotphpmailer.class.php";
$dotApp->register_email_sender("dotphpmailer",new dotphpmailer($dotApp));

?>