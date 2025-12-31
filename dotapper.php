#!/usr/bin/env php
<?php
namespace Dotsystems\DotApper;
use Dotsystems\App\Parts\Config;
use Dotsystems\App\Parts\Tester;
use Dotsystems\App\Parts\Installer;
use Dotsystems\App\Parts\HttpHelper;

define('__DOTAPPER_RUN__',1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'Access denied !';
    exit(1);
}

class DotApper {
    private array $args;
    private array $options;
    private $modul="";
    private $basePath = "./app/modules";

    /**
     * Konštruktor inicializuje argumenty z príkazového riadka.
     *
     * @param array $args Argumenty z $argv (bez názvu skriptu)
     */
    public function __construct(array $args) {
        $this->args = $args;
        $this->options = []; // Inicializácia $options v konštruktore
        $this->parseArguments();
    }

    /**
     * Spustí hlavnú logiku skriptu.
     */
    public function run() {
        // Ak nie sú žiadne argumenty, vypíš help
        if (empty($this->args)) {
            $this->printHelp();
            exit(1);
        }

        // Spracuj rozpoznané možnosti, ak su to argumenty zlava doprava
        foreach ($this->options as $key => $value) {
            switch ($key) {
                case 'install':
                    if ($this->modul == "") {
                        $this->installDotapp();
                    } else {
                        $this->runModuleInstaller($value);
                    }
                    break;
                case 'update':
                    $this->updateDotapp();
                    break;
                case 'create-module':
                    $this->createModule($value);
                    break;
                case 'create-modules':
                    // Only for testing purposes, replace init.php source file for init2.php and create 2000 modules
                    //$this->createModules();
                    break;
                case 'create-example-module':
                    $this->createExampleModule($value);
                    break;
                case 'list-routes':
                    $this->printRoutes();
                    break;
                case 'list-route':
                    $this->printRoutes($value);
                    break;
                case 'list-modules':
                    $this->printModules($this->listModules());
                    break;
                case 'create-htaccess':
                    $this->htaccess();
                    break;
                case 'modules':
                    $this->printModules($this->listModules());
                    break;
                case 'test-modules':
                    $this->runTests(2);
                    break;
                case 'module':
                    $moduly = $this->listModules();
                    if (is_numeric($value)) {
                        if (isSet($moduly[intval($value)-1])) {
                            $this->modul = $moduly[intval($value)-1];
                        } else {
                            echo "Unknown module number: $value\n";
                            exit();
                        }
                    } else {
                        if (in_array($value,$moduly)) {
                            $this->modul = $value;
                        } else {
                            echo "Unknown module: $value\n";
                            exit();
                        }
                    }
                    break;
                case 'create-controller':
                    if ($this->modul == "") {
                        echo "Select module first.\n\nUse:\n  php dotapper.php --modules\n  to list modules.\n\nThen use\n\nphp dotapper.php --module=<name or number> --create-controller=NameOfController\n\nTo create new controller";
                    } else {
                        if ($value != "") $this->createController($value); else echo "Specify controller name ! --create-controller=NAME\n\n";
                    }
                    break;
                case 'test':
                    $this->runTests(1);
                    break;
                case 'optimize-modules':
                    $this->optimizeModules();
                    break;
                case 'create-middleware':
                    if ($this->modul == "") {
                        echo "Select module first.\n\nUse:\n  php dotapper.php --modules\n  to list modules.\n\nThen use\n\nphp dotapper.php --module=<name or number> --create-middleware=NameOfController\n\nTo create new middleware";
                    } else {
                         if ($value != "") $this->createMiddleware($value); else echo "Specify controller name ! --create-controller=NAME\n\n";
                    }
                    break;
                case 'create-model':
                    if ($this->modul == "") {
                        echo "Select module first.\n\nUse:\n  php dotapper.php --modules\n  to list modules.\n\nThen use\n\nphp dotapper.php --module=<name or number> --create-middleware=NameOfController\n\nTo create new middleware";
                    } else {
                            if ($value != "") $this->createModel($value); else echo "Specify controller name ! --create-controller=NAME\n\n";
                    }
                    break;
                case 'prepare-database':
                    if ($value != "") {
                        $this->prepareDatabase($value);
                    } else {
                        $this->prepareDatabase(null);
                    }
                    break;
                case 'install-module':
                    $this->installModule($value);
                    break;
                default:
                    echo "Unknown option: --$key\n";
                    exit(1);
            }
        }

        foreach (array_reverse($this->options) as $key => $value) { 
            switch ($key) {
                case 'install777':
                    $this->installDotapp();
                    break;
                default:
                    //echo "Unknown option: --$key\n";
                    exit(1);
            }
        }
    }
    private function runTests($type) {
        // $type = 2 - vsetky moduly. type-1 bud to core alebo ak je zadany modul tak konkretny modul
        $_SERVER['REQUEST_URI'] = '/'.md5(random_bytes(10))."/".md5(random_bytes(10))."/".md5(random_bytes(10));
        $_SERVER['SERVER_NAME'] = 'localhost'; 
        $_SERVER['REQUEST_METHOD'] = 'dotapper'; 
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        include(__DIR__."/index.php");
        if ($type == 1) {
            if ($this->modul == "") {
                Tester::loadTests(true,false);
                $testResult = Tester::run();
                $this->testResults($testResult);
            } else {
                $moduldir = __ROOTDIR__."/app/modules/".$this->modul;
                $testdir = $moduldir."/tests";
                if (is_dir($moduldir)) {
                    if (is_dir($testdir)) {
                        Tester::loadTests(false,$this->modul);
                        $testResult = Tester::run();
                        $this->testResults($testResult);
                    } else {
                        echo $this->colorText("red", "No tests found in '$testdir'.\n");
                    }
                } else {
                    echo $this->colorText("red", "Module '$this->modul' not found.\n");
                }
            }
        }
        if ($type == 2) {
            Tester::loadTests(false,true);
            $testResult = Tester::run();
            $this->testResults($testResult);
        }
        
    }

    private function testResults($tests) {
        echo $this->colorText("white", "\nRunning tests\n");
        echo "----------------------------------------\n";
        foreach ($tests['results'] as $test) {
            echo "Name: ".$test['test_name']."\n";
            echo "Info: ".$test['info']."\n";
            echo "Duration: ".number_format($test['duration'], 6)."s\n";
            echo "Memory Delta: ".number_format($test['memory_delta']/1024, 2)." KB\n";
            echo "Context: ".json_encode($test['context'])."\n";
            if ($test['status'] == 1) {
                echo $this->colorText("green", "Status: OK\n");
            } else if ($test['status'] == 2) {
                echo $this->colorText("orange", "Status: SKIPPED\n");
            } else {
                echo $this->colorText("red", "Status: FAIL\n");
            }
            echo "----------------------------------------\n";
        }
        echo "\n************** RESULT **************\n";
        echo $this->colorText("cyan", "Summary: ".$tests['summary']['passed']."/".$tests['summary']['total']." tests passed (".$tests['summary']['skipped']." skipped, ".$tests['summary']['failed']." failed)\n");
        echo "\n";
    }


    private function prepareDatabase($prefix=null) {
        $_SERVER['REQUEST_URI'] = '/'.md5(random_bytes(10))."/".md5(random_bytes(10))."/".md5(random_bytes(10));
        $_SERVER['SERVER_NAME'] = 'localhost'; 
        $_SERVER['REQUEST_METHOD'] = 'dotapper'; 
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        include(__DIR__."/index.php");
        if ($prefix === null) $prefix = Config::db('prefix');
        $prepare = $this->confirmAction("Prepare database with prefix '$prefix' into ".__DIR__."/".$prefix."sql.sql ?");
        if ($prepare) {
            $file_body = base64_decode($this->file_base("/sql.sql"));
            $file_body = str_replace("DEFAULTDatabasePrefix_",$prefix,$file_body);
            $this->createFile(__DIR__."/".$prefix."sql.sql",base64_encode($file_body));
            echo $this->colorText("green", "Database with prefix '$prefix' prepared successfully.\n");
        } else echo $this->colorText("red", "Canceled by the user.\n");
    }

    private function installDotapp() {
        // function downloadAndUnzip($urlOfFile, $whereToExtract, $overwrite = false, $filesToCopy = null, $filesToSkip = null, $sourceDir = null, $deleteZip = true)
        // Easy peazy checkujeme ci existuje dotapp lacnym sposoboom ale lepsi ako nic
        if (!file_exists(__DIR__."/app/DotApp.php")) {
            $install = $this->confirmAction("Do you want to install the DotApp PHP Framework into \"".__DIR__."\"?");
            if ($install === true) {
                $installation = $this->downloadAndUnzip("https://github.com/dotsystems-sk/DotApp/archive/refs/heads/main.zip", __DIR__, false, null, [__DIR__.'/dotapper.php'], "DotApp-main", true);
                if ($installation === true) {
                    echo $this->colorText("green", "Installation successful.");
                } else {
                    echo $this->colorText("red", "Installation failed.");
                }
            } else {
                echo $this->colorText("red", "Installation canceled by the user.");
            }
        } else {
            echo $this->colorText("red", "Detected DotApp. Run the update command to update or install in a new folder.");
        }        
    }

    private function updateDotapp() {
        // Easy peazy checkujeme ci existuje dotapp lacnym sposoboom ale lepsi ako nic
        if (file_exists(__DIR__."/app/DotApp.php")) {
            $install = $this->confirmAction("Do you want to UPDATE the DotApp PHP Framework?");
            if ($install === true) {
                $installation = $this->downloadAndUnzip("https://github.com/dotsystems-sk/DotApp/archive/refs/heads/main.zip", __DIR__, true, null, [__DIR__.'/index.php',__DIR__.'/app/config.php',__DIR__.'/app/listeners.php'], "DotApp-main", true);
                if ($installation === true) {
                    echo $this->colorText("green", "UPDATE successful.");
                } else {
                    echo $this->colorText("red", "UPDATE failed.");
                }
            } else {
                echo $this->colorText("red", "UPDATE canceled by the user.");
            }
        } else {
            echo $this->colorText("red", "DotApp not detected. Run the install command to install it.");
        }        
    }

    public function confirmAction(string $message): bool {
        echo "$message [Y/n]: ";
        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);
        
        return in_array(strtolower($input), ['y', 'yes', '']);
    }

    private function printRoutes($route = null) {
        $_SERVER['REQUEST_URI'] = '/'.md5(random_bytes(10))."/".md5(random_bytes(10))."/".md5(random_bytes(10)); 
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['REQUEST_METHOD'] = 'dotapper';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        include("./index.php");
        if ($route === null) {
            $this->clrScr();
            $vystup = $this->colorText("green","\n\n Global MIDDLEWARE ( before, after ) \n");
            $vystup = $this->bgColorText("white",$vystup);
            echo $vystup."\n";
            print_r($dotApp->dotapper['GlobalHooks']);

            $vystup = $this->colorText("green","\n\n ALL ROUTES: \n");
            $vystup = $this->bgColorText("white",$vystup);
            echo $vystup."\n";
            print_r($dotApp->dotapper['RouteByURL']);
        }
        if ($route !== null) {
            $this->clrScr();
            $vystup = $this->colorText("green","\n\n Global MIDDLEWARE ( before, after ) \n");
            $vystup = $this->bgColorText("white",$vystup);
            echo $vystup."\n";
            $vztahujuSa = array();
            foreach ($dotApp->dotapper['GlobalHooks'] as $key => $hook) {
                if ($dotApp->router->match_url($key,$route)) {
                    if (isset($vztahujuSa[$key])) $vztahujuSa[$key] = $dotApp->router->doatpperMergeArrays($vztahujuSa[$key],$hook);
                    if (!isset($vztahujuSa[$key])) $vztahujuSa[$key] = $hook;
                }
            }
            print_r($vztahujuSa);
            if (isset($dotApp->dotapper['RouteByURL'][$route])) {
                $vystup = $this->colorText("green","\n\n ROUTE: \"".$route."\"\n");
                $vystup = $this->bgColorText("white",$vystup);
                echo $vystup."\n";
                print_r($dotApp->dotapper['RouteByURL'][$route]);
            } else {
                $vystup = $this->colorText("white"," ROUTE \"");
                $vystupRouta = $this->colorText("red",$route);
                $vystupRouta = $this->bgColorText("white",$vystupRouta);
                $vystup .= $vystupRouta;
                $vystup .= $this->colorText("white","\" NOT FOUND !!! ");
                $vystup = $this->bgColorText("red",$vystup);
                echo $vystup;
            }
            
        }
        /*echo "\n\nAll routes:\n";
        print_r($dotApp->dotapper['routes']);*/
    }

    private function createModules() {
        $i2 = 0;
        for ($i=0; $i < 2000; $i++) {
            $i2++;
            $this->createModule("Modul".$i,$i2);
            if ($i2 == 80) $i2 = 0;
        }
    }

    private function optimizeModules() {
		define('__DOTAPPER_OPTIMIZER__',1);
        // Simulácia $_SERVER premenných
        $_SERVER['REQUEST_URI'] = '/'.md5(random_bytes(10))."/".md5(random_bytes(10))."/".md5(random_bytes(10)); 
        $_SERVER['SERVER_NAME'] = 'localhost'; 
        $_SERVER['REQUEST_METHOD'] = 'dotapper'; 
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        include("./index.php");
		$vysledok = \Dotsystems\App\Parts\Module::optimize();
        if ($vysledok == true) {
			echo "Optimized loader ".__ROOTDIR__ . "/app/modules/modulesAutoLoader.php sucesfully created !";
		} else {
			echo "Creating optimized loader failed !";
		}
		
    }

    private function htaccess() {
        try {
            // Load the application context to access constants like __ROOTDIR__
            @include("./index.php");
            
            // Retrieve the base .htaccess template from the Base64 storage
            $file_body = base64_decode($this->file_base("/.htaccess"));

            // Check if the application is installed in a subdirectory
            if ( !(__ROOTDIR__ === __DIR__) ) {
                // Calculate the URL prefix (e.g., /project-folder)
                $calculateURL = str_replace(__DIR__, "", __ROOTDIR__);
                $calculateURL = rtrim($calculateURL, '/');

                // 1. Fix routing for static JS files (reactive and template modules)
                // Converts /app/parts/js/ to /subdirectory/app/parts/js/
                $file_body = str_replace("/app/parts/js/", $calculateURL . "/app/parts/js/", $file_body);

                // 2. Fix routing for module-specific assets
                // Converts /app/modules/ to /subdirectory/app/modules/
                $file_body = str_replace("/app/modules/", $calculateURL . "/app/modules/", $file_body);

                // 3. Update security conditions (RewriteCond) for directory access
                // Ensures the private /app/ folder remains protected while allowing the calculated JS path
                $file_body = str_replace("!^/app/", "!^" . $calculateURL . "/app/", $file_body);
            }            

            // Write the processed .htaccess file to the root directory
            if ($this->createFile(__ROOTDIR__ . "/.htaccess", base64_encode($file_body))) {
                echo $this->colorText("green", ".htaccess successfully created/updated in " . __ROOTDIR__ . "\n");
            }
            return true;
        } catch (\Exception $e) {
            echo $this->colorText("red", "Error creating .htaccess: {$e->getMessage()}\n");
            return false;
        }
    }

    /**
     * Parses command-line arguments and stores them in $options.
     *
     * @return void
     */
    private function parseArguments() {
        foreach ($this->args as $arg) {
            if ($arg === '--help' || $arg === '?') {
                $this->printHelp();
                exit(0);
            }

            if (preg_match('/^--([\w-]+)(?:=(.+))?$/', $arg, $matches)) {
                $key = $matches[1];
                $value = isset($matches[2]) ? $matches[2] : '';

                // Handle install-module with optional version
                if ($key === 'install-module') {
                    // Match value and optional version, handling URLs with colons
                    if (preg_match('/^(.+?)(?::([a-zA-Z0-9._-]+))?$/', $value, $moduleMatches)) {
                        $parsedValue = $moduleMatches[1];
                        // Check if the value is a URL and adjust if it includes the version part
                        if (preg_match('#https?://#', $parsedValue) && isset($moduleMatches[2])) {
                            // If it's a URL and a version was matched, reconstruct the value
                            if (preg_match('#https?://.*?:([a-zA-Z0-9._-]+)$#', $value, $urlVersionMatches)) {
                                $this->options[$key] = [
                                    'value' => substr($value, 0, -strlen($urlVersionMatches[1]) - 1),
                                    'version' => $urlVersionMatches[1]
                                ];
                            } else {
                                $this->options[$key] = [
                                    'value' => $value,
                                    'version' => null
                                ];
                            }
                        } else {
                            $this->options[$key] = [
                                'value' => $parsedValue,
                                'version' => isset($moduleMatches[2]) ? $moduleMatches[2] : null
                            ];
                        }
                    } else {
                        echo $this->colorText("red", "Invalid format for --install-module. Use: --install-module=<git_url|module_name>[:version]\n");
                        exit(1);
                    }
                } else {
                    $this->options[$key] = $value;
                }
            } else {
                echo $this->colorText("red", "Invalid argument format: $arg\n");
                echo $this->colorText("red", "Use: --key=value, --key, --help, or ?\n");
                exit(1);
            }
        }
    }

    private function installModule($value) {
        $_SERVER['REQUEST_URI'] = '/' . md5(random_bytes(10)) . "/" . md5(random_bytes(10)) . "/" . md5(random_bytes(10));
        $_SERVER['SERVER_NAME'] = 'localhost';
        $_SERVER['REQUEST_METHOD'] = 'dotapper';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        if (!@include(__DIR__ . "/index.php")) {
            echo $this->colorText("red", "Error: Failed to load index.php.\n");
            exit(1);
        }
        $options = [
            'force' => isset($this->options['force']),
            'github_token' => $this->options['github-token'] ?? null
        ];
        $result = Installer::module('temp')->installModule($value['value'], $value['version'], $options, $this);
        if (!$result['success']) {
            echo $this->colorText("red", "Installation failed: {$result['error_message']}\n");
            exit(1);
        } else {
            echo $this->colorText("green", "Module '{$result['module_name']}' successfully installed.\n");
        }
    }

    private function listModules() {
        $modulesPath = './app/modules';
        
        // Skontroluj, či priečinok existuje
        if (!is_dir($modulesPath)) {
            echo "Modules directory does not exist: $modulesPath\n";
            exit(1);
        }

        // Načítaj zoznam podpriečinkov
        $modules = array_filter(
            scandir($modulesPath),
            function ($item) use ($modulesPath) {
                // Preskoč . a .. a skontroluj, či je to priečinok
                return $item !== '.' && $item !== '..' && is_dir($modulesPath . '/' . $item);
            }
        );

        // Ak nie sú žiadne moduly
        if (empty($modules)) {
            echo "No modules found in: $modulesPath\n";
            return;
        }
        
        $modules = array_values($modules);

        return $modules;
    }

    private function printModules($modules) {
        // Vypíš zoznam modulov
        echo "Available modules:\n";
        $i=1;
        foreach ($modules as $module) {
            echo $i.". - $module\n";
            $i++;
        }        
    }

    private function createController(string $controllerName) {
        $file_body = base64_decode($this->file_base("/Controllers/Controller.php"));
        $file_body = str_replace("class Controller extends","class ".$controllerName." extends",$file_body);
        $file_body = str_replace("#modulenamelower",strtolower($this->modul),$file_body);
        $file_body = str_replace("#modulename",$this->modul,$file_body);
        if (file_exists($this->basePath."/".$this->modul."/Controllers/".$controllerName.".php")) {
            echo "Controller '".$controllerName."' already exist !\n";
        } else {
            $this->createFile($this->basePath."/".$this->modul."/Controllers/".$controllerName.".php",base64_encode($file_body));
            echo "Controller '".$controllerName."' sucesfully created !\n";
        }
        
    }

    private function createMiddleware(string $middlewareName) {
        $file_body = base64_decode($this->file_base("/Middleware/Middleware.php"));
        $file_body = str_replace("class Middleware extends","class ".$middlewareName." extends",$file_body);
        $file_body = str_replace("Middleware::register();",$middlewareName."::register();",$file_body);
        $file_body = str_replace("#modulenamelower",strtolower($this->modul),$file_body);
        $file_body = str_replace("#modulename",$this->modul,$file_body);
        if (file_exists($this->basePath."/".$this->modul."/Middleware/".$middlewareName.".php")) {
            echo "Middleware '".$middlewareName."' already exist !\n";
        } else {
            $this->createFile($this->basePath."/".$this->modul."/Middleware/".$middlewareName.".php",base64_encode($file_body));
            echo "Middleware '".$middlewareName."' sucesfully created !\n";
        }        
    }

    private function createModel(string $modelName) {
        $file_body = base64_decode($this->file_base("/Models/Model.php"));
        $file_body = str_replace("class Model extends","class ".$modelName." extends",$file_body);
        $file_body = str_replace("#modulenamelower",strtolower($this->modul),$file_body);
        $file_body = str_replace("#modulename",$this->modul,$file_body);
        if (file_exists($this->basePath."/".$this->modul."/Models/".$modelName.".php")) {
            echo "Model '".$modelName."' already exist !\n";
        } else {
            $this->createFile($this->basePath."/".$this->modul."/Models/".$modelName.".php",base64_encode($file_body));
            echo "Model '".$modelName."' sucesfully created !\n";
        }
    }

    private function runModuleInstaller(string $moduleName) {
        $moduleName = ucfirst($moduleName);
        $basePath = $this->basePath;
        $modulePath = "$basePath/$moduleName";

        // 1. Skontroluj, či existuje cesta ./app/modules/modulename
        if (!is_dir($basePath)) {
            echo "Module $moduleName not found !\n";
            exit(1);
        }

        

    }

    /**
     * Vytvorí nový modul s daným názvom.
     *
     * @param string $moduleName Názov modulu
     */
    private function createModule(string $moduleName, $i="") {
        $moduleName = ucfirst($moduleName);
        $basePath = $this->basePath;
        $modulePath = "$basePath/$moduleName";

        // 1. Skontroluj, či existuje cesta ./app/modules
        if (!is_dir($basePath)) {
            // Rekurzívne vytvor ./app/modules
            $this->createDir($basePath);
            if (!is_dir($basePath)) {
                echo "Failed to create directory: $basePath\n";
                exit(1);
            }
        }

        // 2. Skontroluj, či už modul neexistuje
        if (is_dir($modulePath)) {
            echo "Module already exists: $modulePath\n";
            exit(1);
        }

        // 3. Vytvor priečinok pre modul
        $this->createDir($modulePath);
        if (!is_dir($modulePath)) {
            echo "Failed to create module directory: $modulePath\n";
            exit(1);
        }

        // 4. Skontroluj práva na zápis
        if (!is_writable($modulePath)) {
            echo "Module directory is not writable: $modulePath\n";
            exit(1);
        }

        $this->createDir($modulePath."/assets");
        $this->createDir($modulePath."/Api");
        $this->createDir($modulePath."/Controllers");
        $this->createDir($modulePath."/Libraries");
        $this->createDir($modulePath."/Models");
        $this->createDir($modulePath."/Middleware");
        $this->createDir($modulePath."/translations");
        $this->createDir($modulePath."/views");
        $this->createDir($modulePath."/views/layouts");
        $this->createDir($modulePath."/tests");

        $file_body = base64_decode($this->file_base("/module.init.php"));
        $file_body = str_replace("#modulenumber",strtolower($i),$file_body);
        $file_body = str_replace("#modulenamelower",strtolower($moduleName),$file_body);
        $file_body = str_replace("#modulename",$moduleName,$file_body);        
        $this->createFile($modulePath."/module.init.php",base64_encode($file_body));

        $file_body = base64_decode($this->file_base("/module.listeners.php"));
        $file_body = str_replace("#modulenamelower",strtolower($moduleName),$file_body);
        $file_body = str_replace("#modulename",$moduleName,$file_body);
        $this->createFile($modulePath."/module.listeners.php",base64_encode($file_body));

        $file_body = base64_decode($this->file_base("/assets/guide.md"));
        $file_body = str_replace("#modulenamelower",strtolower($moduleName),$file_body);
        $file_body = str_replace("#modulename",$moduleName,$file_body);
        $this->createFile($modulePath."/assets/ASSETS_AI_guide.md",base64_encode($file_body));

        $file_body = base64_decode($this->file_base("/Api/Api.php"));
        $file_body = str_replace("#modulenamelower",strtolower($moduleName),$file_body);
        $file_body = str_replace("#modulename",$moduleName,$file_body);
        $this->createFile($modulePath."/Api/Api.php",base64_encode($file_body));

        $file_body = base64_decode($this->file_base("/Controllers/Controller.php"));
        $file_body = str_replace("#modulenamelower",strtolower($moduleName),$file_body);
        $file_body = str_replace("#modulename",$moduleName,$file_body);
        $this->createFile($modulePath."/Controllers/Controller.php",base64_encode($file_body));

        $this->createFile($modulePath."/Controllers/CONTROLLERS_AI_guide.md",$this->file_base("/Controllers/guide.md"));
        
        $this->createFile($modulePath."/views/clean.view.php",$this->file_base("/views/clean.view.php"));
        $this->createFile($modulePath."/views/VIEWS_AI_guide.md",$this->file_base("/views/guide.md"));

        $file_body = base64_decode($this->file_base("/views/layouts/example.layout.php"));
        $file_body = str_replace("#modulename",$moduleName,$file_body);
        $this->createFile($modulePath."/views/layouts/example.layout.php",base64_encode($file_body));

        $file_body = base64_decode($this->file_base("/tests/guide.md"));
        $this->createFile($modulePath."/tests/TESTS_AI_guide.md",base64_encode($file_body));

        // Navod pre AI na preklady...
        $this->createFile($modulePath."/translations/TRANSLATION_AI_guide.md",$this->file_base("/translations/guide.md"));

        // Navod AI pre samotny modul
        $this->createFile($modulePath."/MODULE_AI_guide.md",$this->file_base("/guide.md"));

       
        echo "Module sucesfully created in: $modulePath\n";
    }

    private function file_base($filename) {
        if ($filename=="/module.init.php") return "PD9waHAKCW5hbWVzcGFjZSBEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXCNtb2R1bGVuYW1lOwogICAgdXNlIFxEb3RzeXN0ZW1zXEFwcFxEb3RBcHA7Cgl1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXFJvdXRlcjsKCXVzZSBcRG90c3lzdGVtc1xBcHBcUGFydHNcTWlkZGxld2FyZTsKCXVzZSBcRG90c3lzdGVtc1xBcHBcUGFydHNcUmVxdWVzdDsKCXVzZSBcRG90c3lzdGVtc1xBcHBcUGFydHNcUmVzcG9uc2U7Cgl1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXElucHV0OwoJdXNlIFxEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xEQjsKCXVzZSBcRG90c3lzdGVtc1xBcHBcUGFydHNcUmVuZGVyZXI7CiAgICAKCQoKCWNsYXNzIE1vZHVsZSBleHRlbmRzIFxEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xNb2R1bGUgewoJCQoJCXB1YmxpYyBmdW5jdGlvbiBpbml0aWFsaXplKCRkb3RBcHApIHsKCQkJLyoKCQkJCURlZmluZSB5b3VyIHJvdXRlcywgQVBJIHBvaW50cywgYW5kIHNpbWlsYXIgY29uZmlndXJhdGlvbnMgaGVyZS4gRXhhbXBsZXM6CgoJCQkJJGRvdEFwcC0+cm91dGVyLT5hcGlQb2ludCgiMSIsICIjbW9kdWxlbmFtZWxvd2VyIiwgIkFwaVxBcGlAYXBpRGlzcGF0Y2giKTsgLy8gQXV0b21hdGljIGRpc3BhdGNoZXIsIHNlZSBkZXRhaWxzIGluIEFwaS9BcGkucGhwCgoJCQkJRXhhbXBsZSB3aXRob3V0IGF1dG9tYXRpYyBkaXNwYXRjaGluZzoKCQkJCSRkb3RBcHAtPnJvdXRlci0+YXBpUG9pbnQoIjEiLCAiI21vZHVsZW5hbWVsb3dlci91c2VycyIsICJBcGlcVXNlcnNAYXBpIik7CgkJCQlUaGlzIGNhbGxzIHRoZSBgYXBpYCBtZXRob2QgaW4gdGhlIGBVc2Vyc2AgY2xhc3MgbG9jYXRlZCBpbiB0aGUgYEFwaWAgZm9sZGVyLgoJCQkJCgkJCQlFeGFtcGxlIG9mIGNhbGxpbmcgY29udHJvbGxlcnM6CgkJCQkkZG90QXBwLT5yb3V0ZXItPmdldCgiLyNtb2R1bGVuYW1lbG93ZXIvaG9tZSIsICJDb250cm9sbGVyQGhvbWUiKTsKCgkJCQlFeGFtcGxlIHVzaW5nIHRoZSBicmlkZ2U6CgkJCQkkZG90QXBwLT5icmlkZ2UtPmZuKCJuZXdzbGV0dGVyIiwgIkNvbnRyb2xsZXJAbmV3c2xldHRlciIpOwoJCQkqLwoJCQkKICAgICAgICAgICAgLy8gQWRkIHlvdXIgcm91dGVzIGFuZCBsb2dpYyBoZXJlCgkJCQoJCX0KCQkKCQkvKgoJCQlUaGlzIGZ1bmN0aW9uIGRlZmluZXMgdGhlIHNwZWNpZmljIFVSTCByb3V0ZXMgd2hlcmUgdGhlIG1vZHVsZSBzaG91bGQgYmUgaW5pdGlhbGl6ZWQuCgoJCQnwn5SnIEhvdyBpdCB3b3JrczoKCQkJLSBSZXR1cm4gYW4gYXJyYXkgb2Ygcm91dGVzIChlLmcuLCBbIi9ibG9nLyoiLCAiL25ld3Mve2lkOml9Il0pIHRvIHNwZWNpZnkgd2hlcmUgdGhlIG1vZHVsZSBzaG91bGQgaW5pdGlhbGl6ZS4KCQkJLSBSZXR1cm4gWycqJ10gdG8gYWx3YXlzIGluaXRpYWxpemUgdGhlIG1vZHVsZSBvbiBldmVyeSByb3V0ZSAobm90IHJlY29tbWVuZGVkIGluIGxhcmdlIGFwcHMpLgoKCQkJVGhpcyByb3V0aW5nIGxvZ2ljIGlzIHVzZWQgaW50ZXJuYWxseSBieSB0aGUgRG90YXBwZXIgYXV0by1pbml0aWFsaXphdGlvbiBzeXN0ZW0KCQkJdGhyb3VnaCB0aGUgYGF1dG9Jbml0aWFsaXplQ29uZGl0aW9uKClgIG1ldGhvZC4KCgkJCeKchSBSZWNvbW1lbmRlZDogSWYgeW91IGFyZSB1c2luZyBhIGxhcmdlIG51bWJlciBvZiBtb2R1bGVzIGFuZCB3YW50IHRvIG9wdGltaXplIHBlcmZvcm1hbmNlLCAKCQkJYWx3YXlzIHJldHVybiBhbiBhcnJheSBvZiByZWxldmFudCByb3V0ZXMgdG8gYWxsb3cgbG9hZGVyIG9wdGltaXphdGlvbi4KCgkJCUV4YW1wbGU6CgkJCQlyZXR1cm4gWyIvYWRtaW4vKiIsICIvZGFzaGJvYXJkIl07CgoJCQnimqDvuI8gSW1wb3J0YW50OiBJZiB5b3UgYWRkLCByZW1vdmUsIG9yIGNoYW5nZSBhbnkgbW9kdWxlcyBvciB0aGVpciByb3V0ZXMsIGFsd2F5cyByZWdlbmVyYXRlIHRoZSBvcHRpbWl6ZWQgbG9hZGVyOgoJCQkJQ29tbWFuZDogcGhwIGRvdGFwcGVyLnBocCAtLW9wdGltaXplLW1vZHVsZXMKCQkqLwoJCXB1YmxpYyBmdW5jdGlvbiBpbml0aWFsaXplUm91dGVzKCkgewoJCQlyZXR1cm4gWycqJ107IC8vIEFsd2F5cyBtYXRjaGVzIGFsbCBVUkxzLCBidXQgbGVzcyBlZmZpY2llbnQKCQl9CgoJCS8qCgkJCVRoaXMgZnVuY3Rpb24gZGVmaW5lcyBhZGRpdGlvbmFsIGNvbmRpdGlvbnMgZm9yIHdoZXRoZXIgdGhlIG1vZHVsZSBzaG91bGQgaW5pdGlhbGl6ZSwKCQkJKiphZnRlcioqIHJvdXRlIG1hdGNoaW5nIGhhcyBhbHJlYWR5IHN1Y2NlZWRlZC4KCgkJCSRyb3V0ZU1hdGNoIOKAkyBib29sZWFuOiBUUlVFIGlmIHRoZSBjdXJyZW50IHJvdXRlIG1hdGNoZWQgb25lIG9mIHRob3NlIHJldHVybmVkIGZyb20gaW5pdGlhbGl6ZVJvdXRlcygpLCBGQUxTRSBvdGhlcndpc2UuCgoJCQlSZXR1cm4gdmFsdWVzOgoJCQktIHRydWU6IE1vZHVsZSB3aWxsIGJlIGluaXRpYWxpemVkLgoJCQktIGZhbHNlOiBNb2R1bGUgd2lsbCBub3QgYmUgaW5pdGlhbGl6ZWQuCgoJCQlVc2UgdGhpcyBpZiB5b3Ugd2FudCB0byBjaGVjayBsb2dpbiBzdGF0ZSwgdXNlciByb2xlcywgb3IgYW55IG90aGVyIGR5bmFtaWMgY29uZGl0aW9ucy4KCQkJRG8gTk9UIHJldHVybiBhbiBhcnJheSBvciBhbnl0aGluZyBvdGhlciB0aGFuIGEgYm9vbGVhbi4KCgkJCUV4YW1wbGU6CgkJCQlpZiAoISR0aGlzLT5kb3RBcHAtPmF1dGgtPmlzTG9nZ2VkSW4oKSkgcmV0dXJuIGZhbHNlOwoJCQkJcmV0dXJuIHRydWU7CgkJKi8KCQlwdWJsaWMgZnVuY3Rpb24gaW5pdGlhbGl6ZUNvbmRpdGlvbigkcm91dGVNYXRjaCkgewoJCQlyZXR1cm4gJHJvdXRlTWF0Y2g7IC8vIEFsd2F5cyBpbml0aWFsaXplIGlmIHRoZSByb3V0ZSBtYXRjaGVkIChkZWZhdWx0IGJlaGF2aW9yKQoJCX0KCX0KCQoJbmV3IE1vZHVsZSgkZG90QXBwKTsKPz4K";
        if ($filename=="/module.listeners.php") return "PD9waHAKCW5hbWVzcGFjZSBEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXCNtb2R1bGVuYW1lOwoJdXNlIFxEb3RzeXN0ZW1zXEFwcFxEb3RBcHA7Cgl1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXFJvdXRlcjsKCXVzZSBcRG90c3lzdGVtc1xBcHBcUGFydHNcUmVxdWVzdDsKCXVzZSBcRG90c3lzdGVtc1xBcHBcUGFydHNcTWlkZGxld2FyZTsKCXVzZSBcRG90c3lzdGVtc1xBcHBcUGFydHNcUmVzcG9uc2U7Cgl1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXElucHV0OwoJdXNlIFxEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xEQjsKCgljbGFzcyBMaXN0ZW5lcnMgZXh0ZW5kcyBcRG90c3lzdGVtc1xBcHBcUGFydHNcTGlzdGVuZXJzIHsKCgkJcHVibGljIGZ1bmN0aW9uIHJlZ2lzdGVyKCRkb3RBcHApIHsKCQkJCgkJCS8qCgkJCQlUaXBzOgoJCQkJCgkJCQlEbyBub3QgZm9yZ2V0IHRvIHJlZ2lzdGVyIHlvdXIgbWlkZGxld2FyZSAhIEZvciBleGFtcGxlOgoJCQkJTWlkZGxld2FyZVxNaWRkbGV3YXJlOjpyZWdpc3RlcigpOwoJCQkJCgkJCQkvLyBDb25maWd1cmUgdGhlIG1vZHVsZSB0byBzZXJ2ZSB0aGUgZGVmYXVsdCAiLyIgcm91dGUgaWYgbm8gb3RoZXIgbW9kdWxlIGhhcyBjbGFpbWVkIGl0CgkJCQkvLyBXYWl0IHVudGlsIGFsbCBtb2R1bGVzIGFyZSBsb2FkZWQsIHRoZW4gY2hlY2sgaWYgdGhlICIvIiByb3V0ZSBpcyBkZWZpbmVkCgkJCQkkZG90QXBwLT5vbigiZG90YXBwLm1vZHVsZXMubG9hZGVkIiwgZnVuY3Rpb24oJG1vZHVsZU9iaikgdXNlICgkZG90QXBwKSB7CgkJCQkJaWYgKCEkZG90QXBwLT5yb3V0ZXItPmhhc1JvdXRlKCJnZXQiLCAiLyIpKSB7CgkJCQkJCS8vIE5vIGRlZmF1bHQgcm91dGUgaXMgZGVmaW5lZCwgc28gc2V0IHRoaXMgbW9kdWxlJ3Mgcm91dGUgYXMgdGhlIGRlZmF1bHQKCQkJCQkJJGRvdEFwcC0+cm91dGVyLT5nZXQoIi8iLCBmdW5jdGlvbigpIHsKCQkJCQkJCWhlYWRlcigiTG9jYXRpb246IC8jbW9kdWxlbmFtZWxvd2VyLyIsIHRydWUsIDMwMSk7CgkJCQkJCQlleGl0KCk7CgkJCQkJCX0pOwoJCQkJCX0KCQkJCX0pOwoJCQkqLwoJCQkKCQkJLy8gQWRkIHlvdXIgY3VzdG9tIGxvZ2ljIGhlcmUKCQkJCgkJfQoJCQoJfQoJCgluZXcgTGlzdGVuZXJzKCRkb3RBcHApOwo/Pg==";
        if ($filename=="/assets/howtouse.txt") return "IyBIb3cgdG8gVXNlIEFzc2V0cyBpbiBUaGlzIE1vZHVsZQoKQWxsIGZpbGVzIHBsYWNlZCBpbiB0aGlzIGZvbGRlciBhcmUgcHVibGljbHkgYWNjZXNzaWJsZSB2aWEgdGhlIGZvbGxvd2luZyBVUkwgc3RydWN0dXJlOgoKL2Fzc2V0cy9tb2R1bGVzLyNtb2R1bGVuYW1lLwoKRm9yIGV4YW1wbGU6Ci0gSWYgeW91IHBsYWNlIGEgZmlsZSBuYW1lZCBgc2NyaXB0LmpzYCBpbiB0aGUgYGpzYCBzdWJmb2xkZXIsIHlvdSBjYW4gaW5jbHVkZSBpdCBpbiB5b3VyIEhUTUwgbGlrZSB0aGlzOgogIGBgYGh0bWwKICA8c2NyaXB0IHNyYz0iL2Fzc2V0cy9tb2R1bGVzLyNtb2R1bGVuYW1lL2pzL3NjcmlwdC5qcyI+PC9zY3JpcHQ+CiAgYGBgCgotIElmIHlvdSBhZGQgYSBmaWxlIG5hbWVkIGBzdHlsZXMuY3NzYCBpbiB0aGUgYGNzc2Agc3ViZm9sZGVyLCB5b3UgY2FuIGxpbmsgaXQgbGlrZSB0aGlzOgogIGBgYGh0bWwKICA8bGluayByZWw9InN0eWxlc2hlZXQiIGhyZWY9Ii9hc3NldHMvbW9kdWxlcy8jbW9kdWxlbmFtZS9jc3Mvc3R5bGVzLmNzcyI+CiAgYGBgCgotIElmIHlvdSBpbmNsdWRlIGFuIGltYWdlIG5hbWVkIGBiYW5uZXIuanBnYCBpbiB0aGUgYGltYWdlc2Agc3ViZm9sZGVyLCB5b3UgY2FuIHVzZSBpdCBhcyBmb2xsb3dzOgogIGBgYGh0bWwKICA8aW1nIHNyYz0iL2Fzc2V0cy9tb2R1bGVzLyNtb2R1bGVuYW1lL2ltYWdlcy9iYW5uZXIuanBnIiBhbHQ9IkJhbm5lciI+CiAgYGBgCgotIElmIHlvdSBwbGFjZSBhIGZvbnQgZmlsZSBuYW1lZCBgbXlmb250LndvZmYyYCBpbiB0aGUgYGZvbnRzYCBzdWJmb2xkZXIsIHlvdSBjYW4gcmVmZXJlbmNlIGl0IGluIHlvdXIgQ1NTIGxpa2UgdGhpczoKICBgYGBodG1sCiAgPHN0eWxlPgogICAgQGZvbnQtZmFjZSB7CiAgICAgIGZvbnQtZmFtaWx5OiAnTXlGb250JzsKICAgICAgc3JjOiB1cmwoJy9hc3NldHMvbW9kdWxlcy8jbW9kdWxlbmFtZS9mb250cy9teWZvbnQud29mZjInKSBmb3JtYXQoJ3dvZmYyJyk7CiAgICB9CiAgPC9zdHlsZT4KICBgYGA=";
        if ($filename=="/Api/Api.php") return "PD9waHAJCgluYW1lc3BhY2UgRG90c3lzdGVtc1xBcHBcTW9kdWxlc1wjbW9kdWxlbmFtZVxBcGk7Cgl1c2UgRG90c3lzdGVtc1xBcHBcRG90QXBwOwoJCgljbGFzcyBBcGkgZXh0ZW5kcyBcRG90c3lzdGVtc1xBcHBcUGFydHNcQ29udHJvbGxlciB7CgkJCgkJLyoKCQkJSWYgeW91IHVzZSB0aGUgYXV0b21hdGljIHJvdXRlciBkaXNwYXRjaGVyIGluIHRoZSBjb250cm9sbGVyIChlLmcuLCBpbiBtb2R1bGUuaW5pdC5waHApIHdpdGg6CgkJCSRkb3RBcHAtPnJvdXRlci0+YXBpUG9pbnQoIjEiLCAiI21vZHVsZW5hbWVsb3dlciIsICJEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXCNtb2R1bGVuYW1lXEFwaVxBcGlAYXBpRGlzcGF0Y2giKTsKCQkJCgkJCVRoZSBmb2xsb3dpbmcgcm91dGVzIHdpbGwgYmUgY3JlYXRlZDoKCQkJLSBHRVQgL2FwaS92MS8jbW9kdWxlbmFtZWxvd2VyL3Rlc3QgLSBDYWxscyB0aGUgZ2V0VGVzdCBtZXRob2QuCgkJCS0gUE9TVCAvYXBpL3YxLyNtb2R1bGVuYW1lbG93ZXIvdGVzdCAtIENhbGxzIHRoZSBwb3N0VGVzdCBtZXRob2QuCgoJCQlEZXBlbmRlbmN5IGluamVjdGlvbiBpcyBzdXBwb3J0ZWQgYnkgZGVmYXVsdC4gRXhhbXBsZSB3aXRoIERvdEFwcCBpbmplY3Rpb246CgkJCQoJCQlwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIGdldFRlc3QoJHJlcXVlc3QsIERvdEFwcCAkZG90QXBwKSB7CgkJCQkvLyBIYW5kbGVzIEdFVCAvYXBpL3YxLyNtb2R1bGVuYW1lbG93ZXIvdGVzdAoJCQl9CgkJCQoJCQlwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIHBvc3RUZXN0KCRyZXF1ZXN0LCBEb3RBcHAgJGRvdEFwcCkgewoJCQkJLy8gSGFuZGxlcyBQT1NUIC9hcGkvdjEvI21vZHVsZW5hbWVsb3dlci90ZXN0CgkJCX0KCQkqLwkJCgkJCQkKCX0KPz4=";
        if ($filename=="/Controllers/Controller.php") return "PD9waHAJCiAgICBuYW1lc3BhY2UgRG90c3lzdGVtc1xBcHBcTW9kdWxlc1wjbW9kdWxlbmFtZVxDb250cm9sbGVyczsKICAgIHVzZSBEb3RzeXN0ZW1zXEFwcFxEb3RBcHA7Cgl1c2UgRG90c3lzdGVtc1xBcHBcUGFydHNcTWlkZGxld2FyZTsKCXVzZSBEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xSZXNwb25zZTsKCXVzZSBEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xSZW5kZXJlcjsKCXVzZSBEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xSb3V0ZXI7CiAgICAKICAgIGNsYXNzIENvbnRyb2xsZXIgZXh0ZW5kcyBcRG90c3lzdGVtc1xBcHBcUGFydHNcQ29udHJvbGxlciB7CiAgICAgICAgCiAgICAgICAgLyoKICAgICAgICAgICAgLy8gRXhhbXBsZSB3aXRoIGRlcGVuZGVuY3kgaW5qZWN0aW9uIAogICAgICAgICAgICBwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIGhvbWUoJHJlcXVlc3QsIERvdEFwcCAkZG90QXBwKSB7CiAgICAgICAgICAgICAgICAvLyBIYW5kbGVzIEdFVCAvYXBpL3YxLyNtb2R1bGVuYW1lbG93ZXIvdGVzdAogICAgICAgICAgICB9CiAgICAgICAgICAgIAogICAgICAgICAgICAvLyBEb3RBcHAgaXMgYXZhaWxhYmxlIGluIHRoZSByZXF1ZXN0IGV2ZW4gd2l0aG91dCBESQogICAgICAgICAgICBwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIGhvbWUoJHJlcXVlc3QsIFJlbmRlcmVyICRyZW5kZXJlcikgewogICAgICAgICAgICAgICAgJGRvdEFwcCA9ICRyZXF1ZXN0LT5kb3RBcHA7CiAgICAgICAgICAgICAgICAkdmlld1ZhcnNbJ3NlbyddWydkZXNjcmlwdGlvbiddID0gIlRoaXMgaXMgYSBob21lIGV4YW1wbGUgcGFnZSBmb3IgdGhlIEV4YW1wbGUgUEhQIGZyYW1ld29yay4iOwogICAgICAgICAgICAgICAgJHZpZXdWYXJzWydzZW8nXVsna2V5d29yZHMnXSA9ICJleGFtcGxlLCBQSFAgZnJhbWV3b3JrLCBob21lLCBkZW1vIjsKICAgICAgICAgICAgICAgICR2aWV3VmFyc1snc2VvJ11bJ3RpdGxlJ10gPSAiSG9tZSAtIEV4YW1wbGUgUEhQIEZyYW1ld29yayI7CgkJCQkKCQkJCQogICAgICAgICAgICAgICAgcmV0dXJuICRyZW5kZXJlci0+bW9kdWxlKCIjbW9kdWxlbmFtZSIpLT5zZXRWaWV3KCJob21lIiktPnNldFZpZXdWYXIoInZhcmlhYmxlcyIsICR2aWV3VmFycyktPnJlbmRlclZpZXcoKTsKCQkJCS8vIGFsZWJvIAoJCQkJLy8gcmV0dXJuICRyZW5kZXJlci0+bW9kdWxlKHNlbGY6Om1vZHVsZU5hbWUoKSktPnNldFZpZXcoImhvbWUiKS0+c2V0Vmlld1ZhcigidmFyaWFibGVzIiwgJHZpZXdWYXJzKS0+cmVuZGVyVmlldygpOwogICAgICAgICAgICB9CiAgICAgICAgKi8JCQogICAgICAgICAgICAgICAgCiAgICB9Cj8+";
        if ($filename=="/Middleware/Middleware.php") return "PD9waHAJCm5hbWVzcGFjZSBEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXCNtb2R1bGVuYW1lXE1pZGRsZXdhcmU7Cgp1c2UgRG90c3lzdGVtc1xBcHBcRG90QXBwOwp1c2UgRG90c3lzdGVtc1xBcHBcUGFydHNcTWlkZGxld2FyZTsKdXNlIERvdHN5c3RlbXNcQXBwXFBhcnRzXFJlc3BvbnNlOwoKY2xhc3MgTWlkZGxld2FyZSBleHRlbmRzIFxEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xNb2R1bGVNaWRkbGV3YXJlIHsKCglwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIHJlZ2lzdGVyKCkgewoJCS8qCgkJc2VsZjo6bWlkZGxld2FyZSgibmFtZSIsIGNhbGxiYWNrKTsgaXMgZXF1aXZhbGVudCB0byBuZXcgTWlkZGxld2FyZSgibmFtZSIsIGNhbGxiYWNrKTsKCQkoIHNlbGY6Om1pZGRsZXdhcmUoIm5hbWUiKSB3aXRob3V0IHRoZSBjYWxsYmFjayBhY3RzIGFzIGEgZ2V0dGVyICkgCgkJCgkJVGhlIGNhbGxiYWNrIGNhbiBiZToKCQktIGFuIGFub255bW91cyBmdW5jdGlvbiwKCQktIGFub3RoZXIgbWlkZGxld2FyZSwKCQktIGEgY29udHJvbGxlciBjYWxsIGluIHRoZSBmb3JtICJtb2R1bGU6Q29udHJvbGxlckBmdW5jdGlvbiIKCiAgICAgICAgRXhhbXBsZSB1c2FnZToKICAgICAgICAKICAgICAgICBzZWxmOjptaWRkbGV3YXJlKCJuYW1lT2ZNaWRkbGV3YXJlIiwgZnVuY3Rpb24oJHJlcXVlc3QsICRuZXh0KSB7CiAgICAgICAgICAgIC8vIFlvdXIgY3VzdG9tIGxvZ2ljIGhlcmUuLi4KCiAgICAgICAgICAgIC8vIElmIHNvbWV0aGluZyBpcyB3cm9uZyDigJMgc3RvcCB0aGUgcGlwZWxpbmUgYW5kIHJldHVybiBhIHJlc3BvbnNlCiAgICAgICAgICAgIHJldHVybiBuZXcgUmVzcG9uc2UoNDAzLCAiWW91IG11c3QgYmUgbG9nZ2VkIGluISIpOwoKICAgICAgICAgICAgLy8gSWYgZXZlcnl0aGluZyBpcyBPSyDigJMgY29udGludWUgdGhlIHBpcGVsaW5lCiAgICAgICAgICAgIHJldHVybiAkbmV4dCgkcmVxdWVzdCk7CiAgICAgICAgfSk7CgogICAgICAgIHNlbGY6Om1pZGRsZXdhcmUoIm5hbWVPZk1pZGRsZXdhcmUiLCBmdW5jdGlvbigkcmVxdWVzdCwgJG5leHQpIHsKICAgICAgICAgICAgLy8gWW91ciBjdXN0b20gbG9naWMgaGVyZS4uLgoKICAgICAgICAgICAgLy8gU3RvcCB0aGUgcGlwZWxpbmUgYW5kIHJldHVybiBhIGRldGFpbGVkIHJlc3BvbnNlIHVzaW5nIGFuIGFycmF5CiAgICAgICAgICAgICRyZXNwb25zZSA9IFtdOwogICAgICAgICAgICAkcmVzcG9uc2VbJ2JvZHknXSA9ICJZb3UgbXVzdCBiZSBsb2dnZWQgaW4hIjsKICAgICAgICAgICAgJHJlc3BvbnNlWydjb250ZW50VHlwZSddID0gInRleHQvaHRtbCI7CiAgICAgICAgICAgICRyZXNwb25zZVsnaGVhZGVycyddID0gWyJDb250ZW50LVR5cGUiID0+ICJ0ZXh0L2h0bWwiXTsKICAgICAgICAgICAgcmV0dXJuIG5ldyBSZXNwb25zZSg0MDMsICRyZXNwb25zZSk7CgogICAgICAgICAgICAvLyBDb250aW51ZSB0aGUgcGlwZWxpbmUKICAgICAgICAgICAgcmV0dXJuICRuZXh0KCRyZXF1ZXN0KTsKICAgICAgICB9KTsKCiAgICAgICAgbmV3IE1pZGRsZXdhcmUoIm5hbWVPZk1pZGRsZXdhcmUyIiwgZnVuY3Rpb24oJHJlcXVlc3QsICRuZXh0KSB7CiAgICAgICAgICAgIC8vIFlvdXIgY3VzdG9tIGxvZ2ljIGhlcmUuLi4KCiAgICAgICAgICAgIC8vIFN0b3AgdGhlIHBpcGVsaW5lIHdpdGggYW4gYXJyYXktYmFzZWQgcmVzcG9uc2UKICAgICAgICAgICAgJHJlc3BvbnNlID0gW107CiAgICAgICAgICAgICRyZXNwb25zZVsnYm9keSddID0gIllvdSBtdXN0IGJlIGxvZ2dlZCBpbiEiOwogICAgICAgICAgICAkcmVzcG9uc2VbJ2NvbnRlbnRUeXBlJ10gPSAidGV4dC9odG1sIjsKICAgICAgICAgICAgJHJlc3BvbnNlWydoZWFkZXJzJ10gPSBbIkNvbnRlbnQtVHlwZSIgPT4gInRleHQvaHRtbCJdOwogICAgICAgICAgICByZXR1cm4gbmV3IFJlc3BvbnNlKDQwMywgJHJlc3BvbnNlKTsKCiAgICAgICAgICAgIC8vIENvbnRpbnVlIHRoZSBwaXBlbGluZQogICAgICAgICAgICByZXR1cm4gJG5leHQoJHJlcXVlc3QpOwogICAgICAgIH0pOwogICAgICAgICovCgl9Cn0KPz4K";
        if ($filename=="/Models/Model.php") return "PD9waHAJCiAgICBuYW1lc3BhY2UgRG90c3lzdGVtc1xBcHBcTW9kdWxlc1wjbW9kdWxlbmFtZVxNb2RlbHM7CiAgICB1c2UgRG90c3lzdGVtc1xBcHBcRG90QXBwOwoJdXNlIERvdHN5c3RlbXNcQXBwXFBhcnRzXEF1dGg7Cgl1c2UgRG90c3lzdGVtc1xBcHBcUGFydHNcUmVzcG9uc2U7CiAgICB1c2UgRG90c3lzdGVtc1xBcHBcUGFydHNcQ3J5cHRvOwoJdXNlIERvdHN5c3RlbXNcQXBwXFBhcnRzXERCOwkKCXVzZSBEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xSZXF1ZXN0OwogICAgCiAgICBjbGFzcyBNb2RlbCBleHRlbmRzIFxEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xNb2RlbCB7CiAgICAgICAgCiAgICAgICAgICAgICAgICAKICAgIH0KPz4=";
        if ($filename=="/views/clean.view.php") return base64_encode("{{ content }}");
        if ($filename=="/views/layouts/example.layout.php") return "PCEtLSBFeGFtcGxlIG9mIGxheW91dCAtLT4KPHA+UHJpbnQgdmFyaWJhbGUgdmFsdWUgaW4gbW9kdWxlICNtb2R1bGVuYW1lPC9wPgo8cD4KCXt7IHZhcjogJHZhcmlhYmxlc1snYXJ0aWNsZSddWydhcnRpY2xlJ10gfX0KPC9wPgo=";
        if ($filename=="/views/guide.md") return "IyBEb3RBcHAgVGVtcGxhdGUgU3lzdGVtIC0gR3VpZGUgZm9yIEFJIE1vZGVscwoKIyMgT3ZlcnZpZXcKCkRvdEFwcCBmcmFtZXdvcmsgdXNlcyBhIHVuaXF1ZSB0ZW1wbGF0ZSBzeXN0ZW0gd2hlcmU6Ci0gKipWSUVXKiogPSBtYWluIHBhZ2Ugc3RydWN0dXJlIChIVE1MIHdyYXBwZXIsIGhlYWQsIGJvZHksIGhlYWRlciwgZm9vdGVyLCBzaWRlYmFyKQotICoqTEFZT1VUKiogPSBzcGVjaWZpYyBjb250ZW50IHRoYXQgZ2V0cyBpbnNlcnRlZCBpbnRvIFZJRVcKCiMjIENvcmUgUHJpbmNpcGxlcwoKIyMjIDEuIFZJRVcgdnMgTEFZT1VUCgoqKlZJRVcqKiAoYCoudmlldy5waHBgKToKLSBDb250YWlucyB0aGUgbWFpbiBIVE1MIHN0cnVjdHVyZSBvZiB0aGUgcGFnZQotIE11c3QgY29udGFpbiBge3sgY29udGVudCB9fWAgcGxhY2Vob2xkZXIKLSBMQVlPVVQgY29udGVudCBnZXRzIGluc2VydGVkIGludG8gdGhpcyBwbGFjZWhvbGRlcgoKKipMQVlPVVQqKiAoYCoubGF5b3V0LnBocGApOgotIENvbnRhaW5zIHNwZWNpZmljIHBhZ2UgY29udGVudAotIFRoaXMgY29udGVudCBnZXRzIGluc2VydGVkIGludG8gYHt7IGNvbnRlbnQgfX1gIGluIFZJRVcKLSBTaG91bGQgY29udGFpbiBvbmx5IEhUTUwgY29udGVudCB3aXRob3V0IHdyYXBwZXIKCiMjIyAyLiBTdHJ1Y3R1cmUgRXhhbXBsZQoKYGBgCmFwcC9tb2R1bGVzL1BoYXJtTGlzdC92aWV3cy8K4pSc4pSA4pSAIGRvY3Mudmlldy5waHAgICAgICAgICAgIyBWSUVXIC0gbWFpbiBzdHJ1Y3R1cmUK4pSU4pSA4pSAIGxheW91dHMvCiAgICDilJTilIDilIAgZG9jcy8KICAgICAgICDilJTilIDilIAgaW5kZXgubGF5b3V0LnBocCAgIyBMQVlPVVQgLSBkb2N1bWVudGF0aW9uIGNvbnRlbnQKYGBgCgoqKmRvY3Mudmlldy5waHAqKiAoVklFVyk6CmBgYGh0bWwKPCFET0NUWVBFIGh0bWw+CjxodG1sPgo8aGVhZD4KICAgIDx0aXRsZT57eyB2YXI6ICRtZXRhWyd0aXRsZSddIH19PC90aXRsZT4KPC9oZWFkPgo8Ym9keT4KICAgIDxoZWFkZXI+Li4uPC9oZWFkZXI+CiAgICA8bWFpbj4KICAgICAgICB7eyBjb250ZW50IH19ICA8IS0tIExBWU9VVCBnZXRzIGluc2VydGVkIGhlcmUgLS0+CiAgICA8L21haW4+CiAgICA8Zm9vdGVyPi4uLjwvZm9vdGVyPgo8L2JvZHk+CjwvaHRtbD4KYGBgCgoqKmRvY3MvaW5kZXgubGF5b3V0LnBocCoqIChMQVlPVVQpOgpgYGBodG1sCjxoMT5XZWxjb21lPC9oMT4KPHA+RG9jdW1lbnRhdGlvbiBjb250ZW50Li4uPC9wPgpgYGAKCiMjIyAzLiBVc2FnZSBpbiBDb250cm9sbGVycwoKYGBgcGhwCnB1YmxpYyBzdGF0aWMgZnVuY3Rpb24gaW5kZXgoJHJlcXVlc3QsIFJlbmRlcmVyICRyZW5kZXJlcikgewogICAgJHZpZXdWYXJzID0gWwogICAgICAgICdtZXRhJyA9PiBbJ3RpdGxlJyA9PiAnRG9jdW1lbnRhdGlvbiddLAogICAgICAgICdhY3RpdmVQYWdlJyA9PiAnaW5kZXgnCiAgICBdOwogICAgCiAgICByZXR1cm4gJHJlbmRlcmVyLT5tb2R1bGUoIlBoYXJtTGlzdCIpCiAgICAgICAgLT5zZXRWaWV3KCJkb2NzIikgICAgICAgICAgIC8vIFZJRVcgZmlsZQogICAgICAgIC0+c2V0TGF5b3V0KCJkb2NzL2luZGV4IikgICAvLyBMQVlPVVQgZmlsZQogICAgICAgIC0+c2V0Vmlld1ZhcigidmFyaWFibGVzIiwgJHZpZXdWYXJzKQogICAgICAgIC0+cmVuZGVyVmlldygpOwp9CmBgYAoKIyMgTGF5b3V0IE5lc3RpbmcKCiMjIyBTeW50YXgKCkluIExBWU9VVCBvciBWSUVXIHlvdSBjYW4gbmVzdCBhZGRpdGlvbmFsIGxheW91dHMgdXNpbmc6CgotIGB7eyBsYXlvdXQ6IGxheW91dE5hbWUgfX1gIC0gaW5zZXJ0cyBsYXlvdXQgZnJvbSBjdXJyZW50IG1vZHVsZQotIGB7eyBiYXNlbGF5b3V0OiBsYXlvdXROYW1lIH19YCAtIGluc2VydHMgbGF5b3V0IGZyb20gYmFzZSBkaXJlY3RvcnkgKGBhcHAvcGFydHMvdmlld3MvbGF5b3V0cy9gKQoKIyMjIEhvdyBJdCBXb3JrcwoKV2hlbiB5b3UgdXNlIGB7eyBsYXlvdXQ6IGRvY3Mvc2VjdGlvbiB9fWA6CjEuIFN5c3RlbSBmaW5kcyBgZG9jcy9zZWN0aW9uLmxheW91dC5waHBgIGluIGN1cnJlbnQgbW9kdWxlCjIuIExvYWRzIGl0cyBjb250ZW50CjMuIFJlY3Vyc2l2ZWx5IHByb2Nlc3NlcyBuZXN0ZWQgbGF5b3V0cyBpbiB0aGF0IGxheW91dAo0LiBSZXBsYWNlcyBge3sgbGF5b3V0OiAuLi4gfX1gIHdpdGggdGhhdCBsYXlvdXQncyBjb250ZW50CgojIyMgTmVzdGluZyBFeGFtcGxlCgoqKmRvY3MvaW5kZXgubGF5b3V0LnBocCoqOgpgYGBodG1sCnt7IGxheW91dDogZG9jcy9zZWN0aW9uIH19Cgo8aDE+VGl0bGU8L2gxPgo8cD5Db250ZW50Li4uPC9wPgoKe3sgL2xheW91dDogZG9jcy9zZWN0aW9uIH19CmBgYAoKKipkb2NzL3NlY3Rpb24ubGF5b3V0LnBocCoqOgpgYGBodG1sCjxhcnRpY2xlIGNsYXNzPSJzZWN0aW9uIj4KICAgIHt7IGNvbnRlbnQgfX0KPC9hcnRpY2xlPgpgYGAKCioqUmVzdWx0Kio6CmBgYGh0bWwKPGFydGljbGUgY2xhc3M9InNlY3Rpb24iPgogICAgPGgxPlRpdGxlPC9oMT4KICAgIDxwPkNvbnRlbnQuLi48L3A+CjwvYXJ0aWNsZT4KYGBgCgoqKk5PVEUqKjogVGhlIGB7eyAvbGF5b3V0OiAuLi4gfX1gIHN5bnRheCBpcyBub3Qgc3VwcG9ydGVkISBge3sgbGF5b3V0OiAuLi4gfX1gIHdvcmtzIG9ubHkgYXMgYW4gaW5jbHVkZSAtIGl0IGdldHMgcmVwbGFjZWQgd2l0aCB0aGF0IGxheW91dCdzIGNvbnRlbnQuIENvbnRlbnQgYXJvdW5kIGl0IGlzIG5vdCBhdXRvbWF0aWNhbGx5IGluc2VydGVkIGludG8gYHt7IGNvbnRlbnQgfX1gIGluIHRoYXQgbGF5b3V0LgoKIyMgQ2FsbGluZyBMYXlvdXRzIGZyb20gT3RoZXIgTW9kdWxlcwoKIyMjIFN5bnRheAoKVXNlIGBtb2R1bGVOYW1lOmxheW91dFBhdGhgIHN5bnRheDoKCmBgYHBocAovLyBJbiBjb250cm9sbGVyCiRyZW5kZXJlci0+c2V0TGF5b3V0KCJPdGhlck1vZHVsZTpkb2NzL2luZGV4Iik7CgovLyBJbiB0ZW1wbGF0ZQp7eyBsYXlvdXQ6IE90aGVyTW9kdWxlOmRvY3Mvc2VjdGlvbiB9fQp7eyBiYXNlbGF5b3V0OiBPdGhlck1vZHVsZTpjb21tb24vaGVhZGVyIH19CmBgYAoKIyMjIEV4YW1wbGVzCgoqKkZyb20gY3VycmVudCBtb2R1bGUqKjoKYGBgaHRtbAp7eyBsYXlvdXQ6IGRvY3Mvc2VjdGlvbiB9fSAgPCEtLSBMb29rcyBpbiBhcHAvbW9kdWxlcy9QaGFybUxpc3Qvdmlld3MvbGF5b3V0cy9kb2NzL3NlY3Rpb24ubGF5b3V0LnBocCAtLT4KYGBgCgoqKkZyb20gYW5vdGhlciBtb2R1bGUqKjoKYGBgaHRtbAp7eyBsYXlvdXQ6IERvdFNob3A6cHJvZHVjdC9jYXJkIH19ICA8IS0tIExvb2tzIGluIGFwcC9tb2R1bGVzL0RvdFNob3Avdmlld3MvbGF5b3V0cy9wcm9kdWN0L2NhcmQubGF5b3V0LnBocCAtLT4KYGBgCgoqKkZyb20gYmFzZSBkaXJlY3RvcnkqKjoKYGBgaHRtbAp7eyBiYXNlbGF5b3V0OiBjb21tb24vaGVhZGVyIH19ICA8IS0tIExvb2tzIGluIGFwcC9wYXJ0cy92aWV3cy9sYXlvdXRzL2NvbW1vbi9oZWFkZXIubGF5b3V0LnBocCAtLT4KYGBgCgojIyBUZW1wbGF0ZSBTeW50YXggUmVmZXJlbmNlCgojIyMgRGlzcGxheWluZyBWYXJpYWJsZXMKCmBgYGh0bWwKe3sgdmFyOiAkdmFyaWFibGVOYW1lIH19Cnt7IHZhcjogJGFycmF5WydrZXknXSB9fQp7eyB2YXI6ICRhcnJheVsna2V5J11bJ25lc3RlZCddIH19CmBgYAoKKipJTVBPUlRBTlQqKjogVGhlIGB7eyB2YXI6IH19YCBzeW50YXggc3VwcG9ydHMgT05MWSBzaW1wbGUgdmFyaWFibGUgYWNjZXNzLiBJdCBkb2VzIE5PVCBzdXBwb3J0OgotIGA/P2AgbnVsbCBjb2FsZXNjaW5nIG9wZXJhdG9yCi0gVGVybmFyeSBvcGVyYXRvcnMgYD8gOmAKLSBDb21wbGV4IGV4cHJlc3Npb25zCgoqKkZvciBkZWZhdWx0IHZhbHVlcywgdXNlIGB7eyBpZiB9fWAgY29uZGl0aW9uczoqKgoKYGBgaHRtbAo8IS0tIElOQ09SUkVDVCAtIGRvZXMgbm90IHdvcmsgLS0+Cnt7IHZhcjogJHZhcmlhYmxlID8/ICdkZWZhdWx0JyB9fQoKPCEtLSBDT1JSRUNUIC0gdXNlIGlmIGNvbmRpdGlvbiAtLT4Ke3sgaWYgaXNzZXQoJHZhcmlhYmxlKSB9fQogICAge3sgdmFyOiAkdmFyaWFibGUgfX0Ke3sgZWxzZSB9fQogICAgZGVmYXVsdAp7eyAvaWYgfX0KYGBgCgojIyMgVHJhbnNsYXRpb25zCgpEb3RBcHAgc3VwcG9ydHMgYnVpbHQtaW4gdHJhbnNsYXRpb24gc3lzdGVtIHdpdGggYXV0b21hdGljIGZhbGxiYWNrOgoKYGBgaHRtbAp7e18gdmFyOiAkdmFyaWFibGVOYW1lIH19ICAgICAgICAgIDwhLS0gVHJhbnNsYXRlcyB2YXJpYWJsZSB2YWx1ZSAtLT4Ke3tfICJUZXh0IHRvIHRyYW5zbGF0ZSIgfX0gICAgICAgIDwhLS0gVHJhbnNsYXRlcyBzdHJpbmcgbGl0ZXJhbCAtLT4KYGBgCgoqKkhvdyBpdCB3b3JrczoqKgoxLiBTeXN0ZW0gbG9va3MgdXAgdGhlIHRleHQgaW4gdGhlIHRyYW5zbGF0aW9uIGRpY3Rpb25hcnkgZm9yIHRoZSBjdXJyZW50IGxvY2FsZQoyLiBJZiB0cmFuc2xhdGlvbiBpcyBmb3VuZCDihpIgcmV0dXJucyB0cmFuc2xhdGVkIHRleHQKMy4gSWYgdHJhbnNsYXRpb24gaXMgTk9UIGZvdW5kIOKGkiByZXR1cm5zIG9yaWdpbmFsIHRleHQgKGZhbGxiYWNrKQoKKipFeGFtcGxlOioqCmBgYGh0bWwKPGgxPnt7XyAiV2VsY29tZSIgfX08L2gxPgo8cD57e18gdmFyOiAkbWVzc2FnZSB9fTwvcD4KYGBgCgoqKkZhbGxiYWNrIGJlaGF2aW9yOioqCi0gSWYgIldlbGNvbWUiIGlzIGluIHRyYW5zbGF0aW9uIGRpY3Rpb25hcnkg4oaSIHNob3dzIHRyYW5zbGF0ZWQgdmVyc2lvbiAoZS5nLiwgIlZpdGFqdGUiIGluIFNsb3ZhaykKLSBJZiAiV2VsY29tZSIgaXMgTk9UIGluIGRpY3Rpb25hcnkg4oaSIHNob3dzIG9yaWdpbmFsICJXZWxjb21lIiB0ZXh0Ci0gU2FtZSBhcHBsaWVzIHRvIHZhcmlhYmxlczogaWYgYCRtZXNzYWdlID0gIkhlbGxvImAgYW5kIHRyYW5zbGF0aW9uIGV4aXN0cyDihpIgc2hvd3MgdHJhbnNsYXRpb24sIG90aGVyd2lzZSBzaG93cyAiSGVsbG8iCgoqKlRoaXMgbWVhbnMgeW91IGNhbiBzYWZlbHkgdXNlIHRyYW5zbGF0aW9ucyB3aXRob3V0IHdvcnJ5aW5nIGFib3V0IG1pc3NpbmcgZW50cmllcyAtIHRoZSBvcmlnaW5hbCB0ZXh0IHdpbGwgYWx3YXlzIGJlIGRpc3BsYXllZCBhcyBmYWxsYmFjay4qKgoKIyMjIENvbmRpdGlvbnMKCmBgYGh0bWwKe3sgaWYgJGNvbmRpdGlvbiB9fQogICAgQ29udGVudAp7eyBlbHNlaWYgJG90aGVyQ29uZGl0aW9uIH19CiAgICBPdGhlciBjb250ZW50Cnt7IGVsc2UgfX0KICAgIERlZmF1bHQgY29udGVudAp7eyAvaWYgfX0KYGBgCgoqKkV4YW1wbGU6KioKYGBgaHRtbAp7eyBpZiBpc3NldCgkdXNlcikgfX0KICAgIDxwPkhlbGxvLCB7eyB2YXI6ICR1c2VyWyduYW1lJ10gfX08L3A+Cnt7IGVsc2UgfX0KICAgIDxwPlBsZWFzZSBsb2cgaW48L3A+Cnt7IC9pZiB9fQpgYGAKCiMjIyBMb29wcwoKKipGb3JlYWNoIGxvb3A6KioKYGBgaHRtbAp7eyBmb3JlYWNoICRpdGVtcyBhcyAkaXRlbSB9fQogICAgPHA+e3sgdmFyOiAkaXRlbSB9fTwvcD4Ke3sgL2ZvcmVhY2ggfX0KCnt7IGZvcmVhY2ggJGFycmF5WydrZXknXSBhcyAkdmFsdWUgfX0KICAgIDxwPnt7IHZhcjogJHZhbHVlIH19PC9wPgp7eyAvZm9yZWFjaCB9fQpgYGAKCioqV2hpbGUgbG9vcDoqKgpgYGBodG1sCnt7IHdoaWxlICRpbmRleCA8IGNvdW50KCRpdGVtcykgfX0KICAgIDxsaT57eyB2YXI6ICRpdGVtc1skaW5kZXhdIH19PC9saT4KICAgIDw/cGhwICRpbmRleCsrOyA/Pgp7eyAvd2hpbGUgfX0KYGBgCgojIyMgRW5jcnlwdGlvbgoKRG90QXBwIHN1cHBvcnRzIGVuY3J5cHRpb24gZGlyZWN0bHkgaW4gdGVtcGxhdGVzOgoKYGBgaHRtbAp7eyBlbmM6ICR2YXJpYWJsZU5hbWUgfX0gICAgICAgICAgICAgICAgICAgIDwhLS0gRW5jcnlwdHMgdmFyaWFibGUgLS0+Cnt7IGVuYyhrZXkpOiAkdmFyaWFibGVOYW1lIH19ICAgICAgICAgICAgICA8IS0tIEVuY3J5cHRzIHdpdGggY3VzdG9tIGtleSAtLT4Ke3sgZW5jOiAic3RyaW5nIHRvIGVuY3J5cHQiIH19ICAgICAgICAgICAgIDwhLS0gRW5jcnlwdHMgc3RyaW5nIGxpdGVyYWwgLS0+Cnt7IGVuYyhjdXN0b21LZXkpOiAic3RyaW5nIiB9fSAgICAgICAgICAgICA8IS0tIEVuY3J5cHRzIHN0cmluZyB3aXRoIGtleSAtLT4KYGBgCgoqKkV4YW1wbGU6KioKYGBgaHRtbAo8aW5wdXQgdHlwZT0iaGlkZGVuIiBuYW1lPSJ0b2tlbiIgdmFsdWU9Int7IGVuYzogJGNzcmZUb2tlbiB9fSI+CjxhIGhyZWY9Ii92ZXJpZnk/Y29kZT17eyBlbmMoc2VjcmV0KTogJHVzZXJDb2RlIH19Ij5WZXJpZnk8L2E+CmBgYAoKIyMjIENTUkYgUHJvdGVjdGlvbgoKR2VuZXJhdGUgQ1NSRiB0b2tlbiBpbnB1dCBmaWVsZDoKCmBgYGh0bWwKe3sgQ1NSRiB9fQpgYGAKCioqT3V0cHV0OioqCmBgYGh0bWwKPGlucHV0IHR5cGU9ImhpZGRlbiIgdmFsdWU9ImNzcmZfdG9rZW5fdmFsdWUiPgpgYGAKCiMjIyBGb3JtIFNlY3VyaXR5CgpHZW5lcmF0ZSBzZWN1cmUgZm9ybSBmaWVsZHMgKGF1dG9tYXRpY2FsbHkgZXh0cmFjdHMgYWN0aW9uIGFuZCBtZXRob2QgZnJvbSBgPGZvcm0+YCB0YWcpOgoKYGBgaHRtbAo8Zm9ybSBhY3Rpb249Ii9zdWJtaXQiIG1ldGhvZD0iUE9TVCI+CiAgICA8aW5wdXQgdHlwZT0idGV4dCIgbmFtZT0idXNlcm5hbWUiPgogICAge3sgZm9ybU5hbWUobXlGb3JtKSB9fQo8L2Zvcm0+CmBgYAoKVGhpcyBnZW5lcmF0ZXMgZW5jcnlwdGVkIGhpZGRlbiBmaWVsZHMgZm9yIGZvcm0gc2VjdXJpdHkgdmFsaWRhdGlvbi4KCiMjIyBDdXN0b20gQmxvY2tzCgpSZWdpc3RlciBjdXN0b20gYmxvY2sgaGFuZGxlcnMgYW5kIHVzZSB0aGVtIGluIHRlbXBsYXRlczoKCioqUmVnaXN0ZXIgYmxvY2sgaW4gUEhQOioqCmBgYHBocAokZG90YXBwLT5jdXN0b21SZW5kZXJlci0+YWRkQmxvY2soImFsZXJ0IiwgZnVuY3Rpb24oJGNvbnRlbnQsICRwYXJhbXMsICR2YXJpYWJsZXMpIHsKICAgICR0eXBlID0gJHBhcmFtc1swXSA/PyAnaW5mbyc7CiAgICByZXR1cm4gIjxkaXYgY2xhc3M9J2FsZXJ0IGFsZXJ0LXskdHlwZX0nPnskY29udGVudH08L2Rpdj4iOwp9KTsKYGBgCgoqKlVzZSBpbiB0ZW1wbGF0ZToqKgpgYGBodG1sCnt7IGJsb2NrOmFsZXJ0KGRhbmdlcikgfX0KICAgIFdhcm5pbmc6IFRoaXMgaXMgaW1wb3J0YW50IQp7eyAvYmxvY2s6YWxlcnQgfX0KCnt7IGJsb2NrOmFsZXJ0IH19CiAgICBJbmZvIG1lc3NhZ2UKe3sgL2Jsb2NrOmFsZXJ0IH19CmBgYAoKIyMjIFByaXZhdGUgQmxvY2tzCgpFeHRyYWN0IHJldXNhYmxlIEhUTUwgZnJhZ21lbnRzIGFzIG9iamVjdHM6CgoqKkluIHRlbXBsYXRlOioqCmBgYGh0bWwKe3sgcHJpdmF0ZWJsb2NrOml0ZW0gfX0KICAgIDxsaT57eyB2YXI6ICRuYW1lIH19PC9saT4Ke3sgL3ByaXZhdGVibG9jayB9fQpgYGAKCioqSW4gUEhQIChzYW1lIGZpbGUgYWZ0ZXIgcmVuZGVyaW5nKToqKgpgYGBwaHAKZm9yZWFjaCgkZGF0YSBhcyAkZCkgewogICAgZWNobyAkYmxvY2tbJ2l0ZW0nXS0+c2V0KCJuYW1lIiwgJGQpLT5odG1sKCk7Cn0KYGBgCgojIyMgTGF5b3V0IE5lc3RpbmcKCk5lc3QgbGF5b3V0cyB3aXRoaW4gbGF5b3V0czoKCmBgYGh0bWwKe3sgbGF5b3V0OiBsYXlvdXROYW1lIH19ICAgICAgICAgIDwhLS0gRnJvbSBjdXJyZW50IG1vZHVsZSAtLT4Ke3sgYmFzZWxheW91dDogbGF5b3V0TmFtZSB9fSAgICAgIDwhLS0gRnJvbSBiYXNlIGRpcmVjdG9yeSAtLT4KYGBgCgpTZWUgIkxheW91dCBOZXN0aW5nIiBzZWN0aW9uIGZvciBkZXRhaWxzLgoKIyMjIEluY2x1ZGUgKEphdmFTY3JpcHQgVGVtcGxhdGUgRW5naW5lKQoKKipOb3RlKio6IGB7eyBpbmNsdWRlIH19YCBpcyBhdmFpbGFibGUgaW4gSmF2YVNjcmlwdCB0ZW1wbGF0ZSBlbmdpbmUgKGBkb3RhcHAudGVtcGxhdGUuanNgKSwgbm90IGluIFBIUCByZW5kZXJlci4KCmBgYGphdmFzY3JpcHQKe3sgaW5jbHVkZSAncGFydGlhbHMvaGVhZGVyJyB9fQp7eyBpbmNsdWRlICJwYXJ0aWFscy9oZWFkZXIiIH19Cnt7IGluY2x1ZGUgcGFydGlhbHMvaGVhZGVyIH19CmBgYAoKIyMjIFZpZXdWYXJzIHZzIExheW91dFZhcnMKCi0gKipWaWV3VmFycyoqIC0gYXZhaWxhYmxlIE9OTFkgaW4gVklFVyAodXNlIGBzZXRWaWV3VmFyKClgKQotICoqTGF5b3V0VmFycyoqIC0gYXZhaWxhYmxlIE9OTFkgaW4gTEFZT1VUICh1c2UgYHNldExheW91dFZhcigpYCkKCioqSW1wb3J0YW50Kio6IFZpZXdWYXJzIGFyZSBOT1QgYXV0b21hdGljYWxseSBhdmFpbGFibGUgaW4gTEFZT1VUISBJZiB5b3UgbmVlZCB2YXJpYWJsZXMgaW4gYm90aCBWSUVXIGFuZCBMQVlPVVQsIHlvdSBtdXN0IHNldCB0aGVtIHNlcGFyYXRlbHkuCgojIyMgVmFyaWFibGUgTmFtZXMKCllvdSBjYW4gdXNlICoqYW55IHZhcmlhYmxlIG5hbWUqKiB5b3Ugd2FudCAtIGl0IGRvZXNuJ3QgaGF2ZSB0byBiZSAidmFyaWFibGVzIi4gVGhlIGZpcnN0IHBhcmFtZXRlciBpbiBgc2V0Vmlld1ZhcigpYCBvciBgc2V0TGF5b3V0VmFyKClgIGlzIHRoZSB2YXJpYWJsZSBuYW1lIHRoYXQgd2lsbCBiZSBhdmFpbGFibGUgaW4gdGhlIHRlbXBsYXRlLgoKKipFeGFtcGxlIHdpdGggY3VzdG9tIHZhcmlhYmxlIG5hbWVzOioqCgpgYGBwaHAKLy8gVXNpbmcgInNlbyIgYXMgdmFyaWFibGUgbmFtZQokc2VvVmFycyA9IFsndGl0bGUnID0+ICdQYWdlIFRpdGxlJywgJ2Rlc2NyaXB0aW9uJyA9PiAnUGFnZSBEZXNjcmlwdGlvbiddOwokcmVuZGVyZXItPnNldFZpZXdWYXIoInNlbyIsICRzZW9WYXJzKTsKCi8vIFVzaW5nICJwYWdlIiBhcyB2YXJpYWJsZSBuYW1lCiRwYWdlVmFycyA9IFsnYWN0aXZlUGFnZScgPT4gJ2luZGV4JywgJ2N1cnJlbnRTZWN0aW9uJyA9PiAnZG9jcyddOwokcmVuZGVyZXItPnNldFZpZXdWYXIoInBhZ2UiLCAkcGFnZVZhcnMpOwoKLy8gVXNpbmcgImRhdGEiIGFzIHZhcmlhYmxlIG5hbWUKJGRhdGFWYXJzID0gWyd1c2VyJyA9PiBbJ25hbWUnID0+ICdKb2huJ10sICdpdGVtcycgPT4gWy4uLl1dOwokcmVuZGVyZXItPnNldFZpZXdWYXIoImRhdGEiLCAkZGF0YVZhcnMpOwpgYGAKCioqSW4gVklFVyB0ZW1wbGF0ZToqKgpgYGBodG1sCjx0aXRsZT57eyB2YXI6ICRzZW9bJ3RpdGxlJ10gfX08L3RpdGxlPgo8bWV0YSBuYW1lPSJkZXNjcmlwdGlvbiIgY29udGVudD0ie3sgdmFyOiAkc2VvWydkZXNjcmlwdGlvbiddIH19Ij4KPGRpdiBjbGFzcz0ie3sgdmFyOiAkcGFnZVsnYWN0aXZlUGFnZSddIH19Ij4KICAgIDxwPlVzZXI6IHt7IHZhcjogJGRhdGFbJ3VzZXInXVsnbmFtZSddIH19PC9wPgo8L2Rpdj4KYGBgCgoqKlN0YW5kYXJkIGV4YW1wbGUgKHVzaW5nICJ2YXJpYWJsZXMiKToqKgoKYGBgcGhwCi8vIFZhcmlhYmxlcyBmb3IgVklFVyAoaGVhZGVyLCBzaWRlYmFyLCBmb290ZXIsIGV0Yy4pCiR2aWV3VmFycyA9IFsnbWV0YScgPT4gWyd0aXRsZScgPT4gJ1BhZ2UgVGl0bGUnXSwgJ2FjdGl2ZVBhZ2UnID0+ICdpbmRleCddOwokcmVuZGVyZXItPnNldFZpZXdWYXIoInZhcmlhYmxlcyIsICR2aWV3VmFycyk7CgovLyBWYXJpYWJsZXMgZm9yIExBWU9VVCAoY29udGVudCBhcmVhKQokbGF5b3V0VmFycyA9IFsncGFnZVRpdGxlJyA9PiAnV2VsY29tZScsICdjb250ZW50JyA9PiAnLi4uJ107CiRyZW5kZXJlci0+c2V0TGF5b3V0VmFyKCJ2YXJpYWJsZXMiLCAkbGF5b3V0VmFycyk7CmBgYAoKKipJbiBWSUVXKiogKGBkb2NzLnZpZXcucGhwYCk6CmBgYGh0bWwKPHRpdGxlPnt7IGlmIGlzc2V0KCR2YXJpYWJsZXNbJ21ldGEnXVsndGl0bGUnXSkgfX17eyB2YXI6ICR2YXJpYWJsZXNbJ21ldGEnXVsndGl0bGUnXSB9fXt7IGVsc2UgfX1EZWZhdWx0IFRpdGxle3sgL2lmIH19PC90aXRsZT4KYGBgCgoqKkluIExBWU9VVCoqIChgZG9jcy9pbmRleC5sYXlvdXQucGhwYCk6CmBgYGh0bWwKPGgxPnt7IHZhcjogJHZhcmlhYmxlc1sncGFnZVRpdGxlJ10gfX08L2gxPgpgYGAKCioqQmVzdCBQcmFjdGljZSoqOiBVc2UgZGVzY3JpcHRpdmUgdmFyaWFibGUgbmFtZXMgdGhhdCBtYWtlIHNlbnNlIGluIHlvdXIgY29udGV4dC4gRm9yIGV4YW1wbGU6Ci0gYHNlb2AgZm9yIFNFTy1yZWxhdGVkIGRhdGEKLSBgcGFnZWAgZm9yIHBhZ2Utc3BlY2lmaWMgZGF0YQotIGB1c2VyYCBmb3IgdXNlciBkYXRhCi0gYGRhdGFgIGZvciBnZW5lcmFsIGRhdGEKLSBgdmFyaWFibGVzYCBmb3IgbWl4ZWQgZGF0YSAoaWYgeW91IHByZWZlciBhIGdlbmVyaWMgbmFtZSkKCiMjIEFzc2V0cyBTeXN0ZW0KCiMjIyBPdmVydmlldwoKQXNzZXRzIChDU1MsIEphdmFTY3JpcHQsIGltYWdlcywgZm9udHMpIGFyZSBzdG9yZWQgaW4gdGhlIG1vZHVsZSdzIGBhc3NldHMvYCBkaXJlY3RvcnkgYW5kIGFyZSBwdWJsaWNseSBhY2Nlc3NpYmxlIHZpYSBVUkwgcm91dGluZy4KCiMjIyBEaXJlY3RvcnkgU3RydWN0dXJlCgpgYGAKYXBwL21vZHVsZXMvTW9kdWxlTmFtZS8K4pSc4pSA4pSAIGFzc2V0cy8K4pSCICAg4pSc4pSA4pSAIGNzcy8K4pSCICAg4pSCICAg4pSU4pSA4pSAIHN0eWxlcy5jc3MK4pSCICAg4pSc4pSA4pSAIGpzLwrilIIgICDilIIgICDilJTilIDilIAgc2NyaXB0LmpzCuKUgiAgIOKUnOKUgOKUgCBpbWFnZXMvCuKUgiAgIOKUgiAgIOKUlOKUgOKUgCBsb2dvLnBuZwrilIIgICDilJTilIDilIAgZm9udHMvCuKUgiAgICAgICDilJTilIDilIAgZm9udC53b2ZmMgpgYGAKCiMjIyBVUkwgQWNjZXNzCgpBc3NldHMgYXJlIGFjY2Vzc2libGUgdmlhIHRoZSBmb2xsb3dpbmcgVVJMIHBhdHRlcm46CgpgYGAKL2Fzc2V0cy9tb2R1bGVzL01vZHVsZU5hbWUvcGF0aC90by9maWxlCmBgYAoKKipIb3cgaXQgd29ya3M6KioKMS4gUmVxdWVzdCBjb21lcyB0byBgL2Fzc2V0cy9tb2R1bGVzL1BoYXJtTGlzdC9jc3MvZG9jcy5jc3NgCjIuIC5odGFjY2VzcyByZXdyaXRlcyBpdCB0byBgL2FwcC9tb2R1bGVzL1BoYXJtTGlzdC9hc3NldHMvY3NzL2RvY3MuY3NzYAozLiBGaWxlIGlzIHNlcnZlZCBkaXJlY3RseSAoaWYgaXQgZXhpc3RzKQoKIyMjIFVzYWdlIGluIFRlbXBsYXRlcwoKKipDU1MgZmlsZXM6KioKYGBgaHRtbAo8bGluayByZWw9InN0eWxlc2hlZXQiIGhyZWY9Ii9hc3NldHMvbW9kdWxlcy9QaGFybUxpc3QvY3NzL2RvY3MuY3NzIj4KYGBgCgoqKkphdmFTY3JpcHQgZmlsZXM6KioKYGBgaHRtbAo8c2NyaXB0IHNyYz0iL2Fzc2V0cy9tb2R1bGVzL1BoYXJtTGlzdC9qcy9hcHAuanMiPjwvc2NyaXB0PgpgYGAKCioqSW1hZ2VzOioqCmBgYGh0bWwKPGltZyBzcmM9Ii9hc3NldHMvbW9kdWxlcy9QaGFybUxpc3QvaW1hZ2VzL2xvZ28ucG5nIiBhbHQ9IkxvZ28iPgpgYGAKCioqSW4gQ1NTIChmb250IGZpbGVzKToqKgpgYGBjc3MKQGZvbnQtZmFjZSB7CiAgICBmb250LWZhbWlseTogJ015Rm9udCc7CiAgICBzcmM6IHVybCgnL2Fzc2V0cy9tb2R1bGVzL1BoYXJtTGlzdC9mb250cy9mb250LndvZmYyJykgZm9ybWF0KCd3b2ZmMicpOwp9CmBgYAoKIyMjIEJlc3QgUHJhY3RpY2VzCgoxLiAqKkV4dHJhY3QgQ1NTIGZyb20gaW5saW5lIHN0eWxlcyoqIC0gTmV2ZXIgcHV0IENTUyBkaXJlY3RseSBpbiBWSUVXIGZpbGVzLiBBbHdheXMgY3JlYXRlIHNlcGFyYXRlIENTUyBmaWxlcyBpbiBgYXNzZXRzL2Nzcy9gCjIuICoqT3JnYW5pemUgYnkgdHlwZSoqIC0gVXNlIHN1YmRpcmVjdG9yaWVzOiBgY3NzL2AsIGBqcy9gLCBgaW1hZ2VzL2AsIGBmb250cy9gCjMuICoqVXNlIHJlbGF0aXZlIHBhdGhzIGluIENTUyoqIC0gV2hlbiByZWZlcmVuY2luZyBvdGhlciBhc3NldHMgaW4gQ1NTLCB1c2UgdGhlIGZ1bGwgVVJMIHBhdGggc3RhcnRpbmcgd2l0aCBgL2Fzc2V0cy9tb2R1bGVzL2AKCiMjIyBFeGFtcGxlOiBNb3ZpbmcgQ1NTIGZyb20gVklFVyB0byBBc3NldHMKCioqQmVmb3JlIChJTkNPUlJFQ1QgLSBpbmxpbmUgQ1NTIGluIFZJRVcpOioqCmBgYGh0bWwKPCEtLSBkb2NzLnZpZXcucGhwIC0tPgo8aGVhZD4KICAgIDxzdHlsZT4KICAgICAgICBib2R5IHsgY29sb3I6IHJlZDsgfQogICAgPC9zdHlsZT4KPC9oZWFkPgpgYGAKCioqQWZ0ZXIgKENPUlJFQ1QgLSBleHRlcm5hbCBDU1MgZmlsZSk6KioKYGBgaHRtbAo8IS0tIGRvY3Mudmlldy5waHAgLS0+CjxoZWFkPgogICAgPGxpbmsgcmVsPSJzdHlsZXNoZWV0IiBocmVmPSIvYXNzZXRzL21vZHVsZXMvUGhhcm1MaXN0L2Nzcy9kb2NzLmNzcyI+CjwvaGVhZD4KYGBgCgpgYGBjc3MKLyogYXNzZXRzL2Nzcy9kb2NzLmNzcyAqLwpib2R5IHsgY29sb3I6IHJlZDsgfQpgYGAKCiMjIEZpbGUgU3RydWN0dXJlCgojIyMgU3RhbmRhcmQgTW9kdWxlIFN0cnVjdHVyZQoKYGBgCmFwcC9tb2R1bGVzL01vZHVsZU5hbWUvCuKUnOKUgOKUgCBhc3NldHMvCuKUgiAgIOKUnOKUgOKUgCBjc3MvCuKUgiAgIOKUgiAgIOKUlOKUgOKUgCBzdHlsZXMuY3NzCuKUgiAgIOKUnOKUgOKUgCBqcy8K4pSCICAg4pSCICAg4pSU4pSA4pSAIHNjcmlwdC5qcwrilIIgICDilJTilIDilIAgaW1hZ2VzLwrilJzilIDilIAgdmlld3MvCuKUgiAgIOKUnOKUgOKUgCBtYWluLnZpZXcucGhwICAgICAgICAgICAgICAjIFZJRVcgLSBtYWluIHN0cnVjdHVyZQrilIIgICDilJzilIDilIAgbGF5b3V0cy8K4pSCICAg4pSCICAg4pSc4pSA4pSAIGluZGV4LmxheW91dC5waHAgICAgICAgIyBMQVlPVVQgLSBob21lIHBhZ2UK4pSCICAg4pSCICAg4pSc4pSA4pSAIHNlY3Rpb24vCuKUgiAgIOKUgiAgIOKUgiAgIOKUlOKUgOKUgCB3cmFwcGVyLmxheW91dC5waHAgIyBOZXN0ZWQgbGF5b3V0CuKUgiAgIOKUgiAgIOKUlOKUgOKUgCBvdGhlci5sYXlvdXQucGhwCuKUgiAgIOKUlOKUgOKUgCBwYXJ0aWFscy8gICAgICAgICAgICAgICAgICAjIE9wdGlvbmFsIC0gcGFydGlhbHMgdXNpbmcge3sgaW5jbHVkZSB9fQrilIIgICAgICAg4pSU4pSA4pSAIGhlYWRlci52aWV3LnBocArilJTilIDilIAgQ29udHJvbGxlcnMvCiAgICDilJTilIDilIAgQ29udHJvbGxlci5waHAKYGBgCgojIyMgRmlsZSBQYXRocwoKLSAqKlZJRVcqKjogYGFwcC9tb2R1bGVzL01vZHVsZU5hbWUvdmlld3Mvdmlld05hbWUudmlldy5waHBgCi0gKipMQVlPVVQqKjogYGFwcC9tb2R1bGVzL01vZHVsZU5hbWUvdmlld3MvbGF5b3V0cy9sYXlvdXRQYXRoLmxheW91dC5waHBgCi0gKipCQVNFIExBWU9VVCoqOiBgYXBwL3BhcnRzL3ZpZXdzL2xheW91dHMvbGF5b3V0UGF0aC5sYXlvdXQucGhwYAotICoqQVNTRVRTKio6IGBhcHAvbW9kdWxlcy9Nb2R1bGVOYW1lL2Fzc2V0cy9wYXRoL3RvL2ZpbGVgIChhY2Nlc3NpYmxlIHZpYSBgL2Fzc2V0cy9tb2R1bGVzL01vZHVsZU5hbWUvcGF0aC90by9maWxlYCkKCiMjIEJlc3QgUHJhY3RpY2VzCgojIyMgMS4gVklFVyBzaG91bGQgY29udGFpbgotIEhUTUw1IHN0cnVjdHVyZSAoRE9DVFlQRSwgaHRtbCwgaGVhZCwgYm9keSkKLSBNZXRhIHRhZ3MKLSBMaW5rcyB0byBDU1MgZmlsZXMgKE5PVCBpbmxpbmUgc3R5bGVzKSAtIHVzZSBgL2Fzc2V0cy9tb2R1bGVzL01vZHVsZU5hbWUvY3NzL2ZpbGUuY3NzYAotIExpbmtzIHRvIEphdmFTY3JpcHQgZmlsZXMgKE5PVCBpbmxpbmUgc2NyaXB0cykgLSB1c2UgYC9hc3NldHMvbW9kdWxlcy9Nb2R1bGVOYW1lL2pzL2ZpbGUuanNgCi0gSGVhZGVyLCBmb290ZXIsIG5hdmlnYXRpb24KLSBge3sgY29udGVudCB9fWAgcGxhY2Vob2xkZXIKCioqSU1QT1JUQU5UKio6IE5ldmVyIHB1dCBDU1Mgb3IgSmF2YVNjcmlwdCBkaXJlY3RseSBpbiBWSUVXIGZpbGVzLiBBbHdheXMgZXh0cmFjdCB0aGVtIHRvIHNlcGFyYXRlIGZpbGVzIGluIHRoZSBgYXNzZXRzL2AgZGlyZWN0b3J5LgoKIyMjIDIuIExBWU9VVCBzaG91bGQgY29udGFpbgotIE9ubHkgY29udGVudCB3aXRob3V0IHdyYXBwZXIKLSBDYW4gbmVzdCBhZGRpdGlvbmFsIGxheW91dHMgdXNpbmcgYHt7IGxheW91dDogLi4uIH19YAotIENhbiB1c2UgdmFyaWFibGVzIGZyb20gVmlld1ZhcnMKCiMjIyAzLiBOZXN0ZWQgbGF5b3V0cwotIFVzZSBmb3IgcmVwZWF0aW5nIHN0cnVjdHVyZXMgKGNhcmRzLCBzZWN0aW9ucywgd3JhcHBlcnMpCi0gRW5hYmxlIERSWSBwcmluY2lwbGUKLSBDYW4gYmUgc2hhcmVkIGJldHdlZW4gbW9kdWxlcyB1c2luZyBge3sgYmFzZWxheW91dDogLi4uIH19YAoKIyMjIDQuIENvcnJlY3QgU3RydWN0dXJlIEV4YW1wbGUKCioqVklFVyoqIChgZG9jcy52aWV3LnBocGApOgpgYGBodG1sCjwhRE9DVFlQRSBodG1sPgo8aHRtbD4KPGhlYWQ+CiAgICA8dGl0bGU+e3sgdmFyOiAkdmFyaWFibGVzWydtZXRhJ11bJ3RpdGxlJ10gfX08L3RpdGxlPgogICAgPGxpbmsgcmVsPSJzdHlsZXNoZWV0IiBocmVmPSIvYXNzZXRzL21vZHVsZXMvUGhhcm1MaXN0L2Nzcy9kb2NzLmNzcyI+CjwvaGVhZD4KPGJvZHk+CiAgICA8aGVhZGVyPkhlYWRlcjwvaGVhZGVyPgogICAgPGFzaWRlPlNpZGViYXI8L2FzaWRlPgogICAgPG1haW4+CiAgICAgICAge3sgY29udGVudCB9fSAgPCEtLSBMQVlPVVQgZ2V0cyBpbnNlcnRlZCBoZXJlIC0tPgogICAgPC9tYWluPgogICAgPGZvb3Rlcj5Gb290ZXI8L2Zvb3Rlcj4KPC9ib2R5Pgo8L2h0bWw+CmBgYAoKKipMQVlPVVQqKiAoYGRvY3MvaW5kZXgubGF5b3V0LnBocGApOgpgYGBodG1sCjxoMT5XZWxjb21lPC9oMT4KPHA+Q29udGVudC4uLjwvcD4KYGBgCgoqKk5lc3RlZCBMQVlPVVQqKiAoYGRvY3Mvc2VjdGlvbi5sYXlvdXQucGhwYCk6CmBgYGh0bWwKPHNlY3Rpb24gY2xhc3M9ImNvbnRlbnQtc2VjdGlvbiI+CiAgICB7eyBjb250ZW50IH19Cjwvc2VjdGlvbj4KYGBgCgojIyBDb21tb24gTWlzdGFrZXMKCiMjIyDinYwgSU5DT1JSRUNUCgpgYGBodG1sCjwhLS0gVklFVyB3aXRob3V0IHt7IGNvbnRlbnQgfX0gLS0+CjwhRE9DVFlQRSBodG1sPgo8aHRtbD4KPGJvZHk+CiAgICA8aDE+Q29udGVudDwvaDE+ICA8IS0tIE1pc3Npbmcge3sgY29udGVudCB9fSAtLT4KPC9ib2R5Pgo8L2h0bWw+CmBgYAoKYGBgaHRtbAo8IS0tIExBWU9VVCB3aXRoIEhUTUwgd3JhcHBlciAtLT4KPCFET0NUWVBFIGh0bWw+CjxodG1sPgo8Ym9keT4KICAgIDxoMT5Db250ZW50PC9oMT4KPC9ib2R5Pgo8L2h0bWw+CmBgYAoKIyMjIOKchSBDT1JSRUNUCgpgYGBodG1sCjwhLS0gVklFVyB3aXRoIHt7IGNvbnRlbnQgfX0gLS0+CjwhRE9DVFlQRSBodG1sPgo8aHRtbD4KPGJvZHk+CiAgICB7eyBjb250ZW50IH19ICA8IS0tIExBWU9VVCBnZXRzIGluc2VydGVkIGhlcmUgLS0+CjwvYm9keT4KPC9odG1sPgpgYGAKCmBgYGh0bWwKPCEtLSBMQVlPVVQgd2l0aCBjb250ZW50IG9ubHkgLS0+CjxoMT5Db250ZW50PC9oMT4KPHA+VGV4dC4uLjwvcD4KYGBgCgojIyBVc2FnZSBFeGFtcGxlcwoKIyMjIEV4YW1wbGUgMTogU2ltcGxlIFBhZ2UKCioqQ29udHJvbGxlcioqOgpgYGBwaHAKcHVibGljIHN0YXRpYyBmdW5jdGlvbiBpbmRleCgkcmVxdWVzdCwgUmVuZGVyZXIgJHJlbmRlcmVyKSB7CiAgICByZXR1cm4gJHJlbmRlcmVyLT5tb2R1bGUoIlBoYXJtTGlzdCIpCiAgICAgICAgLT5zZXRWaWV3KCJkb2NzIikKICAgICAgICAtPnNldExheW91dCgiZG9jcy9pbmRleCIpCiAgICAgICAgLT5yZW5kZXJWaWV3KCk7Cn0KYGBgCgojIyMgRXhhbXBsZSAyOiBXaXRoIFZhcmlhYmxlcwoKKipDb250cm9sbGVyKio6CmBgYHBocApwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIHNob3coJHJlcXVlc3QsIFJlbmRlcmVyICRyZW5kZXJlcikgewogICAgJHZpZXdWYXJzID0gWwogICAgICAgICdtZXRhJyA9PiBbJ3RpdGxlJyA9PiAnUHJvZHVjdCddLAogICAgICAgICdwcm9kdWN0JyA9PiBbJ25hbWUnID0+ICdNZWRpY2luZSBYWVonXQogICAgXTsKICAgIAogICAgcmV0dXJuICRyZW5kZXJlci0+bW9kdWxlKCJQaGFybUxpc3QiKQogICAgICAgIC0+c2V0VmlldygiZG9jcyIpCiAgICAgICAgLT5zZXRMYXlvdXQoImRvY3MvcHJvZHVjdCIpCiAgICAgICAgLT5zZXRWaWV3VmFyKCJ2YXJpYWJsZXMiLCAkdmlld1ZhcnMpCiAgICAgICAgLT5yZW5kZXJWaWV3KCk7Cn0KYGBgCgoqKkxBWU9VVCoqIChgZG9jcy9wcm9kdWN0LmxheW91dC5waHBgKToKYGBgaHRtbAo8aDE+e3sgdmFyOiAkdmFyaWFibGVzWydwcm9kdWN0J11bJ25hbWUnXSB9fTwvaDE+CmBgYAoKIyMjIEV4YW1wbGUgMzogV2l0aCBOZXN0ZWQgTGF5b3V0CgoqKkxBWU9VVCoqIChgZG9jcy9pbmRleC5sYXlvdXQucGhwYCk6CmBgYGh0bWwKe3sgbGF5b3V0OiBkb2NzL3NlY3Rpb24gfX0KCjxoMT5UaXRsZTwvaDE+CjxwPkNvbnRlbnQuLi48L3A+Cgp7eyAvbGF5b3V0OiBkb2NzL3NlY3Rpb24gfX0KYGBgCgoqKk5lc3RlZCBMQVlPVVQqKiAoYGRvY3Mvc2VjdGlvbi5sYXlvdXQucGhwYCk6CmBgYGh0bWwKPGFydGljbGUgY2xhc3M9InNlY3Rpb24iPgogICAge3sgY29udGVudCB9fQo8L2FydGljbGU+CmBgYAoKIyMjIEV4YW1wbGUgNDogTGF5b3V0IGZyb20gQW5vdGhlciBNb2R1bGUKCioqTEFZT1VUKio6CmBgYGh0bWwKe3sgbGF5b3V0OiBEb3RTaG9wOnByb2R1Y3QvY2FyZCB9fQoKPGgyPlByb2R1Y3QgTmFtZTwvaDI+Cgp7eyAvbGF5b3V0OiBEb3RTaG9wOnByb2R1Y3QvY2FyZCB9fQpgYGAKCiMjIFN1bW1hcnkKCiMjIyBDb3JlIENvbmNlcHRzCgoxLiAqKlZJRVcqKiA9IG1haW4gc3RydWN0dXJlIHdpdGggYHt7IGNvbnRlbnQgfX1gCjIuICoqTEFZT1VUKiogPSBjb250ZW50IHRoYXQgZ2V0cyBpbnNlcnRlZCBpbnRvIFZJRVcKMy4gYHt7IGxheW91dDogLi4uIH19YCA9IG5lc3QgbGF5b3V0IGZyb20gY3VycmVudCBtb2R1bGUKNC4gYHt7IGJhc2VsYXlvdXQ6IC4uLiB9fWAgPSBuZXN0IGxheW91dCBmcm9tIGJhc2UgZGlyZWN0b3J5CjUuIGBNb2R1bGVOYW1lOnBhdGhgID0gY2FsbCBsYXlvdXQgZnJvbSBhbm90aGVyIG1vZHVsZQo2LiAqKlZpZXdWYXJzIGFyZSBhdmFpbGFibGUgT05MWSBpbiBWSUVXKiogKHVzZSBgc2V0Vmlld1ZhcigpYCkKNy4gKipMYXlvdXRWYXJzIGFyZSBhdmFpbGFibGUgT05MWSBpbiBMQVlPVVQqKiAodXNlIGBzZXRMYXlvdXRWYXIoKWApCjguICoqVmFyaWFibGUgbmFtZXMgY2FuIGJlIGN1c3RvbSoqIC0gdXNlIGRlc2NyaXB0aXZlIG5hbWVzIGxpa2UgYHNlb2AsIGBwYWdlYCwgYGRhdGFgIGluc3RlYWQgb2YganVzdCBgdmFyaWFibGVzYAo5LiAqKkFzc2V0cyoqID0gQ1NTLCBKUywgaW1hZ2VzIHN0b3JlZCBpbiBgYXNzZXRzL2AgZGlyZWN0b3J5LCBhY2Nlc3NpYmxlIHZpYSBgL2Fzc2V0cy9tb2R1bGVzL01vZHVsZU5hbWUvcGF0aGAKCiMjIyBTdXBwb3J0ZWQgVGVtcGxhdGUgRnVuY3Rpb25zCgoxLiAqKlZhcmlhYmxlcyoqOiBge3sgdmFyOiAkdmFyaWFibGVOYW1lIH19YAoyLiAqKlRyYW5zbGF0aW9ucyoqOiBge3tfIHZhcjogJHZhcmlhYmxlIH19YCwgYHt7XyAidGV4dCIgfX1gCjMuICoqQ29uZGl0aW9ucyoqOiBge3sgaWYgfX1gLCBge3sgZWxzZWlmIH19YCwgYHt7IGVsc2UgfX1gLCBge3sgL2lmIH19YAo0LiAqKkxvb3BzKio6IGB7eyBmb3JlYWNoIH19YCwgYHt7IC9mb3JlYWNoIH19YCwgYHt7IHdoaWxlIH19YCwgYHt7IC93aGlsZSB9fWAKNS4gKipFbmNyeXB0aW9uKio6IGB7eyBlbmM6IH19YCwgYHt7IGVuYyhrZXkpOiB9fWAKNi4gKipDU1JGKio6IGB7eyBDU1JGIH19YAo3LiAqKkZvcm0gU2VjdXJpdHkqKjogYHt7IGZvcm1OYW1lKG5hbWUpIH19YAo4LiAqKkN1c3RvbSBCbG9ja3MqKjogYHt7IGJsb2NrOm5hbWUgfX1gLCBge3sgL2Jsb2NrOm5hbWUgfX1gCjkuICoqUHJpdmF0ZSBCbG9ja3MqKjogYHt7IHByaXZhdGVibG9jazpuYW1lIH19YCwgYHt7IC9wcml2YXRlYmxvY2sgfX1gCjEwLiAqKkxheW91dCBOZXN0aW5nKio6IGB7eyBsYXlvdXQ6IH19YCwgYHt7IGJhc2VsYXlvdXQ6IH19YAoKIyMjIEltcG9ydGFudCBOb3RlcwoKLSBJbiBEb3RBcHAsIFZJRVcgaXMgdGhlIHdyYXBwZXIsIExBWU9VVCBpcyB0aGUgY29udGVudCEKLSBWaWV3VmFycyBhbmQgTGF5b3V0VmFycyBhcmUgc2VwYXJhdGUgLSBpZiB5b3UgbmVlZCB2YXJpYWJsZXMgaW4gYm90aCwgc2V0IHRoZW0gc2VwYXJhdGVseSEKLSAqKk5ldmVyIHB1dCBDU1Mgb3IgSmF2YVNjcmlwdCBpbmxpbmUgaW4gVklFVyBmaWxlcyoqIC0gYWx3YXlzIGV4dHJhY3QgdG8gYGFzc2V0cy9jc3MvYCBhbmQgYGFzc2V0cy9qcy9gIGRpcmVjdG9yaWVzIQotIEFzc2V0cyBhcmUgYWNjZXNzaWJsZSB2aWEgYC9hc3NldHMvbW9kdWxlcy9Nb2R1bGVOYW1lL3BhdGgvdG8vZmlsZWAgVVJMIHBhdHRlcm4KLSBge3sgdmFyOiB9fWAgZG9lcyBOT1Qgc3VwcG9ydCBgPz9gIG9wZXJhdG9yIG9yIHRlcm5hcnkgb3BlcmF0b3JzIC0gdXNlIGB7eyBpZiB9fWAgY29uZGl0aW9ucyBpbnN0ZWFkIQoK";
        if ($filename=="/.htaccess") return "IyBOYXN0YXZlbmllIGtvZG92YW5pYSBhIGphenlrYQpBZGREZWZhdWx0Q2hhcnNldCBVVEYtOApEZWZhdWx0TGFuZ3VhZ2Ugc2sKCiMgUHJpZGF0IGhsYXZpY2t5IHByZSBkb3RhcHAKPElmTW9kdWxlIG1vZF9oZWFkZXJzLmM+CiAgICBIZWFkZXIgYWx3YXlzIHNldCBYLVBvd2VyZWQtQnkgImRvdGFwcDsgd3d3LmRvdHN5c3RlbXMuc2siCiAgICBIZWFkZXIgYWx3YXlzIHNldCBYLUZyYW1ld29yayAiZG90YXBwIgo8L0lmTW9kdWxlPgoKIyBLb21wcmVzaWEgc3Vib3JvdiAtIG1vZF9kZWZsYXRlIChub3ZzaSBzcG9zb2IpCjxJZk1vZHVsZSBtb2RfZGVmbGF0ZS5jPgogICAgU2V0T3V0cHV0RmlsdGVyIERFRkxBVEUKICAgIEFkZE91dHB1dEZpbHRlckJ5VHlwZSBERUZMQVRFIHRleHQvaHRtbCB0ZXh0L3BsYWluIHRleHQveG1sIHRleHQvY3NzIHRleHQvamF2YXNjcmlwdAogICAgQWRkT3V0cHV0RmlsdGVyQnlUeXBlIERFRkxBVEUgYXBwbGljYXRpb24vamF2YXNjcmlwdCBhcHBsaWNhdGlvbi94LWphdmFzY3JpcHQKICAgIEJyb3dzZXJNYXRjaCBeTW96aWxsYS80IGd6aXAtb25seS10ZXh0L2h0bWwKICAgIEJyb3dzZXJNYXRjaCBeTW96aWxsYS80XC4wWzY3OF0gbm8tZ3ppcAogICAgQnJvd3Nlck1hdGNoIFxiTVNJRSAhbm8tZ3ppcCAhZ3ppcC1vbmx5LXRleHQvaHRtbAo8L0lmTW9kdWxlPgoKIyBLb21wcmVzaWEgc3Vib3JvdiAtIG1vZF9nemlwIChzdGFyc2lhIHZlcnppYSBhayBuZW5pIGRlZmxhdGUpCjxJZk1vZHVsZSAhbW9kX2RlZmxhdGUuYz4KICAgIDxJZk1vZHVsZSBtb2RfZ3ppcC5jPgogICAgICAgIG1vZF9nemlwX29uIFllcwogICAgICAgIG1vZF9nemlwX2RlY2h1bmsgWWVzCiAgICAgICAgbW9kX2d6aXBfaXRlbV9pbmNsdWRlIGZpbGUgXC4oaHRtbD98dHh0fGNzc3xqc3xwaHB8cGwpJAogICAgICAgIG1vZF9nemlwX2l0ZW1faW5jbHVkZSBoYW5kbGVyIF5jZ2ktc2NyaXB0JAogICAgICAgIG1vZF9nemlwX2l0ZW1faW5jbHVkZSBtaW1lIF50ZXh0Ly4qCiAgICAgICAgbW9kX2d6aXBfaXRlbV9pbmNsdWRlIG1pbWUgXmFwcGxpY2F0aW9uL3gtamF2YXNjcmlwdC4qCiAgICAgICAgbW9kX2d6aXBfaXRlbV9leGNsdWRlIG1pbWUgXmltYWdlLy4qCiAgICAgICAgbW9kX2d6aXBfaXRlbV9leGNsdWRlIHJzcGhlYWRlciBeQ29udGVudC1FbmNvZGluZzouKmd6aXAuKgogICAgPC9JZk1vZHVsZT4KPC9JZk1vZHVsZT4KCiMgUG92b2xpdCBwcmlzdHUga3UgdnNldGtlbXUgLSBub3ZzaSBhcGFjaGUKPElmTW9kdWxlIG1vZF9hdXRoel9ob3N0LmM+CiAgICBSZXF1aXJlIGFsbCBncmFudGVkCjwvSWZNb2R1bGU+CgojIFBvdm9saXQgcHJpc3R1IC0gc3RhcnNpIGFwYWNoZQo8SWZNb2R1bGUgIW1vZF9hdXRoel9ob3N0LmM+CiAgICBPcmRlciBBbGxvdyxEZW55CiAgICBBbGxvdyBmcm9tIGFsbAo8L0lmTW9kdWxlPgoKIyBOYXN0YXZlbmllIHR5cG92IHN1Ym9yb3YKQWRkVHlwZSBmb250L3dvZmYgLndvZmYKQWRkVHlwZSBhcHBsaWNhdGlvbi9mb250LXdvZmYyIC53b2ZmMgpBZGRUeXBlIGFwcGxpY2F0aW9uL2phdmFzY3JpcHQgLmpzCkFkZFR5cGUgdGV4dC9jc3MgLmNzcwoKIyBaYXBudXQgcHJlcGlzb3ZhbmllIHVybApSZXdyaXRlRW5naW5lIE9uClJld3JpdGVCYXNlIC8KCiMgWmFibG9rb3ZhdCBwcmlzdHUgayBkb3RhcHBlcnUKUmV3cml0ZUNvbmQgJXtSRVFVRVNUX1VSSX0gXi9kb3RhcHBlciQKUmV3cml0ZVJ1bGUgXiAtIFtGLExdCgojIFByZXNrb2NpdCBwcmVwaXMgcHJlIHNwZWNpZmlja2Ugc3Vib3J5ClJld3JpdGVSdWxlIF4oc2l0ZW1hcFwueG1sfHJvYm90c1wudHh0KSQgLSBbTkMsTF0KCiMgWmFibG9rb3ZhdCAvYXBwLyBva3JlbSBhc3NldHMgdiBtb2R1bG9jaApSZXdyaXRlQ29uZCAle1JFUVVFU1RfVVJJfSAhXi9hcHAvbW9kdWxlcy8oW14vXSspL2Fzc2V0cy8KUmV3cml0ZUNvbmQgJXtSRVFVRVNUX1VSSX0gIV4vYXBwL3BhcnRzL2pzLwpSZXdyaXRlUnVsZSBeYXBwKC98JCkgLSBbRixMXQoKIyA9PT0gQVNTRVRTIFNQUkFDT1ZBTklFID09PQoKIyBFeHBsaWNpdG5lIG1hcG92YW5pZSBwcmUgcmVhY3RpdmUgYSB0ZW1wbGF0ZSBjYXN0aSAocHJpdmF0ZSAtPiBwdWJsaWMpClJld3JpdGVSdWxlIF5hc3NldHMvZG90YXBwL2RvdGFwcFwucmVhY3RpdmVcLmpzJCAvYXBwL3BhcnRzL2pzL2RvdGFwcC5yZWFjdGl2ZS5qcyBbTkMsTF0KUmV3cml0ZVJ1bGUgXmFzc2V0cy9kb3RhcHAvZG90YXBwXC50ZW1wbGF0ZVwuanMkIC9hcHAvcGFydHMvanMvZG90YXBwLnRlbXBsYXRlLmpzIFtOQyxMXQoKIyBBayBzdWJvciB2IC9hc3NldHMvbW9kdWxlcy8gbmVleGlzdHVqZSwgc2t1cyBobyBuYWNpdGF0IHogL2FwcC9tb2R1bGVzLwpSZXdyaXRlQ29uZCAle1JFUVVFU1RfRklMRU5BTUV9ICEtZgpSZXdyaXRlQ29uZCAle1JFUVVFU1RfRklMRU5BTUV9ICEtZApSZXdyaXRlUnVsZSBeYXNzZXRzL21vZHVsZXMvKFteL10rKS8oLiopJCAvYXBwL21vZHVsZXMvJDEvYXNzZXRzLyQyIFtMXQoKIyBTcGVjaWFsbmUgc3ByYWNvdmFuaWUgbGVuIHByZSBkb3RhcHAuanMgKHByZXNtZXJvdmFuaWUgbmEgaW5kZXgucGhwKQpSZXdyaXRlQ29uZCAle1JFUVVFU1RfRklMRU5BTUV9ICEtZgpSZXdyaXRlUnVsZSBeYXNzZXRzL2RvdGFwcC9kb3RhcHBcLmpzJCBpbmRleC5waHAgW05DLExdCgojIEFrIG9zdGF0bsOpIHPDumJvcnkgdiAvYXNzZXRzL2RvdGFwcC8gbmVleGlzdHVqw7ogKG9rcmVtIGRvdGFwcC5qcyksIHNrw7pzIGljaCBuYcSNw610YcWlIHogL2FwcC9wYXJ0cy9qcy8KUmV3cml0ZUNvbmQgJXtSRVFVRVNUX0ZJTEVOQU1FfSAhLWYKUmV3cml0ZUNvbmQgJXtSRVFVRVNUX0ZJTEVOQU1FfSAhLWQKUmV3cml0ZUNvbmQgJXtSRVFVRVNUX1VSSX0gIV4vYXNzZXRzL2RvdGFwcC9kb3RhcHBcLmpzJApSZXdyaXRlUnVsZSBeYXNzZXRzL2RvdGFwcC8oLitcLmpzKSQgL2FwcC9wYXJ0cy9qcy8kMSBbTkMsTF0KCiMgQWsgc3Vib3IgdiAvYXNzZXRzLyBleGlzdHVqZSwgbmVwcmVwaXN1agpSZXdyaXRlQ29uZCAle1JFUVVFU1RfRklMRU5BTUV9IC1mClJld3JpdGVSdWxlIF5hc3NldHMvLiokIC0gW05DLExdCgojID09PSBLT05JRUMgQVNTRVRTIFNQUkFDT1ZBTklBID09PQoKIyBOZXByZXBpc292YXQgb2JyYXpreQpSZXdyaXRlUnVsZSBcLihpY298cG5nfGpwZT9nfGdpZnxzdmd8d2VicHxibXApJCAtIFtOQyxMXQoKIyBWc2V0a3kgb3N0YXRuZSBwb3ppYWRhdmt5IGlkdSBuYSBpbmRleC5waHAsIG9rcmVtIHNwZWNpZmlja3ljaCB2eW5pbWllawpSZXdyaXRlQ29uZCAle1JFUVVFU1RfVVJJfSAhXi9kb3RhcHBlciQKUmV3cml0ZUNvbmQgJXtSRVFVRVNUX1VSSX0gIV4vYXBwL21vZHVsZXMvKFteL10rKS9hc3NldHMvClJld3JpdGVDb25kICV7UkVRVUVTVF9VUkl9ICFeL2FwcC9wYXJ0cy9qcy8KUmV3cml0ZUNvbmQgJXtSRVFVRVNUX1VSSX0gIV4vYXNzZXRzLwpSZXdyaXRlUnVsZSBeLiokIGluZGV4LnBocCBbTkMsTF0=";
        if ($filename=="/module.init2.php") return "PD9waHAKCW5hbWVzcGFjZSBEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXCNtb2R1bGVuYW1lOwogICAgdXNlIFxEb3RzeXN0ZW1zXEFwcFxEb3RBcHA7CiAgICB1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXE1pZGRsZXdhcmU7Cgl1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXFJlc3BvbnNlOwoKCWNsYXNzIE1vZHVsZSBleHRlbmRzIFxEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xNb2R1bGUgewoJCQoJCXB1YmxpYyBmdW5jdGlvbiBpbml0aWFsaXplKCRkb3RBcHApIHsKCQkJLyoKCQkJCURlZmluZSB5b3VyIHJvdXRlcywgQVBJIHBvaW50cywgYW5kIHNpbWlsYXIgY29uZmlndXJhdGlvbnMgaGVyZS4gRXhhbXBsZXM6CgoJCQkJJGRvdEFwcC0+cm91dGVyLT5hcGlQb2ludCgiMSIsICIjbW9kdWxlbmFtZWxvd2VyIiwgIkFwaVxBcGlAYXBpRGlzcGF0Y2giKTsgLy8gQXV0b21hdGljIGRpc3BhdGNoZXIsIHNlZSBkZXRhaWxzIGluIEFwaS9BcGkucGhwCgoJCQkJRXhhbXBsZSB3aXRob3V0IGF1dG9tYXRpYyBkaXNwYXRjaGluZzoKCQkJCSRkb3RBcHAtPnJvdXRlci0+YXBpUG9pbnQoIjEiLCAiI21vZHVsZW5hbWVsb3dlci91c2VycyIsICJBcGlcVXNlcnNAYXBpIik7CgkJCQlUaGlzIGNhbGxzIHRoZSBgYXBpYCBtZXRob2QgaW4gdGhlIGBVc2Vyc2AgY2xhc3MgbG9jYXRlZCBpbiB0aGUgYEFwaWAgZm9sZGVyLgoJCQkJCgkJCQlFeGFtcGxlIG9mIGNhbGxpbmcgY29udHJvbGxlcnM6CgkJCQkkZG90QXBwLT5yb3V0ZXItPmdldCgiLyNtb2R1bGVuYW1lbG93ZXIvaG9tZSIsICJDb250cm9sbGVyQGhvbWUiKTsKCgkJCQlFeGFtcGxlIHVzaW5nIHRoZSBicmlkZ2U6CgkJCQkkZG90QXBwLT5icmlkZ2UtPmZuKCJuZXdzbGV0dGVyIiwgIkNvbnRyb2xsZXJAbmV3c2xldHRlciIpOwoJCQkqLwoJCQkKICAgICAgICAgICAgLy8gQWRkIHlvdXIgcm91dGVzIGFuZCBsb2dpYyBoZXJlCgkJCQoJCX0KCQkKCQkvKgoJCQlUaGlzIGZ1bmN0aW9uIGRlZmluZXMgdGhlIHNwZWNpZmljIFVSTCByb3V0ZXMgd2hlcmUgdGhlIG1vZHVsZSBzaG91bGQgYmUgaW5pdGlhbGl6ZWQuCgoJCQnwn5SnIEhvdyBpdCB3b3JrczoKCQkJLSBSZXR1cm4gYW4gYXJyYXkgb2Ygcm91dGVzIChlLmcuLCBbIi9ibG9nLyoiLCAiL25ld3Mve2lkOml9Il0pIHRvIHNwZWNpZnkgd2hlcmUgdGhlIG1vZHVsZSBzaG91bGQgaW5pdGlhbGl6ZS4KCQkJLSBSZXR1cm4gWycqJ10gdG8gYWx3YXlzIGluaXRpYWxpemUgdGhlIG1vZHVsZSBvbiBldmVyeSByb3V0ZSAobm90IHJlY29tbWVuZGVkIGluIGxhcmdlIGFwcHMpLgoKCQkJVGhpcyByb3V0aW5nIGxvZ2ljIGlzIHVzZWQgaW50ZXJuYWxseSBieSB0aGUgRG90YXBwZXIgYXV0by1pbml0aWFsaXphdGlvbiBzeXN0ZW0KCQkJdGhyb3VnaCB0aGUgYGF1dG9Jbml0aWFsaXplQ29uZGl0aW9uKClgIG1ldGhvZC4KCgkJCeKchSBSZWNvbW1lbmRlZDogSWYgeW91IGFyZSB1c2luZyBhIGxhcmdlIG51bWJlciBvZiBtb2R1bGVzIGFuZCB3YW50IHRvIG9wdGltaXplIHBlcmZvcm1hbmNlLCAKCQkJYWx3YXlzIHJldHVybiBhbiBhcnJheSBvZiByZWxldmFudCByb3V0ZXMgdG8gYWxsb3cgbG9hZGVyIG9wdGltaXphdGlvbi4KCgkJCUV4YW1wbGU6CgkJCQlyZXR1cm4gWyIvYWRtaW4vKiIsICIvZGFzaGJvYXJkIl07CgoJCQnimqDvuI8gSW1wb3J0YW50OiBJZiB5b3UgYWRkLCByZW1vdmUsIG9yIGNoYW5nZSBhbnkgbW9kdWxlcyBvciB0aGVpciByb3V0ZXMsIGFsd2F5cyByZWdlbmVyYXRlIHRoZSBvcHRpbWl6ZWQgbG9hZGVyOgoJCQkJQ29tbWFuZDogcGhwIGRvdGFwcGVyLnBocCAtLW9wdGltaXplLW1vZHVsZXMKCQkqLwoJCXB1YmxpYyBmdW5jdGlvbiBpbml0aWFsaXplUm91dGVzKCkgewoJCQlyZXR1cm4gWycvZG9jdW1lbnRhdGlvbi9pbnRyby8jbW9kdWxlbnVtYmVyJ107IC8vIEFsd2F5cyBtYXRjaGVzIGFsbCBVUkxzLCBidXQgbGVzcyBlZmZpY2llbnQKCQl9CgoJCS8qCgkJCVRoaXMgZnVuY3Rpb24gZGVmaW5lcyBhZGRpdGlvbmFsIGNvbmRpdGlvbnMgZm9yIHdoZXRoZXIgdGhlIG1vZHVsZSBzaG91bGQgaW5pdGlhbGl6ZSwKCQkJKiphZnRlcioqIHJvdXRlIG1hdGNoaW5nIGhhcyBhbHJlYWR5IHN1Y2NlZWRlZC4KCgkJCSRyb3V0ZU1hdGNoIOKAkyBib29sZWFuOiBUUlVFIGlmIHRoZSBjdXJyZW50IHJvdXRlIG1hdGNoZWQgb25lIG9mIHRob3NlIHJldHVybmVkIGZyb20gaW5pdGlhbGl6ZVJvdXRlcygpLCBGQUxTRSBvdGhlcndpc2UuCgoJCQlSZXR1cm4gdmFsdWVzOgoJCQktIHRydWU6IE1vZHVsZSB3aWxsIGJlIGluaXRpYWxpemVkLgoJCQktIGZhbHNlOiBNb2R1bGUgd2lsbCBub3QgYmUgaW5pdGlhbGl6ZWQuCgoJCQlVc2UgdGhpcyBpZiB5b3Ugd2FudCB0byBjaGVjayBsb2dpbiBzdGF0ZSwgdXNlciByb2xlcywgb3IgYW55IG90aGVyIGR5bmFtaWMgY29uZGl0aW9ucy4KCQkJRG8gTk9UIHJldHVybiBhbiBhcnJheSBvciBhbnl0aGluZyBvdGhlciB0aGFuIGEgYm9vbGVhbi4KCgkJCUV4YW1wbGU6CgkJCQlpZiAoISR0aGlzLT5kb3RBcHAtPmF1dGgtPmlzTG9nZ2VkSW4oKSkgcmV0dXJuIGZhbHNlOwoJCQkJcmV0dXJuIHRydWU7CgkJKi8KCQlwdWJsaWMgZnVuY3Rpb24gaW5pdGlhbGl6ZUNvbmRpdGlvbigkcm91dGVNYXRjaCkgewoJCQlyZXR1cm4gJHJvdXRlTWF0Y2g7IC8vIEFsd2F5cyBpbml0aWFsaXplIGlmIHRoZSByb3V0ZSBtYXRjaGVkIChkZWZhdWx0IGJlaGF2aW9yKQoJCX0KCX0KCQoJbmV3IE1vZHVsZSgkZG90QXBwKTsKPz4K";
        if ($filename=="/sql.sql") return "U0VUIFNRTF9NT0RFID0gIk5PX0FVVE9fVkFMVUVfT05fWkVSTyI7ClNUQVJUIFRSQU5TQUNUSU9OOwpTRVQgdGltZV96b25lID0gIiswMDowMCI7CgpEUk9QIFRBQkxFIElGIEVYSVNUUyBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzYDsKQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc2AgKAogIGBpZGAgaW50IE5PVCBOVUxMIEFVVE9fSU5DUkVNRU5ULAogIGB1c2VybmFtZWAgdmFyY2hhcig1MCkgQ09MTEFURSB1dGY4bWI0X2dlbmVyYWxfY2kgTk9UIE5VTEwgQ09NTUVOVCAnLy8gVXppdmF0ZWxza2UgbWVubycsCiAgYGVtYWlsYCB2YXJjaGFyKDEwMCkgQ09MTEFURSB1dGY4bWI0X2dlbmVyYWxfY2kgTk9UIE5VTEwgQ09NTUVOVCAnLy8gRW1haWwsIG1vemUgc2EgcG91eml0IG5hIHByaWhsYXNlbmllIHRpZXouIE1vemUgc2EgcG91eml2YXQgbmEgZW1haWxvdmUgbm90aWZpa2FjaWUnLAogIGBwYXNzd29yZGAgdmFyY2hhcigxMDApIENPTExBVEUgdXRmOG1iNF9nZW5lcmFsX2NpIE5PVCBOVUxMIENPTU1FTlQgJy8vIEhlc2xvJywKICBgdGZhX2ZpcmV3YWxsYCBpbnQgTk9UIE5VTEwgQ09NTUVOVCAnLy8gUG91eml0IGFsZWJvIG5lcG91eml0IGZpcmV3YWxsJywKICBgdGZhX3Ntc2AgaW50IE5PVCBOVUxMIENPTU1FTlQgJy8vIFBvdXppdmFtZSAyZmFrdG9yIGNleiBTTVM/JywKICBgdGZhX3Ntc19udW1iZXJfcHJlZml4YCB2YXJjaGFyKDgpIENPTExBVEUgdXRmOG1iNF9nZW5lcmFsX2NpIE5PVCBOVUxMLAogIGB0ZmFfc21zX251bWJlcmAgdmFyY2hhcigyMCkgQ09MTEFURSB1dGY4bWI0X2dlbmVyYWxfY2kgTk9UIE5VTEwgQ09NTUVOVCAnLy8gQ2lzbG8gcHJlIHphc2xhbmllIFNNUycsCiAgYHRmYV9zbXNfbnVtYmVyX2NvbmZpcm1lZGAgaW50IE5PVCBOVUxMIENPTU1FTlQgJy8vIENpc2xvIHBvdHZyZGVuZSB6YWRhbmltIGtvZHUnLAogIGB0ZmFfYXV0aGAgaW50IE5PVCBOVUxMIENPTU1FTlQgJy8vIFBvdXppdmFtZSAyIGZha3RvciBjZXogR09PR0xFIEFVVEggPycsCiAgYHRmYV9hdXRoX3NlY3JldGAgdmFyY2hhcig1MCkgQ09MTEFURSB1dGY4bWI0X2dlbmVyYWxfY2kgTk9UIE5VTEwgQ09NTUVOVCAnLy8gQWsgYW1tZSBnb29nbGUgYXV0aCwgdGFrIHRyZWJhIGRyemF0IHVsb3plbnkgc2VjcmV0ICcsCiAgYHRmYV9hdXRoX3NlY3JldF9jb25maXJtZWRgIGludCBOT1QgTlVMTCBDT01NRU5UICcvLyBCb2xvIHBvdHZyZGVuZSAyRkEgYXV0aD8nLAogIGB0ZmFfZW1haWxgIGludCBOT1QgTlVMTCBDT01NRU5UICcvLyBQb3V6aXZhbWUgMiBmYWt0b3IgY2V6IGUtbWFpbD8nLAogIGBzdGF0dXNgIGludCBOT1QgTlVMTCBDT01NRU5UICcvLyBTdGF0dXMgcHJpaGxhc2VuaWEuIDEgLSBBa3Rpdm55LCAyLURMaHNpZSBuZWFrdGl2bnksIDMgLSBPZmZsaW5lJywKICBgY3JlYXRlZF9hdGAgdGltZXN0YW1wIE5PVCBOVUxMLAogIGB1cGRhdGVkX2F0YCB0aW1lc3RhbXAgTk9UIE5VTEwsCiAgYGxhc3RfbG9nZ2VkX2F0YCB0aW1lc3RhbXAgTk9UIE5VTEwsCiAgUFJJTUFSWSBLRVkgKGBpZGApLAogIFVOSVFVRSBLRVkgYHVzZXJuYW1lYCAoYHVzZXJuYW1lYCksCiAgVU5JUVVFIEtFWSBgZW1haWxgIChgZW1haWxgKQopIEVOR0lORT1Jbm5vREIgREVGQVVMVCBDSEFSU0VUPXV0ZjhtYjQgQ09MTEFURT11dGY4bWI0X2dlbmVyYWxfY2kgQ09NTUVOVD0nVGFidWxreSBzIHV6aXZhdGVsbWkgbW9kdWx1IHVzZXJzJzsKCgpEUk9QIFRBQkxFIElGIEVYSVNUUyBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX2ZpcmV3YWxsYDsKQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19maXJld2FsbGAgKAogIGBpZGAgYmlnaW50IE5PVCBOVUxMIEFVVE9fSU5DUkVNRU5ULAogIGB1c2VyX2lkYCBpbnQgTk9UIE5VTEwsCiAgYHJ1bGVgIHZhcmNoYXIoNTApIENPTExBVEUgdXRmOG1iNF9nZW5lcmFsX2NpIE5PVCBOVUxMIENPTU1FTlQgJy8vIFByYXZpZGxvIHByZSBmaXJld2FsbC4gQ0lEUiB0dmFyLiBOYXByaWtsYWQgMTkyLjE2OC4xLjAvMjQnLAogIGBhY3Rpb25gIGludCBOT1QgTlVMTCBDT01NRU5UICcwIC0gQmxvY2ssIDEgLSBBbGxvdycsCiAgYGFjdGl2ZWAgaW50IE5PVCBOVUxMIENPTU1FTlQgJy8vIFJ1bGUgaXMgYWN0aXZlIG9yIGluYWN0aXZlJywKICBgb3JkZXJpbmdgIGludCBOT1QgTlVMTCBDT01NRU5UICcvLyBQb3JhZGllIHByYXZpZGxhJywKICBQUklNQVJZIEtFWSAoYGlkYCksCiAgS0VZIGBvcmRlcmluZ2AgKGBvcmRlcmluZ2ApLAogIEtFWSBgdXNlcl9pZGAgKGB1c2VyX2lkYCksCiAgS0VZIGB1c2VyX2lkXzJgIChgdXNlcl9pZGAsYGFjdGl2ZWApCikgRU5HSU5FPUlubm9EQiBERUZBVUxUIENIQVJTRVQ9dXRmOG1iNCBDT0xMQVRFPXV0ZjhtYjRfZ2VuZXJhbF9jaTsKCkRST1AgVEFCTEUgSUYgRVhJU1RTIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNfcGFzc3dvcmRfcmVzZXRzYDsKQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19wYXNzd29yZF9yZXNldHNgICgKICBgaWRgIGJpZ2ludCBOT1QgTlVMTCBBVVRPX0lOQ1JFTUVOVCwKICBgdXNlcl9pZGAgaW50IE5PVCBOVUxMLAogIGB0b2tlbmAgdmFyY2hhcigyNTUpIENPTExBVEUgdXRmOG1iNF9nZW5lcmFsX2NpIE5PVCBOVUxMLAogIGBjcmVhdGVkX2F0YCB0aW1lc3RhbXAgTk9UIE5VTEwgREVGQVVMVCBDVVJSRU5UX1RJTUVTVEFNUCwKICBgZXhwaXJlc19hdGAgdGltZXN0YW1wIE5PVCBOVUxMLAogIFBSSU1BUlkgS0VZIChgaWRgKSwKICBLRVkgYHVzZXJfaWRgIChgdXNlcl9pZGApCikgRU5HSU5FPUlubm9EQiBERUZBVUxUIENIQVJTRVQ9dXRmOG1iNCBDT0xMQVRFPXV0ZjhtYjRfZ2VuZXJhbF9jaTsKCkRST1AgVEFCTEUgSUYgRVhJU1RTIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNfcmlnaHRzYDsKQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19yaWdodHNgICgKICBgaWRgIGludCBOT1QgTlVMTCBBVVRPX0lOQ1JFTUVOVCwKICBgdXNlcl9pZGAgaW50IE5PVCBOVUxMLAogIGByaWdodF9pZGAgaW50IE5PVCBOVUxMLAogIFBSSU1BUlkgS0VZIChgaWRgKSwKICBLRVkgYHVzZXJfaWRgIChgdXNlcl9pZGAsYHJpZ2h0X2lkYCksCiAgS0VZIGB1c2VyX2lkXzJgIChgdXNlcl9pZGApLAogIEtFWSBgcmlnaHRfaWRgIChgcmlnaHRfaWRgKQopIEVOR0lORT1Jbm5vREIgREVGQVVMVCBDSEFSU0VUPXV0ZjhtYjQgQ09MTEFURT11dGY4bWI0X2dlbmVyYWxfY2k7CgpEUk9QIFRBQkxFIElGIEVYSVNUUyBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX3JpZ2h0c19ncm91cHNgOwpDUkVBVEUgVEFCTEUgSUYgTk9UIEVYSVNUUyBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX3JpZ2h0c19ncm91cHNgICgKICBgaWRgIGludCBOT1QgTlVMTCBBVVRPX0lOQ1JFTUVOVCwKICBgbmFtZWAgbWVkaXVtdGV4dCBDT0xMQVRFIHV0ZjhtYjRfZ2VuZXJhbF9jaSBOT1QgTlVMTCBDT01NRU5UICcvLyBOYXpvdiBncnVweSAtIE5vcm1hbG5lIHRleHRvbScsCiAgYG9yZGVyaW5nYCBpbnQgTk9UIE5VTEwgQ09NTUVOVCAnLy8gUG9yYWRpZScsCiAgYGNyZWF0b3JgIHZhcmNoYXIoMTAwKSBDT0xMQVRFIHV0ZjhtYjRfZ2VuZXJhbF9jaSBOT1QgTlVMTCBDT01NRU5UICcvLyBLdG9yeSBtb2R1bCB0byB2eXR2b3JpbCBwcmUgb2RpbnN0YWxhY2l1LiBBayBqZSBwcmF6ZG5lIHRhayBqZSB0byB2c3RhdmFuZSBkZWZhdWx0bmUgZG8gc3lzdGVtdScsCiAgYGVkaXRhYmxlYCBpbnQgTk9UIE5VTEwgQ09NTUVOVCAnLy8gMCAtIG5lc21pZSBzYSB1cHJhdm92YXQgLyAxIC0gbW96ZSBzYSB1cHJhdm92YXQnLAogIFBSSU1BUlkgS0VZIChgaWRgKSwKICBLRVkgYG9yZGVyaW5nYCAoYG9yZGVyaW5nYCksCiAgS0VZIGBjcmVhdG9yYCAoYGNyZWF0b3JgKQopIEVOR0lORT1Jbm5vREIgREVGQVVMVCBDSEFSU0VUPXV0ZjhtYjQgQ09MTEFURT11dGY4bWI0X2dlbmVyYWxfY2k7CgpEUk9QIFRBQkxFIElGIEVYSVNUUyBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX3JpZ2h0c19saXN0YDsKQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19yaWdodHNfbGlzdGAgKAogIGBpZGAgaW50IE5PVCBOVUxMIEFVVE9fSU5DUkVNRU5ULAogIGBncm91cF9pZGAgaW50IE5PVCBOVUxMIENPTU1FTlQgJy8vIElkIHpvc2t1cGVuaWEgb3ByYXZuZW5pIGtlZHplIGthemR5bSBvZHp1bCBtb3plIG1hdCB2bGFzdG51IHNrdXBpbnUgbmVjaCB2IHRvbSBuaWUgamUgYm9yZGVsJywKICBgbmFtZWAgdGV4dCBDSEFSQUNURVIgU0VUIHV0ZjhtYjMgTk9UIE5VTEwgQ09NTUVOVCAnLy8gTmF6b3YgcHJhdmEgdiBkbGhvbSBmb3JtYXRlJywKICBgZGVzY3JpcHRpb25gIHRleHQgQ0hBUkFDVEVSIFNFVCB1dGY4bWIzIE5PVCBOVUxMIENPTU1FTlQgJy8vIFBvcGlzIG9wcmF2bmVuaWEgdiBkZXRhaWxvY2gnLAogIGBtb2R1bGVgIHZhcmNoYXIoMTAwKSBDSEFSQUNURVIgU0VUIHV0ZjhtYjMgTk9UIE5VTEwgQ09NTUVOVCAnLy8gTmF6b3YgbW9kdWx1IGt0b3J5IHByYXZvIHZ5dHZvcmlsJywKICBgcmlnaHRuYW1lYCB2YXJjaGFyKDEwMCkgQ0hBUkFDVEVSIFNFVCB1dGY4bWIzIE5PVCBOVUxMIENPTU1FTlQgJy8vIE9wcmF2bmVuaWUgJywKICBgYWN0aXZlYCBpbnQgTk9UIE5VTEwgQ09NTUVOVCAnLy8gMCBuaWUgMSBhbm8nLAogIGBvcmRlcmluZ2AgaW50IE5PVCBOVUxMIENPTU1FTlQgJy8vIFpvcmFkZW5pZScsCiAgYGNyZWF0b3JgIHZhcmNoYXIoMTAwKSBDSEFSQUNURVIgU0VUIHV0ZjhtYjMgTk9UIE5VTEwgQ09NTUVOVCAnLy8gS3RvcnkgbW9kdWwgdnl0dm9yaWwgem96bmFtIGFieSBib2xvIG1vem5lIHByaSBvZGluc3RhbGFjaWkgaG8gem1hemF0JywKICBgY3VzdG9tYCBpbnQgTk9UIE5VTEwgQ09NTUVOVCAnMCAtIG5pZSwgMSAtIGFubycsCiAgUFJJTUFSWSBLRVkgKGBpZGApLAogIEtFWSBgbW9kdWxlYCAoYG1vZHVsZWApLAogIEtFWSBgcmlnaHRuYW1lYCAoYHJpZ2h0bmFtZWApLAogIEtFWSBgbW9kdWxlXzJgIChgbW9kdWxlYCxgcmlnaHRuYW1lYCksCiAgS0VZIGBvcmRlcmluZ2AgKGBvcmRlcmluZ2ApLAogIEtFWSBgcmlnaHRuYW1lXzJgIChgcmlnaHRuYW1lYCxgYWN0aXZlYCxgb3JkZXJpbmdgKSwKICBLRVkgYGdyb3VwX2lkYCAoYGdyb3VwX2lkYCxgbW9kdWxlYCxgcmlnaHRuYW1lYCxgb3JkZXJpbmdgKSwKICBLRVkgYGlkYCAoYGlkYCxgYWN0aXZlYCkKKSBFTkdJTkU9SW5ub0RCIERFRkFVTFQgQ0hBUlNFVD11dGY4bWI0IENPTExBVEU9dXRmOG1iNF9nZW5lcmFsX2NpIENPTU1FTlQ9J1pvem5hbSBvcHJhdm5lbmkga3RvcmUgamUgbW96bmUgdXppdmF0ZWx2aSBwcmlyYWRpdCc7CgpEUk9QIFRBQkxFIElGIEVYSVNUUyBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX3JtdG9rZW5zYDsKQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19ybXRva2Vuc2AgKAogIGBpZGAgaW50IE5PVCBOVUxMIEFVVE9fSU5DUkVNRU5ULAogIGB1c2VyX2lkYCBpbnQgTk9UIE5VTEwsCiAgYHRva2VuYCB2YXJjaGFyKDI1NSkgQ09MTEFURSB1dGY4bWI0X2dlbmVyYWxfY2kgTk9UIE5VTEwsCiAgYGV4cGlyZXNfYXRgIHRpbWVzdGFtcCBOT1QgTlVMTCwKICBQUklNQVJZIEtFWSAoYGlkYCksCiAgVU5JUVVFIEtFWSBgdG9rZW5gIChgdG9rZW5gKSwKICBLRVkgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19zZXNzaW9uc19pYmZrXzFgIChgdXNlcl9pZGApCikgRU5HSU5FPUlubm9EQiBERUZBVUxUIENIQVJTRVQ9dXRmOG1iNCBDT0xMQVRFPXV0ZjhtYjRfZ2VuZXJhbF9jaTsKCkRST1AgVEFCTEUgSUYgRVhJU1RTIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNfcm9sZXNgOwpDUkVBVEUgVEFCTEUgSUYgTk9UIEVYSVNUUyBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX3JvbGVzYCAoCiAgYGlkYCBpbnQgTk9UIE5VTEwgQVVUT19JTkNSRU1FTlQsCiAgYHVzZXJfaWRgIGludCBOT1QgTlVMTCwKICBgcm9sZV9pZGAgaW50IE5PVCBOVUxMLAogIGBhc3NpZ25lZF9hdGAgdGltZXN0YW1wIE5VTEwgREVGQVVMVCBDVVJSRU5UX1RJTUVTVEFNUCwKICBQUklNQVJZIEtFWSAoYGlkYCksCiAgVU5JUVVFIEtFWSBgdW5pcXVlX3VzZXJfcm9sZWAgKGB1c2VyX2lkYCxgcm9sZV9pZGApLAogIEtFWSBgaWRfcm9seWAgKGByb2xlX2lkYCkKKSBFTkdJTkU9SW5ub0RCIERFRkFVTFQgQ0hBUlNFVD11dGY4bWI0IENPTExBVEU9dXRmOG1iNF9nZW5lcmFsX2NpOwoKRFJPUCBUQUJMRSBJRiBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19yb2xlc19saXN0YDsKQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19yb2xlc19saXN0YCAoCiAgYGlkYCBpbnQgTk9UIE5VTEwgQVVUT19JTkNSRU1FTlQsCiAgYG5hbWVgIHZhcmNoYXIoNTApIENIQVJBQ1RFUiBTRVQgdXRmMTYgTk9UIE5VTEwsCiAgYGRlc2NyaXB0aW9uYCB0ZXh0IENIQVJBQ1RFUiBTRVQgdXRmMTYsCiAgUFJJTUFSWSBLRVkgKGBpZGApLAogIFVOSVFVRSBLRVkgYG5hbWVgIChgbmFtZWApCikgRU5HSU5FPUlubm9EQiBERUZBVUxUIENIQVJTRVQ9dXRmOG1iNCBDT0xMQVRFPXV0ZjhtYjRfZ2VuZXJhbF9jaTsKCkRST1AgVEFCTEUgSUYgRVhJU1RTIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNfcm9sZXNfcmlnaHRzYDsKQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19yb2xlc19yaWdodHNgICgKICBgaWRgIGludCBOT1QgTlVMTCBBVVRPX0lOQ1JFTUVOVCwKICBgcmlnaHRfaWRgIGludCBOT1QgTlVMTCwKICBgcm9sZV9pZGAgaW50IE5PVCBOVUxMLAogIGBhc3NpZ25lZF9hdGAgdGltZXN0YW1wIE5VTEwgREVGQVVMVCBDVVJSRU5UX1RJTUVTVEFNUCwKICBQUklNQVJZIEtFWSAoYGlkYCksCiAgVU5JUVVFIEtFWSBgdW5pcV9yaWdodF9yb2xlYCAoYHJpZ2h0X2lkYCxgcm9sZV9pZGApLAogIEtFWSBgcm9sZV9pZGAgKGByb2xlX2lkYCkKKSBFTkdJTkU9SW5ub0RCIERFRkFVTFQgQ0hBUlNFVD11dGY4bWI0IENPTExBVEU9dXRmOG1iNF9nZW5lcmFsX2NpOwoKRFJPUCBUQUJMRSBJRiBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19zZXNzaW9uc2A7CkNSRUFURSBUQUJMRSBJRiBOT1QgRVhJU1RTIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNfc2Vzc2lvbnNgICgKICBgc2Vzc2lvbl9pZGAgdmFyY2hhcig2NCkgQ09MTEFURSB1dGY4bWI0X2dlbmVyYWxfY2kgTk9UIE5VTEwsCiAgYHNlc3NuYW1lYCB2YXJjaGFyKDI1NSkgQ09MTEFURSB1dGY4bWI0X2dlbmVyYWxfY2kgTk9UIE5VTEwsCiAgYHZhbHVlc2AgbG9uZ3RleHQgQ09MTEFURSB1dGY4bWI0X2dlbmVyYWxfY2kgTk9UIE5VTEwsCiAgYHZhcmlhYmxlc2AgbG9uZ3RleHQgQ09MTEFURSB1dGY4bWI0X2dlbmVyYWxfY2kgTk9UIE5VTEwsCiAgYGV4cGlyeWAgYmlnaW50IE5PVCBOVUxMLAogIGBjcmVhdGVkX2F0YCB0aW1lc3RhbXAgTk9UIE5VTEwgREVGQVVMVCBDVVJSRU5UX1RJTUVTVEFNUCwKICBgdXBkYXRlZF9hdGAgdGltZXN0YW1wIE5PVCBOVUxMIERFRkFVTFQgQ1VSUkVOVF9USU1FU1RBTVAgT04gVVBEQVRFIENVUlJFTlRfVElNRVNUQU1QLAogIFBSSU1BUlkgS0VZIChgc2Vzc2lvbl9pZGAsYHNlc3NuYW1lYCksCiAgS0VZIGBpZHhfZXhwaXJ5YCAoYGV4cGlyeWApCikgRU5HSU5FPUlubm9EQiBERUZBVUxUIENIQVJTRVQ9dXRmOG1iNCBDT0xMQVRFPXV0ZjhtYjRfZ2VuZXJhbF9jaTsKCkRST1AgVEFCTEUgSUYgRVhJU1RTIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNfdXJsX2ZpcmV3YWxsYDsKQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc191cmxfZmlyZXdhbGxgICgKICBgaWRgIGludCBOT1QgTlVMTCwKICBgdXNlcmAgaW50IE5PVCBOVUxMLAogIGB1cmxgIHZhcmNoYXIoMjAwKSBDSEFSQUNURVIgU0VUIHV0ZjhtYjMgTk9UIE5VTEwgQ09NTUVOVCAnLy8gVXJsIG1vemUgYnl0IHMgKiBuYXByaWtsYWQgbW96ZSBieXQgKiAtIHRvIHpuYW1lbmEgdnNldGt5IGFkcmVzeSBibG9rbmVtZS4gQWxlYm8gYmxva25lbWUgbGVuICovdXppdmF0ZWxpYS8qIHRha3plIGFrIGplIHYgVVIhIC91eml2YXRlbGlhLyB0YWsgYmxva25lbWUgYWxlYm8gbmFvcGFrIHBvdm9saW1lJywKICBgYWN0aW9uYCBpbnQgTk9UIE5VTEwgQ09NTUVOVCAnMC1CbG9rbmkgLyAxIC0gUG92b2wnLAogIGBhY3RpdmVgIGludCBOT1QgTlVMTCBDT01NRU5UICcvLyBQcmF2aWRsbyBqZSBha3Rpdm92YW5lIGFsZWJvIGRlYWt0aXZvdmFuZScKKSBFTkdJTkU9SW5ub0RCIERFRkFVTFQgQ0hBUlNFVD11dGY4bWI0IENPTExBVEU9dXRmOG1iNF9nZW5lcmFsX2NpOwoKQUxURVIgVEFCTEUgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19maXJld2FsbGAKICBBREQgQ09OU1RSQUlOVCBgdXNlcnNfdnNfZmlyZXdhbGxgIEZPUkVJR04gS0VZIChgdXNlcl9pZGApIFJFRkVSRU5DRVMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc2AgKGBpZGApIE9OIERFTEVURSBDQVNDQURFIE9OIFVQREFURSBDQVNDQURFOwoKQUxURVIgVEFCTEUgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19wYXNzd29yZF9yZXNldHNgCiAgQUREIENPTlNUUkFJTlQgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19wYXNzd29yZF9yZXNldHNfaWJma18xYCBGT1JFSUdOIEtFWSAoYHVzZXJfaWRgKSBSRUZFUkVOQ0VTIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNgIChgaWRgKSBPTiBERUxFVEUgQ0FTQ0FERTsKCkFMVEVSIFRBQkxFIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNfcmlnaHRzYAogIEFERCBDT05TVFJBSU5UIGBwcmF2b19pZGAgRk9SRUlHTiBLRVkgKGByaWdodF9pZGApIFJFRkVSRU5DRVMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19yaWdodHNfbGlzdGAgKGBpZGApIE9OIERFTEVURSBDQVNDQURFIE9OIFVQREFURSBDQVNDQURFLAogIEFERCBDT05TVFJBSU5UIGB1eml2X2lkYCBGT1JFSUdOIEtFWSAoYHVzZXJfaWRgKSBSRUZFUkVOQ0VTIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNgIChgaWRgKSBPTiBERUxFVEUgQ0FTQ0FERSBPTiBVUERBVEUgQ0FTQ0FERTsKCkFMVEVSIFRBQkxFIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNfcmlnaHRzX2xpc3RgCiAgQUREIENPTlNUUkFJTlQgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19yaWdodHNfbGlzdF9pYmZrXzFgIEZPUkVJR04gS0VZIChgZ3JvdXBfaWRgKSBSRUZFUkVOQ0VTIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNfcmlnaHRzX2dyb3Vwc2AgKGBpZGApIE9OIERFTEVURSBDQVNDQURFIE9OIFVQREFURSBDQVNDQURFOwoKQUxURVIgVEFCTEUgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19ybXRva2Vuc2AKICBBREQgQ09OU1RSQUlOVCBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX3JtdG9rZW5zX2liZmtfMWAgRk9SRUlHTiBLRVkgKGB1c2VyX2lkYCkgUkVGRVJFTkNFUyBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzYCAoYGlkYCkgT04gREVMRVRFIENBU0NBREUgT04gVVBEQVRFIENBU0NBREU7CgpBTFRFUiBUQUJMRSBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX3JvbGVzYAogIEFERCBDT05TVFJBSU5UIGBpZF9yb2x5YCBGT1JFSUdOIEtFWSAoYHJvbGVfaWRgKSBSRUZFUkVOQ0VTIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNfcm9sZXNfbGlzdGAgKGBpZGApIE9OIERFTEVURSBDQVNDQURFLAogIEFERCBDT05TVFJBSU5UIGB1eml2YXRlbG92ZV9pZGAgRk9SRUlHTiBLRVkgKGB1c2VyX2lkYCkgUkVGRVJFTkNFUyBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzYCAoYGlkYCkgT04gREVMRVRFIENBU0NBREUgT04gVVBEQVRFIENBU0NBREU7CgpBTFRFUiBUQUJMRSBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX3JvbGVzX3JpZ2h0c2AKICBBREQgQ09OU1RSQUlOVCBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX3JvbGVzX3JpZ2h0c19pYmZrXzFgIEZPUkVJR04gS0VZIChgcm9sZV9pZGApIFJFRkVSRU5DRVMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19yb2xlc19saXN0YCAoYGlkYCkgT04gREVMRVRFIENBU0NBREUgT04gVVBEQVRFIENBU0NBREUsCiAgQUREIENPTlNUUkFJTlQgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19yb2xlc19yaWdodHNfaWJma18yYCBGT1JFSUdOIEtFWSAoYHJpZ2h0X2lkYCkgUkVGRVJFTkNFUyBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX3JpZ2h0c19saXN0YCAoYGlkYCkgT04gREVMRVRFIENBU0NBREUgT04gVVBEQVRFIENBU0NBREU7CkNPTU1JVDsK";
        if ($filename=="/tests/guide.md") return "IyBHdWlkZSB0byBDcmVhdGluZyBUZXN0cyBmb3IgRG90QXBwIEZyYW1ld29yayBNb2R1bGVzCgpUaGlzIGd1aWRlIHByb3ZpZGVzIHNpbXBsZSBzdGVwcyBmb3IgY3JlYXRpbmcgdGVzdHMgZm9yIHlvdXIgbW9kdWxlcyBpbiB0aGUgRG90QXBwIEZyYW1ld29yayAodmVyc2lvbiAxLjcgRlJFRSkgdXNpbmcgdGhlIGBUZXN0ZXJgIGNsYXNzLiBJdCBpcyBkZXNpZ25lZCBmb3IgbW9kdWxlIGRldmVsb3BlcnMgZmFtaWxpYXIgd2l0aCB0aGUgZnJhbWV3b3Jr4oCZcyBtb2R1bGFyIHN0cnVjdHVyZSwgc2hvd2luZyBob3cgdG8gd3JpdGUgdGVzdHMgaW4gYGFwcC9tb2R1bGVzL01PRFVMRV9OQU1FL3Rlc3RzL2AgdXNpbmcgdGhlIGBEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXE1PRFVMRV9OQU1FXHRlc3RzYCBuYW1lc3BhY2UuIFRlc3RzIGFyZSBydW4gdXNpbmcgdGhlIGJ1aWx0LWluIGBkb3RhcHBlci5waHBgIENMSSB0b29sLgoKIyMgVGFibGUgb2YgQ29udGVudHMKCjEuIFtJbnRyb2R1Y3Rpb25dKCNpbnRyb2R1Y3Rpb24pCjIuIFtDcmVhdGluZyBUZXN0c10oI2NyZWF0aW5nLXRlc3RzKQogICAtIFtCYXNpYyBUZXN0XSgjYmFzaWMtdGVzdCkKICAgLSBbVGVzdCBSZXN1bHQgRm9ybWF0XSgjdGVzdC1yZXN1bHQtZm9ybWF0KQozLiBbT3JnYW5pemluZyBUZXN0c10oI29yZ2FuaXppbmctdGVzdHMpCjQuIFtSdW5uaW5nIFRlc3RzIHdpdGggYGRvdGFwcGVyLnBocGBdKCNydW5uaW5nLXRlc3RzLXdpdGgtZG90YXBwZXJwaHApCjUuIFtUaXBzIGFuZCBCZXN0IFByYWN0aWNlc10oI3RpcHMtYW5kLWJlc3QtcHJhY3RpY2VzKQo2LiBbVHJvdWJsZXNob290aW5nXSgjdHJvdWJsZXNob290aW5nKQoKIyMgSW50cm9kdWN0aW9uCgpUaGUgYFRlc3RlcmAgY2xhc3MgYWxsb3dzIHlvdSB0byB3cml0ZSB0ZXN0cyBmb3IgeW91ciBEb3RBcHAgRnJhbWV3b3JrIG1vZHVsZXMuIFRlc3RzIGFyZSByZWdpc3RlcmVkIHVzaW5nIGBUZXN0ZXI6OmFkZFRlc3RgIGFuZCBwbGFjZWQgaW4geW91ciBtb2R1bGXigJlzIGB0ZXN0cy9gIGRpcmVjdG9yeS4gVGhlIGZyYW1ld29ya+KAmXMgYXV0b2xvYWRlciBoYW5kbGVzIGRlcGVuZGVuY2llcywgcmVxdWlyaW5nIG9ubHkgYHVzZSBEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xUZXN0ZXI7YCBpbiB0ZXN0IGZpbGVzLiBUaGlzIGd1aWRlIHNob3dzIGhvdyB0byBjcmVhdGUgYSBzaW1wbGUgdGVzdCBmb3IgYSBtb2R1bGUgbmFtZWQgYE1PRFVMRV9OQU1FYCAoZS5nLiwgYEJsb2dgLCBgU2hvcGApIGFuZCBydW4gaXQgdXNpbmcgYGRvdGFwcGVyLnBocGAuCgojIyBDcmVhdGluZyBUZXN0cwoKIyMjIEJhc2ljIFRlc3QKClRlc3RzIGFyZSB3cml0dGVuIGFzIFBIUCBmaWxlcyBpbiBgYXBwL21vZHVsZXMvTU9EVUxFX05BTUUvdGVzdHMvYCB1c2luZyB0aGUgYERvdHN5c3RlbXNcQXBwXE1vZHVsZXNcTU9EVUxFX05BTUVcdGVzdHNgIG5hbWVzcGFjZS4gRWFjaCB0ZXN0IGlzIGEgY2FsbGJhY2sgZnVuY3Rpb24gcmVnaXN0ZXJlZCB3aXRoIGBUZXN0ZXI6OmFkZFRlc3RgLgoKRXhhbXBsZSBvZiBhIGJhc2ljIHRlc3QgKGBhcHAvbW9kdWxlcy9NT0RVTEVfTkFNRS90ZXN0cy9FeGFtcGxlVGVzdC5waHBgKToKCmBgYHBocAo8P3BocApuYW1lc3BhY2UgRG90c3lzdGVtc1xBcHBcTW9kdWxlc1xNT0RVTEVfTkFNRVx0ZXN0czsKCnVzZSBEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xUZXN0ZXI7CgpUZXN0ZXI6OmFkZFRlc3QoJ0V4YW1wbGUgdGVzdCcsIGZ1bmN0aW9uICgpIHsKICAgICRyZXN1bHQgPSAyICsgMiA9PT0gNDsKICAgIHJldHVybiBbCiAgICAgICAgJ3N0YXR1cycgPT4gJHJlc3VsdCA/IDEgOiAwLAogICAgICAgICdpbmZvJyA9PiAkcmVzdWx0ID8gJzIgKyAyIGVxdWFscyA0JyA6ICcyICsgMiBkb2VzIG5vdCBlcXVhbCA0JywKICAgICAgICAndGVzdF9uYW1lJyA9PiAnRXhhbXBsZSB0ZXN0JywKICAgICAgICAnY29udGV4dCcgPT4gWydtb2R1bGUnID0+ICdNT0RVTEVfTkFNRScsICdtZXRob2QnID0+ICdhZGRpdGlvbicsICd0ZXN0X3R5cGUnID0+ICd1bml0J10KICAgIF07Cn0pOwo/PgpgYGAKCiMjIyBUZXN0IFJlc3VsdCBGb3JtYXQKClRoZSBjYWxsYmFjayBmdW5jdGlvbiBtdXN0IHJldHVybiBhbiBhcnJheSB3aXRoOgoKLSAqKmBzdGF0dXNgKiogKGludCk6IFRlc3Qgc3RhdHVzOgogIC0gYDFgOiBQYXNzZWQgKE9LKS4KICAtIGAwYDogRmFpbGVkIChOT1QgT0spLgogIC0gYDJgOiBTa2lwcGVkIChTS0lQUEVEKS4KLSAqKmBpbmZvYCoqIChzdHJpbmcpOiBEZXNjcmlwdGlvbiBvZiB0aGUgcmVzdWx0IChlLmcuLCB3aHkgdGhlIHRlc3QgZmFpbGVkKS4KLSAqKmB0ZXN0X25hbWVgKiogKHN0cmluZyk6IFRlc3QgbmFtZSAodXN1YWxseSBtYXRjaGVzIGBhZGRUZXN0YCBuYW1lKS4KLSAqKmBjb250ZXh0YCoqIChhcnJheSwgb3B0aW9uYWwpOiBNZXRhZGF0YSAoZS5nLiwgbW9kdWxlLCBtZXRob2QsIHRlc3QgdHlwZSkuCgojIyBPcmdhbml6aW5nIFRlc3RzCgotIFBsYWNlIGFsbCB0ZXN0cyBpbiBgYXBwL21vZHVsZXMvTU9EVUxFX05BTUUvdGVzdHMvYCwgd2hlcmUgYE1PRFVMRV9OQU1FYCBpcyB5b3VyIG1vZHVsZeKAmXMgbmFtZSAoZS5nLiwgYEJsb2dgLCBgU2hvcGApLgotIFVzZSBkZXNjcmlwdGl2ZSBmaWxlIG5hbWVzLCBlLmcuLCBgRXhhbXBsZVRlc3QucGhwYCwgYE9yZGVyVGVzdC5waHBgLgotIFVzZSB0aGUgbmFtZXNwYWNlIGBEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXE1PRFVMRV9OQU1FXHRlc3RzYCBmb3IgYWxsIHRlc3QgZmlsZXMuCgojIyBSdW5uaW5nIFRlc3RzIHdpdGggYGRvdGFwcGVyLnBocGAKClRlc3RzIGFyZSBleGVjdXRlZCB1c2luZyB0aGUgYnVpbHQtaW4gYGRvdGFwcGVyLnBocGAgQ0xJIHRvb2wgZnJvbSB0aGUgcHJvamVjdOKAmXMgcm9vdCBkaXJlY3RvcnkuIFN1cHBvcnRlZCBjb21tYW5kczoKCi0gKipSdW4gYWxsIHRlc3RzIChjb3JlICsgYWxsIG1vZHVsZXMpKio6CiAgYGBgYmFzaAogIHBocCBkb3RhcHBlci5waHAgLS10ZXN0CiAgYGBgCgotICoqUnVuIGFsbCBtb2R1bGUgdGVzdHMgKG5vIGNvcmUgdGVzdHMpKio6CiAgYGBgYmFzaAogIHBocCBkb3RhcHBlci5waHAgLS10ZXN0LW1vZHVsZXMKICBgYGAKCi0gKipSdW4gdGVzdHMgZm9yIGEgc3BlY2lmaWMgbW9kdWxlKio6CiAgYGBgYmFzaAogIHBocCBkb3RhcHBlci5waHAgLS1tb2R1bGU9TU9EVUxFX05BTUUgLS10ZXN0CiAgYGBgCgpUaGUgb3V0cHV0IGluY2x1ZGVzIGZvciBlYWNoIHRlc3Q6Ci0gKipUZXN0IE5hbWUqKiAoYHRlc3RfbmFtZWApLgotICoqU3RhdHVzKiogKGBPS2AsIGBOT1QgT0tgLCBgU0tJUFBFRGApLgotICoqRGVzY3JpcHRpb24qKiAoYGluZm9gKS4KLSAqKkR1cmF0aW9uKiogKGluIHNlY29uZHMpLgotICoqTWVtb3J5IFVzYWdlKiogKGBtZW1vcnlfZGVsdGFgIGluIEtCKS4KLSAqKkNvbnRleHQqKiAoSlNPTi1lbmNvZGVkIGFycmF5KS4KCkV4YW1wbGUgb3V0cHV0OgoKYGBgClRlc3Q6IEV4YW1wbGUgdGVzdApTdGF0dXM6IE9LCkluZm86IDIgKyAyIGVxdWFscyA0CkR1cmF0aW9uOiAwLjAwMDEyM3MKTWVtb3J5IERlbHRhOiAyNTYuNTAgS0IKQ29udGV4dDogeyJtb2R1bGUiOiJNT0RVTEVfTkFNRSIsIm1ldGhvZCI6ImFkZGl0aW9uIiwidGVzdF90eXBlIjoidW5pdCJ9Ci0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0KU3VtbWFyeTogMS8xIHRlc3RzIHBhc3NlZCAoMCBza2lwcGVkLCAwIGZhaWxlZCkKYGBgCgojIyBUaXBzIGFuZCBCZXN0IFByYWN0aWNlcwoKMS4gKipVc2UgRGVzY3JpcHRpdmUgVGVzdCBOYW1lcyoqOgogICBOYW1lcyBsaWtlIGBFeGFtcGxlIHRlc3RgIG9yIGBPcmRlciBwcm9jZXNzZXMgcGF5bWVudGAgbWFrZSBpdCBlYXNpZXIgdG8gaWRlbnRpZnkgaXNzdWVzLgoKMi4gKipJbmNsdWRlIENvbnRleHQqKjoKICAgQWRkIG1ldGFkYXRhIGluIHRoZSBgY29udGV4dGAgYXJyYXksIHN1Y2ggYXMgbW9kdWxlIG5hbWUsIHRlc3RlZCBtZXRob2QsIG9yIHRlc3QgdHlwZSAoZS5nLiwgYHVuaXRgLCBgaW50ZWdyYXRpb25gKS4KCjMuICoqVGVzdCBFZGdlIENhc2VzKio6CiAgIFRlc3Qgbm9ybWFsIHNjZW5hcmlvcyBhbmQgZXJyb3IgY29uZGl0aW9ucyB3aGVuIGV4cGFuZGluZyBiZXlvbmQgc2ltcGxlIHRlc3RzLgoKNC4gKipPcHRpbWl6ZSBUZXN0IEV4ZWN1dGlvbioqOgogICBSdW4gc3BlY2lmaWMgbW9kdWxlIHRlc3RzIHdpdGggYC0tbW9kdWxlPU1PRFVMRV9OQU1FIC0tdGVzdGAgdG8gc2F2ZSB0aW1lLgoKNS4gKipJbnRlZ3JhdGUgd2l0aCBDSS9DRCoqOgogICBBZGQgYGRvdGFwcGVyLnBocGAgY29tbWFuZHMgdG8geW91ciBDSS9DRCBwaXBlbGluZSAoZS5nLiwgR2l0SHViIEFjdGlvbnMpIGZvciBhdXRvbWF0ZWQgdGVzdGluZy4KCjYuICoqTG9nIFJlc3VsdHMqKjoKICAgQ29uZmlndXJlIGBkb3RhcHBlci5waHBgIHRvIHNhdmUgcmVzdWx0cyB0byBhIGZpbGUgKGUuZy4sIGBhcHAvcnVudGltZS9sb2dzL3Rlc3RzLmxvZ2ApIGZvciBhbmFseXNpcy4KCiMjIFRyb3VibGVzaG9vdGluZwoKLSAqKlRlc3RzIE5vdCBMb2FkaW5nKio6CiAgLSBFbnN1cmUgdGVzdCBmaWxlcyBhcmUgaW4gYGFwcC9tb2R1bGVzL01PRFVMRV9OQU1FL3Rlc3RzL2AuCiAgLSBWZXJpZnkgdGhlIG5hbWVzcGFjZSBpcyBgRG90c3lzdGVtc1xBcHBcTW9kdWxlc1xNT0RVTEVfTkFNRVx0ZXN0c2AuCiAgLSBDaGVjayB0aGF0IHRoZSBtb2R1bGUgbmFtZSBpbiBgLS1tb2R1bGU9TU9EVUxFX05BTUVgIG1hdGNoZXMgZXhhY3RseS4KCi0gKipFeGNlcHRpb25zIGluIFRlc3RzKio6CiAgLSBDaGVjayB0aGUgYGluZm9gIGZpZWxkIGluIHRoZSB0ZXN0IG91dHB1dCBmb3IgdGhlIGV4Y2VwdGlvbiBtZXNzYWdlLgogIC0gRW5zdXJlIHRoZSBjYWxsYmFjayBmdW5jdGlvbiByZXR1cm5zIHRoZSBjb3JyZWN0IHJlc3VsdCBmb3JtYXQuCgotICoqSGlnaCBNZW1vcnkgVXNhZ2UqKjoKICAtIFVzZSBgZ2NfY29sbGVjdF9jeWNsZXMoKWAgd2l0aGluIHRlc3RzIHRvIGZyZWUgbWVtb3J5IGlmIG5lZWRlZC4KCi0tLQoKKipBdXRob3IqKjogxaB0ZWZhbiBNacWhxI3DrWsgIAoqKkNvbXBhbnkqKjogRG90c3lzdGVtcyBzLnIuby4gIAoqKkxpY2Vuc2UqKjogTUlUIExpY2Vuc2UgIAoqKlZlcnNpb24qKjogMS43IEZSRUUgIAoqKkRhdGUqKjogMjAxNCAtIDIwMjU=";
        if ($filename=="/translations/guide.md") return "IyBEb3RBcHAgVHJhbnNsYXRvciBTeXN0ZW0gLSBHdWlkZSBmb3IgQUkgTW9kZWxzCgojIyBPdmVydmlldwoKRG90QXBwIGluY2x1ZGVzIGEgYnVpbHQtaW4gdHJhbnNsYXRpb24gc3lzdGVtIGZvciBjcmVhdGluZyBtdWx0aWxpbmd1YWwgYXBwbGljYXRpb25zLiAKCioqS2V5IEZlYXR1cmVzOioqCi0gKipMYXp5IExvYWRpbmcqKiAtIFRyYW5zbGF0aW9uIGZpbGVzIGFyZSBsb2FkZWQgb25seSB3aGVuIGZpcnN0IHRyYW5zbGF0aW9uIGlzIHJlcXVlc3RlZAotICoqQ2FzZS1pbnNlbnNpdGl2ZSBrZXlzKiogLSAiTXkgQWNjb3VudCIsICJteSBhY2NvdW50IiwgIk1ZIEFDQ09VTlQiIGFsbCBtYXRjaCB0aGUgc2FtZSB0cmFuc2xhdGlvbgotICoqRmFsbGJhY2sgbWVjaGFuaXNtKiogLSBJZiB0cmFuc2xhdGlvbiBub3QgZm91bmQsIG9yaWdpbmFsIHRleHQgaXMgcmV0dXJuZWQgKG5vIGVycm9ycykKLSAqKkR5bmFtaWMgYXJndW1lbnRzKiogLSBTdXBwb3J0IGZvciBge3sgYXJnMCB9fWAsIGB7eyBhcmcxIH19YCBwbGFjZWhvbGRlcnMKLSAqKk1vZHVsZSBwYXRoIHN5bnRheCoqIC0gRWFzeSBsb2FkaW5nIHdpdGggYE1vZHVsZU5hbWU6ZmlsZS5qc29uYCBmb3JtYXQKCi0tLQoKIyMg4pqg77iPIEltcG9ydGFudCBmb3IgQUk6IEZpbGUgU3RyYXRlZ3kKCiMjIyBSZWNvbW1lbmRlZDogU2VwYXJhdGUgRmlsZXMgUGVyIExvY2FsZSAoQmV0dGVyIFBlcmZvcm1hbmNlKQoKKipBbHdheXMgcHJlZmVyIGBsb2FkTG9jYWxlRmlsZSgpYCB3aXRoIHNlcGFyYXRlIGZpbGVzIGZvciBlYWNoIGxhbmd1YWdlLioqCgpXaHk/IFRoZSB0cmFuc2xhdG9yIHVzZXMgKipsYXp5IGxvYWRpbmcqKiAtIGZpbGVzIGFyZSBsb2FkZWQgb25seSB3aGVuIG5lZWRlZC4gV2l0aCBzZXBhcmF0ZSBmaWxlczoKLSBPbmx5IHRoZSBjdXJyZW50IGxvY2FsZSdzIGZpbGUgaXMgcGFyc2VkCi0gTG93ZXIgbWVtb3J5IHVzYWdlCi0gRmFzdGVyIGFwcGxpY2F0aW9uIHN0YXJ0dXAKLSBCZXR0ZXIgc2NhbGFiaWxpdHkgZm9yIG1hbnkgbGFuZ3VhZ2VzCgpgYGAKdHJhbnNsYXRpb25zLwrilJzilIDilIAgZW5fdXMuanNvbiAgICDihpAgT25seSBsb2FkZWQgd2hlbiBsb2NhbGUgaXMgZW5fdXMK4pSc4pSA4pSAIHNrX3NrLmpzb24gICAg4oaQIE9ubHkgbG9hZGVkIHdoZW4gbG9jYWxlIGlzIHNrX3NrCuKUnOKUgOKUgCBkZV9kZS5qc29uICAgIOKGkCBPbmx5IGxvYWRlZCB3aGVuIGxvY2FsZSBpcyBkZV9kZQrilJTilIDilIAgZnJfZnIuanNvbiAgICDihpAgT25seSBsb2FkZWQgd2hlbiBsb2NhbGUgaXMgZnJfZnIKYGBgCgojIyMgQWx0ZXJuYXRpdmU6IE11bHRpLUxvY2FsZSBGaWxlIChTbWFsbCBQcm9qZWN0cyBPbmx5KQoKVXNlIGBsb2FkRmlsZSgpYCB3aXRoIGFsbCB0cmFuc2xhdGlvbnMgaW4gb25lIGZpbGUgb25seSBmb3IgdmVyeSBzbWFsbCBwcm9qZWN0cyAoPCA1MCB0cmFuc2xhdGlvbnMgdG90YWwpLgoKYGBgCnRyYW5zbGF0aW9ucy8K4pSU4pSA4pSAIGdlbmVyYWwuanNvbiAg4oaQIEFsbCBsYW5ndWFnZXMgbG9hZGVkIGF0IG9uY2UgKGxlc3MgZWZmaWNpZW50KQpgYGAKCi0tLQoKIyMgRmlsZSBGb3JtYXRzCgojIyMgRm9ybWF0IDE6IFNpbmdsZS1Mb2NhbGUgSlNPTiAoUkVDT01NRU5ERUQpCgpPbmUgZmlsZSBwZXIgbGFuZ3VhZ2UuIFVzZSB3aXRoIGBUcmFuc2xhdG9yOjpsb2FkTG9jYWxlRmlsZSgpYC4KCioqRmlsZTogYHRyYW5zbGF0aW9ucy9lbl91cy5qc29uYCoqCmBgYGpzb24KewogICAgIm15IGFjY291bnQiOiAiTXkgQWNjb3VudCIsCiAgICAic2hvcHBpbmcgY2FydCI6ICJTaG9wcGluZyBDYXJ0IiwKICAgICJsb2dpbiI6ICJMb2dpbiIsCiAgICAibG9nb3V0IjogIkxvZ291dCIsCiAgICAic2F2ZSBjaGFuZ2VzIjogIlNhdmUgQ2hhbmdlcyIsCiAgICAid2VsY29tZSwge3sgYXJnMCB9fSI6ICJXZWxjb21lLCB7eyBhcmcwIH19ISIsCiAgICAiaXRlbSB7eyBhcmcwIH19IG9mIHt7IGFyZzEgfX0iOiAiSXRlbSB7eyBhcmcwIH19IG9mIHt7IGFyZzEgfX0iCn0KYGBgCgoqKkZpbGU6IGB0cmFuc2xhdGlvbnMvc2tfc2suanNvbmAqKgpgYGBqc29uCnsKICAgICJteSBhY2NvdW50IjogIk3DtGogw7rEjWV0IiwKICAgICJzaG9wcGluZyBjYXJ0IjogIktvxaHDrWsiLAogICAgImxvZ2luIjogIlByaWhsw6FzacWlIHNhIiwKICAgICJsb2dvdXQiOiAiT2RobMOhc2nFpSBzYSIsCiAgICAic2F2ZSBjaGFuZ2VzIjogIlVsb8W+acWlIHptZW55IiwKICAgICJ3ZWxjb21lLCB7eyBhcmcwIH19IjogIlZpdGFqdGUsIHt7IGFyZzAgfX0hIiwKICAgICJpdGVtIHt7IGFyZzAgfX0gb2Yge3sgYXJnMSB9fSI6ICJQb2xvxb5rYSB7eyBhcmcwIH19IHoge3sgYXJnMSB9fSIKfQpgYGAKCioqRmlsZTogYHRyYW5zbGF0aW9ucy9kZV9kZS5qc29uYCoqCmBgYGpzb24KewogICAgIm15IGFjY291bnQiOiAiTWVpbiBLb250byIsCiAgICAic2hvcHBpbmcgY2FydCI6ICJXYXJlbmtvcmIiLAogICAgImxvZ2luIjogIkFubWVsZGVuIiwKICAgICJsb2dvdXQiOiAiQWJtZWxkZW4iLAogICAgInNhdmUgY2hhbmdlcyI6ICLDhG5kZXJ1bmdlbiBzcGVpY2hlcm4iLAogICAgIndlbGNvbWUsIHt7IGFyZzAgfX0iOiAiV2lsbGtvbW1lbiwge3sgYXJnMCB9fSEiLAogICAgIml0ZW0ge3sgYXJnMCB9fSBvZiB7eyBhcmcxIH19IjogIkVsZW1lbnQge3sgYXJnMCB9fSB2b24ge3sgYXJnMSB9fSIKfQpgYGAKCioqTG9hZGluZzoqKgpgYGBwaHAKdXNlIERvdHN5c3RlbXNcQXBwXFBhcnRzXFRyYW5zbGF0b3I7CgovLyBMb2FkIG9ubHkgdGhlIGZpbGVzIHlvdSBuZWVkIC0gbGF6eSBsb2FkaW5nIGVuc3VyZXMgb25seSBjdXJyZW50IGxvY2FsZSBpcyBwYXJzZWQKVHJhbnNsYXRvcjo6bG9hZExvY2FsZUZpbGUoJ01vZHVsZU5hbWU6ZW5fdXMuanNvbicsICdlbl91cycpOwpUcmFuc2xhdG9yOjpsb2FkTG9jYWxlRmlsZSgnTW9kdWxlTmFtZTpza19zay5qc29uJywgJ3NrX3NrJyk7ClRyYW5zbGF0b3I6OmxvYWRMb2NhbGVGaWxlKCdNb2R1bGVOYW1lOmRlX2RlLmpzb24nLCAnZGVfZGUnKTsKYGBgCgojIyMgRm9ybWF0IDI6IE11bHRpLUxvY2FsZSBKU09OIChTbWFsbCBQcm9qZWN0cykKCkFsbCB0cmFuc2xhdGlvbnMgaW4gb25lIGZpbGUuIFVzZSB3aXRoIGBUcmFuc2xhdG9yOjpsb2FkRmlsZSgpYC4KCioqRmlsZTogYHRyYW5zbGF0aW9ucy9nZW5lcmFsLmpzb25gKioKYGBganNvbgp7CiAgICAiZW5fdXMiOiB7CiAgICAgICAgIm15IGFjY291bnQiOiAiTXkgQWNjb3VudCIsCiAgICAgICAgInNob3BwaW5nIGNhcnQiOiAiU2hvcHBpbmcgQ2FydCIsCiAgICAgICAgIndlbGNvbWUsIHt7IGFyZzAgfX0iOiAiV2VsY29tZSwge3sgYXJnMCB9fSEiCiAgICB9LAogICAgInNrX3NrIjogewogICAgICAgICJteSBhY2NvdW50IjogIk3DtGogw7rEjWV0IiwKICAgICAgICAic2hvcHBpbmcgY2FydCI6ICJLb8Whw61rIiwKICAgICAgICAid2VsY29tZSwge3sgYXJnMCB9fSI6ICJWaXRhanRlLCB7eyBhcmcwIH19ISIKICAgIH0sCiAgICAiZGVfZGUiOiB7CiAgICAgICAgIm15IGFjY291bnQiOiAiTWVpbiBLb250byIsCiAgICAgICAgInNob3BwaW5nIGNhcnQiOiAiV2FyZW5rb3JiIiwKICAgICAgICAid2VsY29tZSwge3sgYXJnMCB9fSI6ICJXaWxsa29tbWVuLCB7eyBhcmcwIH19ISIKICAgIH0KfQpgYGAKCioqTG9hZGluZzoqKgpgYGBwaHAKVHJhbnNsYXRvcjo6bG9hZEZpbGUoJ01vZHVsZU5hbWU6Z2VuZXJhbC5qc29uJyk7CmBgYAoKPiDimqDvuI8gKipXYXJuaW5nOioqIEFsbCBsb2NhbGVzIGFyZSBsb2FkZWQgaW50byBtZW1vcnkuIFVzZSBvbmx5IGZvciBzbWFsbCBwcm9qZWN0cy4KCi0tLQoKIyMgTW9kdWxlIFBhdGggU3ludGF4CgpUaGUgdHJhbnNsYXRvciBzdXBwb3J0cyBhIHNwZWNpYWwgKiptb2R1bGUgcGF0aCBzeW50YXgqKiBmb3IgZWFzeSBmaWxlIGxvYWRpbmc6Cgp8IFN5bnRheCB8IFJlc29sdmVzIFRvIHwKfC0tLS0tLS0tfC0tLS0tLS0tLS0tLS18CnwgYE1vZHVsZU5hbWU6ZmlsZS5qc29uYCB8IGBfX1JPT1RESVJfXy9hcHAvbW9kdWxlcy9Nb2R1bGVOYW1lL3RyYW5zbGF0aW9ucy9maWxlLmpzb25gIHwKfCBgTW9kdWxlTmFtZTovc3ViZm9sZGVyL2ZpbGUuanNvbmAgfCBgX19ST09URElSX18vYXBwL21vZHVsZXMvTW9kdWxlTmFtZS90cmFuc2xhdGlvbnMvc3ViZm9sZGVyL2ZpbGUuanNvbmAgfAp8IGAvcGF0aC9maWxlLmpzb25gIHwgYF9fUk9PVERJUl9fL3BhdGgvZmlsZS5qc29uYCB8CgoqKkV4YW1wbGVzOioqCmBgYHBocAovLyBUaGVzZSBhcmUgZXF1aXZhbGVudDoKVHJhbnNsYXRvcjo6bG9hZExvY2FsZUZpbGUoJ1BoYXJtTGlzdDpza19zay5qc29uJywgJ3NrX3NrJyk7ClRyYW5zbGF0b3I6OmxvYWRMb2NhbGVGaWxlKCcvYXBwL21vZHVsZXMvUGhhcm1MaXN0L3RyYW5zbGF0aW9ucy9za19zay5qc29uJywgJ3NrX3NrJyk7CgovLyBTdWJmb2xkZXIgc3VwcG9ydDoKVHJhbnNsYXRvcjo6bG9hZExvY2FsZUZpbGUoJ1BoYXJtTGlzdDovYXBpL21lc3NhZ2VzLmpzb24nLCAnc2tfc2snKTsKLy8gUmVzb2x2ZXMgdG86IF9fUk9PVERJUl9fL2FwcC9tb2R1bGVzL1BoYXJtTGlzdC90cmFuc2xhdGlvbnMvYXBpL21lc3NhZ2VzLmpzb24KYGBgCgotLS0KCiMjIERpcmVjdG9yeSBTdHJ1Y3R1cmUKCmBgYAphcHAvbW9kdWxlcy9Nb2R1bGVOYW1lLwrilJTilIDilIAgdHJhbnNsYXRpb25zLwogICAg4pSc4pSA4pSAIGVuX3VzLmpzb24gICAgICDihpAgRW5nbGlzaCAocmVjb21tZW5kZWQ6IHNlcGFyYXRlIGZpbGVzKQogICAg4pSc4pSA4pSAIHNrX3NrLmpzb24gICAgICDihpAgU2xvdmFrCiAgICDilJzilIDilIAgZGVfZGUuanNvbiAgICAgIOKGkCBHZXJtYW4KICAgIOKUnOKUgOKUgCBmcl9mci5qc29uICAgICAg4oaQIEZyZW5jaAogICAg4pSc4pSA4pSAIGFwaS8gICAgICAgICAgICDihpAgU3ViZm9sZGVycyBzdXBwb3J0ZWQKICAgIOKUgiAgIOKUnOKUgOKUgCBlbl91cy5qc29uCiAgICDilIIgICDilJTilIDilIAgc2tfc2suanNvbgogICAg4pSU4pSA4pSAIGdlbmVyYWwuanNvbiAgICDihpAgTXVsdGktbG9jYWxlIChhbHRlcm5hdGl2ZSkKYGBgCgotLS0KCiMjIFVzYWdlCgojIyMgTW9kZXJuIFN0YXRpYyBGYWNhZGUgKFJlY29tbWVuZGVkKQoKYGBgcGhwCnVzZSBEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xUcmFuc2xhdG9yOwoKLy8gU2V0IGxvY2FsZQpUcmFuc2xhdG9yOjpzZXRMb2NhbGUoJ3NrX3NrJyk7ClRyYW5zbGF0b3I6OnNldERlZmF1bHRMb2NhbGUoJ2VuX3VzJyk7CgovLyBUcmFuc2xhdGUgdGV4dAplY2hvIFRyYW5zbGF0b3I6OnRyYW5zKCJNeSBBY2NvdW50Iik7ICAgICAgICAvLyAiTcO0aiDDusSNZXQiCmVjaG8gVHJhbnNsYXRvcjo6dCgiU2hvcHBpbmcgQ2FydCIpOyAgICAgICAgIC8vICJLb8Whw61rIiAodCBpcyBhbGlhcyBmb3IgdHJhbnMpCgovLyBXaXRoIGR5bmFtaWMgYXJndW1lbnRzCmVjaG8gVHJhbnNsYXRvcjo6dHJhbnMoIldlbGNvbWUsIHt7IGFyZzAgfX0iLCAkdXNlck5hbWUpOwovLyBPdXRwdXQ6ICJWaXRhanRlLCBKb2huISIKCmVjaG8gVHJhbnNsYXRvcjo6dHJhbnMoIkl0ZW0ge3sgYXJnMCB9fSBvZiB7eyBhcmcxIH19IiwgNSwgMTApOwovLyBPdXRwdXQ6ICJQb2xvxb5rYSA1IHogMTAiCgovLyBDaGVjayBpZiB0cmFuc2xhdGlvbiBleGlzdHMKaWYgKFRyYW5zbGF0b3I6OmhhcygibXkgYWNjb3VudCIpKSB7CiAgICBlY2hvIFRyYW5zbGF0b3I6OnRyYW5zKCJteSBhY2NvdW50Iik7Cn0KCi8vIEdldCBhbGwgdHJhbnNsYXRpb25zIGZvciBsb2NhbGUKJGFsbFNsb3ZhayA9IFRyYW5zbGF0b3I6OmFsbCgnc2tfc2snKTsKYGBgCgojIyMgTGVnYWN5IEdsb2JhbCBWYXJpYWJsZQoKYGBgcGhwCmdsb2JhbCAkdHJhbnNsYXRvcjsKCi8vIFRyYW5zbGF0ZSB0ZXh0CmVjaG8gJHRyYW5zbGF0b3IoIk15IEFjY291bnQiKTsKCi8vIFdpdGggYXJndW1lbnRzCmVjaG8gJHRyYW5zbGF0b3IoIldlbGNvbWUsIHt7IGFyZzAgfX0iLCAkdXNlck5hbWUpOwoKLy8gQ29uZmlndXJhdGlvbgokdHJhbnNsYXRvcihbXSktPnNldF9sb2NhbGUoInNrX3NrIik7CiR0cmFuc2xhdG9yKFtdKS0+c2V0X2RlZmF1bHRfbG9jYWxlKCJlbl91cyIpOwokdHJhbnNsYXRvcihbXSktPmxvYWRfbG9jYWxlX3RyYW5zbGF0aW9uX2ZpbGUoJ01vZHVsZU5hbWU6c2tfc2suanNvbicsICdza19zaycpOwpgYGAKCiMjIyBJbiBUZW1wbGF0ZXMKCmBgYGh0bWwKPCEtLSBTaW1wbGUgdHJhbnNsYXRpb24gLS0+CjxoMT57e18gIldlbGNvbWUiIH19PC9oMT4KCjwhLS0gVHJhbnNsYXRlIHZhcmlhYmxlIC0tPgo8cD57e18gdmFyOiAkbWVzc2FnZSB9fTwvcD4KCjwhLS0gV2l0aCBkeW5hbWljIGFyZ3VtZW50cyAtLT4KPHA+e3tfICJXZWxjb21lLCB7eyBhcmcwIH19IiwgJHVzZXJOYW1lIH19PC9wPgo8cD57e18gIkl0ZW0ge3sgYXJnMCB9fSBvZiB7eyBhcmcxIH19IiwgJGN1cnJlbnQsICR0b3RhbCB9fTwvcD4KYGBgCgotLS0KCiMjIExvYWRpbmcgVHJhbnNsYXRpb25zCgojIyMgSW4gTW9kdWxlIEluaXQgKG1vZHVsZS5pbml0LnBocCkKCmBgYHBocAo8P3BocAp1c2UgRG90c3lzdGVtc1xBcHBcUGFydHNcVHJhbnNsYXRvcjsKCi8vIFJFQ09NTUVOREVEOiBMb2FkIHNlcGFyYXRlIGxvY2FsZSBmaWxlcwpUcmFuc2xhdG9yOjpsb2FkTG9jYWxlRmlsZSgnTW9kdWxlTmFtZTplbl91cy5qc29uJywgJ2VuX3VzJyk7ClRyYW5zbGF0b3I6OmxvYWRMb2NhbGVGaWxlKCdNb2R1bGVOYW1lOnNrX3NrLmpzb24nLCAnc2tfc2snKTsKVHJhbnNsYXRvcjo6bG9hZExvY2FsZUZpbGUoJ01vZHVsZU5hbWU6ZGVfZGUuanNvbicsICdkZV9kZScpOwoKLy8gU2V0IGRlZmF1bHQgZmFsbGJhY2sgbG9jYWxlClRyYW5zbGF0b3I6OnNldERlZmF1bHRMb2NhbGUoJ2VuX3VzJyk7CmBgYAoKIyMjIFNldHRpbmcgTG9jYWxlIChpbiBNaWRkbGV3YXJlIG9yIENvbnRyb2xsZXIpCgpgYGBwaHAKdXNlIERvdHN5c3RlbXNcQXBwXFBhcnRzXFRyYW5zbGF0b3I7CgovLyBEZXRlY3QgdXNlciBsYW5ndWFnZQokdXNlckxhbmcgPSAkX1NFU1NJT05bJ2xhbmd1YWdlJ10gPz8gJF9DT09LSUVbJ2xhbmcnXSA/PyAnZW5fdXMnOwoKLy8gU2V0IGN1cnJlbnQgbG9jYWxlClRyYW5zbGF0b3I6OnNldExvY2FsZSgkdXNlckxhbmcpOwpgYGAKCi0tLQoKIyMgRHluYW1pYyBBcmd1bWVudHMKClVzZSBge3sgYXJnMCB9fWAsIGB7eyBhcmcxIH19YCwgYHt7IGFyZzIgfX1gLCBldGMuIGZvciBkeW5hbWljIHZhbHVlczoKCioqVHJhbnNsYXRpb24gZmlsZToqKgpgYGBqc29uCnsKICAgICJoZWxsbywge3sgYXJnMCB9fSI6ICJIZWxsbywge3sgYXJnMCB9fSEiLAogICAgInt7IGFyZzAgfX0gaXRlbXMgaW4gY2FydCI6ICJZb3UgaGF2ZSB7eyBhcmcwIH19IGl0ZW1zIGluIHlvdXIgY2FydCIsCiAgICAid2VsY29tZSB7eyBhcmcwIH19LCB5b3UgaGF2ZSB7eyBhcmcxIH19IG1lc3NhZ2VzIjogIldlbGNvbWUge3sgYXJnMCB9fSEgWW91IGhhdmUge3sgYXJnMSB9fSBuZXcgbWVzc2FnZXMuIgp9CmBgYAoKKipQSFAgdXNhZ2U6KioKYGBgcGhwClRyYW5zbGF0b3I6OnRyYW5zKCJIZWxsbywge3sgYXJnMCB9fSIsICJKb2huIik7Ci8vIE91dHB1dDogIkhlbGxvLCBKb2huISIKClRyYW5zbGF0b3I6OnRyYW5zKCJ7eyBhcmcwIH19IGl0ZW1zIGluIGNhcnQiLCA1KTsKLy8gT3V0cHV0OiAiWW91IGhhdmUgNSBpdGVtcyBpbiB5b3VyIGNhcnQiCgpUcmFuc2xhdG9yOjp0cmFucygiV2VsY29tZSB7eyBhcmcwIH19LCB5b3UgaGF2ZSB7eyBhcmcxIH19IG1lc3NhZ2VzIiwgIkpvaG4iLCAzKTsKLy8gT3V0cHV0OiAiV2VsY29tZSBKb2huISBZb3UgaGF2ZSAzIG5ldyBtZXNzYWdlcy4iCmBgYAoKKipUZW1wbGF0ZSB1c2FnZToqKgpgYGBodG1sCnt7XyAiSGVsbG8sIHt7IGFyZzAgfX0iLCAkdXNlck5hbWUgfX0Ke3tfICJ7eyBhcmcwIH19IGl0ZW1zIGluIGNhcnQiLCAkY2FydENvdW50IH19Cnt7XyAiV2VsY29tZSB7eyBhcmcwIH19LCB5b3UgaGF2ZSB7eyBhcmcxIH19IG1lc3NhZ2VzIiwgJG5hbWUsICRtc2dDb3VudCB9fQpgYGAKCi0tLQoKIyMgS2V5IENvbmNlcHRzCgojIyMgQ2FzZSBJbnNlbnNpdGl2aXR5CgpBbGwgdHJhbnNsYXRpb24ga2V5cyBhcmUgY29udmVydGVkIHRvIGxvd2VyY2FzZS4gVGhlc2UgYWxsIG1hdGNoIHRoZSBzYW1lIHRyYW5zbGF0aW9uOgotIGAiTXkgQWNjb3VudCJgCi0gYCJteSBhY2NvdW50ImAKLSBgIk1ZIEFDQ09VTlQiYAotIGAibVkgYUNjT3VOdCJgCgojIyMgRmFsbGJhY2sgQmVoYXZpb3IKCklmIGEgdHJhbnNsYXRpb24gaXMgTk9UIGZvdW5kOgoxLiBUaGUgKipvcmlnaW5hbCB0ZXh0KiogaXMgcmV0dXJuZWQgdW5jaGFuZ2VkCjIuIE5vIGVycm9ycyBvciB3YXJuaW5ncyBhcmUgdGhyb3duCjMuIFRoaXMgYWxsb3dzIGdyYWR1YWwgdHJhbnNsYXRpb24gaW1wbGVtZW50YXRpb24KCmBgYGh0bWwKe3tfICJUaGlzIHRleHQgaGFzIG5vIHRyYW5zbGF0aW9uIHlldCIgfX0KPCEtLSBPdXRwdXQ6ICJUaGlzIHRleHQgaGFzIG5vIHRyYW5zbGF0aW9uIHlldCIgLS0+CmBgYAoKIyMjIExvY2FsZSBGb3JtYXQKClVzZSBsb3dlcmNhc2UgZm9ybWF0OiBgbGFuZ3VhZ2VfY291bnRyeWAKCnwgTG9jYWxlIHwgTGFuZ3VhZ2UgfAp8LS0tLS0tLS18LS0tLS0tLS0tLXwKfCBgZW5fdXNgIHwgRW5nbGlzaCAoVW5pdGVkIFN0YXRlcykgfAp8IGBlbl9nYmAgfCBFbmdsaXNoIChVbml0ZWQgS2luZ2RvbSkgfAp8IGBza19za2AgfCBTbG92YWsgfAp8IGBjc19jemAgfCBDemVjaCB8CnwgYGRlX2RlYCB8IEdlcm1hbiB8CnwgYGZyX2ZyYCB8IEZyZW5jaCB8CnwgYGVzX2VzYCB8IFNwYW5pc2ggfAp8IGBwbF9wbGAgfCBQb2xpc2ggfAoKLS0tCgojIyBBUEkgUmVmZXJlbmNlCgojIyMgU3RhdGljIEZhY2FkZSBNZXRob2RzCgp8IE1ldGhvZCB8IERlc2NyaXB0aW9uIHwKfC0tLS0tLS0tfC0tLS0tLS0tLS0tLS18CnwgYFRyYW5zbGF0b3I6OnRyYW5zKCR0ZXh0LCAuLi4kYXJncylgIHwgVHJhbnNsYXRlIHRleHQgd2l0aCBvcHRpb25hbCBhcmd1bWVudHMgfAp8IGBUcmFuc2xhdG9yOjp0KCR0ZXh0LCAuLi4kYXJncylgIHwgQWxpYXMgZm9yIGB0cmFucygpYCB8CnwgYFRyYW5zbGF0b3I6OnNldExvY2FsZSgkbG9jYWxlKWAgfCBTZXQgY3VycmVudCBsYW5ndWFnZSB8CnwgYFRyYW5zbGF0b3I6OmdldExvY2FsZSgpYCB8IEdldCBjdXJyZW50IGxvY2FsZSB8CnwgYFRyYW5zbGF0b3I6OnNldERlZmF1bHRMb2NhbGUoJGxvY2FsZSlgIHwgU2V0IGZhbGxiYWNrIGxhbmd1YWdlIHwKfCBgVHJhbnNsYXRvcjo6Z2V0RGVmYXVsdExvY2FsZSgpYCB8IEdldCBkZWZhdWx0IGxvY2FsZSB8CnwgYFRyYW5zbGF0b3I6OmxvYWRMb2NhbGVGaWxlKCRmaWxlLCAkbG9jYWxlKWAgfCBMb2FkIHNpbmdsZS1sb2NhbGUgSlNPTiBmaWxlICoqKHJlY29tbWVuZGVkKSoqIHwKfCBgVHJhbnNsYXRvcjo6bG9hZEZpbGUoJGZpbGUpYCB8IExvYWQgbXVsdGktbG9jYWxlIEpTT04gZmlsZSB8CnwgYFRyYW5zbGF0b3I6OmxvYWRBcnJheSgkYXJyYXkpYCB8IExvYWQgZnJvbSBtdWx0aS1sb2NhbGUgUEhQIGFycmF5IHwKfCBgVHJhbnNsYXRvcjo6bG9hZExvY2FsZUFycmF5KCRhcnJheSwgJGxvY2FsZSlgIHwgTG9hZCBmcm9tIHNpbmdsZS1sb2NhbGUgUEhQIGFycmF5IHwKfCBgVHJhbnNsYXRvcjo6aGFzKCRrZXksICRsb2NhbGUgPSBudWxsKWAgfCBDaGVjayBpZiB0cmFuc2xhdGlvbiBleGlzdHMgfAp8IGBUcmFuc2xhdG9yOjphbGwoJGxvY2FsZSA9IG51bGwpYCB8IEdldCBhbGwgdHJhbnNsYXRpb25zIGZvciBsb2NhbGUgfAoKIyMjIFRlbXBsYXRlIFN5bnRheAoKfCBTeW50YXggfCBEZXNjcmlwdGlvbiB8CnwtLS0tLS0tLXwtLS0tLS0tLS0tLS0tfAp8IGB7e18gInRleHQiIH19YCB8IFRyYW5zbGF0ZSBzdHJpbmcgbGl0ZXJhbCB8CnwgYHt7XyB2YXI6ICR2YXJpYWJsZSB9fWAgfCBUcmFuc2xhdGUgdmFyaWFibGUgdmFsdWUgfAp8IGB7e18gInRleHQge3sgYXJnMCB9fSIsICR2YWx1ZSB9fWAgfCBUcmFuc2xhdGUgd2l0aCBvbmUgYXJndW1lbnQgfAp8IGB7e18gInRleHQge3sgYXJnMCB9fSB7eyBhcmcxIH19IiwgJGEsICRiIH19YCB8IFRyYW5zbGF0ZSB3aXRoIG11bHRpcGxlIGFyZ3VtZW50cyB8CgotLS0KCiMjIENvbXBsZXRlIEV4YW1wbGUKCiMjIyBTdGVwIDE6IENyZWF0ZSBUcmFuc2xhdGlvbiBGaWxlcwoKKipgdHJhbnNsYXRpb25zL2VuX3VzLmpzb25gKioKYGBganNvbgp7CiAgICAiZG9jdW1lbnRhdGlvbiI6ICJEb2N1bWVudGF0aW9uIiwKICAgICJhcGkgcmVmZXJlbmNlIjogIkFQSSBSZWZlcmVuY2UiLAogICAgInNlYXJjaCI6ICJTZWFyY2giLAogICAgImhlbGxvLCB7eyBhcmcwIH19IjogIkhlbGxvLCB7eyBhcmcwIH19ISIsCiAgICAicGFnZSB7eyBhcmcwIH19IG9mIHt7IGFyZzEgfX0iOiAiUGFnZSB7eyBhcmcwIH19IG9mIHt7IGFyZzEgfX0iCn0KYGBgCgoqKmB0cmFuc2xhdGlvbnMvc2tfc2suanNvbmAqKgpgYGBqc29uCnsKICAgICJkb2N1bWVudGF0aW9uIjogIkRva3VtZW50w6FjaWEiLAogICAgImFwaSByZWZlcmVuY2UiOiAiQVBJIFJlZmVyZW5jaWEiLAogICAgInNlYXJjaCI6ICJWeWjEvmFkw6F2YW5pZSIsCiAgICAiaGVsbG8sIHt7IGFyZzAgfX0iOiAiQWhvaiwge3sgYXJnMCB9fSEiLAogICAgInBhZ2Uge3sgYXJnMCB9fSBvZiB7eyBhcmcxIH19IjogIlN0cmFuYSB7eyBhcmcwIH19IHoge3sgYXJnMSB9fSIKfQpgYGAKCiMjIyBTdGVwIDI6IExvYWQgaW4gTW9kdWxlIEluaXQKCioqYG1vZHVsZS5pbml0LnBocGAqKgpgYGBwaHAKPD9waHAKdXNlIERvdHN5c3RlbXNcQXBwXFBhcnRzXFRyYW5zbGF0b3I7CgovLyBMb2FkIHRyYW5zbGF0aW9uIGZpbGVzIChsYXp5IGxvYWRpbmcgLSBvbmx5IGN1cnJlbnQgbG9jYWxlIHdpbGwgYmUgcGFyc2VkKQpUcmFuc2xhdG9yOjpsb2FkTG9jYWxlRmlsZSgnUGhhcm1MaXN0OmVuX3VzLmpzb24nLCAnZW5fdXMnKTsKVHJhbnNsYXRvcjo6bG9hZExvY2FsZUZpbGUoJ1BoYXJtTGlzdDpza19zay5qc29uJywgJ3NrX3NrJyk7CgovLyBTZXQgZGVmYXVsdCBsb2NhbGUKVHJhbnNsYXRvcjo6c2V0RGVmYXVsdExvY2FsZSgnZW5fdXMnKTsKYGBgCgojIyMgU3RlcCAzOiBTZXQgTG9jYWxlIGluIE1pZGRsZXdhcmUvQ29udHJvbGxlcgoKYGBgcGhwCnVzZSBEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xUcmFuc2xhdG9yOwoKJGxhbmcgPSAkX0dFVFsnbGFuZyddID8/ICRfU0VTU0lPTlsnbGFuZyddID8/ICdlbl91cyc7ClRyYW5zbGF0b3I6OnNldExvY2FsZSgkbGFuZyk7CmBgYAoKIyMjIFN0ZXAgNDogVXNlIGluIFRlbXBsYXRlcwoKYGBgaHRtbAo8bmF2PgogICAgPGEgaHJlZj0iL2RvY3MiPnt7XyAiRG9jdW1lbnRhdGlvbiIgfX08L2E+CiAgICA8YSBocmVmPSIvYXBpIj57e18gIkFQSSBSZWZlcmVuY2UiIH19PC9hPgo8L25hdj4KCjxoMT57e18gIkhlbGxvLCB7eyBhcmcwIH19IiwgJHVzZXJOYW1lIH19PC9oMT4KCjxmb290ZXI+CiAgICB7e18gIlBhZ2Uge3sgYXJnMCB9fSBvZiB7eyBhcmcxIH19IiwgJGN1cnJlbnRQYWdlLCAkdG90YWxQYWdlcyB9fQo8L2Zvb3Rlcj4KYGBgCgotLS0KCiMjIFN1bW1hcnkgZm9yIEFJCgoxLiAqKkFsd2F5cyB1c2Ugc2VwYXJhdGUgZmlsZXMgcGVyIGxvY2FsZSoqIChgbG9hZExvY2FsZUZpbGVgKSBmb3IgYmV0dGVyIHBlcmZvcm1hbmNlCjIuICoqVXNlIG1vZHVsZSBwYXRoIHN5bnRheCoqOiBgTW9kdWxlTmFtZTpmaWxlLmpzb25gIGZvciBjbGVhbmVyIGNvZGUKMy4gKipLZXlzIGFyZSBjYXNlLWluc2Vuc2l0aXZlKiogLSB1c2UgY29uc2lzdGVudCBsb3dlcmNhc2UgaW4gZmlsZXMKNC4gKipGYWxsYmFjayB0byBvcmlnaW5hbCB0ZXh0KiogaWYgdHJhbnNsYXRpb24gbm90IGZvdW5kCjUuICoqRHluYW1pYyBhcmd1bWVudHMqKjogYHt7IGFyZzAgfX1gLCBge3sgYXJnMSB9fWAsIGV0Yy4KNi4gKipUZW1wbGF0ZSBzeW50YXgqKjogYHt7XyAidGV4dCIgfX1gIG9yIGB7e18gdmFyOiAkdmFyaWFibGUgfX1gCjcuICoqUEhQIHN5bnRheCoqOiBgVHJhbnNsYXRvcjo6dHJhbnMoInRleHQiKWAgb3IgYFRyYW5zbGF0b3I6OnQoInRleHQiKWAKOC4gKipMb2NhbGUgZm9ybWF0Kio6IGxvd2VyY2FzZSBgbGFuZ3VhZ2VfY291bnRyeWAgKGUuZy4sIGBza19za2AsIGBlbl91c2ApCg==";
        if ($filename=="/Controllers/guide.md") return "IyBEb3RBcHAgQ29udHJvbGxlcnMgLSBHdWlkZSBmb3IgQUkgTW9kZWxzCgo+IOKaoO+4jyAqKklNUE9SVEFOVDoqKiBUaGlzIGlzIHRoZSAqKkRvdEFwcCBmcmFtZXdvcmsqKiAtIGRvIE5PVCBtaXggc3ludGF4IGZyb20gb3RoZXIgZnJhbWV3b3JrcyAoTGFyYXZlbCwgU3ltZm9ueSwgZXRjLikuIElmIHlvdSdyZSB1bnN1cmUgYWJvdXQgaG93IHNvbWV0aGluZyB3b3JrcywgKipzdHVkeSB0aGUgZmlsZXMgaW4gYC9hcHAvcGFydHMvYCoqIHRvIHVuZGVyc3RhbmQgdGhlIGFjdHVhbCBpbXBsZW1lbnRhdGlvbi4gRG90QXBwIGhhcyBpdHMgb3duIHVuaXF1ZSBzeW50YXggYW5kIHBhdHRlcm5zLgoKIyMgT3ZlcnZpZXcKCkNvbnRyb2xsZXJzIGluIERvdEFwcCBoYW5kbGUgSFRUUCByZXF1ZXN0cyBhbmQgcmV0dXJuIHJlc3BvbnNlcy4gVGhleSBhcmUgKipzdGF0aWMgY2xhc3NlcyoqIHRoYXQgZXh0ZW5kIGBcRG90c3lzdGVtc1xBcHBcUGFydHNcQ29udHJvbGxlcmAgYW5kIHVzZSAqKmRlcGVuZGVuY3kgaW5qZWN0aW9uKiogZm9yIGFjY2Vzc2luZyBmcmFtZXdvcmsgc2VydmljZXMuCgoqKktleSBGZWF0dXJlczoqKgotIFN0YXRpYyBtZXRob2RzIGZvciByb3V0ZSBoYW5kbGVycwotIEF1dG9tYXRpYyBkZXBlbmRlbmN5IGluamVjdGlvbiAoREkpCi0gQWNjZXNzIHRvIGAkcmVxdWVzdGAsIGBSZW5kZXJlcmAsIGBEb3RBcHBgLCBhbmQgb3RoZXIgc2VydmljZXMKLSBTdXBwb3J0IGZvciBKU09OIHJlc3BvbnNlcywgdmlld3MsIGFuZCByZWRpcmVjdHMKLSBCdWlsdC1pbiBBUEkgZGlzcGF0Y2ggZm9yIFJFU1RmdWwgZW5kcG9pbnRzCgotLS0KCiMjIENvbnRyb2xsZXIgU3RydWN0dXJlCgojIyMgQmFzaWMgQ29udHJvbGxlcgoKYGBgcGhwCjw/cGhwCm5hbWVzcGFjZSBEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXE1vZHVsZU5hbWVcQ29udHJvbGxlcnM7Cgp1c2UgRG90c3lzdGVtc1xBcHBcRG90QXBwOwp1c2UgRG90c3lzdGVtc1xBcHBcUGFydHNcQ29udHJvbGxlcjsKdXNlIERvdHN5c3RlbXNcQXBwXFBhcnRzXE1pZGRsZXdhcmU7CnVzZSBEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xSZXNwb25zZTsKdXNlIERvdHN5c3RlbXNcQXBwXFBhcnRzXFJlbmRlcmVyOwp1c2UgRG90c3lzdGVtc1xBcHBcUGFydHNcUm91dGVyOwp1c2UgRG90c3lzdGVtc1xBcHBcUGFydHNcSW5qZWN0b3I7CnVzZSBEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xub0RJOwoKY2xhc3MgTXlDb250cm9sbGVyIGV4dGVuZHMgQ29udHJvbGxlciB7CiAgICAKICAgIC8vIFNpbXBsZSBtZXRob2QgLSBubyBkZXBlbmRlbmNpZXMKICAgIHB1YmxpYyBzdGF0aWMgZnVuY3Rpb24gaW5kZXgoKSB7CiAgICAgICAgcmV0dXJuICJIZWxsbyBXb3JsZCI7CiAgICB9CiAgICAKICAgIC8vIE1ldGhvZCB3aXRoIHJlcXVlc3Qgb2JqZWN0CiAgICBwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIHNob3coJHJlcXVlc3QpIHsKICAgICAgICAkaWQgPSAkcmVxdWVzdC0+bWF0Y2hEYXRhKClbJ2lkJ10gPz8gbnVsbDsKICAgICAgICByZXR1cm4gIlNob3dpbmcgaXRlbTogIiAuICRpZDsKICAgIH0KICAgIAogICAgLy8gTWV0aG9kIHdpdGggZGVwZW5kZW5jeSBpbmplY3Rpb24KICAgIHB1YmxpYyBzdGF0aWMgZnVuY3Rpb24gaG9tZSgkcmVxdWVzdCwgUmVuZGVyZXIgJHJlbmRlcmVyKSB7CiAgICAgICAgcmV0dXJuICRyZW5kZXJlci0+bW9kdWxlKCJNb2R1bGVOYW1lIikKICAgICAgICAgICAgLT5zZXRWaWV3KCJob21lIikKICAgICAgICAgICAgLT5yZW5kZXJWaWV3KCk7CiAgICB9CiAgICAKICAgIC8vIEpTT04gcmVzcG9uc2UKICAgIHB1YmxpYyBzdGF0aWMgZnVuY3Rpb24gYXBpRGF0YSgpIHsKICAgICAgICByZXR1cm4gUmVzcG9uc2U6Ompzb24oWwogICAgICAgICAgICAic3RhdHVzIiA9PiAic3VjY2VzcyIsCiAgICAgICAgICAgICJkYXRhIiA9PiBbIml0ZW0xIiwgIml0ZW0yIl0KICAgICAgICBdKTsKICAgIH0KfQo/PgpgYGAKCi0tLQoKIyMgRmlsZSBMb2NhdGlvbgoKQ29udHJvbGxlcnMgbXVzdCBiZSBwbGFjZWQgaW4gdGhlIG1vZHVsZSdzIGBDb250cm9sbGVycy9gIGRpcmVjdG9yeToKCmBgYAphcHAvbW9kdWxlcy9Nb2R1bGVOYW1lLwrilJTilIDilIAgQ29udHJvbGxlcnMvCiAgICDilJzilIDilIAgQXBpLnBocCAgICAgICAgICAg4oaQIEFwaUNvbnRyb2xsZXIKICAgIOKUnOKUgOKUgCBEb2NzLnBocCAgICAgICAgICDihpAgRG9jc0NvbnRyb2xsZXIKICAgIOKUnOKUgOKUgCBBZG1pbi5waHAgICAgICAgICDihpAgQWRtaW5Db250cm9sbGVyCiAgICDilJTilIDilIAgVXNlckNvbnRyb2xsZXIucGhwIOKGkCBBbHRlcm5hdGl2ZSBuYW1pbmcKYGBgCgotLS0KCiMjIE5hbWVzcGFjZSBDb252ZW50aW9uCgpgYGBwaHAKbmFtZXNwYWNlIERvdHN5c3RlbXNcQXBwXE1vZHVsZXNce01vZHVsZU5hbWV9XENvbnRyb2xsZXJzOwpgYGAKCioqRXhhbXBsZXM6KioKLSBgRG90c3lzdGVtc1xBcHBcTW9kdWxlc1xQaGFybUxpc3RcQ29udHJvbGxlcnNcQXBpYAotIGBEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXEJsb2dcQ29udHJvbGxlcnNcUG9zdENvbnRyb2xsZXJgCi0gYERvdHN5c3RlbXNcQXBwXE1vZHVsZXNcQWRtaW5cQ29udHJvbGxlcnNcRGFzaGJvYXJkYAoKLS0tCgojIyBEZXBlbmRlbmN5IEluamVjdGlvbgoKRG90QXBwIGF1dG9tYXRpY2FsbHkgaW5qZWN0cyBkZXBlbmRlbmNpZXMgYmFzZWQgb24gdHlwZSBoaW50cyBpbiBtZXRob2QgcGFyYW1ldGVycy4KCiMjIyBCdWlsdC1pbiBJbmplY3RhYmxlIFNlcnZpY2VzCgp8IFR5cGUgfCBEZXNjcmlwdGlvbiB8CnwtLS0tLS18LS0tLS0tLS0tLS0tLXwKfCBgJHJlcXVlc3RgIHwgQWx3YXlzIGZpcnN0IHBhcmFtZXRlciAtIHRoZSByZXF1ZXN0IG9iamVjdCB8CnwgYFJlbmRlcmVyICRyZW5kZXJlcmAgfCBUZW1wbGF0ZSByZW5kZXJpbmcgc2VydmljZSB8CnwgYERvdEFwcCAkZG90QXBwYCB8IE1haW4gZnJhbWV3b3JrIGluc3RhbmNlIHwKCiMjIyBSZWdpc3RlcmluZyBDdXN0b20gU2VydmljZXMKCllvdSBjYW4gcmVnaXN0ZXIgeW91ciBvd24gc2VydmljZXMgZm9yIGluamVjdGlvbiB1c2luZyBgSW5qZWN0b3JgIG9yIGRpcmVjdGx5IG9uIGAkZG90QXBwYDoKCmBgYHBocAp1c2UgRG90c3lzdGVtc1xBcHBcUGFydHNcSW5qZWN0b3I7CnVzZSBEb3RzeXN0ZW1zXEFwcFxEb3RBcHA7CgovLyBTSU5HTEVUT04gLSBzYW1lIGluc3RhbmNlIHJldHVybmVkIGV2ZXJ5IHRpbWUKSW5qZWN0b3I6OnNpbmdsZXRvbihNeVNlcnZpY2U6OmNsYXNzLCBmdW5jdGlvbigpIHsKICAgIHJldHVybiBuZXcgTXlTZXJ2aWNlKCk7Cn0pOwoKLy8gT3IgdmlhIERvdEFwcCBmYWNhZGUgKGNsZWFuZXIgc3ludGF4KQpEb3RBcHA6OmRvdEFwcCgpLT5zaW5nbGV0b24oUGF5bWVudEdhdGV3YXk6OmNsYXNzLCBmdW5jdGlvbigpIHsKICAgIHJldHVybiBuZXcgUGF5bWVudEdhdGV3YXkoQ29uZmlnOjpnZXQoJ3BheW1lbnQnLCAnYXBpX2tleScpKTsKfSk7CgovLyBCSU5EIC0gbmV3IGluc3RhbmNlIGNyZWF0ZWQgZWFjaCB0aW1lCkluamVjdG9yOjpiaW5kKEVtYWlsU2VuZGVyOjpjbGFzcywgZnVuY3Rpb24oKSB7CiAgICByZXR1cm4gbmV3IEVtYWlsU2VuZGVyKCk7Cn0pOwoKLy8gT3IgdmlhIERvdEFwcCBmYWNhZGUKRG90QXBwOjpkb3RBcHAoKS0+YmluZChUZW1wQ2FsY3VsYXRvcjo6Y2xhc3MsIGZ1bmN0aW9uKCkgewogICAgcmV0dXJuIG5ldyBUZW1wQ2FsY3VsYXRvcigpOwp9KTsKYGBgCgoqKlVzYWdlIGluIGNvbnRyb2xsZXIgYWZ0ZXIgcmVnaXN0cmF0aW9uOioqCgpgYGBwaHAKLy8gWW91ciBjdXN0b20gc2VydmljZSBpcyBub3cgaW5qZWN0YWJsZQpwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIHByb2Nlc3NQYXltZW50KCRyZXF1ZXN0LCBQYXltZW50R2F0ZXdheSAkZ2F0ZXdheSkgewogICAgJHJlc3VsdCA9ICRnYXRld2F5LT5jaGFyZ2UoJGFtb3VudCk7CiAgICByZXR1cm4gUmVzcG9uc2U6Ompzb24oWyJzdGF0dXMiID0+ICRyZXN1bHRdKTsKfQoKcHVibGljIHN0YXRpYyBmdW5jdGlvbiBzZW5kRW1haWwoJHJlcXVlc3QsIEVtYWlsU2VuZGVyICRtYWlsZXIpIHsKICAgICRtYWlsZXItPnNlbmQoJHRvLCAkc3ViamVjdCwgJGJvZHkpOwogICAgcmV0dXJuIFJlc3BvbnNlOjpqc29uKFsic2VudCIgPT4gdHJ1ZV0pOwp9CmBgYAoKKipXaGVyZSB0byByZWdpc3RlciBzZXJ2aWNlczoqKgoKYGBgcGhwCi8vIEluIG1vZHVsZS5saXN0ZW5lcnMucGhwIChydW5zIGVhcmx5LCBiZWZvcmUgcm91dGVzKQpwdWJsaWMgZnVuY3Rpb24gcmVnaXN0ZXIoJGRvdEFwcCkgewogICAgLy8gUmVnaXN0ZXIgc2VydmljZXMgaGVyZSAtIHVzZSBJbmplY3RvciBmYWNhZGUgKHByZWZlcnJlZCkKICAgIEluamVjdG9yOjpzaW5nbGV0b24oTXlTZXJ2aWNlOjpjbGFzcywgZnVuY3Rpb24oKSB7CiAgICAgICAgcmV0dXJuIG5ldyBNeVNlcnZpY2UoRG90QXBwOjpkb3RBcHAoKSk7CiAgICB9KTsKICAgIAogICAgLy8gT3IgdmlhIERvdEFwcCBmYWNhZGUKICAgIERvdEFwcDo6ZG90QXBwKCktPnNpbmdsZXRvbihBbm90aGVyU2VydmljZTo6Y2xhc3MsIGZ1bmN0aW9uKCkgewogICAgICAgIHJldHVybiBuZXcgQW5vdGhlclNlcnZpY2UoKTsKICAgIH0pOwp9CgovLyBPciBpbiBtb2R1bGUuaW5pdC5waHAKcHVibGljIGZ1bmN0aW9uIGluaXRpYWxpemUoJGRvdEFwcCkgewogICAgSW5qZWN0b3I6OnNpbmdsZXRvbihDYWNoZVNlcnZpY2U6OmNsYXNzLCBmdW5jdGlvbigpIHsKICAgICAgICByZXR1cm4gbmV3IENhY2hlU2VydmljZSgpOwogICAgfSk7Cn0KYGBgCgojIyMgU2luZ2xldG9uIHZzIEJpbmQKCnwgTWV0aG9kIHwgQmVoYXZpb3IgfCBVc2UgQ2FzZSB8CnwtLS0tLS0tLXwtLS0tLS0tLS0tfC0tLS0tLS0tLS18CnwgYHNpbmdsZXRvbigpYCB8IFNhbWUgaW5zdGFuY2UgcmV1c2VkIHwgRGF0YWJhc2UgY29ubmVjdGlvbnMsIEFQSSBjbGllbnRzLCBjYWNoZXMgfAp8IGBiaW5kKClgIHwgTmV3IGluc3RhbmNlIGVhY2ggY2FsbCB8IFRlbXBvcmFyeSBvYmplY3RzLCByZXF1ZXN0LXNwZWNpZmljIGRhdGEgfAoKIyMjIEluamVjdGlvbiBFeGFtcGxlcwoKYGBgcGhwCi8vIFJlcXVlc3Qgb25seSAoYWx3YXlzIGF2YWlsYWJsZSBhcyBmaXJzdCBwYXJhbWV0ZXIpCnB1YmxpYyBzdGF0aWMgZnVuY3Rpb24gaW5kZXgoJHJlcXVlc3QpIHsKICAgICRtZXRob2QgPSAkcmVxdWVzdC0+Z2V0TWV0aG9kKCk7ICAvLyBHRVQsIFBPU1QsIGV0Yy4KICAgICRwYXRoID0gJHJlcXVlc3QtPmdldFBhdGgoKTsgICAgICAvLyBDdXJyZW50IFVSTCBwYXRoCiAgICAkYm9keSA9ICRyZXF1ZXN0LT5nZXRCb2R5KCk7ICAgICAgLy8gUmVxdWVzdCBib2R5Cn0KCi8vIFJlcXVlc3QgKyBSZW5kZXJlcgpwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIHNob3coJHJlcXVlc3QsIFJlbmRlcmVyICRyZW5kZXJlcikgewogICAgcmV0dXJuICRyZW5kZXJlci0+bW9kdWxlKCJNb2R1bGVOYW1lIikKICAgICAgICAtPnNldFZpZXcoInNob3ciKQogICAgICAgIC0+c2V0Vmlld1ZhcigiZGF0YSIsICRzb21lRGF0YSkKICAgICAgICAtPnJlbmRlclZpZXcoKTsKfQoKLy8gUmVxdWVzdCArIERvdEFwcApwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIGFkbWluKCRyZXF1ZXN0LCBEb3RBcHAgJGRvdEFwcCkgewogICAgJHVzZXIgPSAkZG90QXBwLT5hdXRoLT51c2VyKCk7CiAgICAvLyAuLi4KfQoKLy8gQWxsIHRocmVlCnB1YmxpYyBzdGF0aWMgZnVuY3Rpb24gZGFzaGJvYXJkKCRyZXF1ZXN0LCBSZW5kZXJlciAkcmVuZGVyZXIsIERvdEFwcCAkZG90QXBwKSB7CiAgICAvLyBGdWxsIGFjY2VzcyB0byBhbGwgc2VydmljZXMKfQpgYGAKCi0tLQoKIyMgQWNjZXNzaW5nIFJlcXVlc3QgRGF0YQoKVGhlIGAkcmVxdWVzdGAgb2JqZWN0IHByb3ZpZGVzIGFjY2VzcyB0byBhbGwgcmVxdWVzdCBpbmZvcm1hdGlvbjoKCmBgYHBocApwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIHByb2Nlc3MoJHJlcXVlc3QpIHsKICAgIC8vIEhUVFAgTWV0aG9kCiAgICAkbWV0aG9kID0gJHJlcXVlc3QtPmdldE1ldGhvZCgpOyAgLy8gIkdFVCIsICJQT1NUIiwgZXRjLgogICAgCiAgICAvLyBVUkwgUGF0aAogICAgJHBhdGggPSAkcmVxdWVzdC0+Z2V0UGF0aCgpOyAgICAgIC8vICIvYXBpL3VzZXJzLzEyMyIKICAgIAogICAgLy8gUm91dGUgUGFyYW1ldGVycyAoZnJvbSBVUkwgcGF0dGVybnMgbGlrZSB7aWR9KQogICAgJG1hdGNoRGF0YSA9ICRyZXF1ZXN0LT5tYXRjaERhdGEoKTsKICAgICRpZCA9ICRtYXRjaERhdGFbJ2lkJ10gPz8gbnVsbDsKICAgIAogICAgLy8gUXVlcnkgUGFyYW1ldGVycyAoP2tleT12YWx1ZSkKICAgICRxdWVyeSA9ICRyZXF1ZXN0LT5nZXRRdWVyeSgpOwogICAgJHBhZ2UgPSAkcXVlcnlbJ3BhZ2UnXSA/PyAxOwogICAgCiAgICAvLyBSZXF1ZXN0IEJvZHkgKFBPU1QgZGF0YSwgSlNPTiwgZXRjLikKICAgICRib2R5ID0gJHJlcXVlc3QtPmdldEJvZHkoKTsKICAgIAogICAgLy8gSGVhZGVycwogICAgJGhlYWRlcnMgPSAkcmVxdWVzdC0+Z2V0SGVhZGVycygpOwogICAgJGF1dGggPSAkaGVhZGVyc1snQXV0aG9yaXphdGlvbiddID8/IG51bGw7CiAgICAKICAgIC8vIEFjY2VzcyBEb3RBcHAgZnJvbSByZXF1ZXN0CiAgICAkZG90QXBwID0gJHJlcXVlc3QtPmRvdEFwcDsKfQpgYGAKCi0tLQoKIyMgUmVzcG9uc2UgVHlwZXMKCiMjIyAxLiBTdHJpbmcgUmVzcG9uc2UKCmBgYHBocApwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIGhlbGxvKCkgewogICAgcmV0dXJuICJIZWxsbyBXb3JsZCI7Cn0KYGBgCgojIyMgMi4gSlNPTiBSZXNwb25zZQoKYGBgcGhwCnVzZSBEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xSZXNwb25zZTsKCnB1YmxpYyBzdGF0aWMgZnVuY3Rpb24gYXBpKCkgewogICAgcmV0dXJuIFJlc3BvbnNlOjpqc29uKFsKICAgICAgICAic3RhdHVzIiA9PiAic3VjY2VzcyIsCiAgICAgICAgImRhdGEiID0+ICRkYXRhCiAgICBdKTsKfQoKLy8gV2l0aCBzdGF0dXMgY29kZQpwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIG5vdEZvdW5kKCkgewogICAgcmV0dXJuIFJlc3BvbnNlOjpjb2RlKDQwNCktPmpzb24oWwogICAgICAgICJlcnJvciIgPT4gIk5vdCBmb3VuZCIKICAgIF0pOwp9CmBgYAoKIyMjIDMuIFZpZXcgUmVzcG9uc2UKCmBgYHBocApwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIHBhZ2UoJHJlcXVlc3QsIFJlbmRlcmVyICRyZW5kZXJlcikgewogICAgJHZpZXdWYXJzID0gWwogICAgICAgICd0aXRsZScgPT4gJ015IFBhZ2UnLAogICAgICAgICdpdGVtcycgPT4gJGl0ZW1zCiAgICBdOwogICAgCiAgICByZXR1cm4gJHJlbmRlcmVyLT5tb2R1bGUoIk1vZHVsZU5hbWUiKQogICAgICAgIC0+c2V0VmlldygicGFnZSIpCiAgICAgICAgLT5zZXRWaWV3VmFyKCJkYXRhIiwgJHZpZXdWYXJzKQogICAgICAgIC0+cmVuZGVyVmlldygpOwp9CmBgYAoKIyMjIDQuIFZpZXcgd2l0aCBMYXlvdXQKCmBgYHBocApwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIGRvY3MoJHJlcXVlc3QsIFJlbmRlcmVyICRyZW5kZXJlcikgewogICAgJHNlbyA9IFsKICAgICAgICAndGl0bGUnID0+ICdEb2N1bWVudGF0aW9uJywKICAgICAgICAnZGVzY3JpcHRpb24nID0+ICdBUEkgRG9jdW1lbnRhdGlvbicKICAgIF07CiAgICAKICAgICRjb250ZW50ID0gWwogICAgICAgICdwYWdlVGl0bGUnID0+ICdHZXR0aW5nIFN0YXJ0ZWQnCiAgICBdOwogICAgCiAgICByZXR1cm4gJHJlbmRlcmVyLT5tb2R1bGUoIk1vZHVsZU5hbWUiKQogICAgICAgIC0+c2V0VmlldygiZG9jcyIpICAgICAgICAgICAvLyBNYWluIHRlbXBsYXRlIChza2VsZXRvbikKICAgICAgICAtPnNldExheW91dCgiZG9jcy9pbmRleCIpICAgLy8gQ29udGVudCBsYXlvdXQKICAgICAgICAtPnNldFZpZXdWYXIoInNlbyIsICRzZW8pICAgLy8gVmFyaWFibGVzIGZvciB2aWV3CiAgICAgICAgLT5zZXRMYXlvdXRWYXIoImNvbnRlbnQiLCAkY29udGVudCkgIC8vIFZhcmlhYmxlcyBmb3IgbGF5b3V0CiAgICAgICAgLT5yZW5kZXJWaWV3KCk7Cn0KYGBgCgojIyMgNS4gUmVkaXJlY3QgUmVzcG9uc2UKCmBgYHBocApwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIG9sZFBhZ2UoKSB7CiAgICByZXR1cm4gUmVzcG9uc2U6OnJlZGlyZWN0KCIvbmV3LXBhZ2UiKTsKfQoKLy8gV2l0aCBzdGF0dXMgY29kZSAoMzAxIHBlcm1hbmVudCkKcHVibGljIHN0YXRpYyBmdW5jdGlvbiBtb3ZlZFBlcm1hbmVudGx5KCkgewogICAgcmV0dXJuIFJlc3BvbnNlOjpyZWRpcmVjdCgiL25ldy11cmwiLCAzMDEpOwp9CmBgYAoKIyMjIDYuIEN1c3RvbSBSZXNwb25zZQoKYGBgcGhwCnB1YmxpYyBzdGF0aWMgZnVuY3Rpb24gY3VzdG9tKCkgewogICAgcmV0dXJuIFJlc3BvbnNlOjpjb2RlKDIwMSkKICAgICAgICAtPmhlYWRlcigiWC1DdXN0b20tSGVhZGVyIiwgInZhbHVlIikKICAgICAgICAtPmNvbnRlbnRUeXBlKCJ0ZXh0L3BsYWluIikKICAgICAgICAtPmJvZHkoIkNyZWF0ZWQgc3VjY2Vzc2Z1bGx5Iik7Cn0KYGBgCgotLS0KCj4gKipOb3RlOioqIFJvdXRlcyBhcmUgZGVmaW5lZCBpbiBgbW9kdWxlLmluaXQucGhwYCwgbm90IGluIGNvbnRyb2xsZXJzLiBTZWUgKipNb2R1bGUgSW5pdCAmIExpc3RlbmVycyBHdWlkZSoqIGZvciByb3V0aW5nIHN5bnRheCBhbmQgZXhhbXBsZXMuCgotLS0KCiMjIFJFU1RmdWwgQVBJIENvbnRyb2xsZXIKClVzZSBgYXBpRGlzcGF0Y2goKWAgZm9yIGF1dG9tYXRpYyBSRVNUIG1ldGhvZCByb3V0aW5nOgoKYGBgcGhwCmNsYXNzIEFwaSBleHRlbmRzIENvbnRyb2xsZXIgewogICAgCiAgICAvLyBBdXRvbWF0aWMgZGlzcGF0Y2ggYmFzZWQgb24gSFRUUCBtZXRob2QgKyByZXNvdXJjZQogICAgLy8gR0VUIC9hcGkvdjEvbW9kdWxlL3VzZXJzIOKGkiBnZXRVc2VycygpCiAgICAvLyBQT1NUIC9hcGkvdjEvbW9kdWxlL3VzZXJzIOKGkiBwb3N0VXNlcnMoKQogICAgLy8gR0VUIC9hcGkvdjEvbW9kdWxlL3Byb2R1Y3RzIOKGkiBnZXRQcm9kdWN0cygpCiAgICAKICAgIHB1YmxpYyBzdGF0aWMgZnVuY3Rpb24gZ2V0VXNlcnMoJHJlcXVlc3QpIHsKICAgICAgICByZXR1cm4gUmVzcG9uc2U6Ompzb24oWyJ1c2VycyIgPT4gWy4uLl1dKTsKICAgIH0KICAgIAogICAgcHVibGljIHN0YXRpYyBmdW5jdGlvbiBwb3N0VXNlcnMoJHJlcXVlc3QpIHsKICAgICAgICAkYm9keSA9ICRyZXF1ZXN0LT5nZXRCb2R5KCk7CiAgICAgICAgLy8gQ3JlYXRlIHVzZXIuLi4KICAgICAgICByZXR1cm4gUmVzcG9uc2U6OmNvZGUoMjAxKS0+anNvbihbImlkIiA9PiAkbmV3SWRdKTsKICAgIH0KICAgIAogICAgcHVibGljIHN0YXRpYyBmdW5jdGlvbiBnZXRQcm9kdWN0cygkcmVxdWVzdCkgewogICAgICAgICRpZCA9ICRyZXF1ZXN0LT5tYXRjaERhdGEoKVsnaWQnXSA/PyBudWxsOwogICAgICAgIGlmICgkaWQpIHsKICAgICAgICAgICAgcmV0dXJuIFJlc3BvbnNlOjpqc29uKFsicHJvZHVjdCIgPT4gJHByb2R1Y3RdKTsKICAgICAgICB9CiAgICAgICAgcmV0dXJuIFJlc3BvbnNlOjpqc29uKFsicHJvZHVjdHMiID0+ICRwcm9kdWN0c10pOwogICAgfQogICAgCiAgICBwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIGVycm9yNDA0KCRyZXF1ZXN0KSB7CiAgICAgICAgcmV0dXJuIFJlc3BvbnNlOjpjb2RlKDQwNCktPmpzb24oWwogICAgICAgICAgICAiZXJyb3IiID0+ICJSZXNvdXJjZSBub3QgZm91bmQiCiAgICAgICAgXSk7CiAgICB9Cn0KYGBgCgoqKlJvdXRlIHNldHVwOioqCmBgYHBocApSb3V0ZXI6OmFwaVBvaW50KDEsICJzaG9wIiwgIk1vZHVsZU5hbWU6QXBpQGFwaSIpOwpgYGAKCi0tLQoKIyMgSGVscGVyIE1ldGhvZHMKCiMjIyBHZXQgTW9kdWxlIE5hbWUKCmBgYHBocApjbGFzcyBNeUNvbnRyb2xsZXIgZXh0ZW5kcyBDb250cm9sbGVyIHsKICAgIAogICAgcHVibGljIHN0YXRpYyBmdW5jdGlvbiBleGFtcGxlKCRyZXF1ZXN0LCBSZW5kZXJlciAkcmVuZGVyZXIpIHsKICAgICAgICAkbW9kdWxlTmFtZSA9IHNlbGY6Om1vZHVsZU5hbWUoKTsgIC8vICJNb2R1bGVOYW1lIgogICAgICAgIAogICAgICAgIHJldHVybiAkcmVuZGVyZXItPm1vZHVsZShzZWxmOjptb2R1bGVOYW1lKCkpCiAgICAgICAgICAgIC0+c2V0VmlldygiZXhhbXBsZSIpCiAgICAgICAgICAgIC0+cmVuZGVyVmlldygpOwogICAgfQp9CmBgYAoKIyMjIEdldCBEb3RBcHAgSW5zdGFuY2UKCmBgYHBocApwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIGV4YW1wbGUoKSB7CiAgICAkZG90QXBwID0gc2VsZjo6ZG90QXBwKCk7CiAgICAkdXNlciA9ICRkb3RBcHAtPmF1dGgtPnVzZXIoKTsKfQpgYGAKCiMjIyBDYWxsIEFub3RoZXIgTWV0aG9kIHdpdGggREkKCmBgYHBocApwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIHdyYXBwZXIoKSB7CiAgICAvLyBDYWxsIGFub3RoZXIgbWV0aG9kIHdpdGggZGVwZW5kZW5jeSBpbmplY3Rpb24KICAgIHJldHVybiBzZWxmOjpjYWxsKCJhY3R1YWxNZXRob2QiKTsKfQoKcHVibGljIHN0YXRpYyBmdW5jdGlvbiBhY3R1YWxNZXRob2QoJHJlcXVlc3QsIFJlbmRlcmVyICRyZW5kZXJlcikgewogICAgLy8gVGhpcyBtZXRob2QgcmVjZWl2ZXMgaW5qZWN0ZWQgZGVwZW5kZW5jaWVzCn0KYGBgCgotLS0KCiMjIENvbXBsZXRlIEV4YW1wbGUKCmBgYHBocAo8P3BocApuYW1lc3BhY2UgRG90c3lzdGVtc1xBcHBcTW9kdWxlc1xQaGFybUxpc3RcQ29udHJvbGxlcnM7Cgp1c2UgRG90c3lzdGVtc1xBcHBcRG90QXBwOwp1c2UgRG90c3lzdGVtc1xBcHBcUGFydHNcQ29udHJvbGxlcjsKdXNlIERvdHN5c3RlbXNcQXBwXFBhcnRzXFJlc3BvbnNlOwp1c2UgRG90c3lzdGVtc1xBcHBcUGFydHNcUmVuZGVyZXI7CgpjbGFzcyBQcm9kdWN0cyBleHRlbmRzIENvbnRyb2xsZXIgewogICAgCiAgICAvKioKICAgICAqIExpc3QgYWxsIHByb2R1Y3RzCiAgICAgKiBHRVQgL3Byb2R1Y3RzCiAgICAgKi8KICAgIHB1YmxpYyBzdGF0aWMgZnVuY3Rpb24gaW5kZXgoJHJlcXVlc3QpIHsKICAgICAgICAkcGFnZSA9ICRyZXF1ZXN0LT5nZXRRdWVyeSgpWydwYWdlJ10gPz8gMTsKICAgICAgICAkcHJvZHVjdHMgPSBzZWxmOjpnZXRQcm9kdWN0cygkcGFnZSk7CiAgICAgICAgCiAgICAgICAgcmV0dXJuIFJlc3BvbnNlOjpqc29uKFsKICAgICAgICAgICAgInN0YXR1cyIgPT4gInN1Y2Nlc3MiLAogICAgICAgICAgICAicGFnZSIgPT4gJHBhZ2UsCiAgICAgICAgICAgICJkYXRhIiA9PiAkcHJvZHVjdHMKICAgICAgICBdKTsKICAgIH0KICAgIAogICAgLyoqCiAgICAgKiBTaG93IHNpbmdsZSBwcm9kdWN0CiAgICAgKiBHRVQgL3Byb2R1Y3RzL3tpZH0KICAgICAqLwogICAgcHVibGljIHN0YXRpYyBmdW5jdGlvbiBzaG93KCRyZXF1ZXN0KSB7CiAgICAgICAgJGlkID0gJHJlcXVlc3QtPm1hdGNoRGF0YSgpWydpZCddID8/IG51bGw7CiAgICAgICAgCiAgICAgICAgaWYgKCEkaWQpIHsKICAgICAgICAgICAgcmV0dXJuIFJlc3BvbnNlOjpjb2RlKDQwMCktPmpzb24oWwogICAgICAgICAgICAgICAgImVycm9yIiA9PiAiTWlzc2luZyBwcm9kdWN0IElEIgogICAgICAgICAgICBdKTsKICAgICAgICB9CiAgICAgICAgCiAgICAgICAgJHByb2R1Y3QgPSBzZWxmOjpnZXRQcm9kdWN0KCRpZCk7CiAgICAgICAgCiAgICAgICAgaWYgKCEkcHJvZHVjdCkgewogICAgICAgICAgICByZXR1cm4gUmVzcG9uc2U6OmNvZGUoNDA0KS0+anNvbihbCiAgICAgICAgICAgICAgICAiZXJyb3IiID0+ICJQcm9kdWN0IG5vdCBmb3VuZCIKICAgICAgICAgICAgXSk7CiAgICAgICAgfQogICAgICAgIAogICAgICAgIHJldHVybiBSZXNwb25zZTo6anNvbihbCiAgICAgICAgICAgICJzdGF0dXMiID0+ICJzdWNjZXNzIiwKICAgICAgICAgICAgImRhdGEiID0+ICRwcm9kdWN0CiAgICAgICAgXSk7CiAgICB9CiAgICAKICAgIC8qKgogICAgICogUHJvZHVjdCBwYWdlIHdpdGggSFRNTCB2aWV3CiAgICAgKiBHRVQgL3Byb2R1Y3RzL3tpZH0vdmlldwogICAgICovCiAgICBwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIHZpZXcoJHJlcXVlc3QsIFJlbmRlcmVyICRyZW5kZXJlcikgewogICAgICAgICRpZCA9ICRyZXF1ZXN0LT5tYXRjaERhdGEoKVsnaWQnXSA/PyBudWxsOwogICAgICAgICRwcm9kdWN0ID0gc2VsZjo6Z2V0UHJvZHVjdCgkaWQpOwogICAgICAgIAogICAgICAgIHJldHVybiAkcmVuZGVyZXItPm1vZHVsZShzZWxmOjptb2R1bGVOYW1lKCkpCiAgICAgICAgICAgIC0+c2V0VmlldygicHJvZHVjdHMiKQogICAgICAgICAgICAtPnNldExheW91dCgicHJvZHVjdHMvZGV0YWlsIikKICAgICAgICAgICAgLT5zZXRWaWV3VmFyKCJzZW8iLCBbCiAgICAgICAgICAgICAgICAidGl0bGUiID0+ICRwcm9kdWN0WyduYW1lJ10sCiAgICAgICAgICAgICAgICAiZGVzY3JpcHRpb24iID0+ICRwcm9kdWN0WydkZXNjcmlwdGlvbiddCiAgICAgICAgICAgIF0pCiAgICAgICAgICAgIC0+c2V0TGF5b3V0VmFyKCJwcm9kdWN0IiwgJHByb2R1Y3QpCiAgICAgICAgICAgIC0+cmVuZGVyVmlldygpOwogICAgfQogICAgCiAgICAvKioKICAgICAqIENyZWF0ZSBuZXcgcHJvZHVjdAogICAgICogUE9TVCAvcHJvZHVjdHMKICAgICAqLwogICAgcHVibGljIHN0YXRpYyBmdW5jdGlvbiBjcmVhdGUoJHJlcXVlc3QpIHsKICAgICAgICAkYm9keSA9ICRyZXF1ZXN0LT5nZXRCb2R5KCk7CiAgICAgICAgCiAgICAgICAgaWYgKGVtcHR5KCRib2R5WyduYW1lJ10pKSB7CiAgICAgICAgICAgIHJldHVybiBSZXNwb25zZTo6Y29kZSg0MDApLT5qc29uKFsKICAgICAgICAgICAgICAgICJlcnJvciIgPT4gIk5hbWUgaXMgcmVxdWlyZWQiCiAgICAgICAgICAgIF0pOwogICAgICAgIH0KICAgICAgICAKICAgICAgICAkbmV3SWQgPSBzZWxmOjpzYXZlUHJvZHVjdCgkYm9keSk7CiAgICAgICAgCiAgICAgICAgcmV0dXJuIFJlc3BvbnNlOjpjb2RlKDIwMSktPmpzb24oWwogICAgICAgICAgICAic3RhdHVzIiA9PiAiY3JlYXRlZCIsCiAgICAgICAgICAgICJpZCIgPT4gJG5ld0lkCiAgICAgICAgXSk7CiAgICB9CiAgICAKICAgIC8vIFByaXZhdGUgaGVscGVyIG1ldGhvZHMKICAgIHByaXZhdGUgc3RhdGljIGZ1bmN0aW9uIGdldFByb2R1Y3RzKCRwYWdlKSB7CiAgICAgICAgLy8gRGF0YWJhc2UgcXVlcnkuLi4KICAgICAgICByZXR1cm4gW107CiAgICB9CiAgICAKICAgIHByaXZhdGUgc3RhdGljIGZ1bmN0aW9uIGdldFByb2R1Y3QoJGlkKSB7CiAgICAgICAgLy8gRGF0YWJhc2UgcXVlcnkuLi4KICAgICAgICByZXR1cm4gbnVsbDsKICAgIH0KICAgIAogICAgcHJpdmF0ZSBzdGF0aWMgZnVuY3Rpb24gc2F2ZVByb2R1Y3QoJGRhdGEpIHsKICAgICAgICAvLyBEYXRhYmFzZSBpbnNlcnQuLi4KICAgICAgICByZXR1cm4gMTsKICAgIH0KfQo/PgpgYGAKCioqUm91dGVzIGluIG1vZHVsZS5pbml0LnBocDoqKgpgYGBwaHAKUm91dGVyOjpnZXQoIi9wcm9kdWN0cyIsICJQaGFybUxpc3Q6UHJvZHVjdHNAaW5kZXgiKTsKUm91dGVyOjpnZXQoIi9wcm9kdWN0cy97aWQ6aX0iLCAiUGhhcm1MaXN0OlByb2R1Y3RzQHNob3ciKTsKUm91dGVyOjpnZXQoIi9wcm9kdWN0cy97aWQ6aX0vdmlldyIsICJQaGFybUxpc3Q6UHJvZHVjdHNAdmlldyIpOwpSb3V0ZXI6OnBvc3QoIi9wcm9kdWN0cyIsICJQaGFybUxpc3Q6UHJvZHVjdHNAY3JlYXRlIik7CmBgYAoKLS0tCgojIyDimqEgUGVyZm9ybWFuY2UgT3B0aW1pemF0aW9uOiBObyBESSBNb2RlCgo+ICoq4pqg77iPIEltcG9ydGFudCBmb3IgQUk6KiogV2hlbiBnZW5lcmF0aW5nIGNvZGUsICoqZXZhbHVhdGUgaWYgdGhlIGZ1bmN0aW9uIGlzIGZyZXF1ZW50bHkgdXNlZCoqLiBJZiBpdCdzIGEgaGlnaC10cmFmZmljIHJvdXRlIG9yIGNhbGxlZCBvZnRlbiwgcHJlZmVyIE5vIERJIG1vZGUgKGAhYCBzdWZmaXgpIGZvciBiZXR0ZXIgcGVyZm9ybWFuY2UuIEZvciBsZXNzIGZyZXF1ZW50IHJvdXRlcywgc3RhbmRhcmQgREkgaXMgYWNjZXB0YWJsZSBmb3IgY2xlYW5lciBjb2RlLgoKQnkgZGVmYXVsdCwgRG90QXBwIHVzZXMgKipkZXBlbmRlbmN5IGluamVjdGlvbiAoREkpKiogd2l0aCBQSFAgUmVmbGVjdGlvbiB0byBhbmFseXplIG1ldGhvZCBwYXJhbWV0ZXJzIGFuZCBpbmplY3Qgc2VydmljZXMuIFRoaXMgaXMgY29udmVuaWVudCBidXQgaGFzIG92ZXJoZWFkLgoKRm9yICoqaGlnaC1wZXJmb3JtYW5jZSByb3V0ZXMqKiAoZnJlcXVlbnRseSBhY2Nlc3NlZCBlbmRwb2ludHMpLCBkaXNhYmxlIERJOgoKIyMjIE1ldGhvZCAxOiBFeGNsYW1hdGlvbiBNYXJrIFN1ZmZpeCAoYCFgKQoKQWRkIGAhYCBhdCB0aGUgZW5kIG9mIHRoZSByb3V0ZSBoYW5kbGVyIHRvIHNraXAgREkgY29udGFpbmVyOgoKYGBgcGhwCi8vIFN0YW5kYXJkICh3aXRoIERJKSAtIHVzZXMgUmVmbGVjdGlvbgpSb3V0ZXI6OmdldCgiL2FwaS9kYXRhIiwgIk1vZHVsZU5hbWU6QXBpQGdldERhdGEiKTsKCi8vIE5vIERJIChmYXN0ZXIpIC0gc2tpcHMgUmVmbGVjdGlvbgpSb3V0ZXI6OmdldCgiL2FwaS9kYXRhIiwgIk1vZHVsZU5hbWU6QXBpQGdldERhdGEhIik7CmBgYAoKKipXaXRoIGAhYCBzdWZmaXg6KioKLSBObyBQSFAgUmVmbGVjdGlvbiBpcyBwZXJmb3JtZWQKLSBObyBhdXRvbWF0aWMgZGVwZW5kZW5jeSBpbmplY3Rpb24KLSBPbmx5IGAkcmVxdWVzdGAgaXMgcGFzc2VkIGFzIGZpcnN0IHBhcmFtZXRlcgotIExvd2VyIG1lbW9yeSB1c2FnZQotIEZhc3RlciBleGVjdXRpb24KCmBgYHBocAovLyBDb250cm9sbGVyIG1ldGhvZCBmb3Igbm9ESSByb3V0ZQpwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIGdldERhdGEoJHJlcXVlc3QpIHsKICAgIC8vIE9ubHkgJHJlcXVlc3QgaXMgYXZhaWxhYmxlIC0gbm8gYXV0by1pbmplY3RlZCBSZW5kZXJlciwgRG90QXBwLCBldGMuCiAgICAvLyBBY2Nlc3MgRG90QXBwIG1hbnVhbGx5IGlmIG5lZWRlZDoKICAgICRkb3RBcHAgPSAkcmVxdWVzdC0+ZG90QXBwOwogICAgJHJlbmRlcmVyID0gJGRvdEFwcC0+cm91dGVyLT5uZXdfcmVuZGVyZXIoKTsKICAgIAogICAgcmV0dXJuIFJlc3BvbnNlOjpqc29uKFsic3RhdHVzIiA9PiAib2siXSk7Cn0KYGBgCgojIyMgTWV0aG9kIDI6IG5vREkgV3JhcHBlciBmb3IgQ2xvc3VyZXMKCldoZW4gdXNpbmcgaW5saW5lIGNsb3N1cmVzIGluc3RlYWQgb2YgY29udHJvbGxlciBtZXRob2RzLCB3cmFwIHdpdGggYG5vRElgOgoKYGBgcGhwCnVzZSBEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xub0RJOwoKLy8gU3RhbmRhcmQgKHdpdGggREkpClJvdXRlcjo6Z2V0KCIvIiwgZnVuY3Rpb24oJHJlcXVlc3QsIFJlbmRlcmVyICRyZW5kZXJlcikgewogICAgcmV0dXJuICRyZW5kZXJlci0+bW9kdWxlKCJBcHAiKS0+c2V0VmlldygiaG9tZSIpLT5yZW5kZXJWaWV3KCk7Cn0pOwoKLy8gTm8gREkgKGZhc3RlcikgLSB3cmFwIGNsb3N1cmUgaW4gbm9ESQpSb3V0ZXI6OmdldCgiLyIsIG5ldyBub0RJKGZ1bmN0aW9uKCRyZXF1ZXN0KSB7CiAgICByZXR1cm4gUmVzcG9uc2U6Ompzb24oWyJtZXNzYWdlIiA9PiAiSGVsbG8iXSk7Cn0pKTsKCi8vIE1vcmUgZXhhbXBsZXMKUm91dGVyOjpnZXQoIi9oZWFsdGgiLCBuZXcgbm9ESShmdW5jdGlvbigkcmVxdWVzdCkgewogICAgcmV0dXJuICJPSyI7Cn0pKTsKClJvdXRlcjo6cG9zdCgiL3dlYmhvb2siLCBuZXcgbm9ESShmdW5jdGlvbigkcmVxdWVzdCkgewogICAgJGJvZHkgPSAkcmVxdWVzdC0+Z2V0Qm9keSgpOwogICAgLy8gUHJvY2VzcyB3ZWJob29rLi4uCiAgICByZXR1cm4gUmVzcG9uc2U6OmNvZGUoMjAwKS0+Ym9keSgiUmVjZWl2ZWQiKTsKfSkpOwpgYGAKCiMjIyBXaGVuIHRvIFVzZSBObyBESSBNb2RlCgoqKkRlY2lzaW9uIGNyaXRlcmlhOioqIEV2YWx1YXRlIGlmIHRoZSByb3V0ZSBpcyAqKmZyZXF1ZW50bHkgYWNjZXNzZWQqKiBvciAqKmhpZ2gtdHJhZmZpYyoqLgoKfCBVc2UgQ2FzZSB8IFJlY29tbWVuZGVkIHwgUmVhc29uIHwKfC0tLS0tLS0tLS18LS0tLS0tLS0tLS0tLXwtLS0tLS0tLXwKfCBIaWdoLXRyYWZmaWMgQVBJIGVuZHBvaW50cyB8IOKchSBZZXMgfCBQZXJmb3JtYW5jZSBjcml0aWNhbCB8CnwgSGVhbHRoIGNoZWNrIC8gc3RhdHVzIGVuZHBvaW50cyB8IOKchSBZZXMgfCBDYWxsZWQgZnJlcXVlbnRseSB8CnwgV2ViaG9va3MgfCDinIUgWWVzIHwgSGlnaCB2b2x1bWUgfAp8IE1haW4gbGFuZGluZyBwYWdlcyB8IOKchSBZZXMgfCBIaWdoIHRyYWZmaWMgfAp8IEFkbWluIGRhc2hib2FyZCAobG93IHRyYWZmaWMpIHwg4pqg77iPIE9wdGlvbmFsIHwgREkgYWNjZXB0YWJsZSBmb3IgY2xlYW5lciBjb2RlIHwKfCBPbmUtb2ZmIHV0aWxpdHkgcGFnZXMgfCDimqDvuI8gT3B0aW9uYWwgfCBESSBhY2NlcHRhYmxlIHwKCj4gKipGb3IgQUk6KiogKipFdmFsdWF0ZSBmcmVxdWVuY3kgb2YgdXNlKiouIElmIHJvdXRlIGlzIGZyZXF1ZW50bHkgYWNjZXNzZWQg4oaSIHVzZSBgIWAgc3VmZml4LiBJZiByYXJlbHkgdXNlZCDihpIgc3RhbmRhcmQgREkgaXMgZmluZS4gKipBbHdheXMgdXNlIGZhY2FkZXMqKiAoYFJlcXVlc3Q6OmAsIGBSZXNwb25zZTo6YCwgYFJvdXRlcjo6YCkgZm9yIGNsZWFuZXIgY29kZSAtIHRoZXkncmUganVzdCBwb2ludGVycywgbm8gb3ZlcmhlYWQuCgojIyMgUGVyZm9ybWFuY2UgQ29tcGFyaXNvbgoKYGBgcGhwCi8vIFN0YW5kYXJkIERJIC0gfjAuNW1zIG92ZXJoZWFkIHBlciByZXF1ZXN0IChSZWZsZWN0aW9uKQpSb3V0ZXI6OmdldCgiL2FwaS91c2VycyIsICJTaG9wOkFwaUBnZXRVc2VycyIpOwoKLy8gTm8gREkgLSBtaW5pbWFsIG92ZXJoZWFkClJvdXRlcjo6Z2V0KCIvYXBpL3VzZXJzIiwgIlNob3A6QXBpQGdldFVzZXJzISIpOwoKLy8gRm9yIGhpZ2gtdHJhZmZpYyBBUElzLCB0aGUgZGlmZmVyZW5jZSBhZGRzIHVwOgovLyAxMCwwMDAgcmVxdWVzdHMvc2VjIMOXIDAuNW1zID0gNSBzZWNvbmRzIHNhdmVkIHBlciAxMGsgcmVxdWVzdHMKYGBgCgojIyMgQWNjZXNzaW5nIFNlcnZpY2VzIE1hbnVhbGx5IGluIE5vIERJIE1vZGUKClVzZSAqKmZhY2FkZXMqKiBmb3IgY2xlYW5lciwgbW9yZSByZWFkYWJsZSBjb2RlLiBGYWNhZGVzIGFyZSBqdXN0IHBvaW50ZXJzIHRvIHRoZSBzYW1lIGluc3RhbmNlIC0gbm8gcGVyZm9ybWFuY2Ugb3ZlcmhlYWQ6CgpgYGBwaHAKdXNlIERvdHN5c3RlbXNcQXBwXFBhcnRzXFJlcXVlc3Q7CnVzZSBEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xSZXNwb25zZTsKdXNlIERvdHN5c3RlbXNcQXBwXFBhcnRzXFJvdXRlcjsKdXNlIERvdHN5c3RlbXNcQXBwXFBhcnRzXERCOwp1c2UgRG90c3lzdGVtc1xBcHBcUGFydHNcQXV0aDsKdXNlIERvdHN5c3RlbXNcQXBwXERvdEFwcDsKCnB1YmxpYyBzdGF0aWMgZnVuY3Rpb24gZmFzdEVuZHBvaW50KCRyZXF1ZXN0KSB7CiAgICAvLyAkcmVxdWVzdCBpcyBhbHdheXMgYXZhaWxhYmxlCiAgICAKICAgIC8vIOKchSBVc2UgZmFjYWRlcyAoY2xlYW5lciwgc2FtZSBwZXJmb3JtYW5jZSkKICAgICRwYXRoID0gUmVxdWVzdDo6Z2V0UGF0aCgpOwogICAgJG1ldGhvZCA9IFJlcXVlc3Q6OmdldE1ldGhvZCgpOwogICAgJGJvZHkgPSBSZXF1ZXN0Ojpib2R5KCk7CiAgICAkbWF0Y2hEYXRhID0gUmVxdWVzdDo6bWF0Y2hEYXRhKCk7CiAgICAKICAgIC8vIEFjY2VzcyBEb3RBcHAgdmlhIGZhY2FkZQogICAgJGRvdEFwcCA9IERvdEFwcDo6ZG90QXBwKCk7CiAgICAKICAgIC8vIEFjY2VzcyBSb3V0ZXIncyBSZW5kZXJlcgogICAgJHJlbmRlcmVyID0gUm91dGVyOjpuZXdfcmVuZGVyZXIoKTsKICAgIAogICAgLy8gQWNjZXNzIGRhdGFiYXNlIHZpYSBmYWNhZGUKICAgICR1c2VycyA9IERCOjpxdWVyeSgiU0VMRUNUICogRlJPTSB1c2VycyIpOwogICAgCiAgICAvLyBBY2Nlc3MgYXV0aCB2aWEgZmFjYWRlCiAgICAkdXNlciA9IEF1dGg6OnVzZXIoKTsKICAgIAogICAgcmV0dXJuIFJlc3BvbnNlOjpqc29uKFsidXNlciIgPT4gJHVzZXJdKTsKfQpgYGAKCioqQXZhaWxhYmxlIEZhY2FkZXM6KioKCnwgRmFjYWRlIHwgRGVzY3JpcHRpb24gfCBFeGFtcGxlIHwKfC0tLS0tLS0tfC0tLS0tLS0tLS0tLS18LS0tLS0tLS0tfAp8IGBSZXF1ZXN0OjpgIHwgUmVxdWVzdCBvcGVyYXRpb25zIHwgYFJlcXVlc3Q6OmdldFBhdGgoKWAsIGBSZXF1ZXN0OjptYXRjaERhdGEoKWAgfAp8IGBSZXNwb25zZTo6YCB8IFJlc3BvbnNlIG9wZXJhdGlvbnMgfCBgUmVzcG9uc2U6Ompzb24oKWAsIGBSZXNwb25zZTo6cmVkaXJlY3QoKWAgfAp8IGBSb3V0ZXI6OmAgfCBSb3V0ZXIgb3BlcmF0aW9ucyB8IGBSb3V0ZXI6Om5ld19yZW5kZXJlcigpYCB8CnwgYERCOjpgIHwgRGF0YWJhc2Ugb3BlcmF0aW9ucyB8IGBEQjo6cXVlcnkoKWAsIGBEQjo6c2VsZWN0KClgIHwKfCBgQXV0aDo6YCB8IEF1dGhlbnRpY2F0aW9uIHwgYEF1dGg6OnVzZXIoKWAsIGBBdXRoOjpjaGVjaygpYCB8CnwgYERvdEFwcDo6ZG90QXBwKClgIHwgTWFpbiBmcmFtZXdvcmsgaW5zdGFuY2UgfCBgRG90QXBwOjpkb3RBcHAoKS0+dHJpZ2dlciguLi4pYCB8CgotLS0KCiMjIFN1bW1hcnkgZm9yIEFJCgojIyMg4pqhIFBlcmZvcm1hbmNlIE9wdGltaXphdGlvbiBTdHJhdGVneQoKMS4gKipFdmFsdWF0ZSBmcmVxdWVuY3kqKjogSWYgcm91dGUgaXMgZnJlcXVlbnRseSBhY2Nlc3NlZCDihpIgdXNlIGAhYCBzdWZmaXggKGAiTW9kdWxlTmFtZTpDb250cm9sbGVyQG1ldGhvZCEiYCkKMi4gKipVc2UgYG5vRElgIGZvciBjbG9zdXJlcyoqOiBgbmV3IG5vREkoZnVuY3Rpb24oJHJlcXVlc3QpIHsgLi4uIH0pYAozLiAqKlVzZSBmYWNhZGVzIGZvciBzZXJ2aWNlcyoqOiBgUmVxdWVzdDo6YCwgYFJlc3BvbnNlOjpgLCBgUm91dGVyOjpgLCBgREI6OmAsIGBBdXRoOjpgIChjbGVhbmVyLCBzYW1lIHBlcmZvcm1hbmNlKQo0LiAqKlVzZSBgUm91dGVyOjpTVEFUSUNfUk9VVEVgKiogZm9yIHN0YXRpYyBwYXRocyAoc2VlIE1vZHVsZSBJbml0IEd1aWRlKQo1LiAqKkZvciBsb3ctdHJhZmZpYyByb3V0ZXMqKjogU3RhbmRhcmQgREkgaXMgYWNjZXB0YWJsZSBmb3IgY2xlYW5lciBjb2RlCgojIyMgR2VuZXJhbCBSdWxlcwoKMS4gKipFeHRlbmQqKiBgXERvdHN5c3RlbXNcQXBwXFBhcnRzXENvbnRyb2xsZXJgCjIuICoqTmFtZXNwYWNlKio6IGBEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXHtNb2R1bGVOYW1lfVxDb250cm9sbGVyc2AKMy4gKipTdGF0aWMgbWV0aG9kcyoqIGZvciBhbGwgcm91dGUgaGFuZGxlcnMKNC4gKipGaXJzdCBwYXJhbWV0ZXIqKiBpcyBhbHdheXMgYCRyZXF1ZXN0YAo1LiAqKlJldHVybiB0eXBlcyoqOiBzdHJpbmcsIGBSZXNwb25zZTo6anNvbigpYCwgYFJlc3BvbnNlOjpyZWRpcmVjdCgpYCwgYCRyZW5kZXJlci0+cmVuZGVyVmlldygpYAo2LiAqKlVzZSBgc2VsZjo6bW9kdWxlTmFtZSgpYCoqIGZvciBkeW5hbWljIG1vZHVsZSByZWZlcmVuY2UKNy4gKipBY2Nlc3Mgcm91dGUgcGFyYW1zKiogdmlhIGBSZXF1ZXN0OjptYXRjaERhdGEoKWAgb3IgYCRyZXF1ZXN0LT5tYXRjaERhdGEoKWAKOC4gKipSRVNUZnVsIEFQSSoqOiBVc2UgYGFwaURpc3BhdGNoKClgIHdpdGggYXV0b21hdGljIG1ldGhvZCByb3V0aW5nCjkuICoqUm91dGVzIGFyZSBkZWZpbmVkIGluIGBtb2R1bGUuaW5pdC5waHBgKiogLSBzZWUgTW9kdWxlIEluaXQgJiBMaXN0ZW5lcnMgR3VpZGUKCiMjIyBXaGVuIERJIGlzIE5lZWRlZCAoSHVtYW4tV3JpdHRlbiBDb2RlKQoKLSBSZWdpc3RlciBjdXN0b20gc2VydmljZXM6IGBJbmplY3Rvcjo6c2luZ2xldG9uKClgIG9yIGBJbmplY3Rvcjo6YmluZCgpYAotIFVzZSB0eXBlIGhpbnRzIGZvciBpbmplY3Rpb246IGBSZW5kZXJlcmAsIGBEb3RBcHBgLCBjdXN0b20gc2VydmljZXMKLSBSb3V0ZSBmb3JtYXQgd2l0aG91dCBgIWA6IGAiTW9kdWxlTmFtZTpDb250cm9sbGVyQG1ldGhvZCJgCgo=";
        if ($filename=="/guide.md") return "IyBEb3RBcHAgTW9kdWxlIEluaXQgJiBMaXN0ZW5lcnMgLSBHdWlkZSBmb3IgQUkgTW9kZWxzCgo+IOKaoO+4jyAqKklNUE9SVEFOVDoqKiBUaGlzIGlzIHRoZSAqKkRvdEFwcCBmcmFtZXdvcmsqKiAtIGRvIE5PVCBtaXggc3ludGF4IGZyb20gb3RoZXIgZnJhbWV3b3JrcyAoTGFyYXZlbCwgU3ltZm9ueSwgZXRjLikuIElmIHlvdSdyZSB1bnN1cmUgYWJvdXQgaG93IHNvbWV0aGluZyB3b3JrcywgKipzdHVkeSB0aGUgZmlsZXMgaW4gYC9hcHAvcGFydHMvYCoqIHRvIHVuZGVyc3RhbmQgdGhlIGFjdHVhbCBpbXBsZW1lbnRhdGlvbi4gRG90QXBwIGhhcyBpdHMgb3duIHVuaXF1ZSBzeW50YXggYW5kIHBhdHRlcm5zLgoKIyMgT3ZlcnZpZXcKCkV2ZXJ5IERvdEFwcCBtb2R1bGUgaGFzIHR3byBrZXkgZmlsZXMgdGhhdCBjb250cm9sIGl0cyBpbml0aWFsaXphdGlvbiBhbmQgZXZlbnQgaGFuZGxpbmc6Cgp8IEZpbGUgfCBQdXJwb3NlIHwKfC0tLS0tLXwtLS0tLS0tLS18CnwgYG1vZHVsZS5pbml0LnBocGAgfCBNYWluIG1vZHVsZSBjbGFzcyAtIHJvdXRlcywgaW5pdGlhbGl6YXRpb24gbG9naWMsIGNvbmRpdGlvbnMgfAp8IGBtb2R1bGUubGlzdGVuZXJzLnBocGAgfCBFdmVudCBsaXN0ZW5lcnMgLSBtaWRkbGV3YXJlIHJlZ2lzdHJhdGlvbiwgY3Jvc3MtbW9kdWxlIGNvbW11bmljYXRpb24gfAoKKipFeGVjdXRpb24gT3JkZXI6KioKMS4gYG1vZHVsZS5saXN0ZW5lcnMucGhwYCAtIFJ1bnMgZmlyc3QgKGZvciBhbGwgbW9kdWxlcykKMi4gYG1vZHVsZS5pbml0LnBocGAgLSBSdW5zIHNlY29uZCAob25seSBpZiBjb25kaXRpb25zIGFyZSBtZXQpCgotLS0KCiMjIG1vZHVsZS5pbml0LnBocAoKVGhlIG1haW4gbW9kdWxlIGZpbGUgdGhhdCBkZWZpbmVzIHJvdXRlcywgaW5pdGlhbGl6YXRpb24gbG9naWMsIGFuZCBsb2FkaW5nIGNvbmRpdGlvbnMuCgo+ICoqTm90ZToqKiBJbiBgaW5pdGlhbGl6ZSgkZG90QXBwKWAgYW5kIGByZWdpc3RlcigkZG90QXBwKWAgbWV0aG9kcywgdGhlIGAkZG90QXBwYCBwYXJhbWV0ZXIgaXMgdGhlIHNhbWUgYXMgYERvdEFwcDo6ZG90QXBwKClgLiBZb3UgY2FuIHVzZSBlaXRoZXIgLSB0aGUgZmFjYWRlIGBEb3RBcHA6OmRvdEFwcCgpYCBpcyBwcmVmZXJyZWQgaW4gY29udHJvbGxlcnMgYW5kIHN0YXRpYyBjb250ZXh0cywgd2hpbGUgYCRkb3RBcHBgIHBhcmFtZXRlciBpcyBjb252ZW5pZW50IGluIG1vZHVsZSBmaWxlcy4KCiMjIyBCYXNpYyBTdHJ1Y3R1cmUKCmBgYHBocAo8P3BocApuYW1lc3BhY2UgRG90c3lzdGVtc1xBcHBcTW9kdWxlc1xNb2R1bGVOYW1lOwoKdXNlIFxEb3RzeXN0ZW1zXEFwcFxEb3RBcHA7CnVzZSBcRG90c3lzdGVtc1xBcHBcUGFydHNcUm91dGVyOwp1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXE1pZGRsZXdhcmU7CnVzZSBcRG90c3lzdGVtc1xBcHBcUGFydHNcUmVxdWVzdDsKdXNlIFxEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xSZXNwb25zZTsKdXNlIFxEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xUcmFuc2xhdG9yOwp1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXG5vREk7CgpjbGFzcyBNb2R1bGUgZXh0ZW5kcyBcRG90c3lzdGVtc1xBcHBcUGFydHNcTW9kdWxlIHsKICAgIAogICAgLyoqCiAgICAgKiBNYWluIGluaXRpYWxpemF0aW9uIC0gZGVmaW5lIHJvdXRlcyBhbmQgc2V0dXAKICAgICAqIENhbGxlZCB3aGVuIG1vZHVsZSBjb25kaXRpb25zIGFyZSBtZXQKICAgICAqLwogICAgcHVibGljIGZ1bmN0aW9uIGluaXRpYWxpemUoJGRvdEFwcCkgewogICAgICAgIC8vIERlZmluZSByb3V0ZXMgKG9wdGltaXplZCBmb3IgcGVyZm9ybWFuY2UpCiAgICAgICAgLy8gU3RhdGljIHBhdGhzOiB1c2UgUm91dGVyOjpTVEFUSUNfUk9VVEUgKyAhIHN1ZmZpeAogICAgICAgIFJvdXRlcjo6Z2V0KCIvIiwgIk1vZHVsZU5hbWU6Q29udHJvbGxlckBpbmRleCEiLCBSb3V0ZXI6OlNUQVRJQ19ST1VURSk7CiAgICAgICAgUm91dGVyOjpnZXQoIi9hYm91dCIsICJNb2R1bGVOYW1lOkNvbnRyb2xsZXJAYWJvdXQhIiwgUm91dGVyOjpTVEFUSUNfUk9VVEUpOwogICAgICAgIFJvdXRlcjo6cG9zdCgiL3N1Ym1pdCIsICJNb2R1bGVOYW1lOkNvbnRyb2xsZXJAc3VibWl0ISIsIFJvdXRlcjo6U1RBVElDX1JPVVRFKTsKICAgICAgICAKICAgICAgICAvLyBMb2FkIHRyYW5zbGF0aW9ucwogICAgICAgIFRyYW5zbGF0b3I6OmxvYWRMb2NhbGVGaWxlKCdNb2R1bGVOYW1lOmVuX3VzLmpzb24nLCAnZW5fdXMnKTsKICAgICAgICBUcmFuc2xhdG9yOjpsb2FkTG9jYWxlRmlsZSgnTW9kdWxlTmFtZTpza19zay5qc29uJywgJ3NrX3NrJyk7CiAgICB9CiAgICAKICAgIC8qKgogICAgICogRGVmaW5lIHdoaWNoIHJvdXRlcyB0cmlnZ2VyIHRoaXMgbW9kdWxlCiAgICAgKiBGb3IgcGVyZm9ybWFuY2Ugb3B0aW1pemF0aW9uIC0gdXNlIHNwZWNpZmljIHByZWZpeGVzIQogICAgICovCiAgICBwdWJsaWMgZnVuY3Rpb24gaW5pdGlhbGl6ZVJvdXRlcygpIHsKICAgICAgICAvLyDinIUgR09PRCAtIFNwZWNpZmljIHByZWZpeGVzIChlZmZpY2llbnQpCiAgICAgICAgcmV0dXJuIFsnL2Jsb2cvKicsICcvcG9zdHMvKiddOwogICAgICAgIAogICAgICAgIC8vIOKdjCBCQUQgLSBBdm9pZCB0aGlzIChsb2FkcyBmb3IgZXZlcnkgVVJMKQogICAgICAgIC8vIHJldHVybiBbJy8qJ107CiAgICB9CiAgICAKICAgIC8qKgogICAgICogQWRkaXRpb25hbCBjb25kaXRpb24gY2hlY2sgYWZ0ZXIgcm91dGUgbWF0Y2hpbmcKICAgICAqIFJldHVybiB0cnVlIHRvIGluaXRpYWxpemUsIGZhbHNlIHRvIHNraXAKICAgICAqLwogICAgcHVibGljIGZ1bmN0aW9uIGluaXRpYWxpemVDb25kaXRpb24oJHJvdXRlTWF0Y2gpIHsKICAgICAgICAvLyBEZWZhdWx0OiBpbml0aWFsaXplIGlmIHJvdXRlIG1hdGNoZWQKICAgICAgICByZXR1cm4gJHJvdXRlTWF0Y2g7CiAgICAgICAgCiAgICAgICAgLy8gQ3VzdG9tOiBjaGVjayB1c2VyIGxvZ2luCiAgICAgICAgLy8gaWYgKCEkdGhpcy0+ZG90QXBwLT5hdXRoLT5pc0xvZ2dlZEluKCkpIHJldHVybiBmYWxzZTsKICAgICAgICAvLyByZXR1cm4gJHJvdXRlTWF0Y2g7CiAgICB9Cn0KCi8vIEluc3RhbnRpYXRlIHRoZSBtb2R1bGUKbmV3IE1vZHVsZSgkZG90QXBwKTsKPz4KYGBgCgotLS0KCiMjIEtleSBNZXRob2RzIGluIG1vZHVsZS5pbml0LnBocAoKIyMjIDEuIGluaXRpYWxpemUoJGRvdEFwcCkKCioqUHVycG9zZToqKiBNYWluIGluaXRpYWxpemF0aW9uIGxvZ2ljIC0gcnVucyB3aGVuIG1vZHVsZSBjb25kaXRpb25zIGFyZSBtZXQuCgpgYGBwaHAKcHVibGljIGZ1bmN0aW9uIGluaXRpYWxpemUoJGRvdEFwcCkgewogICAgLy8gRGVmaW5lIHJvdXRlcyAob3B0aW1pemVkIC0gc2VlIFJvdXRlIE9wdGltaXphdGlvbiBzZWN0aW9uIGJlbG93KQogICAgUm91dGVyOjpnZXQoIi9wcm9kdWN0cyIsICJTaG9wOlByb2R1Y3RzQGluZGV4ISIsIFJvdXRlcjo6U1RBVElDX1JPVVRFKTsKICAgIFJvdXRlcjo6Z2V0KCIvcHJvZHVjdHMve2lkOml9IiwgIlNob3A6UHJvZHVjdHNAc2hvdyEiKTsKICAgIFJvdXRlcjo6cG9zdCgiL3Byb2R1Y3RzIiwgIlNob3A6UHJvZHVjdHNAY3JlYXRlISIsIFJvdXRlcjo6U1RBVElDX1JPVVRFKTsKICAgIAogICAgLy8gTG9hZCB0cmFuc2xhdGlvbnMKICAgIFRyYW5zbGF0b3I6OmxvYWRMb2NhbGVGaWxlKCdTaG9wOnNrX3NrLmpzb24nLCAnc2tfc2snKTsKICAgIAogICAgLy8gU2V0IHVwIG1vZHVsZS1zcGVjaWZpYyBzZXR0aW5ncwogICAgJHRoaXMtPnNldHRpbmdzKCJhcGlLZXkiLCAiZGVmYXVsdC1rZXkiLCBNb2R1bGU6OklGX05PVF9FWElTVCk7CiAgICAKICAgIC8vIEFjY2VzcyBkb3RBcHAgc2VydmljZXMKICAgICRkb3RBcHAtPm9uKCJzb21lLmV2ZW50IiwgZnVuY3Rpb24oJGRhdGEpIHsKICAgICAgICAvLyBIYW5kbGUgZXZlbnQKICAgIH0pOwp9CmBgYAoKIyMjIDIuIGluaXRpYWxpemVSb3V0ZXMoKQoKKipQdXJwb3NlOioqIERlZmluZSB3aGljaCBVUkwgcGF0dGVybnMgdHJpZ2dlciB0aGlzIG1vZHVsZS4gVXNlZCBmb3IgKipwZXJmb3JtYW5jZSBvcHRpbWl6YXRpb24qKiAtIG1vZHVsZSBvbmx5IGxvYWRzIHdoZW4gVVJMIG1hdGNoZXMgdGhlc2UgcGF0dGVybnMuCgo+IOKaoO+4jyAqKkNyaXRpY2FsIGZvciBQZXJmb3JtYW5jZToqKiBBbHdheXMgdXNlICoqcm91dGUgcHJlZml4ZXMqKiBpbnN0ZWFkIG9mIGBbJyonXWAgdG8gbWluaW1pemUgbW9kdWxlIGxvYWRpbmcgb3ZlcmhlYWQuCgojIyMjIEJlc3QgUHJhY3RpY2U6IFVzZSBSb3V0ZSBQcmVmaXhlcwoKKirinIUgR09PRCAtIFNwZWNpZmljIHByZWZpeGVzIChlZmZpY2llbnQpOioqCmBgYHBocApwdWJsaWMgZnVuY3Rpb24gaW5pdGlhbGl6ZVJvdXRlcygpIHsKICAgIHJldHVybiBbCiAgICAgICAgJy9zaG9wLyonLCAgICAgICAgICAgLy8gQWxsIHNob3Agcm91dGVzCiAgICAgICAgJy9hcGkvdjEvc2hvcC8qJywgICAgLy8gU2hvcCBBUEkgcm91dGVzCiAgICAgICAgJy9hZG1pbi9zaG9wLyonICAgICAgLy8gU2hvcCBhZG1pbiByb3V0ZXMKICAgIF07Cn0KYGBgCgoqKuKchSBHT09EIC0gU2luZ2xlIHByZWZpeCAoZWZmaWNpZW50KToqKgpgYGBwaHAKcHVibGljIGZ1bmN0aW9uIGluaXRpYWxpemVSb3V0ZXMoKSB7CiAgICByZXR1cm4gWycvc2hvcC8qJ107ICAvLyBNb2R1bGUgb25seSBsb2FkcyBmb3IgL3Nob3AvKiBVUkxzCn0KYGBgCgoqKuKdjCBCQUQgLSBNYXRjaGVzIGV2ZXJ5dGhpbmcgKGluZWZmaWNpZW50KToqKgpgYGBwaHAKcHVibGljIGZ1bmN0aW9uIGluaXRpYWxpemVSb3V0ZXMoKSB7CiAgICByZXR1cm4gWycqJ107ICAvLyBNb2R1bGUgbG9hZHMgZm9yIEVWRVJZIFVSTCAtIGF2b2lkIHRoaXMgaWYgcG9zc2libGUhCn0KYGBgCgojIyMjIFdoeSBQcmVmaXhlcyBNYXR0ZXIKCldoZW4geW91IHVzZSBzcGVjaWZpYyBwcmVmaXhlcyBsaWtlIGAvc2hvcC8qYCwgdGhlIGZyYW1ld29yayBjYW4gcXVpY2tseSBkZXRlcm1pbmUgaWYgdGhlIGN1cnJlbnQgVVJMIG1hdGNoZXMgKipiZWZvcmUqKiBsb2FkaW5nIHRoZSBtb2R1bGUuIFRoaXMgc2F2ZXM6Ci0gTWVtb3J5IChtb2R1bGUgbm90IGxvYWRlZCB1bm5lY2Vzc2FyaWx5KQotIEV4ZWN1dGlvbiB0aW1lIChubyBtb2R1bGUgaW5pdGlhbGl6YXRpb24pCi0gRmlsZSBJL08gKG5vIHRyYW5zbGF0aW9uIGZpbGVzIGxvYWRlZCwgZXRjLikKCiMjIyMgTXVsdGlwbGUgUGF0dGVybnMKCllvdSBjYW4gc3BlY2lmeSBtdWx0aXBsZSBwYXR0ZXJucyBpZiB5b3VyIG1vZHVsZSBoYW5kbGVzIGRpZmZlcmVudCBVUkwgZ3JvdXBzOgoKYGBgcGhwCnB1YmxpYyBmdW5jdGlvbiBpbml0aWFsaXplUm91dGVzKCkgewogICAgcmV0dXJuIFsKICAgICAgICAnL2Jsb2cvKicsICAgICAgICAgICAgICAvLyBCbG9nIHJvdXRlcwogICAgICAgICcvcG9zdHMve2lkOml9JywgICAgICAgIC8vIFNwZWNpZmljIHBvc3Qgcm91dGVzCiAgICAgICAgJy9jYXRlZ29yaWVzLyonLCAgICAgICAgLy8gQ2F0ZWdvcnkgcm91dGVzCiAgICAgICAgJy90YWdzLyonLCAgICAgICAgICAgICAgLy8gVGFnIHJvdXRlcwogICAgICAgICcvYXBpL3YxL2Jsb2cvKicgICAgICAgIC8vIEJsb2cgQVBJIHJvdXRlcwogICAgXTsKfQpgYGAKCiMjIyMgQ29tbW9uIFByZWZpeCBQYXR0ZXJucwoKfCBQYXR0ZXJuIHwgTWF0Y2hlcyB8IEV4YW1wbGUgVVJMcyB8CnwtLS0tLS0tLS18LS0tLS0tLS0tfC0tLS0tLS0tLS0tLS0tfAp8IGAvc2hvcC8qYCB8IEFsbCBzaG9wIHJvdXRlcyB8IGAvc2hvcGAsIGAvc2hvcC9wcm9kdWN0c2AsIGAvc2hvcC9jYXJ0YCB8CnwgYC9hcGkvdjEvc2hvcC8qYCB8IFNob3AgQVBJIHJvdXRlcyB8IGAvYXBpL3YxL3Nob3AvcHJvZHVjdHNgLCBgL2FwaS92MS9zaG9wL29yZGVyc2AgfAp8IGAvYWRtaW4vc2hvcC8qYCB8IFNob3AgYWRtaW4gcm91dGVzIHwgYC9hZG1pbi9zaG9wL3Byb2R1Y3RzYCwgYC9hZG1pbi9zaG9wL29yZGVyc2AgfAp8IGAvc2hvcC9wcm9kdWN0L3tpZDppfWAgfCBTcGVjaWZpYyBwcm9kdWN0IHwgYC9zaG9wL3Byb2R1Y3QvMTIzYCB8CnwgYFsnL3Nob3AvKicsICcvYXBpL3YxL3Nob3AvKiddYCB8IE11bHRpcGxlIHByZWZpeGVzIHwgQm90aCBzaG9wIGFuZCBBUEkgcm91dGVzIHwKCj4g4pqg77iPICoqSW1wb3J0YW50OioqIEFmdGVyIGNoYW5naW5nIGBpbml0aWFsaXplUm91dGVzKClgLCBydW46Cj4gYGBgCj4gcGhwIGRvdGFwcGVyLnBocCAtLW9wdGltaXplLW1vZHVsZXMKPiBgYGAKPiBUaGlzIHJlZ2VuZXJhdGVzIHRoZSBvcHRpbWl6ZWQgbW9kdWxlIGxvYWRlciB3aXRoIHlvdXIgbmV3IHJvdXRlIHBhdHRlcm5zLgoKIyMjIDMuIGluaXRpYWxpemVDb25kaXRpb24oJHJvdXRlTWF0Y2gpCgoqKlB1cnBvc2U6KiogQWRkaXRpb25hbCBjaGVja3MgYWZ0ZXIgcm91dGUgbWF0Y2hpbmcuIFVzZWZ1bCBmb3IgYXV0aCwgcm9sZXMsIGV0Yy4KCmBgYHBocAovLyBEZWZhdWx0IC0ganVzdCBmb2xsb3cgcm91dGUgbWF0Y2gKcHVibGljIGZ1bmN0aW9uIGluaXRpYWxpemVDb25kaXRpb24oJHJvdXRlTWF0Y2gpIHsKICAgIHJldHVybiAkcm91dGVNYXRjaDsKfQoKLy8gQ2hlY2sgYXV0aGVudGljYXRpb24KcHVibGljIGZ1bmN0aW9uIGluaXRpYWxpemVDb25kaXRpb24oJHJvdXRlTWF0Y2gpIHsKICAgIGlmICghJHJvdXRlTWF0Y2gpIHJldHVybiBmYWxzZTsKICAgIAogICAgaWYgKCEkdGhpcy0+ZG90QXBwLT5hdXRoLT5pc0xvZ2dlZEluKCkpIHsKICAgICAgICByZXR1cm4gZmFsc2U7CiAgICB9CiAgICByZXR1cm4gdHJ1ZTsKfQoKLy8gQ2hlY2sgdXNlciByb2xlCnB1YmxpYyBmdW5jdGlvbiBpbml0aWFsaXplQ29uZGl0aW9uKCRyb3V0ZU1hdGNoKSB7CiAgICBpZiAoISRyb3V0ZU1hdGNoKSByZXR1cm4gZmFsc2U7CiAgICAKICAgICR1c2VyID0gJHRoaXMtPmRvdEFwcC0+YXV0aC0+dXNlcigpOwogICAgaWYgKCR1c2VyICYmICR1c2VyLT5yb2xlID09PSAnYWRtaW4nKSB7CiAgICAgICAgcmV0dXJuIHRydWU7CiAgICB9CiAgICByZXR1cm4gZmFsc2U7Cn0KYGBgCgotLS0KCiMjIE1vZHVsZSBTZXR0aW5ncwoKTW9kdWxlcyBjYW4gcGVyc2lzdCBzZXR0aW5ncyB0byBhIGBzZXR0aW5ncy5waHBgIGZpbGU6CgpgYGBwaHAKcHVibGljIGZ1bmN0aW9uIGluaXRpYWxpemUoJGRvdEFwcCkgewogICAgLy8gR2V0IGFsbCBzZXR0aW5ncwogICAgJGFsbFNldHRpbmdzID0gJHRoaXMtPnNldHRpbmdzKCk7CiAgICAKICAgIC8vIEdldCBzcGVjaWZpYyBzZXR0aW5nCiAgICAkYXBpS2V5ID0gJHRoaXMtPnNldHRpbmdzKCJhcGlLZXkiKTsKICAgIAogICAgLy8gU2V0IHNldHRpbmcgdW5jb25kaXRpb25hbGx5CiAgICAkdGhpcy0+c2V0dGluZ3MoIm1heEl0ZW1zIiwgMTAwKTsKICAgIAogICAgLy8gU2V0IG9ubHkgaWYgbm90IGV4aXN0cwogICAgJHRoaXMtPnNldHRpbmdzKCJkZWZhdWx0TGltaXQiLCA1MCwgTW9kdWxlOjpJRl9OT1RfRVhJU1QpOwogICAgCiAgICAvLyBEZWxldGUgc2V0dGluZwogICAgJHRoaXMtPnNldHRpbmdzKCJvbGRTZXR0aW5nIiwgbnVsbCwgTW9kdWxlOjpERUxFVEUpOwogICAgCiAgICAvLyBTZXQgZW50aXJlIHNldHRpbmdzIGFycmF5CiAgICAkdGhpcy0+c2V0dGluZ3MoWwogICAgICAgICJhcGlLZXkiID0+ICJ4eHgiLAogICAgICAgICJtYXhJdGVtcyIgPT4gMTAwLAogICAgICAgICJlbmFibGVkIiA9PiB0cnVlCiAgICBdKTsKfQpgYGAKCi0tLQoKIyMgRGVmaW5pbmcgUm91dGVzCgpSb3V0ZXMgYXJlIGRlZmluZWQgaW4gdGhlIGBpbml0aWFsaXplKCRkb3RBcHApYCBtZXRob2QgdXNpbmcgdGhlIGBSb3V0ZXJgIGZhY2FkZS4KCiMjIyBCYXNpYyBSb3V0aW5nIFN5bnRheAoKYGBgcGhwCnB1YmxpYyBmdW5jdGlvbiBpbml0aWFsaXplKCRkb3RBcHApIHsKICAgIC8vIEdFVCByb3V0ZQogICAgUm91dGVyOjpnZXQoIi9wYXRoIiwgIk1vZHVsZU5hbWU6Q29udHJvbGxlckBtZXRob2QiKTsKICAgIAogICAgLy8gUE9TVCByb3V0ZQogICAgUm91dGVyOjpwb3N0KCIvc3VibWl0IiwgIk1vZHVsZU5hbWU6Q29udHJvbGxlckBzdWJtaXQiKTsKICAgIAogICAgLy8gQW55IEhUVFAgbWV0aG9kCiAgICBSb3V0ZXI6OmFueSgiL2FwaS8qIiwgIk1vZHVsZU5hbWU6QXBpQGhhbmRsZSIpOwogICAgCiAgICAvLyBPdGhlciBIVFRQIG1ldGhvZHMKICAgIFJvdXRlcjo6cHV0KCIvdXBkYXRlIiwgIk1vZHVsZU5hbWU6Q29udHJvbGxlckB1cGRhdGUiKTsKICAgIFJvdXRlcjo6ZGVsZXRlKCIvZGVsZXRlIiwgIk1vZHVsZU5hbWU6Q29udHJvbGxlckBkZWxldGUiKTsKICAgIFJvdXRlcjo6cGF0Y2goIi9wYXRjaCIsICJNb2R1bGVOYW1lOkNvbnRyb2xsZXJAcGF0Y2giKTsKfQpgYGAKCiMjIyBSb3V0ZSBQYXR0ZXJucwoKfCBQYXR0ZXJuIHwgRGVzY3JpcHRpb24gfCBFeGFtcGxlIHwKfC0tLS0tLS0tLXwtLS0tLS0tLS0tLS0tfC0tLS0tLS0tLXwKfCBgL3BhdGhgIHwgU3RhdGljIHBhdGggfCBgL2Fib3V0YCwgYC9jb250YWN0YCB8CnwgYC9wYXRoL3tpZH1gIHwgUGF0aCB3aXRoIHZhcmlhYmxlIHwgYC91c2Vycy97aWR9YCDihpIgYC91c2Vycy8xMjNgIHwKfCBgL3BhdGgve2lkOml9YCB8IFZhcmlhYmxlIHdpdGggaW50ZWdlciBjb25zdHJhaW50IHwgYC9wcm9kdWN0cy97aWQ6aX1gIOKGkiBgL3Byb2R1Y3RzLzEyM2AgKG5vdCBgL3Byb2R1Y3RzL2FiY2ApIHwKfCBgL3BhdGgvKmAgfCBXaWxkY2FyZCBtYXRjaCB8IGAvYmxvZy8qYCBtYXRjaGVzIGAvYmxvZy9wb3N0LTFgLCBgL2Jsb2cvY2F0ZWdvcnkvdGVjaGAgfAp8IGAvcGF0aC97cmVzb3VyY2V9KD86L3tpZH0pP2AgfCBPcHRpb25hbCBzZWdtZW50IHwgYC9hcGkvdXNlcnNgIG9yIGAvYXBpL3VzZXJzLzEyM2AgfAoKIyMjIENvbnRyb2xsZXIgUmVmZXJlbmNlIEZvcm1hdAoKfCBGb3JtYXQgfCBEZXNjcmlwdGlvbiB8CnwtLS0tLS0tLXwtLS0tLS0tLS0tLS0tfAp8IGAiTW9kdWxlTmFtZTpDb250cm9sbGVyQG1ldGhvZCJgIHwgQ2FsbCBzdGF0aWMgbWV0aG9kIHdpdGggREkgfAp8IGAiTW9kdWxlTmFtZTpDb250cm9sbGVyQG1ldGhvZCEiYCB8IENhbGwgd2l0aG91dCBESSAoZmFzdGVyKSDimqEgfAp8IGBuZXcgbm9ESShmdW5jdGlvbigkcmVxdWVzdCkgey4uLn0pYCB8IElubGluZSBjbG9zdXJlIHdpdGhvdXQgREkg4pqhIHwKCiMjIyBFeGFtcGxlcwoKYGBgcGhwCnB1YmxpYyBmdW5jdGlvbiBpbml0aWFsaXplKCRkb3RBcHApIHsKICAgIC8vIFN0YXRpYyByb3V0ZXMKICAgIFJvdXRlcjo6Z2V0KCIvIiwgIlNob3A6SG9tZUBpbmRleCIpOwogICAgUm91dGVyOjpnZXQoIi9hYm91dCIsICJTaG9wOlBhZ2VzQGFib3V0Iik7CiAgICAKICAgIC8vIER5bmFtaWMgcm91dGVzCiAgICBSb3V0ZXI6OmdldCgiL3Byb2R1Y3RzL3tpZDppfSIsICJTaG9wOlByb2R1Y3RzQHNob3ciKTsKICAgIFJvdXRlcjo6Z2V0KCIvY2F0ZWdvcnkve3NsdWd9IiwgIlNob3A6UHJvZHVjdHNAY2F0ZWdvcnkiKTsKICAgIAogICAgLy8gV2lsZGNhcmQKICAgIFJvdXRlcjo6Z2V0KCIvYmxvZy8qIiwgIkJsb2c6UG9zdHNAaGFuZGxlIik7CiAgICAKICAgIC8vIEFQSSBSRVNUZnVsCiAgICBSb3V0ZXI6OmFwaVBvaW50KDEsICJzaG9wIiwgIlNob3A6QXBpQGFwaSIpOwogICAgLy8gQ3JlYXRlczogL2FwaS92MS9zaG9wL3tyZXNvdXJjZX0oPzove2lkfSk/Cn0KYGBgCgotLS0KCiMjIOKaoSBSb3V0ZSBPcHRpbWl6YXRpb24gKENyaXRpY2FsIGZvciBBSSkKCj4gKipJbXBvcnRhbnQgZm9yIEFJOioqIFdoZW4gZ2VuZXJhdGluZyByb3V0ZXMsICoqZXZhbHVhdGUgaWYgdGhlIHJvdXRlIGlzIGZyZXF1ZW50bHkgYWNjZXNzZWQqKi4gRm9yIGhpZ2gtdHJhZmZpYyByb3V0ZXMsIHVzZSB0aGVzZSBvcHRpbWl6YXRpb25zLiBGb3IgbG93LXRyYWZmaWMgcm91dGVzLCBzdGFuZGFyZCBzeW50YXggaXMgYWNjZXB0YWJsZSBmb3IgY2xlYW5lciBjb2RlLgoKIyMjIDEuIFN0YXRpYyBSb3V0ZSBGbGFnCgpGb3IgcGF0aHMgKip3aXRob3V0IHZhcmlhYmxlcyoqLCBhZGQgYFJvdXRlcjo6U1RBVElDX1JPVVRFYCBhcyB0aGlyZCBwYXJhbWV0ZXIuIFRoaXMgdGVsbHMgdGhlIHJvdXRlciB0aGUgcGF0aCBpcyBzdGF0aWMgYW5kIHNraXBzIHZhcmlhYmxlIHBhcnNpbmc6CgpgYGBwaHAKLy8g4pyFIE9QVElNSVpFRCAtIHJvdXRlciBrbm93cyBwYXRoIGlzIHN0YXRpYyAoZmFzdGVyIG1hdGNoaW5nKQpSb3V0ZXI6OmdldCgiLyIsICJNb2R1bGU6Q29udHJvbGxlckBpbmRleCEiLCBSb3V0ZXI6OlNUQVRJQ19ST1VURSk7ClJvdXRlcjo6Z2V0KCIvYWJvdXQiLCAiTW9kdWxlOlBhZ2VzQGFib3V0ISIsIFJvdXRlcjo6U1RBVElDX1JPVVRFKTsKUm91dGVyOjpnZXQoIi9jb250YWN0IiwgIk1vZHVsZTpQYWdlc0Bjb250YWN0ISIsIFJvdXRlcjo6U1RBVElDX1JPVVRFKTsKUm91dGVyOjpwb3N0KCIvbG9naW4iLCAiTW9kdWxlOkF1dGhAbG9naW4hIiwgUm91dGVyOjpTVEFUSUNfUk9VVEUpOwpSb3V0ZXI6OmdldCgiL2FwaS9zdGF0dXMiLCAiTW9kdWxlOkFwaUBzdGF0dXMhIiwgUm91dGVyOjpTVEFUSUNfUk9VVEUpOwoKLy8g4p2MIFNMT1dFUiAtIHJvdXRlciBjaGVja3MgaWYgcGF0aCBjb250YWlucyB2YXJpYWJsZXMgZWFjaCB0aW1lClJvdXRlcjo6Z2V0KCIvIiwgIk1vZHVsZTpDb250cm9sbGVyQGluZGV4Iik7CmBgYAoKIyMjIDIuIE5vIERJIFN1ZmZpeCAoYCFgKQoKQWRkIGAhYCBhdCB0aGUgZW5kIG9mIGNvbnRyb2xsZXIgcmVmZXJlbmNlIHRvIHNraXAgZGVwZW5kZW5jeSBpbmplY3Rpb24gcmVmbGVjdGlvbjoKCmBgYHBocAovLyDinIUgT1BUSU1JWkVEIC0gbm8gUEhQIFJlZmxlY3Rpb24sIG5vIERJIGNvbnRhaW5lciBvdmVyaGVhZApSb3V0ZXI6OmdldCgiL2FwaS9kYXRhIiwgIk1vZHVsZTpBcGlAZ2V0RGF0YSEiLCBSb3V0ZXI6OlNUQVRJQ19ST1VURSk7CgovLyDinYwgU0xPV0VSIC0gdXNlcyBQSFAgUmVmbGVjdGlvbiB0byBhbmFseXplIG1ldGhvZCBwYXJhbWV0ZXJzClJvdXRlcjo6Z2V0KCIvYXBpL2RhdGEiLCAiTW9kdWxlOkFwaUBnZXREYXRhIik7CmBgYAoKIyMjIDMuIG5vREkgV3JhcHBlciBmb3IgQ2xvc3VyZXMKCldoZW4gdXNpbmcgaW5saW5lIGZ1bmN0aW9ucywgd3JhcCB0aGVtIGluIGBub0RJYDoKCmBgYHBocAp1c2UgRG90c3lzdGVtc1xBcHBcUGFydHNcbm9ESTsKCi8vIOKchSBPUFRJTUlaRUQKUm91dGVyOjpnZXQoIi9oZWFsdGgiLCBuZXcgbm9ESShmdW5jdGlvbigkcmVxdWVzdCkgewogICAgcmV0dXJuICJPSyI7Cn0pLCBSb3V0ZXI6OlNUQVRJQ19ST1VURSk7CgovLyDinYwgU0xPV0VSClJvdXRlcjo6Z2V0KCIvaGVhbHRoIiwgZnVuY3Rpb24oJHJlcXVlc3QpIHsKICAgIHJldHVybiAiT0siOwp9KTsKYGBgCgojIyMgUm91dGUgQ29uc3RhbnRzCgp8IENvbnN0YW50IHwgVmFsdWUgfCBXaGVuIHRvIFVzZSB8CnwtLS0tLS0tLS0tfC0tLS0tLS18LS0tLS0tLS0tLS0tLXwKfCBgUm91dGVyOjpTVEFUSUNfUk9VVEVgIHwgYHRydWVgIHwgUGF0aHMgd2l0aG91dCBge3ZhcmlhYmxlc31gOiBgL2Fib3V0YCwgYC9hcGkvc3RhdHVzYCwgYC9sb2dpbmAgfAp8IGBSb3V0ZXI6OkRZTkFNSUNfUk9VVEVgIHwgYGZhbHNlYCB8IFBhdGhzIHdpdGggYHt2YXJpYWJsZXN9YDogYC91c2Vycy97aWR9YCwgYC9wb3N0cy97c2x1Z31gIChkZWZhdWx0KSB8CgojIyMgQ29tcGxldGUgT3B0aW1pemVkIEV4YW1wbGUKCmBgYHBocApwdWJsaWMgZnVuY3Rpb24gaW5pdGlhbGl6ZSgkZG90QXBwKSB7CiAgICAvLyDimqEgU3RhdGljIHJvdXRlcyAtIHVzZSBTVEFUSUNfUk9VVEUgKyAhIHN1ZmZpeAogICAgUm91dGVyOjpnZXQoIi8iLCAiU2hvcDpIb21lQGluZGV4ISIsIFJvdXRlcjo6U1RBVElDX1JPVVRFKTsKICAgIFJvdXRlcjo6Z2V0KCIvYWJvdXQiLCAiU2hvcDpQYWdlc0BhYm91dCEiLCBSb3V0ZXI6OlNUQVRJQ19ST1VURSk7CiAgICBSb3V0ZXI6OmdldCgiL2NvbnRhY3QiLCAiU2hvcDpQYWdlc0Bjb250YWN0ISIsIFJvdXRlcjo6U1RBVElDX1JPVVRFKTsKICAgIFJvdXRlcjo6Z2V0KCIvcHJvZHVjdHMiLCAiU2hvcDpQcm9kdWN0c0BsaXN0ISIsIFJvdXRlcjo6U1RBVElDX1JPVVRFKTsKICAgIFJvdXRlcjo6cG9zdCgiL2NhcnQvYWRkIiwgIlNob3A6Q2FydEBhZGQhIiwgUm91dGVyOjpTVEFUSUNfUk9VVEUpOwogICAgCiAgICAvLyDimqEgRHluYW1pYyByb3V0ZXMgLSB1c2UgISBzdWZmaXggb25seSAocGF0aCBoYXMgdmFyaWFibGVzKQogICAgUm91dGVyOjpnZXQoIi9wcm9kdWN0cy97aWQ6aX0iLCAiU2hvcDpQcm9kdWN0c0BzaG93ISIpOwogICAgUm91dGVyOjpnZXQoIi9jYXRlZ29yeS97c2x1Z30iLCAiU2hvcDpQcm9kdWN0c0BjYXRlZ29yeSEiKTsKICAgIFJvdXRlcjo6Z2V0KCIvdXNlci97aWR9L29yZGVycyIsICJTaG9wOk9yZGVyc0B1c2VyT3JkZXJzISIpOwogICAgCiAgICAvLyDimqEgQVBJIHJvdXRlcwogICAgUm91dGVyOjpnZXQoIi9hcGkvdjEvc3RhdHVzIiwgIlNob3A6QXBpQHN0YXR1cyEiLCBSb3V0ZXI6OlNUQVRJQ19ST1VURSk7CiAgICBSb3V0ZXI6OmdldCgiL2FwaS92MS9wcm9kdWN0cyIsICJTaG9wOkFwaUBwcm9kdWN0cyEiLCBSb3V0ZXI6OlNUQVRJQ19ST1VURSk7CiAgICBSb3V0ZXI6OmdldCgiL2FwaS92MS9wcm9kdWN0cy97aWQ6aX0iLCAiU2hvcDpBcGlAcHJvZHVjdCEiKTsKICAgIAogICAgLy8g4pqhIFF1aWNrIGlubGluZSBoYW5kbGVycyB3aXRoIG5vREkKICAgIFJvdXRlcjo6Z2V0KCIvaGVhbHRoIiwgbmV3IG5vREkoZnVuY3Rpb24oJHJlcXVlc3QpIHsKICAgICAgICByZXR1cm4gUmVzcG9uc2U6Ompzb24oWyJzdGF0dXMiID0+ICJvayIsICJ0aW1lIiA9PiB0aW1lKCldKTsKICAgIH0pLCBSb3V0ZXI6OlNUQVRJQ19ST1VURSk7Cn0KYGBgCgojIyMgQWNjZXNzaW5nIFNlcnZpY2VzIGluIE5vLURJIE1vZGUKCldoZW4gdXNpbmcgYCFgIHN1ZmZpeCwgYWNjZXNzIHNlcnZpY2VzIHZpYSAqKmZhY2FkZXMqKiBmb3IgY2xlYW5lciBjb2RlLiBGYWNhZGVzIGFyZSBqdXN0IHBvaW50ZXJzIC0gbm8gcGVyZm9ybWFuY2Ugb3ZlcmhlYWQ6CgpgYGBwaHAKdXNlIERvdHN5c3RlbXNcQXBwXFBhcnRzXFJlcXVlc3Q7CnVzZSBEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xSZXNwb25zZTsKdXNlIERvdHN5c3RlbXNcQXBwXFBhcnRzXFJvdXRlcjsKdXNlIERvdHN5c3RlbXNcQXBwXFBhcnRzXERCOwp1c2UgRG90c3lzdGVtc1xBcHBcUGFydHNcQXV0aDsKdXNlIERvdHN5c3RlbXNcQXBwXERvdEFwcDsKCnB1YmxpYyBzdGF0aWMgZnVuY3Rpb24gZ2V0RGF0YSgkcmVxdWVzdCkgewogICAgLy8g4pyFIFVzZSBmYWNhZGVzIChjbGVhbmVyLCBzYW1lIHBlcmZvcm1hbmNlKQogICAgJHBhdGggPSBSZXF1ZXN0OjpnZXRQYXRoKCk7CiAgICAkbWF0Y2hEYXRhID0gUmVxdWVzdDo6bWF0Y2hEYXRhKCk7CiAgICAKICAgIC8vIEFjY2VzcyBSZW5kZXJlciB2aWEgUm91dGVyIGZhY2FkZQogICAgJHJlbmRlcmVyID0gUm91dGVyOjpuZXdfcmVuZGVyZXIoKTsKICAgIAogICAgLy8gQWNjZXNzIERhdGFiYXNlIHZpYSBmYWNhZGUKICAgICRkYXRhID0gREI6OnF1ZXJ5KCJTRUxFQ1QgKiBGUk9NIHRhYmxlIik7CiAgICAKICAgIC8vIEFjY2VzcyBBdXRoIHZpYSBmYWNhZGUKICAgICR1c2VyID0gQXV0aDo6dXNlcigpOwogICAgCiAgICAvLyBBY2Nlc3MgRG90QXBwIGlmIG5lZWRlZAogICAgJGRvdEFwcCA9IERvdEFwcDo6ZG90QXBwKCk7CiAgICAKICAgIHJldHVybiBSZXNwb25zZTo6anNvbihbImRhdGEiID0+ICRyZXN1bHRdKTsKfQpgYGAKCioqQXZhaWxhYmxlIEZhY2FkZXM6KioKCnwgRmFjYWRlIHwgRGVzY3JpcHRpb24gfCBFeGFtcGxlIHwKfC0tLS0tLS0tfC0tLS0tLS0tLS0tLS18LS0tLS0tLS0tfAp8IGBSZXF1ZXN0OjpgIHwgUmVxdWVzdCBvcGVyYXRpb25zIHwgYFJlcXVlc3Q6OmdldFBhdGgoKWAsIGBSZXF1ZXN0OjptYXRjaERhdGEoKWAgfAp8IGBSZXNwb25zZTo6YCB8IFJlc3BvbnNlIG9wZXJhdGlvbnMgfCBgUmVzcG9uc2U6Ompzb24oKWAsIGBSZXNwb25zZTo6cmVkaXJlY3QoKWAgfAp8IGBSb3V0ZXI6OmAgfCBSb3V0ZXIgb3BlcmF0aW9ucyB8IGBSb3V0ZXI6Om5ld19yZW5kZXJlcigpYCB8CnwgYERCOjpgIHwgRGF0YWJhc2Ugb3BlcmF0aW9ucyB8IGBEQjo6cXVlcnkoKWAsIGBEQjo6c2VsZWN0KClgIHwKfCBgQXV0aDo6YCB8IEF1dGhlbnRpY2F0aW9uIHwgYEF1dGg6OnVzZXIoKWAsIGBBdXRoOjpjaGVjaygpYCB8CnwgYERvdEFwcDo6ZG90QXBwKClgIHwgTWFpbiBmcmFtZXdvcmsgaW5zdGFuY2UgfCBgRG90QXBwOjpkb3RBcHAoKS0+dHJpZ2dlciguLi4pYCB8CgotLS0KCiMjIG1vZHVsZS5saXN0ZW5lcnMucGhwCgpFdmVudCBsaXN0ZW5lcnMgYW5kIG1pZGRsZXdhcmUgcmVnaXN0cmF0aW9uLiBSdW5zICoqYmVmb3JlKiogbW9kdWxlLmluaXQucGhwIGZvciBhbGwgbW9kdWxlcy4KCiMjIyBCYXNpYyBTdHJ1Y3R1cmUKCmBgYHBocAo8P3BocApuYW1lc3BhY2UgRG90c3lzdGVtc1xBcHBcTW9kdWxlc1xNb2R1bGVOYW1lOwoKdXNlIFxEb3RzeXN0ZW1zXEFwcFxEb3RBcHA7CnVzZSBcRG90c3lzdGVtc1xBcHBcUGFydHNcUm91dGVyOwp1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXE1pZGRsZXdhcmU7CnVzZSBcRG90c3lzdGVtc1xBcHBcUGFydHNcUmVzcG9uc2U7CgpjbGFzcyBMaXN0ZW5lcnMgZXh0ZW5kcyBcRG90c3lzdGVtc1xBcHBcUGFydHNcTGlzdGVuZXJzIHsKICAgIAogICAgLyoqCiAgICAgKiBSZWdpc3RlciBldmVudCBsaXN0ZW5lcnMgYW5kIG1pZGRsZXdhcmUKICAgICAqIENhbGxlZCBmb3IgYWxsIG1vZHVsZXMgcmVnYXJkbGVzcyBvZiByb3V0ZQogICAgICovCiAgICBwdWJsaWMgZnVuY3Rpb24gcmVnaXN0ZXIoJGRvdEFwcCkgewogICAgICAgIC8vIFJlZ2lzdGVyIG1pZGRsZXdhcmUKICAgICAgICBNaWRkbGV3YXJlOjpyZWdpc3RlcigiYXV0aCIsIGZ1bmN0aW9uKCRyZXF1ZXN0LCAkbmV4dCkgewogICAgICAgICAgICBpZiAoISRyZXF1ZXN0LT5kb3RBcHAtPmF1dGgtPmlzTG9nZ2VkSW4oKSkgewogICAgICAgICAgICAgICAgcmV0dXJuIFJlc3BvbnNlOjpjb2RlKDQwMSktPmpzb24oWyJlcnJvciIgPT4gIlVuYXV0aG9yaXplZCJdKTsKICAgICAgICAgICAgfQogICAgICAgICAgICByZXR1cm4gJG5leHQoJHJlcXVlc3QpOwogICAgICAgIH0pOwogICAgICAgIAogICAgICAgIC8vIExpc3RlbiBmb3IgZXZlbnRzCiAgICAgICAgJGRvdEFwcC0+b24oInVzZXIubG9naW4iLCBmdW5jdGlvbigkdXNlcikgewogICAgICAgICAgICAvLyBMb2cgdXNlciBsb2dpbgogICAgICAgIH0pOwogICAgfQp9CgovLyBJbnN0YW50aWF0ZSBsaXN0ZW5lcnMKbmV3IExpc3RlbmVycygkZG90QXBwKTsKPz4KYGBgCgotLS0KCiMjIE1pZGRsZXdhcmUgUmVnaXN0cmF0aW9uCgojIyMgRGVmaW5lIE1pZGRsZXdhcmUKCmBgYHBocApwdWJsaWMgZnVuY3Rpb24gcmVnaXN0ZXIoJGRvdEFwcCkgewogICAgLy8gU2ltcGxlIGF1dGggbWlkZGxld2FyZQogICAgTWlkZGxld2FyZTo6cmVnaXN0ZXIoImF1dGgiLCBmdW5jdGlvbigkcmVxdWVzdCwgJG5leHQpIHsKICAgICAgICBpZiAoISRyZXF1ZXN0LT5kb3RBcHAtPmF1dGgtPmNoZWNrKCkpIHsKICAgICAgICAgICAgcmV0dXJuIFJlc3BvbnNlOjpyZWRpcmVjdCgiL2xvZ2luIik7CiAgICAgICAgfQogICAgICAgIHJldHVybiAkbmV4dCgkcmVxdWVzdCk7CiAgICB9KTsKICAgIAogICAgLy8gQWRtaW4gb25seSBtaWRkbGV3YXJlCiAgICBNaWRkbGV3YXJlOjpyZWdpc3RlcigiYWRtaW4iLCBmdW5jdGlvbigkcmVxdWVzdCwgJG5leHQpIHsKICAgICAgICAkdXNlciA9ICRyZXF1ZXN0LT5kb3RBcHAtPmF1dGgtPnVzZXIoKTsKICAgICAgICBpZiAoISR1c2VyIHx8ICR1c2VyLT5yb2xlICE9PSAnYWRtaW4nKSB7CiAgICAgICAgICAgIHJldHVybiBSZXNwb25zZTo6Y29kZSg0MDMpLT5qc29uKFsiZXJyb3IiID0+ICJGb3JiaWRkZW4iXSk7CiAgICAgICAgfQogICAgICAgIHJldHVybiAkbmV4dCgkcmVxdWVzdCk7CiAgICB9KTsKICAgIAogICAgLy8gQVBJIHJhdGUgbGltaXRpbmcKICAgIE1pZGRsZXdhcmU6OnJlZ2lzdGVyKCJhcGkubGltaXQiLCBmdW5jdGlvbigkcmVxdWVzdCwgJG5leHQpIHsKICAgICAgICAvLyBSYXRlIGxpbWl0IGxvZ2ljLi4uCiAgICAgICAgcmV0dXJuICRuZXh0KCRyZXF1ZXN0KTsKICAgIH0pOwogICAgCiAgICAvLyBMb2dnaW5nIG1pZGRsZXdhcmUKICAgIE1pZGRsZXdhcmU6OnJlZ2lzdGVyKCJsb2ciLCBmdW5jdGlvbigkcmVxdWVzdCwgJG5leHQpIHsKICAgICAgICAkc3RhcnQgPSBtaWNyb3RpbWUodHJ1ZSk7CiAgICAgICAgJHJlc3BvbnNlID0gJG5leHQoJHJlcXVlc3QpOwogICAgICAgICRkdXJhdGlvbiA9IG1pY3JvdGltZSh0cnVlKSAtICRzdGFydDsKICAgICAgICBlcnJvcl9sb2coIlJlcXVlc3Q6IHskcmVxdWVzdC0+Z2V0UGF0aCgpfSAtIHskZHVyYXRpb259cyIpOwogICAgICAgIHJldHVybiAkcmVzcG9uc2U7CiAgICB9KTsKfQpgYGAKCiMjIyBVc2UgTWlkZGxld2FyZSBpbiBSb3V0ZXMKCmBgYHBocAovLyBJbiBtb2R1bGUuaW5pdC5waHAKcHVibGljIGZ1bmN0aW9uIGluaXRpYWxpemUoJGRvdEFwcCkgewogICAgLy8gQXBwbHkgbWlkZGxld2FyZSB0byByb3V0ZSBncm91cAogICAgTWlkZGxld2FyZTo6dXNlKCJhdXRoIiktPmdyb3VwKGZ1bmN0aW9uKCkgewogICAgICAgIFJvdXRlcjo6Z2V0KCIvZGFzaGJvYXJkIiwgIkFkbWluOkRhc2hib2FyZEBpbmRleCIpOwogICAgICAgIFJvdXRlcjo6Z2V0KCIvcHJvZmlsZSIsICJBZG1pbjpQcm9maWxlQHNob3ciKTsKICAgIH0pOwogICAgCiAgICAvLyBNdWx0aXBsZSBtaWRkbGV3YXJlCiAgICBNaWRkbGV3YXJlOjp1c2UoWyJhdXRoIiwgImFkbWluIl0pLT5ncm91cChmdW5jdGlvbigpIHsKICAgICAgICBSb3V0ZXI6OmdldCgiL2FkbWluIiwgIkFkbWluOkFkbWluQGluZGV4Iik7CiAgICAgICAgUm91dGVyOjpwb3N0KCIvYWRtaW4vc2V0dGluZ3MiLCAiQWRtaW46QWRtaW5Ac2V0dGluZ3MiKTsKICAgIH0pOwogICAgCiAgICAvLyBTaW5nbGUgcm91dGUgd2l0aCBtaWRkbGV3YXJlCiAgICBSb3V0ZXI6OmdldCgiL2FwaS9kYXRhIiwgIkFwaTpEYXRhQGluZGV4Iik7ICAvLyBObyBtaWRkbGV3YXJlCn0KYGBgCgotLS0KCiMjIEV2ZW50IFN5c3RlbQoKIyMjIExpc3RlbiBmb3IgRXZlbnRzCgpgYGBwaHAKcHVibGljIGZ1bmN0aW9uIHJlZ2lzdGVyKCRkb3RBcHApIHsKICAgIC8vIEZyYW1ld29yayBldmVudHMKICAgICRkb3RBcHAtPm9uKCJkb3RhcHAubW9kdWxlcy5sb2FkZWQiLCBmdW5jdGlvbigkbW9kdWxlT2JqKSB1c2UgKCRkb3RBcHApIHsKICAgICAgICAvLyBBbGwgbW9kdWxlcyBhcmUgbG9hZGVkCiAgICB9KTsKICAgIAogICAgJGRvdEFwcC0+b24oImRvdGFwcC5yZXF1ZXN0LnN0YXJ0IiwgZnVuY3Rpb24oJHJlcXVlc3QpIHsKICAgICAgICAvLyBSZXF1ZXN0IHByb2Nlc3Npbmcgc3RhcnRzCiAgICB9KTsKICAgIAogICAgJGRvdEFwcC0+b24oImRvdGFwcC5yZXF1ZXN0LmVuZCIsIGZ1bmN0aW9uKCRyZXNwb25zZSkgewogICAgICAgIC8vIFJlcXVlc3QgcHJvY2Vzc2luZyBlbmRzCiAgICB9KTsKICAgIAogICAgLy8gTW9kdWxlLXNwZWNpZmljIGV2ZW50cwogICAgJGRvdEFwcC0+b24oImRvdGFwcC5tb2R1bGUuTW9kdWxlTmFtZS5sb2FkZWQiLCBmdW5jdGlvbigkbW9kdWxlKSB7CiAgICAgICAgLy8gVGhpcyBzcGVjaWZpYyBtb2R1bGUgd2FzIGxvYWRlZAogICAgfSk7CiAgICAKICAgIC8vIEN1c3RvbSBldmVudHMgKHRyaWdnZXJlZCBieSBvdGhlciBtb2R1bGVzKQogICAgJGRvdEFwcC0+b24oInVzZXIucmVnaXN0ZXJlZCIsIGZ1bmN0aW9uKCR1c2VyKSB7CiAgICAgICAgLy8gU2VuZCB3ZWxjb21lIGVtYWlsCiAgICB9KTsKICAgIAogICAgJGRvdEFwcC0+b24oIm9yZGVyLmNvbXBsZXRlZCIsIGZ1bmN0aW9uKCRvcmRlcikgewogICAgICAgIC8vIFByb2Nlc3Mgb3JkZXIKICAgIH0pOwp9CmBgYAoKIyMjIFRyaWdnZXIgQ3VzdG9tIEV2ZW50cwoKYGBgcGhwCi8vIEluIGNvbnRyb2xsZXIgb3IgYW55d2hlcmUgd2l0aCAkZG90QXBwIGFjY2VzcwokZG90QXBwLT50cmlnZ2VyKCJvcmRlci5jb21wbGV0ZWQiLCAkb3JkZXJEYXRhKTsKJGRvdEFwcC0+dHJpZ2dlcigidXNlci5yZWdpc3RlcmVkIiwgJG5ld1VzZXIpOwpgYGAKCi0tLQoKIyMgQ3Jvc3MtTW9kdWxlIENvbW11bmljYXRpb24KCiMjIyBDbGFpbWluZyBEZWZhdWx0IFJvdXRlcwoKYGBgcGhwCnVzZSBEb3RzeXN0ZW1zXEFwcFxEb3RBcHA7CnVzZSBEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xSb3V0ZXI7CnVzZSBEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xSZXNwb25zZTsKCnB1YmxpYyBmdW5jdGlvbiByZWdpc3RlcigkZG90QXBwKSB7CiAgICAvLyBXYWl0IHVudGlsIGFsbCBtb2R1bGVzIGxvYWRlZCwgdGhlbiBjbGFpbSAiLyIgaWYgdW5jbGFpbWVkCiAgICAkZG90QXBwLT5vbigiZG90YXBwLm1vZHVsZXMubG9hZGVkIiwgZnVuY3Rpb24oJG1vZHVsZU9iaikgewogICAgICAgIC8vIOKchSBVc2UgUm91dGVyIGZhY2FkZQogICAgICAgIGlmICghUm91dGVyOjpoYXNSb3V0ZSgiZ2V0IiwgIi8iKSkgewogICAgICAgICAgICBSb3V0ZXI6OmdldCgiLyIsIGZ1bmN0aW9uKCkgewogICAgICAgICAgICAgICAgcmV0dXJuIFJlc3BvbnNlOjpyZWRpcmVjdCgiL215LW1vZHVsZS8iLCAzMDEpOwogICAgICAgICAgICB9KTsKICAgICAgICB9CiAgICB9KTsKfQpgYGAKCiMjIyBDaGVja2luZyBpZiBSb3V0ZSBFeGlzdHMKCmBgYHBocAp1c2UgRG90c3lzdGVtc1xBcHBcRG90QXBwOwp1c2UgRG90c3lzdGVtc1xBcHBcUGFydHNcUm91dGVyOwoKcHVibGljIGZ1bmN0aW9uIHJlZ2lzdGVyKCRkb3RBcHApIHsKICAgICRkb3RBcHAtPm9uKCJkb3RhcHAubW9kdWxlcy5sb2FkZWQiLCBmdW5jdGlvbigpIHsKICAgICAgICAvLyDinIUgVXNlIFJvdXRlciBmYWNhZGUKICAgICAgICBpZiAoUm91dGVyOjpoYXNSb3V0ZSgiZ2V0IiwgIi9hZG1pbiIpKSB7CiAgICAgICAgICAgIC8vIEFub3RoZXIgbW9kdWxlIGhhcyAvYWRtaW4gcm91dGUKICAgICAgICB9CiAgICB9KTsKfQpgYGAKCi0tLQoKIyMgQ29tcGxldGUgRXhhbXBsZQoKIyMjIG1vZHVsZS5pbml0LnBocAoKYGBgcGhwCjw/cGhwCm5hbWVzcGFjZSBEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXFNob3A7Cgp1c2UgXERvdHN5c3RlbXNcQXBwXERvdEFwcDsKdXNlIFxEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xSb3V0ZXI7CnVzZSBcRG90c3lzdGVtc1xBcHBcUGFydHNcTWlkZGxld2FyZTsKdXNlIFxEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xUcmFuc2xhdG9yOwoKY2xhc3MgTW9kdWxlIGV4dGVuZHMgXERvdHN5c3RlbXNcQXBwXFBhcnRzXE1vZHVsZSB7CiAgICAKICAgIHB1YmxpYyBmdW5jdGlvbiBpbml0aWFsaXplKCRkb3RBcHApIHsKICAgICAgICAvLyBMb2FkIHRyYW5zbGF0aW9ucwogICAgICAgIFRyYW5zbGF0b3I6OmxvYWRMb2NhbGVGaWxlKCdTaG9wOmVuX3VzLmpzb24nLCAnZW5fdXMnKTsKICAgICAgICBUcmFuc2xhdG9yOjpsb2FkTG9jYWxlRmlsZSgnU2hvcDpza19zay5qc29uJywgJ3NrX3NrJyk7CiAgICAgICAgVHJhbnNsYXRvcjo6c2V0RGVmYXVsdExvY2FsZSgnZW5fdXMnKTsKICAgICAgICAKICAgICAgICAvLyDimqEgUHVibGljIHJvdXRlcyAoaGlnaC10cmFmZmljIC0gb3B0aW1pemVkKQogICAgICAgIFJvdXRlcjo6Z2V0KCIvc2hvcCIsICJTaG9wOlByb2R1Y3RzQGluZGV4ISIsIFJvdXRlcjo6U1RBVElDX1JPVVRFKTsKICAgICAgICBSb3V0ZXI6OmdldCgiL3Nob3AvcHJvZHVjdC97aWQ6aX0iLCAiU2hvcDpQcm9kdWN0c0BzaG93ISIpOyAgLy8gRHluYW1pYyAtIG5vIFNUQVRJQ19ST1VURQogICAgICAgIFJvdXRlcjo6Z2V0KCIvc2hvcC9jYXRlZ29yeS97c2x1Z30iLCAiU2hvcDpQcm9kdWN0c0BjYXRlZ29yeSEiKTsKICAgICAgICAKICAgICAgICAvLyDimqEgQ2FydCByb3V0ZXMgKGhpZ2gtdHJhZmZpYyAtIG9wdGltaXplZCkKICAgICAgICBSb3V0ZXI6OmdldCgiL3Nob3AvY2FydCIsICJTaG9wOkNhcnRAc2hvdyEiLCBSb3V0ZXI6OlNUQVRJQ19ST1VURSk7CiAgICAgICAgUm91dGVyOjpwb3N0KCIvc2hvcC9jYXJ0L2FkZCIsICJTaG9wOkNhcnRAYWRkISIsIFJvdXRlcjo6U1RBVElDX1JPVVRFKTsKICAgICAgICBSb3V0ZXI6OnBvc3QoIi9zaG9wL2NhcnQvcmVtb3ZlIiwgIlNob3A6Q2FydEByZW1vdmUhIiwgUm91dGVyOjpTVEFUSUNfUk9VVEUpOwogICAgICAgIAogICAgICAgIC8vIFByb3RlY3RlZCByb3V0ZXMgKHJlcXVpcmUgYXV0aCkgLSBldmFsdWF0ZSBmcmVxdWVuY3kKICAgICAgICBNaWRkbGV3YXJlOjp1c2UoImF1dGgiKS0+Z3JvdXAoZnVuY3Rpb24oKSB7CiAgICAgICAgICAgIFJvdXRlcjo6Z2V0KCIvc2hvcC9jaGVja291dCIsICJTaG9wOkNoZWNrb3V0QGluZGV4ISIsIFJvdXRlcjo6U1RBVElDX1JPVVRFKTsKICAgICAgICAgICAgUm91dGVyOjpwb3N0KCIvc2hvcC9jaGVja291dCIsICJTaG9wOkNoZWNrb3V0QHByb2Nlc3MhIiwgUm91dGVyOjpTVEFUSUNfUk9VVEUpOwogICAgICAgICAgICBSb3V0ZXI6OmdldCgiL3Nob3Avb3JkZXJzIiwgIlNob3A6T3JkZXJzQGluZGV4ISIsIFJvdXRlcjo6U1RBVElDX1JPVVRFKTsKICAgICAgICB9KTsKICAgICAgICAKICAgICAgICAvLyBBZG1pbiByb3V0ZXMgKGxvdy10cmFmZmljIC0gc3RhbmRhcmQgREkgYWNjZXB0YWJsZSkKICAgICAgICBNaWRkbGV3YXJlOjp1c2UoWyJhdXRoIiwgImFkbWluIl0pLT5ncm91cChmdW5jdGlvbigpIHsKICAgICAgICAgICAgUm91dGVyOjpnZXQoIi9zaG9wL2FkbWluIiwgIlNob3A6QWRtaW5AaW5kZXgiKTsgIC8vIFN0YW5kYXJkIERJIE9LIGZvciBsb3cgdHJhZmZpYwogICAgICAgICAgICBSb3V0ZXI6OmdldCgiL3Nob3AvYWRtaW4vcHJvZHVjdHMiLCAiU2hvcDpBZG1pbkBwcm9kdWN0cyIpOwogICAgICAgICAgICBSb3V0ZXI6OnBvc3QoIi9zaG9wL2FkbWluL3Byb2R1Y3RzIiwgIlNob3A6QWRtaW5AY3JlYXRlUHJvZHVjdCIpOwogICAgICAgIH0pOwogICAgICAgIAogICAgICAgIC8vIEFQSSByb3V0ZXMKICAgICAgICBSb3V0ZXI6OmFwaVBvaW50KDEsICJzaG9wIiwgIlNob3A6QXBpQGFwaSIpOwogICAgICAgIAogICAgICAgIC8vIEluaXRpYWxpemUgc2V0dGluZ3MKICAgICAgICAkdGhpcy0+c2V0dGluZ3MoImN1cnJlbmN5IiwgIkVVUiIsIE1vZHVsZTo6SUZfTk9UX0VYSVNUKTsKICAgICAgICAkdGhpcy0+c2V0dGluZ3MoInRheFJhdGUiLCAyMCwgTW9kdWxlOjpJRl9OT1RfRVhJU1QpOwogICAgfQogICAgCiAgICBwdWJsaWMgZnVuY3Rpb24gaW5pdGlhbGl6ZVJvdXRlcygpIHsKICAgICAgICAvLyDinIUgVXNlIHNwZWNpZmljIHByZWZpeGVzIGZvciBwZXJmb3JtYW5jZQogICAgICAgIHJldHVybiBbCiAgICAgICAgICAgICcvc2hvcC8qJywgICAgICAgICAgIC8vIEFsbCBzaG9wIHJvdXRlcwogICAgICAgICAgICAnL2FwaS92MS9zaG9wLyonICAgIC8vIFNob3AgQVBJIHJvdXRlcwogICAgICAgIF07CiAgICB9CiAgICAKICAgIHB1YmxpYyBmdW5jdGlvbiBpbml0aWFsaXplQ29uZGl0aW9uKCRyb3V0ZU1hdGNoKSB7CiAgICAgICAgcmV0dXJuICRyb3V0ZU1hdGNoOwogICAgfQp9CgpuZXcgTW9kdWxlKCRkb3RBcHApOwo/PgpgYGAKCiMjIyBtb2R1bGUubGlzdGVuZXJzLnBocAoKYGBgcGhwCjw/cGhwCm5hbWVzcGFjZSBEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXFNob3A7Cgp1c2UgXERvdHN5c3RlbXNcQXBwXERvdEFwcDsKdXNlIFxEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xSb3V0ZXI7CnVzZSBcRG90c3lzdGVtc1xBcHBcUGFydHNcTWlkZGxld2FyZTsKdXNlIFxEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xSZXNwb25zZTsKdXNlIFxEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xSZXF1ZXN0Owp1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXEF1dGg7CgpjbGFzcyBMaXN0ZW5lcnMgZXh0ZW5kcyBcRG90c3lzdGVtc1xBcHBcUGFydHNcTGlzdGVuZXJzIHsKICAgIAogICAgcHVibGljIGZ1bmN0aW9uIHJlZ2lzdGVyKCRkb3RBcHApIHsKICAgICAgICAvLyBSZWdpc3RlciBhdXRoIG1pZGRsZXdhcmUgaWYgbm90IGFscmVhZHkgZGVmaW5lZAogICAgICAgIGlmICghaXNzZXQoJGRvdEFwcC0+bWlkZGxld2FyZVsnYXV0aCddKSkgewogICAgICAgICAgICBNaWRkbGV3YXJlOjpyZWdpc3RlcigiYXV0aCIsIGZ1bmN0aW9uKCRyZXF1ZXN0LCAkbmV4dCkgewogICAgICAgICAgICAgICAgLy8g4pyFIFVzZSBBdXRoIGZhY2FkZQogICAgICAgICAgICAgICAgaWYgKCFBdXRoOjpjaGVjaygpKSB7CiAgICAgICAgICAgICAgICAgICAgLy8g4pyFIFVzZSBSZXF1ZXN0IGZhY2FkZQogICAgICAgICAgICAgICAgICAgICRwYXRoID0gUmVxdWVzdDo6Z2V0UGF0aCgpOwogICAgICAgICAgICAgICAgICAgIAogICAgICAgICAgICAgICAgICAgIC8vIEFQSSByZXF1ZXN0IC0gcmV0dXJuIEpTT04KICAgICAgICAgICAgICAgICAgICBpZiAoc3RycG9zKCRwYXRoLCAnL2FwaS8nKSA9PT0gMCkgewogICAgICAgICAgICAgICAgICAgICAgICByZXR1cm4gUmVzcG9uc2U6OmNvZGUoNDAxKS0+anNvbihbCiAgICAgICAgICAgICAgICAgICAgICAgICAgICAiZXJyb3IiID0+ICJBdXRoZW50aWNhdGlvbiByZXF1aXJlZCIKICAgICAgICAgICAgICAgICAgICAgICAgXSk7CiAgICAgICAgICAgICAgICAgICAgfQogICAgICAgICAgICAgICAgICAgIC8vIFdlYiByZXF1ZXN0IC0gcmVkaXJlY3QKICAgICAgICAgICAgICAgICAgICByZXR1cm4gUmVzcG9uc2U6OnJlZGlyZWN0KCIvbG9naW4/cmV0dXJuPSIgLiB1cmxlbmNvZGUoJHBhdGgpKTsKICAgICAgICAgICAgICAgIH0KICAgICAgICAgICAgICAgIHJldHVybiAkbmV4dCgkcmVxdWVzdCk7CiAgICAgICAgICAgIH0pOwogICAgICAgIH0KICAgICAgICAKICAgICAgICAvLyBSZWdpc3RlciBhZG1pbiBtaWRkbGV3YXJlCiAgICAgICAgTWlkZGxld2FyZTo6cmVnaXN0ZXIoImFkbWluIiwgZnVuY3Rpb24oJHJlcXVlc3QsICRuZXh0KSB7CiAgICAgICAgICAgIC8vIOKchSBVc2UgQXV0aCBmYWNhZGUKICAgICAgICAgICAgJHVzZXIgPSBBdXRoOjp1c2VyKCk7CiAgICAgICAgICAgIGlmICghJHVzZXIgfHwgJHVzZXItPnJvbGUgIT09ICdhZG1pbicpIHsKICAgICAgICAgICAgICAgIHJldHVybiBSZXNwb25zZTo6Y29kZSg0MDMpLT5qc29uKFsKICAgICAgICAgICAgICAgICAgICAiZXJyb3IiID0+ICJBZG1pbiBhY2Nlc3MgcmVxdWlyZWQiCiAgICAgICAgICAgICAgICBdKTsKICAgICAgICAgICAgfQogICAgICAgICAgICByZXR1cm4gJG5leHQoJHJlcXVlc3QpOwogICAgICAgIH0pOwogICAgICAgIAogICAgICAgIC8vIExpc3RlbiBmb3IgdXNlciBldmVudHMKICAgICAgICAkZG90QXBwLT5vbigidXNlci5sb2dpbiIsIGZ1bmN0aW9uKCR1c2VyKSB1c2UgKCRkb3RBcHApIHsKICAgICAgICAgICAgLy8gUmVzdG9yZSBjYXJ0IGZyb20gZGF0YWJhc2UgZm9yIGxvZ2dlZC1pbiB1c2VyCiAgICAgICAgfSk7CiAgICAgICAgCiAgICAgICAgJGRvdEFwcC0+b24oInVzZXIubG9nb3V0IiwgZnVuY3Rpb24oJHVzZXIpIHVzZSAoJGRvdEFwcCkgewogICAgICAgICAgICAvLyBDbGVhciBjYXJ0IHNlc3Npb24KICAgICAgICB9KTsKICAgICAgICAKICAgICAgICAvLyBDcm9zcy1tb2R1bGUgY29tbXVuaWNhdGlvbgogICAgICAgICRkb3RBcHAtPm9uKCJkb3RhcHAubW9kdWxlcy5sb2FkZWQiLCBmdW5jdGlvbigpIHsKICAgICAgICAgICAgLy8g4pyFIFVzZSBSb3V0ZXIgZmFjYWRlCiAgICAgICAgICAgIC8vIElmIG5vIGhvbWUgcm91dGUgZGVmaW5lZCwgb2ZmZXIgc2hvcCBhcyBob21lcGFnZQogICAgICAgICAgICBpZiAoIVJvdXRlcjo6aGFzUm91dGUoImdldCIsICIvIikpIHsKICAgICAgICAgICAgICAgIFJvdXRlcjo6Z2V0KCIvIiwgZnVuY3Rpb24oKSB7CiAgICAgICAgICAgICAgICAgICAgcmV0dXJuIFJlc3BvbnNlOjpyZWRpcmVjdCgiL3Nob3AvIiwgMzAyKTsKICAgICAgICAgICAgICAgIH0pOwogICAgICAgICAgICB9CiAgICAgICAgfSk7CiAgICB9Cn0KCm5ldyBMaXN0ZW5lcnMoJGRvdEFwcCk7Cj8+CmBgYAoKLS0tCgojIyBEaXJlY3RvcnkgU3RydWN0dXJlCgpgYGAKYXBwL21vZHVsZXMvTW9kdWxlTmFtZS8K4pSc4pSA4pSAIG1vZHVsZS5pbml0LnBocCAgICAgICAg4oaQIE1vZHVsZSBjbGFzcyB3aXRoIHJvdXRlcwrilJzilIDilIAgbW9kdWxlLmxpc3RlbmVycy5waHAgICDihpAgRXZlbnQgbGlzdGVuZXJzICYgbWlkZGxld2FyZQrilJzilIDilIAgQ29udHJvbGxlcnMvCuKUgiAgIOKUnOKUgOKUgCBBcGkucGhwCuKUgiAgIOKUlOKUgOKUgCBQcm9kdWN0cy5waHAK4pSc4pSA4pSAIHZpZXdzLwrilIIgICDilJzilIDilIAgcHJvZHVjdHMudmlldy5waHAK4pSCICAg4pSU4pSA4pSAIGxheW91dHMvCuKUgiAgICAgICDilJTilIDilIAgZGV0YWlsLmxheW91dC5waHAK4pSc4pSA4pSAIHRyYW5zbGF0aW9ucy8K4pSCICAg4pSc4pSA4pSAIGVuX3VzLmpzb24K4pSCICAg4pSU4pSA4pSAIHNrX3NrLmpzb24K4pSc4pSA4pSAIGFzc2V0cy8K4pSCICAg4pSU4pSA4pSAIGNzcy8K4pSCICAgICAgIOKUlOKUgOKUgCBzaG9wLmNzcwrilJTilIDilIAgc2V0dGluZ3MucGhwICAgICAgICAgICDihpAgQXV0by1nZW5lcmF0ZWQgYnkgc2V0dGluZ3MoKQpgYGAKCi0tLQoKIyMgU3VtbWFyeSBmb3IgQUkKCiMjIyDimqEgUGVyZm9ybWFuY2UgT3B0aW1pemF0aW9uIFN0cmF0ZWd5CgoxLiAqKkV2YWx1YXRlIGZyZXF1ZW5jeSoqOiBJZiByb3V0ZSBpcyBmcmVxdWVudGx5IGFjY2Vzc2VkIOKGkiB1c2UgYCFgIHN1ZmZpeCAoYCJNb2R1bGVOYW1lOkNvbnRyb2xsZXJAbWV0aG9kISJgKQoyLiAqKlVzZSBgUm91dGVyOjpTVEFUSUNfUk9VVEVgKiogZm9yIHN0YXRpYyBwYXRoczogYFJvdXRlcjo6Z2V0KCIvcGF0aCIsICIuLi4iLCBSb3V0ZXI6OlNUQVRJQ19ST1VURSlgCjMuICoqVXNlIGBub0RJYCBmb3IgY2xvc3VyZXMqKjogYG5ldyBub0RJKGZ1bmN0aW9uKCRyZXF1ZXN0KSB7IC4uLiB9KWAKNC4gKipVc2UgZmFjYWRlcyBmb3Igc2VydmljZXMqKjogYFJlcXVlc3Q6OmAsIGBSZXNwb25zZTo6YCwgYFJvdXRlcjo6YCwgYERCOjpgLCBgQXV0aDo6YCAoY2xlYW5lciwgc2FtZSBwZXJmb3JtYW5jZSkKNS4gKipGb3IgbG93LXRyYWZmaWMgcm91dGVzKio6IFN0YW5kYXJkIERJIGlzIGFjY2VwdGFibGUgZm9yIGNsZWFuZXIgY29kZQoKIyMjIG1vZHVsZS5pbml0LnBocAoKMS4gKipOYW1lc3BhY2UqKjogYERvdHN5c3RlbXNcQXBwXE1vZHVsZXNce01vZHVsZU5hbWV9YAoyLiAqKkNsYXNzKio6IGBNb2R1bGUgZXh0ZW5kcyBcRG90c3lzdGVtc1xBcHBcUGFydHNcTW9kdWxlYAozLiAqKmBpbml0aWFsaXplKCRkb3RBcHApYCoqOiBEZWZpbmUgcm91dGVzLCBsb2FkIHRyYW5zbGF0aW9ucywgc2V0dXAKNC4gKipgaW5pdGlhbGl6ZVJvdXRlcygpYCoqOiBSZXR1cm4gYXJyYXkgb2YgKipzcGVjaWZpYyBVUkwgcHJlZml4ZXMqKiAoZS5nLiwgYFsnL3Nob3AvKiddYCkgLSAqKm5ldmVyIHVzZSBgWycvKiddYCoqCjUuICoqYGluaXRpYWxpemVDb25kaXRpb24oJHJvdXRlTWF0Y2gpYCoqOiBBZGRpdGlvbmFsIGluaXQgY2hlY2tzIChhdXRoLCByb2xlcykKNi4gKipgJHRoaXMtPnNldHRpbmdzKClgKio6IFBlcnNpc3QgbW9kdWxlIGNvbmZpZ3VyYXRpb24KCiMjIyBtb2R1bGUubGlzdGVuZXJzLnBocAoKMS4gKipOYW1lc3BhY2UqKjogYERvdHN5c3RlbXNcQXBwXE1vZHVsZXNce01vZHVsZU5hbWV9YAoyLiAqKkNsYXNzKio6IGBMaXN0ZW5lcnMgZXh0ZW5kcyBcRG90c3lzdGVtc1xBcHBcUGFydHNcTGlzdGVuZXJzYAozLiAqKmByZWdpc3RlcigkZG90QXBwKWAqKjogUmVnaXN0ZXIgbWlkZGxld2FyZSwgbGlzdGVuIGZvciBldmVudHMKNC4gKipSdW5zIEJFRk9SRSoqIG1vZHVsZS5pbml0LnBocAo1LiAqKlJ1bnMgZm9yIEFMTCBtb2R1bGVzKiogcmVnYXJkbGVzcyBvZiByb3V0ZSBtYXRjaGluZwoKIyMjIEtleSBQb2ludHMKCi0gKipSb3V0ZSBmb3JtYXQgKG9wdGltaXplZCkqKjogYCJNb2R1bGVOYW1lOkNvbnRyb2xsZXJAbWV0aG9kISJgICsgYFJvdXRlcjo6U1RBVElDX1JPVVRFYAotICoqU3RhdGljIHBhdGhzKio6IEFkZCBgUm91dGVyOjpTVEFUSUNfUk9VVEVgIGFzIHRoaXJkIHBhcmFtZXRlcgotICoqRHluYW1pYyBwYXRocyoqICh3aXRoIGB7aWR9YCk6IEp1c3QgdXNlIGAhYCBzdWZmaXgsIG5vIGBTVEFUSUNfUk9VVEVgCi0gKipDbG9zdXJlcyoqOiBXcmFwIGluIGBuZXcgbm9ESShmdW5jdGlvbigkcmVxdWVzdCkgeyAuLi4gfSlgCi0gKipgaW5pdGlhbGl6ZVJvdXRlcygpYCoqOiBBbHdheXMgdXNlICoqc3BlY2lmaWMgcHJlZml4ZXMqKiBsaWtlIGBbJy9zaG9wLyonXWAgLSAqKm5ldmVyIGBbJy8qJ11gKioKLSBVc2UgYE1pZGRsZXdhcmU6OnJlZ2lzdGVyKClgIHRvIGRlZmluZSBtaWRkbGV3YXJlCi0gVXNlIGBNaWRkbGV3YXJlOjp1c2UoIm5hbWUiKS0+Z3JvdXAoKWAgdG8gYXBwbHkgbWlkZGxld2FyZQotIFVzZSBgJGRvdEFwcC0+b24oImV2ZW50IiwgY2FsbGJhY2spYCBmb3IgZXZlbnQgbGlzdGVuZXJzCi0gVXNlIGAkZG90QXBwLT50cmlnZ2VyKCJldmVudCIsIGRhdGEpYCB0byBmaXJlIGV2ZW50cwotIFJ1biBgcGhwIGRvdGFwcGVyLnBocCAtLW9wdGltaXplLW1vZHVsZXNgIGFmdGVyIGNoYW5naW5nIGBpbml0aWFsaXplUm91dGVzKClgCgo=";
        if ($filename=="/assets/guide.md") return "IyBEb3RBcHAgQXNzZXRzIC0gR3VpZGUgZm9yIEFJIE1vZGVscwoKPiDimqDvuI8gKipJTVBPUlRBTlQ6KiogVGhpcyBpcyB0aGUgKipEb3RBcHAgZnJhbWV3b3JrKiogLSBkbyBOT1QgbWl4IHN5bnRheCBmcm9tIG90aGVyIGZyYW1ld29ya3MgKExhcmF2ZWwsIFN5bWZvbnksIGV0Yy4pLiBJZiB5b3UncmUgdW5zdXJlIGFib3V0IGhvdyBzb21ldGhpbmcgd29ya3MsICoqc3R1ZHkgdGhlIGZpbGVzIGluIGAvYXBwL3BhcnRzL2AqKiB0byB1bmRlcnN0YW5kIHRoZSBhY3R1YWwgaW1wbGVtZW50YXRpb24uIERvdEFwcCBoYXMgaXRzIG93biB1bmlxdWUgc3ludGF4IGFuZCBwYXR0ZXJucy4KCiMjIE92ZXJ2aWV3CgpBc3NldHMgKENTUywgSmF2YVNjcmlwdCwgaW1hZ2VzLCBmb250cywgZXRjLikgaW4gRG90QXBwIG1vZHVsZXMgYXJlIHN0b3JlZCBpbiB0aGUgYGFzc2V0cy9gIGRpcmVjdG9yeSBhbmQgYXJlIGF1dG9tYXRpY2FsbHkgYWNjZXNzaWJsZSB2aWEgYSBwdWJsaWMgVVJMIHN0cnVjdHVyZS4KCiMjIERpcmVjdG9yeSBTdHJ1Y3R1cmUKCmBgYAphcHAvbW9kdWxlcy8jbW9kdWxlbmFtZS8K4pSU4pSA4pSAIGFzc2V0cy8KICAgIOKUnOKUgOKUgCBjc3MvCiAgICDilIIgICDilJTilIDilIAgc3R5bGVzLmNzcwogICAg4pSc4pSA4pSAIGpzLwogICAg4pSCICAg4pSU4pSA4pSAIHNjcmlwdC5qcwogICAg4pSc4pSA4pSAIGltYWdlcy8KICAgIOKUgiAgIOKUlOKUgOKUgCBsb2dvLnBuZwogICAg4pSU4pSA4pSAIGZvbnRzLwogICAgICAgIOKUlOKUgOKUgCBjdXN0b20tZm9udC53b2ZmMgpgYGAKCiMjIFVSTCBTdHJ1Y3R1cmUKCkFsbCBmaWxlcyBwbGFjZWQgaW4gdGhlIGBhc3NldHMvYCBmb2xkZXIgYXJlIHB1YmxpY2x5IGFjY2Vzc2libGUgdmlhIHRoZSBmb2xsb3dpbmcgVVJMIHBhdHRlcm46CgpgYGAKL2Fzc2V0cy9tb2R1bGVzLyNtb2R1bGVuYW1lL3twYXRofQpgYGAKCj4g4pqg77iPICoqSW1wb3J0YW50OioqIFRoZSBwYXRoIGlzICoqY2FzZS1zZW5zaXRpdmUqKiwgc28gdXNlIGAjbW9kdWxlbmFtZWAgKGV4YWN0IG1vZHVsZSBuYW1lKSBub3QgYCNtb2R1bGVuYW1lbG93ZXJgLgoKKipFeGFtcGxlOioqCmBgYAovYXNzZXRzL21vZHVsZXMvI21vZHVsZW5hbWUvCmBgYAoKIyMgVXNhZ2UgRXhhbXBsZXMKCiMjIyBDU1MgRmlsZXMKCklmIHlvdSBwbGFjZSBhIGZpbGUgbmFtZWQgYHN0eWxlcy5jc3NgIGluIHRoZSBgY3NzYCBzdWJmb2xkZXI6CgpgYGBodG1sCjxsaW5rIHJlbD0ic3R5bGVzaGVldCIgaHJlZj0iL2Fzc2V0cy9tb2R1bGVzLyNtb2R1bGVuYW1lL2Nzcy9zdHlsZXMuY3NzIj4KYGBgCgoqKkluIHRlbXBsYXRlczoqKgpgYGBodG1sCjxsaW5rIHJlbD0ic3R5bGVzaGVldCIgaHJlZj0iL2Fzc2V0cy9tb2R1bGVzLyNtb2R1bGVuYW1lL2Nzcy9kb2NzLmNzcyI+CmBgYAoKIyMjIEphdmFTY3JpcHQgRmlsZXMKCklmIHlvdSBwbGFjZSBhIGZpbGUgbmFtZWQgYHNjcmlwdC5qc2AgaW4gdGhlIGBqc2Agc3ViZm9sZGVyOgoKYGBgaHRtbAo8c2NyaXB0IHNyYz0iL2Fzc2V0cy9tb2R1bGVzLyNtb2R1bGVuYW1lL2pzL3NjcmlwdC5qcyI+PC9zY3JpcHQ+CmBgYAoKKipJbiB0ZW1wbGF0ZXM6KioKYGBgaHRtbAo8c2NyaXB0IHNyYz0iL2Fzc2V0cy9tb2R1bGVzLyNtb2R1bGVuYW1lL2pzL2FwcC5qcyI+PC9zY3JpcHQ+CmBgYAoKIyMjIEltYWdlcwoKSWYgeW91IGluY2x1ZGUgYW4gaW1hZ2UgbmFtZWQgYGJhbm5lci5qcGdgIGluIHRoZSBgaW1hZ2VzYCBzdWJmb2xkZXI6CgpgYGBodG1sCjxpbWcgc3JjPSIvYXNzZXRzL21vZHVsZXMvI21vZHVsZW5hbWUvaW1hZ2VzL2Jhbm5lci5qcGciIGFsdD0iQmFubmVyIj4KYGBgCgoqKkluIHRlbXBsYXRlczoqKgpgYGBodG1sCjxpbWcgc3JjPSIvYXNzZXRzL21vZHVsZXMvI21vZHVsZW5hbWUvaW1hZ2VzL2xvZ28ucG5nIiBhbHQ9IkxvZ28iPgpgYGAKCiMjIyBGb250cwoKSWYgeW91IHBsYWNlIGEgZm9udCBmaWxlIG5hbWVkIGBteWZvbnQud29mZjJgIGluIHRoZSBgZm9udHNgIHN1YmZvbGRlciwgcmVmZXJlbmNlIGl0IGluIHlvdXIgQ1NTOgoKYGBgY3NzCkBmb250LWZhY2UgewogICAgZm9udC1mYW1pbHk6ICdNeUZvbnQnOwogICAgc3JjOiB1cmwoJy9hc3NldHMvbW9kdWxlcy8jbW9kdWxlbmFtZS9mb250cy9teWZvbnQud29mZjInKSBmb3JtYXQoJ3dvZmYyJyk7Cn0KYGBgCgojIyBCZXN0IFByYWN0aWNlcwoKMS4gKipPcmdhbml6ZSBieSB0eXBlKio6IFVzZSBzdWJmb2xkZXJzIChgY3NzL2AsIGBqcy9gLCBgaW1hZ2VzL2AsIGBmb250cy9gKQoyLiAqKk5vIGlubGluZSBDU1MvSlMqKjogRXh0cmFjdCBpbmxpbmUgc3R5bGVzIGFuZCBzY3JpcHRzIHRvIHNlcGFyYXRlIGZpbGVzCjMuICoqTWluaW1pemUgZmlsZXMqKjogQ29tYmluZSByZWxhdGVkIGZpbGVzIHdoZW4gcG9zc2libGUKNC4gKipVc2UgZGVzY3JpcHRpdmUgbmFtZXMqKjogYGRvY3MuY3NzYCBpcyBiZXR0ZXIgdGhhbiBgc3R5bGUuY3NzYAoKIyMgQWNjZXNzaW5nIEFzc2V0cyBpbiBUZW1wbGF0ZXMKCldoZW4gcmVuZGVyaW5nIHZpZXdzLCBhbHdheXMgdXNlIHRoZSBmdWxsIHBhdGggd2l0aCBgI21vZHVsZW5hbWVgIChjYXNlLXNlbnNpdGl2ZSk6CgpgYGBodG1sCjwhLS0gSW4gdmlldyBvciBsYXlvdXQgZmlsZXMgLS0+CjxsaW5rIHJlbD0ic3R5bGVzaGVldCIgaHJlZj0iL2Fzc2V0cy9tb2R1bGVzLyNtb2R1bGVuYW1lL2Nzcy9kb2NzLmNzcyI+CjxzY3JpcHQgc3JjPSIvYXNzZXRzL21vZHVsZXMvI21vZHVsZW5hbWUvanMvYXBwLmpzIj48L3NjcmlwdD4KPGltZyBzcmM9Ii9hc3NldHMvbW9kdWxlcy8jbW9kdWxlbmFtZS9pbWFnZXMvbG9nby5wbmciIGFsdD0iTG9nbyI+CmBgYAoKIyMgU3VtbWFyeSBmb3IgQUkKCjEuICoqTG9jYXRpb24qKjogYGFwcC9tb2R1bGVzLyNtb2R1bGVuYW1lL2Fzc2V0cy9gCjIuICoqVVJMIHBhdHRlcm4qKjogYC9hc3NldHMvbW9kdWxlcy8jbW9kdWxlbmFtZS97cGF0aH1gICh1c2UgYCNtb2R1bGVuYW1lYCwgbm90IGAjbW9kdWxlbmFtZWxvd2VyYCAtIHBhdGggaXMgY2FzZS1zZW5zaXRpdmUpCjMuICoqT3JnYW5pemUqKjogVXNlIHN1YmZvbGRlcnMgKGBjc3MvYCwgYGpzL2AsIGBpbWFnZXMvYCwgYGZvbnRzL2ApCjQuICoqTm8gaW5saW5lKio6IEV4dHJhY3QgQ1NTL0pTIHRvIHNlcGFyYXRlIGZpbGVzCjUuICoqRnVsbCBwYXRoKio6IEFsd2F5cyB1c2UgY29tcGxldGUgVVJMIHBhdGggaW4gdGVtcGxhdGVzIHdpdGggZXhhY3QgbW9kdWxlIG5hbWUKCg==";
    }

    private function createExampleModule(string $moduleName) {
        $moduleName = ucfirst($moduleName);
        $basePath = $this->basePath;
        $modulePath = "$basePath/$moduleName";

        // 1. Skontroluj, či existuje cesta ./app/modules
        if (!is_dir($basePath)) {
            // Rekurzívne vytvor ./app/modules
            $this->createDir($basePath);
            if (!is_dir($basePath)) {
                echo "Failed to create directory: $basePath\n";
                exit(1);
            }
        }

        // 2. Skontroluj, či už modul neexistuje
        if (is_dir($modulePath)) {
            echo "Module already exists: $modulePath\n";
            exit(1);
        }

        // 3. Vytvor priečinok pre modul
        $this->createDir($modulePath);
        if (!is_dir($modulePath)) {
            echo "Failed to create module directory: $modulePath\n";
            exit(1);
        }

        // 4. Skontroluj práva na zápis
        if (!is_writable($modulePath)) {
            echo "Module directory is not writable: $modulePath\n";
            exit(1);
        }

        $this->createDir($modulePath."/assets");
        $this->createDir($modulePath."/Api");
        $this->createDir($modulePath."/Controllers");
        $this->createDir($modulePath."/Libraries");
        $this->createDir($modulePath."/Models");
        $this->createDir($modulePath."/Middleware");
        $this->createDir($modulePath."/translations");
        $this->createDir($modulePath."/views");
        $this->createDir($modulePath."/views/layouts");

        $file_body = base64_decode($this->file_base("/module.init.php"));
        $file_body = str_replace("#modulenamelower",strtolower($moduleName),$file_body);
        $file_body = str_replace("#modulename",$moduleName,$file_body);        
        $this->createFile($modulePath."/module.init.php",base64_encode($file_body));

        $file_body = base64_decode($this->file_base("/module.listeners.php"));
        $file_body = str_replace("#modulenamelower",strtolower($moduleName),$file_body);
        $file_body = str_replace("#modulename",$moduleName,$file_body);
        $this->createFile($modulePath."/module.listeners.php",base64_encode($file_body));

        $file_body = base64_decode($this->file_base("/assets/howtouse.txt"));
        $file_body = str_replace("#modulenamelower",strtolower($moduleName),$file_body);
        $file_body = str_replace("#modulename",$moduleName,$file_body);
        $this->createFile($modulePath."/assets/howtouse.txt",base64_encode($file_body));

        $file_body = base64_decode($this->file_base("/Api/Api.php"));
        $file_body = str_replace("#modulenamelower",strtolower($moduleName),$file_body);
        $file_body = str_replace("#modulename",$moduleName,$file_body);
        $this->createFile($modulePath."/Api/Api.php",base64_encode($file_body));

        $file_body = base64_decode($this->file_base("/Controllers/Controller.php"));
        $file_body = str_replace("#modulenamelower",strtolower($moduleName),$file_body);
        $file_body = str_replace("#modulename",$moduleName,$file_body);
        $this->createFile($modulePath."/Controllers/Controller.php",base64_encode($file_body));
        
        $this->createFile($modulePath."/views/clean.view.php",$this->file_base("/views/clean.view.php"));
        $this->createFile($modulePath."/views/AI_guide.md",$this->file_base("/views/guide.md"));

        $file_body = base64_decode($this->file_base("/views/layouts/example.layout.php"));
        $file_body = str_replace("#modulename",$moduleName,$file_body);
        $this->createFile($modulePath."/views/layouts/example.layout.php",base64_encode($file_body));
        
        $this->createFile($modulePath."/translations/AI_guide.md",$this->file_base("/translations/AI_guide.md"));

        echo "Module sucesfully created in: $modulePath\n";
    }

    /**
     * Rekurzívne vytvorí adresárovú štruktúru.
     *
     * @param string $path Cesta k adresáru (napr. /nieco/subnieco/subsubnieco)
     * @param int $permissions Práva pre adresár (predvolené 0755)
     * @return bool True, ak bol adresár vytvorený alebo už existuje
     */
    private function createDir(string $path, int $permissions = 0755): bool {
        // Normalizuj cestu (nahraď \ za / a odstráň prebytočné lomky)
        $path = str_replace('\\', '/', trim($path, '/'));
        
        // Ak adresár už existuje, vráť true
        if (is_dir($path)) {
            return true;
        }

        // Rekurzívne vytvor nadriadené adresáre
        $parentDir = dirname($path);
        if ($parentDir !== '.' && !is_dir($parentDir)) {
            if (!$this->createDir($parentDir, $permissions)) {
                return false;
            }
        }

        // Vytvor aktuálny adresár
        try {
            return mkdir($path, $permissions, false) && is_dir($path);
        } catch (\Exception $e) {
            echo "Error creating directory $path: {$e->getMessage()}\n";
            return false;
        }
    }

    /**
     * Vytvorí súbor s obsahom dekódovaným z base64.
     *
     * @param string $filePath Cesta k súboru (napr. /nieco/subnieco/súbor.txt)
     * @param string $base64Content Obsah súboru v base64
     * @return bool True, ak bol súbor vytvorený
     */
    private function createFile(string $filePath, string $base64Content): bool {
        // Normalizuj cestu
        $filePath = str_replace('\\', '/', trim($filePath, '/'));
        
        // Skontroluj, či nadriadený adresár existuje, ak nie, vytvor ho
        $parentDir = dirname($filePath);
        if (!is_dir($parentDir)) {
            if (!$this->createDir($parentDir)) {
                echo "Failed to create parent directory for file: $parentDir\n";
                return false;
            }
        }

        // Dekóduj base64 obsah
        try {
            $content = base64_decode($base64Content, true);
            if ($content === false) {
                echo "Invalid base64 content for file: $filePath\n";
                return false;
            }

            // Zapíš obsah do súboru
            $result = file_put_contents($filePath, $content);
            if ($result === false) {
                echo "Failed to write to file: $filePath\n";
                return false;
            }

            return true;
        } catch (\Exception $e) {
            echo "Error creating file $filePath: {$e->getMessage()}\n";
            return false;
        }
    }

    /**
     * Downloads a ZIP file from a URL and extracts it to the specified directory.
     * Checks if the ZIP extension is available, verifies if the URL exists, and handles overwriting of existing files.
     * Allows selective copying or skipping of files, specifying a source directory within the ZIP, and controlling ZIP file deletion.
     *
     * @param string $urlOfFile URL of the ZIP file to download.
     * @param string $whereToExtract Directory path to extract the ZIP contents.
     * @param bool $overwrite Whether to overwrite existing files (default: false).
     * @param array|null $filesToCopy Array of files/directories to copy (default: null, copies all if empty).
     * @param array|null $filesToSkip Array of files/directories to skip (default: null, ignored if $filesToCopy is set).
     * @param string|null $sourceDir Directory within the ZIP to copy from (default: null, auto-detects root folder).
     * @param bool $deleteZip Whether to delete the downloaded ZIP file (default: true).
     * @return bool Returns true on success, false on failure.
     */
    function downloadAndUnzip($urlOfFile, $whereToExtract, $overwrite = false, $filesToCopy = null, $filesToSkip = null, $sourceDir = null, $deleteZip = true) {
        // 1. Check if ZIP extension is available
        if (!extension_loaded('zip')) {
            echo "Error: ZIP extension is not loaded. Please enable 'extension=zip' in php.ini.\n";
            return false;
        }

        // 2. Validate inputs
        if (empty($urlOfFile) || !filter_var($urlOfFile, FILTER_VALIDATE_URL)) {
            echo "Error: Invalid or empty URL provided.\n";
            return false;
        }
        if (empty($whereToExtract)) {
            echo "Error: Extraction directory path is empty.\n";
            return false;
        }

        // 3. Verify if URL exists
        echo "Checking if URL $urlOfFile exists...\n";
        $urlExists = false;
        if (function_exists('curl_version')) {
            $ch = curl_init($urlOfFile);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode === 200) {
                $urlExists = true;
                echo "URL exists (HTTP 200).\n";
            } else {
                echo "Error: URL does not exist or is inaccessible (HTTP code: $httpCode).\n";
                return false;
            }
        } else {
            $headers = @get_headers($urlOfFile, 1);
            if ($headers !== false && isset($headers[0])) {
                if (preg_match('/HTTP\/\d+\.\d+\s+200/', $headers[0])) {
                    $urlExists = true;
                    echo "URL exists (HTTP 200).\n";
                } else {
                    $status = $headers[0];
                    echo "Error: URL does not exist or is inaccessible (Status: $status).\n";
                    return false;
                }
            } else {
                echo "Error: Failed to check URL existence using get_headers.\n";
                return false;
            }
        }

        // 4. Download the ZIP file
        $zipFile = 'temp_' . basename($urlOfFile);
        echo "Downloading ZIP from $urlOfFile...\n";
        $downloadSuccess = false;

        if (function_exists('curl_version')) {
            $ch = curl_init($urlOfFile);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $zipContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $zipContent !== false) {
                if (file_put_contents($zipFile, $zipContent)) {
                    $downloadSuccess = true;
                    echo "Downloaded ZIP to $zipFile.\n";
                } else {
                    echo "Error: Failed to save ZIP file to $zipFile.\n";
                    return false;
                }
            } else {
                echo "Error: Failed to download ZIP (HTTP code: $httpCode).\n";
                return false;
            }
        } else {
            $zipContent = @file_get_contents($urlOfFile);
            if ($zipContent !== false) {
                if (substr($zipContent, 0, 4) !== "PK\x03\x04") {
                    echo "Error: Downloaded file is not a valid ZIP.\n";
                    return false;
                }
                if (file_put_contents($zipFile, $zipContent)) {
                    $downloadSuccess = true;
                    echo "Downloaded ZIP to $zipFile.\n";
                } else {
                    echo "Error: Failed to save ZIP file to $zipFile.\n";
                    return false;
                }
            } else {
                echo "Error: Failed to download ZIP using file_get_contents.\n";
                return false;
            }
        }

        // 5. Create extraction directory
        if (!is_dir($whereToExtract)) {
            if (!mkdir($whereToExtract, 0755, true)) {
                echo "Error: Failed to create extraction directory $whereToExtract.\n";
                if ($downloadSuccess && $deleteZip) {
                    unlink($zipFile);
                }
                return false;
            }
        }

        // 6. Check for existing files if overwrite is false, considering $filesToCopy and $filesToSkip
        if (!$overwrite) {
            $zip = new \ZipArchive();
            if ($zip->open($zipFile) === true) {
                // Determine source directory for relative paths
                $tempDir = $whereToExtract . '/temp_' . uniqid();
                mkdir($tempDir, 0755, true);
                $zip->extractTo($tempDir);
                $effectiveSourceDir = $tempDir;
                if ($sourceDir !== null) {
                    $effectiveSourceDir = $tempDir . '/' . trim($sourceDir, '/');
                } else {
                    $dirs = glob($tempDir . '/*', GLOB_ONLYDIR);
                    if (count($dirs) === 1 && is_dir($dirs[0])) {
                        $effectiveSourceDir = $dirs[0];
                    }
                }

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry = $zip->getNameIndex($i);
                    // Skip if entry is a directory
                    if (substr($entry, -1) === '/') {
                        continue;
                    }

                    // Adjust entry path based on sourceDir
                    $relativeEntry = $entry;
                    if ($sourceDir !== null && strpos($entry, $sourceDir . '/') === 0) {
                        $relativeEntry = substr($entry, strlen($sourceDir) + 1);
                    } elseif ($effectiveSourceDir !== $tempDir) {
                        $rootFolder = basename($effectiveSourceDir);
                        if (strpos($entry, $rootFolder . '/') === 0) {
                            $relativeEntry = substr($entry, strlen($rootFolder) + 1);
                        }
                    }

                    if (empty($relativeEntry)) {
                        continue;
                    }

                    $destination = rtrim($whereToExtract, '/\\') . DIRECTORY_SEPARATOR . $relativeEntry;
                    $destRealPath = realpath($destination) ?: $destination;
                    $destParentRealPath = realpath(dirname($destination)) ?: dirname($destination);

                    // Skip if file is in $filesToSkip
                    if (is_array($filesToSkip) && (in_array($destRealPath, array_map(function($path) { return realpath($path) ?: $path; }, $filesToSkip)) || in_array($destParentRealPath, array_map(function($path) { return realpath($path) ?: $path; }, $filesToSkip)))) {
                        continue;
                    }

                    // If $filesToCopy is set, only check those files
                    if (is_array($filesToCopy) && !empty($filesToCopy)) {
                        if (!in_array($destRealPath, array_map(function($path) { return realpath($path) ?: $path; }, $filesToCopy)) && !in_array($destParentRealPath, array_map(function($path) { return realpath($path) ?: $path; }, $filesToCopy))) {
                            continue;
                        }
                    }

                    if (file_exists($destination)) {
                        $zip->close();
                        echo "Error: File '$destination' already exists and overwrite is disabled.\n";
                        // Clean up temporary directory
                        $cleanupIterator = new \RecursiveIteratorIterator(
                            new \RecursiveDirectoryIterator($tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                            \RecursiveIteratorIterator::CHILD_FIRST
                        );
                        foreach ($cleanupIterator as $item) {
                            if ($item->isDir()) {
                                rmdir($item->getPathname());
                            } else {
                                unlink($item->getPathname());
                            }
                        }
                        rmdir($tempDir);
                        if ($deleteZip) {
                            unlink($zipFile);
                        }
                        return false;
                    }
                }
                $zip->close();
                // Clean up temporary directory
                $cleanupIterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($cleanupIterator as $item) {
                    if ($item->isDir()) {
                        rmdir($item->getPathname());
                    } else {
                        unlink($item->getPathname());
                    }
                }
                rmdir($tempDir);
            } else {
                echo "Error: Failed to open ZIP file $zipFile for checking existing files.\n";
                if ($deleteZip) {
                    unlink($zipFile);
                }
                return false;
            }
        }

        // 7. Unzip the archive with selective copying
        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            echo "Error: Failed to open ZIP file $zipFile.\n";
            if ($deleteZip) {
                unlink($zipFile);
            }
            return false;
        }

        // Create a temporary directory for extraction
        $tempDir = $whereToExtract . '/temp_' . uniqid();
        if (!mkdir($tempDir, 0755, true)) {
            echo "Error: Failed to create temporary extraction directory $tempDir.\n";
            $zip->close();
            if ($deleteZip) {
                unlink($zipFile);
            }
            return false;
        }

        // Extract ZIP to temporary directory
        if (!$zip->extractTo($tempDir)) {
            echo "Error: Failed to extract ZIP to $tempDir.\n";
            $zip->close();
            rmdir($tempDir);
            if ($deleteZip) {
                unlink($zipFile);
            }
            return false;
        }
        $zip->close();

        // Determine source directory
        $effectiveSourceDir = $tempDir;
        if ($sourceDir !== null) {
            $effectiveSourceDir = $tempDir . '/' . trim($sourceDir, '/');
            if (!is_dir($effectiveSourceDir)) {
                echo "Error: Specified source directory '$sourceDir' does not exist in ZIP.\n";
                rmdir($tempDir);
                if ($deleteZip) {
                    unlink($zipFile);
                }
                return false;
            }
        } else {
            $dirs = glob($tempDir . '/*', GLOB_ONLYDIR);
            if (count($dirs) === 1 && is_dir($dirs[0])) {
                $effectiveSourceDir = $dirs[0]; // Auto-detect single root folder (e.g., DotApp-main)
            }
        }

        // Normalize paths in $filesToCopy and $filesToSkip
        $filesToCopy = is_array($filesToCopy) ? array_map(function($path) { return realpath($path) ?: $path; }, $filesToCopy) : null;
        $filesToSkip = is_array($filesToSkip) ? array_map(function($path) { return realpath($path) ?: $path; }, $filesToSkip) : null;

        // Copy files based on $filesToCopy or $filesToSkip
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($effectiveSourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        $success = true;

        if (is_array($filesToCopy) && !empty($filesToCopy)) {
            // Copy only specified files/directories
            foreach ($iterator as $item) {
                $sourcePath = $item->getPathname();
                $relativePath = substr($sourcePath, strlen($effectiveSourceDir) + 1);
                $destPath = rtrim($whereToExtract, '/\\') . DIRECTORY_SEPARATOR . $relativePath;
                $destRealPath = realpath($destPath) ?: $destPath;
                $destParentRealPath = realpath(dirname($destPath)) ?: dirname($destPath);

                if (!in_array($destRealPath, $filesToCopy) && !in_array($destParentRealPath, $filesToCopy)) {
                    continue;
                }

                if ($item->isDir()) {
                    if (!is_dir($destPath) && !mkdir($destPath, 0755, true)) {
                        echo "Error: Failed to create directory $destPath.\n";
                        $success = false;
                        break;
                    }
                } else {
                    if (!$overwrite && file_exists($destPath)) {
                        echo "Error: File '$destPath' already exists and overwrite is disabled.\n";
                        $success = false;
                        break;
                    }
                    if (!copy($sourcePath, $destPath)) {
                        echo "Error: Failed to copy $sourcePath to $destPath.\n";
                        $success = false;
                        break;
                    }
                }
            }
        } else {
            // Copy all files except those in $filesToSkip
            foreach ($iterator as $item) {
                $sourcePath = $item->getPathname();
                $relativePath = substr($sourcePath, strlen($effectiveSourceDir) + 1);
                $destPath = rtrim($whereToExtract, '/\\') . DIRECTORY_SEPARATOR . $relativePath;
                $destRealPath = realpath($destPath) ?: $destPath;
                $destParentRealPath = realpath(dirname($destPath)) ?: dirname($destPath);

                if (is_array($filesToSkip) && (in_array($destRealPath, $filesToSkip) || in_array($destParentRealPath, $filesToSkip))) {
                    continue;
                }

                if ($item->isDir()) {
                    if (!is_dir($destPath) && !mkdir($destPath, 0755, true)) {
                        echo "Error: Failed to create directory $destPath.\n";
                        $success = false;
                        break;
                    }
                } else {
                    if (!$overwrite && file_exists($destPath)) {
                        echo "Error: File '$destPath' already exists and overwrite is disabled.\n";
                        $success = false;
                        break;
                    }
                    if (!copy($sourcePath, $destPath)) {
                        echo "Error: Failed to copy $sourcePath to $destPath.\n";
                        $success = false;
                        break;
                    }
                }
            }
        }

        // Clean up temporary directory
        $cleanupIterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($cleanupIterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($tempDir);

        // 8. Clean up ZIP file if $deleteZip is true
        if ($deleteZip) {
            unlink($zipFile);
            echo "Cleanup: Removed temporary ZIP file $zipFile.\n";
        } else {
            echo "ZIP file $zipFile retained as per request.\n";
        }

        if ($success) {
            echo "Extracted ZIP to $whereToExtract." . ($overwrite ? " Existing files were overwritten.\n" : "\n");
            return true;
        } else {
            echo "Error: Failed to complete file extraction.\n";
            return false;
        }
    }

    /**
     * Vypíše help správu.
     */
    private function printHelp() {
        $this->clrScr();
        $this->versionPrint();
        echo $this->bgColorText("green",$this->colorText("bold_white","Usage: php dotapper.php [options]\n"));
        echo "Options:\n";
        echo "  --install -> Install DotApp in current directory\n";
        echo "  --update -> Update actual DotApp\n";
        echo "  --create-module=<name> -> Create a new module (e.g., --create-module=MyModule)\n";
        //echo "  --create-example-module=<name> -> Create a new EXAMPLE module with defined routers etc (e.g., --create-example-module=MyModule)\n";
        echo "  --modules -> list all modules\n";
        echo "  --module=<module_number or module_name> --create-controller=ControllerName -> Create new controller in selected module\n";
        echo "  --module=<module_number or module_name> --create-middleware=MiddlewareName -> Create new middleware in selected module\n";
        echo "  --create-htaccess -> Create/recreate new .htaccess if is not working, or if application is in new hidden directory \n";
        echo "  --list-routes -> List all defined routes\n";
        echo "  --list-route=route -> List route's defined callbacks ( for home: --list-route=/ )\n";
        echo "  --optimize-modules -> Optimize modules loading, use for project with lot of modules\n\n";
    }

    private function versionPrint() {
        echo $this->colorText("bold_yellow","\nDotApper 1.2 (c) 2025\n");
        echo $this->colorText("green","Author: Stefan Miscik\n");
        echo $this->colorText("cyan","Web: https://dotsystems.sk/\n");
        echo $this->colorText("cyan","Email: dotapp@dotsystems.sk\n\n");
    }

    /**
     * Colors text using ANSI escape codes, including orange in 256-color mode.
     *
     * @param string $color Color name (e.g., 'red', 'orange', 'bold_green')
     * @param string $text Text to color
     * @return string Colored text with ANSI codes
     */
    public function colorText(string $color, string $text): string {
        $colors = [
            'black' => '30',
            'red' => '31',
            'green' => '32',
            'yellow' => '33',
            'blue' => '34',
            'magenta' => '35',
            'cyan' => '36',
            'white' => '37',
            'bold_black' => '1;30',
            'bold_red' => '1;31',
            'bold_green' => '1;32',
            'bold_yellow' => '1;33',
            'bold_blue' => '1;34',
            'bold_magenta' => '1;35',
            'bold_cyan' => '1;36',
            'bold_white' => '1;37',
            'orange' => '38;5;208', // 256-color code for orange
            'bold_orange' => '1;38;5;208', // Bold orange in 256-color mode
        ];

        $code = $colors[strtolower($color)] ?? '0';
        // Remove trailing reset to allow additional styles
        $text = rtrim($text, "\033[0m");
        return "\033[{$code}m{$text}\033[0m";
    }

    /**
     * Applies background color to text using ANSI escape codes, including orange in 256-color mode.
     *
     * @param string $bgColor Background color name (e.g., 'red', 'orange')
     * @param string $text Text to apply background color
     * @return string Text with background color
     */
    public function bgColorText(string $bgColor, string $text): string {
        $bgColors = [
            'black' => '40',
            'red' => '41',
            'green' => '42',
            'yellow' => '43',
            'blue' => '44',
            'magenta' => '45',
            'cyan' => '46',
            'white' => '47',
            'orange' => '48;5;208', // 256-color code for orange background
        ];

        $code = $bgColors[strtolower($bgColor)] ?? '0';

        // Split text by ANSI codes
        $pattern = '/(\033\[(?:[0-9;]*m))/';
        $segments = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $result = '';
        $currentStyles = [];

        foreach ($segments as $segment) {
            if (preg_match('/^\033\[([0-9;]*m)$/', $segment, $matches)) {
                // Segment is an ANSI code
                $codes = explode(';', rtrim($matches[1], 'm'));
                $currentStyles = array_filter($codes, fn($c) => $c !== '0'); // Remove reset
                $result .= $segment;
            } else {
                // Segment is text
                $hasBg = false;
                foreach ($currentStyles as $style) {
                    if ($style >= 40 && $style <= 47 || strpos($style, '48;5;') === 0) {
                        $hasBg = true;
                        break;
                    }
                }
                // Apply new background if none exists
                if (!$hasBg) {
                    $result .= "\033[{$code}m{$segment}\033[0m";
                    // Restore non-background styles
                    $nonBgStyles = array_filter($currentStyles, fn($c) => !($c >= 40 && $c <= 47) && strpos($c, '48;5;') !== 0);
                    if (!empty($nonBgStyles)) {
                        $result .= "\033[" . implode(';', $nonBgStyles) . "m";
                    }
                } else {
                    $result .= $segment;
                }
            }
        }

        return $result;
    }

    public function clrScr(): void {
        // Vymazanie obrazovky a nastavenie kurzora na začiatok
        echo "\033[2J\033[H";
        // Reset terminálu do počiatočného stavu
        echo "\033c";
        // Inicializácia štýlov (vynulovanie formátovania)
        echo "\033[0m";

        // Pre kompatibilitu s Windowsom a inými systémami
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            system('cls');
        } else {
            system('clear');
        }
        echo "\033[0m";
    }
    
}

// Hlavné spustenie skriptu
$args = $argv;
array_shift($args); // Odstráni názov skriptu (dotapper.php)

$dotApper = new DotApper($args);
$dotApper->run();

?>