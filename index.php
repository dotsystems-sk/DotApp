<?php 

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');
session_set_cookie_params(8640000,"/");
date_default_timezone_set('Europe/Bratislava');
ini_set('mysql.connect_timeout', 10);
ini_set('default_socket_timeout', 10);
set_time_limit(60);
ini_set("memory_limit","64M");


define('__MAINTENANCE__',FALSE);
define('__DEBUG__',FALSE);
define('__RENDER_TO_FILE__',FALSE); // Useful for debugging

// Set ROOT DIR, i.e., the directory where the application runs...
// define('__ROOTDIR__',"/path/to/dotapp"); // - Where dotapp is located. It can be in a hidden directory, it's up to you. You can put index.php only in folder, then /app/ folder anywhere on server and make it work and run.

if (!defined('__ROOTDIR__')) {
    define('__ROOTDIR__', __DIR__);
} elseif (!is_dir(__ROOTDIR__)) {
    die("__ROOTDIR__ path is invalid !");
}
require_once __ROOTDIR__ . '/app/config.php';

if (!__MAINTENANCE__) {
    if (!defined('__CRON__') || __CRON__ == false ) {
        // This is where the magic happens :)
        $dotApp->davajhet(); // Alias for $dotApp->run();
    }
} else {
    // If we call functions from a CRON file, __CRON__ indicates it’s called from CRON, so we skip the router.
    if (!defined('__CRON__') || __CRON__ == false ) echo $dotApp->router->renderer->loadViewStatic('maintenance');
}

?>