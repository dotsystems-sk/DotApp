<?php 

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');
session_set_cookie_params(8640000,"/");
date_default_timezone_set('Europe/Bratislava');
ini_set('mysql.connect_timeout', 10);
ini_set('default_socket_timeout', 10);
set_time_limit(60);
ini_set("memory_limit","64M");
session_start();

// Toto nie je vobec nutne, pouzivam to len pre debugging aby som vdel aka je spotreba zdrojov.
global $start_time;
global $memoryStart;
$memoryStart = memory_get_usage();
$start_time = microtime(true);

define('__MAINTENANCE__',FALSE);
define('__DEBUG__',FALSE);
define('__RENDER_TO_FILE__',FALSE); // Dobre ak chceme robit debugging

// Nastavime ROOT DIR teda adresar kde bezime...
define('__ROOTDIR__',"c:\wamp\dotapp"); // - Priecinok kde bezime ( kde je index.php )

require_once __ROOTDIR__ . '/app/config.php';

if (!__MAINTENANCE__) {
	if (!defined('__CRON__') || __CRON__ == false ) {
		// Tu sa deje cary mary :)
		$dotApp->davajhet(); // Alias for $dotApp->run();
	}
} else {
	// Ak budeme volat funkcie z nejakeho CRON suboru, tak __CRON__ nam urcuje ze je to volane z CRONU a vynechame teda router.
	if (!defined('__CRON__') || __CRON__ == false ) echo $dotApp->router->renderer->loadViewStatic('maintenance');
}

?>