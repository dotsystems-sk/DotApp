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
            @include("./index.php");
            $file_body = base64_decode($this->file_base("/.htaccess"));

            if ( !(__ROOTDIR__ === __DIR__) ) {
                $calculateURL = str_replace(__DIR__,"",__ROOTDIR__);
                $file_body = str_replace("/app/modules/$1/assets/$2",$calculateURL."/app/modules/$1/assets/$2",$file_body);
            }            

            $this->createFile(__ROOTDIR__."/.htaccess",base64_encode($file_body));
        } catch (\Exception $e) {
            echo "Error creating .htaccess {$e->getMessage()}\n";
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

        $file_body = base64_decode($this->file_base("/views/layouts/example.layout.php"));
        $file_body = str_replace("#modulename",$moduleName,$file_body);
        $this->createFile($modulePath."/views/layouts/example.layout.php",base64_encode($file_body));

        $file_body = base64_decode($this->file_base("/tests/guide.md"));
        $this->createFile($modulePath."/tests/guide.md",base64_encode($file_body));
        
        echo "Module sucesfully created in: $modulePath\n";
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

        $file_body = base64_decode($this->file_base("/views/layouts/example.layout.php"));
        $file_body = str_replace("#modulename",$moduleName,$file_body);
        $this->createFile($modulePath."/views/layouts/example.layout.php",base64_encode($file_body));
        
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
        if ($filename=="/.htaccess") return "IyBOYXN0YXZlbmllIGtvZG92YW5pYSBhIGphenlrYQpBZGREZWZhdWx0Q2hhcnNldCBVVEYtOApEZWZhdWx0TGFuZ3VhZ2Ugc2sKCiMgUHJpZGF0IGhsYXZpY2t5IHByZSBkb3RhcHAKPElmTW9kdWxlIG1vZF9oZWFkZXJzLmM+CiAgICBIZWFkZXIgYWx3YXlzIHNldCBYLVBvd2VyZWQtQnkgImRvdGFwcDsgd3d3LmRvdHN5c3RlbXMuc2siCiAgICBIZWFkZXIgYWx3YXlzIHNldCBYLUZyYW1ld29yayAiZG90YXBwIgo8L0lmTW9kdWxlPgoKIyBLb21wcmVzaWEgc3Vib3JvdiAtIG1vZF9kZWZsYXRlIChub3ZzaSBzcG9zb2IpCjxJZk1vZHVsZSBtb2RfZGVmbGF0ZS5jPgogICAgU2V0T3V0cHV0RmlsdGVyIERFRkxBVEUKICAgIEFkZE91dHB1dEZpbHRlckJ5VHlwZSBERUZMQVRFIHRleHQvaHRtbCB0ZXh0L3BsYWluIHRleHQveG1sIHRleHQvY3NzIHRleHQvamF2YXNjcmlwdAogICAgQWRkT3V0cHV0RmlsdGVyQnlUeXBlIERFRkxBVEUgYXBwbGljYXRpb24vamF2YXNjcmlwdCBhcHBsaWNhdGlvbi94LWphdmFzY3JpcHQKICAgIEJyb3dzZXJNYXRjaCBeTW96aWxsYS80IGd6aXAtb25seS10ZXh0L2h0bWwKICAgIEJyb3dzZXJNYXRjaCBeTW96aWxsYS80XC4wWzY3OF0gbm8tZ3ppcAogICAgQnJvd3Nlck1hdGNoIFxiTVNJRSAhbm8tZ3ppcCAhZ3ppcC1vbmx5LXRleHQvaHRtbAo8L0lmTW9kdWxlPgoKIyBLb21wcmVzaWEgc3Vib3JvdiAtIG1vZF9nemlwIChzdGFyc2lhIHZlcnppYSBhayBuZW5pIGRlZmxhdGUpCjxJZk1vZHVsZSAhbW9kX2RlZmxhdGUuYz4KCTxJZk1vZHVsZSBtb2RfZ3ppcC5jPgoJCW1vZF9nemlwX29uIFllcwoJCW1vZF9nemlwX2RlY2h1bmsgWWVzCgkJbW9kX2d6aXBfaXRlbV9pbmNsdWRlIGZpbGUgXC4oaHRtbD98dHh0fGNzc3xqc3xwaHB8cGwpJAoJCW1vZF9nemlwX2l0ZW1faW5jbHVkZSBoYW5kbGVyIF5jZ2ktc2NyaXB0JAoJCW1vZF9nemlwX2l0ZW1faW5jbHVkZSBtaW1lIF50ZXh0Ly4qCgkJbW9kX2d6aXBfaXRlbV9pbmNsdWRlIG1pbWUgXmFwcGxpY2F0aW9uL3gtamF2YXNjcmlwdC4qCgkJbW9kX2d6aXBfaXRlbV9leGNsdWRlIG1pbWUgXmltYWdlLy4qCgkJbW9kX2d6aXBfaXRlbV9leGNsdWRlIHJzcGhlYWRlciBeQ29udGVudC1FbmNvZGluZzouKmd6aXAuKgoJPC9JZk1vZHVsZT4KPC9JZk1vZHVsZT4KCiMgUG92b2xpdCBwcmlzdHUga3UgdnNldGtlbXUgLSBub3ZzaSBhcGFjaGUKPElmTW9kdWxlIG1vZF9hdXRoel9ob3N0LmM+CiAgICBSZXF1aXJlIGFsbCBncmFudGVkCjwvSWZNb2R1bGU+CgojIFBvdm9saXQgcHJpc3R1IC0gc3RhcnNpIGFwYWNoZQo8SWZNb2R1bGUgIW1vZF9hdXRoel9ob3N0LmM+CiAgICBPcmRlciBBbGxvdyxEZW55CiAgICBBbGxvdyBmcm9tIGFsbAo8L0lmTW9kdWxlPgoKIyBOYXN0YXZlbmllIHR5cG92IHN1Ym9yb3YKQWRkVHlwZSBmb250L3dvZmYgLndvZmYKQWRkVHlwZSBhcHBsaWNhdGlvbi9mb250LXdvZmYyIC53b2ZmMgpBZGRUeXBlIGFwcGxpY2F0aW9uL2phdmFzY3JpcHQgLmpzCkFkZFR5cGUgdGV4dC9jc3MgLmNzcwoKIyBaYXBudXQgcHJlcGlzb3ZhbmllIHVybApSZXdyaXRlRW5naW5lIE9uClJld3JpdGVCYXNlIC8KCiMgWmFibG9rb3ZhdCBwcmlzdHUgayBkb3RhcHBlcnUKUmV3cml0ZUNvbmQgJXtSRVFVRVNUX1VSSX0gXi9kb3RhcHBlciQKUmV3cml0ZVJ1bGUgXiAtIFtGLExdCgojIFByZXNrb2NpdCBwcmVwaXMgcHJlIHNwZWNpZmlja2Ugc3Vib3J5ClJld3JpdGVSdWxlIF4oc2l0ZW1hcFwueG1sfHJvYm90c1wudHh0KSQgLSBbTkMsTF0KCiMgWmFibG9rb3ZhdCAvYXBwLyBva3JlbSBhc3NldHMgdiBtb2R1bG9jaApSZXdyaXRlQ29uZCAle1JFUVVFU1RfVVJJfSAhXi9hcHAvbW9kdWxlcy8oW14vXSspL2Fzc2V0cy8KUmV3cml0ZUNvbmQgJXtSRVFVRVNUX1VSSX0gIV4vYXBwL3BhcnRzL2pzLwpSZXdyaXRlUnVsZSBeYXBwKC98JCkgLSBbRixMXQoKIyA9PT0gQVNTRVRTIFNQUkFDT1ZBTklFID09PQoKIyBBayBzdWJvciB2IC9hc3NldHMvbW9kdWxlcy8gbmVleGlzdHVqZSwgc2t1cyBobyBuYWNpdGF0IHogL2FwcC9tb2R1bGVzLwpSZXdyaXRlQ29uZCAle1JFUVVFU1RfRklMRU5BTUV9ICEtZgpSZXdyaXRlQ29uZCAle1JFUVVFU1RfRklMRU5BTUV9ICEtZApSZXdyaXRlUnVsZSBeYXNzZXRzL21vZHVsZXMvKFteL10rKS8oLiopJCAvYXBwL21vZHVsZXMvJDEvYXNzZXRzLyQyIFtMXQoKIyBTcGVjaWFsbmUgc3ByYWNvdmFuaWUgbGVuIHByZSBkb3RhcHAuanMgKHByZXNtZXJvdmFuaWUgbmEgaW5kZXgucGhwKQpSZXdyaXRlQ29uZCAle1JFUVVFU1RfRklMRU5BTUV9ICEtZgpSZXdyaXRlUnVsZSBeYXNzZXRzL2RvdGFwcC9kb3RhcHBcLmpzJCBpbmRleC5waHAgW05DLExdCgojIEFrIG9zdGF0bsOpIHPDumJvcnkgdiAvYXNzZXRzL2RvdGFwcC8gbmVleGlzdHVqw7ogKG9rcmVtIGRvdGFwcC5qcyksIHNrw7pzIGljaCBuYcSNw610YcWlIHogL2FwcC9wYXJ0cy9qcy8KUmV3cml0ZUNvbmQgJXtSRVFVRVNUX0ZJTEVOQU1FfSAhLWYKUmV3cml0ZUNvbmQgJXtSRVFVRVNUX0ZJTEVOQU1FfSAhLWQKUmV3cml0ZUNvbmQgJXtSRVFVRVNUX1VSSX0gIV4vYXNzZXRzL2RvdGFwcC9kb3RhcHBcLmpzJApSZXdyaXRlUnVsZSBeYXNzZXRzL2RvdGFwcC8oLitcLmpzKSQgL2FwcC9wYXJ0cy9qcy8kMSBbTkMsTF0KCiMgQWsgc3Vib3IgdiAvYXNzZXRzLyBleGlzdHVqZSwgbmVwcmVwaXN1agpSZXdyaXRlQ29uZCAle1JFUVVFU1RfRklMRU5BTUV9IC1mClJld3JpdGVSdWxlIF5hc3NldHMvLiokIC0gW05DLExdCgojID09PSBLT05JRUMgQVNTRVRTIFNQUkFDT1ZBTklBID09PQoKIyBOZXByZXBpc292YXQgb2JyYXpreQpSZXdyaXRlUnVsZSBcLihpY298cG5nfGpwZT9nfGdpZnxzdmd8d2VicHxibXApJCAtIFtOQyxMXQoKIyBWc2V0a3kgb3N0YXRuZSBwb3ppYWRhdmt5IGlkdSBuYSBpbmRleC5waHAsIG9rcmVtIHNwZWNpZmlja3ljaCB2eW5pbWllawpSZXdyaXRlQ29uZCAle1JFUVVFU1RfVVJJfSAhXi9kb3RhcHBlciQKUmV3cml0ZUNvbmQgJXtSRVFVRVNUX1VSSX0gIV4vYXBwL21vZHVsZXMvKFteL10rKS9hc3NldHMvClJld3JpdGVDb25kICV7UkVRVUVTVF9VUkl9ICFeL2FwcC9wYXJ0cy9qcy8KUmV3cml0ZUNvbmQgJXtSRVFVRVNUX1VSSX0gIV4vYXNzZXRzLwpSZXdyaXRlUnVsZSBeLiokIGluZGV4LnBocCBbTkMsTF0=";
        if ($filename=="/module.init2.php") return "PD9waHAKCW5hbWVzcGFjZSBEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXCNtb2R1bGVuYW1lOwogICAgdXNlIFxEb3RzeXN0ZW1zXEFwcFxEb3RBcHA7CiAgICB1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXE1pZGRsZXdhcmU7Cgl1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXFJlc3BvbnNlOwoKCWNsYXNzIE1vZHVsZSBleHRlbmRzIFxEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xNb2R1bGUgewoJCQoJCXB1YmxpYyBmdW5jdGlvbiBpbml0aWFsaXplKCRkb3RBcHApIHsKCQkJLyoKCQkJCURlZmluZSB5b3VyIHJvdXRlcywgQVBJIHBvaW50cywgYW5kIHNpbWlsYXIgY29uZmlndXJhdGlvbnMgaGVyZS4gRXhhbXBsZXM6CgoJCQkJJGRvdEFwcC0+cm91dGVyLT5hcGlQb2ludCgiMSIsICIjbW9kdWxlbmFtZWxvd2VyIiwgIkFwaVxBcGlAYXBpRGlzcGF0Y2giKTsgLy8gQXV0b21hdGljIGRpc3BhdGNoZXIsIHNlZSBkZXRhaWxzIGluIEFwaS9BcGkucGhwCgoJCQkJRXhhbXBsZSB3aXRob3V0IGF1dG9tYXRpYyBkaXNwYXRjaGluZzoKCQkJCSRkb3RBcHAtPnJvdXRlci0+YXBpUG9pbnQoIjEiLCAiI21vZHVsZW5hbWVsb3dlci91c2VycyIsICJBcGlcVXNlcnNAYXBpIik7CgkJCQlUaGlzIGNhbGxzIHRoZSBgYXBpYCBtZXRob2QgaW4gdGhlIGBVc2Vyc2AgY2xhc3MgbG9jYXRlZCBpbiB0aGUgYEFwaWAgZm9sZGVyLgoJCQkJCgkJCQlFeGFtcGxlIG9mIGNhbGxpbmcgY29udHJvbGxlcnM6CgkJCQkkZG90QXBwLT5yb3V0ZXItPmdldCgiLyNtb2R1bGVuYW1lbG93ZXIvaG9tZSIsICJDb250cm9sbGVyQGhvbWUiKTsKCgkJCQlFeGFtcGxlIHVzaW5nIHRoZSBicmlkZ2U6CgkJCQkkZG90QXBwLT5icmlkZ2UtPmZuKCJuZXdzbGV0dGVyIiwgIkNvbnRyb2xsZXJAbmV3c2xldHRlciIpOwoJCQkqLwoJCQkKICAgICAgICAgICAgLy8gQWRkIHlvdXIgcm91dGVzIGFuZCBsb2dpYyBoZXJlCgkJCQoJCX0KCQkKCQkvKgoJCQlUaGlzIGZ1bmN0aW9uIGRlZmluZXMgdGhlIHNwZWNpZmljIFVSTCByb3V0ZXMgd2hlcmUgdGhlIG1vZHVsZSBzaG91bGQgYmUgaW5pdGlhbGl6ZWQuCgoJCQnwn5SnIEhvdyBpdCB3b3JrczoKCQkJLSBSZXR1cm4gYW4gYXJyYXkgb2Ygcm91dGVzIChlLmcuLCBbIi9ibG9nLyoiLCAiL25ld3Mve2lkOml9Il0pIHRvIHNwZWNpZnkgd2hlcmUgdGhlIG1vZHVsZSBzaG91bGQgaW5pdGlhbGl6ZS4KCQkJLSBSZXR1cm4gWycqJ10gdG8gYWx3YXlzIGluaXRpYWxpemUgdGhlIG1vZHVsZSBvbiBldmVyeSByb3V0ZSAobm90IHJlY29tbWVuZGVkIGluIGxhcmdlIGFwcHMpLgoKCQkJVGhpcyByb3V0aW5nIGxvZ2ljIGlzIHVzZWQgaW50ZXJuYWxseSBieSB0aGUgRG90YXBwZXIgYXV0by1pbml0aWFsaXphdGlvbiBzeXN0ZW0KCQkJdGhyb3VnaCB0aGUgYGF1dG9Jbml0aWFsaXplQ29uZGl0aW9uKClgIG1ldGhvZC4KCgkJCeKchSBSZWNvbW1lbmRlZDogSWYgeW91IGFyZSB1c2luZyBhIGxhcmdlIG51bWJlciBvZiBtb2R1bGVzIGFuZCB3YW50IHRvIG9wdGltaXplIHBlcmZvcm1hbmNlLCAKCQkJYWx3YXlzIHJldHVybiBhbiBhcnJheSBvZiByZWxldmFudCByb3V0ZXMgdG8gYWxsb3cgbG9hZGVyIG9wdGltaXphdGlvbi4KCgkJCUV4YW1wbGU6CgkJCQlyZXR1cm4gWyIvYWRtaW4vKiIsICIvZGFzaGJvYXJkIl07CgoJCQnimqDvuI8gSW1wb3J0YW50OiBJZiB5b3UgYWRkLCByZW1vdmUsIG9yIGNoYW5nZSBhbnkgbW9kdWxlcyBvciB0aGVpciByb3V0ZXMsIGFsd2F5cyByZWdlbmVyYXRlIHRoZSBvcHRpbWl6ZWQgbG9hZGVyOgoJCQkJQ29tbWFuZDogcGhwIGRvdGFwcGVyLnBocCAtLW9wdGltaXplLW1vZHVsZXMKCQkqLwoJCXB1YmxpYyBmdW5jdGlvbiBpbml0aWFsaXplUm91dGVzKCkgewoJCQlyZXR1cm4gWycvZG9jdW1lbnRhdGlvbi9pbnRyby8jbW9kdWxlbnVtYmVyJ107IC8vIEFsd2F5cyBtYXRjaGVzIGFsbCBVUkxzLCBidXQgbGVzcyBlZmZpY2llbnQKCQl9CgoJCS8qCgkJCVRoaXMgZnVuY3Rpb24gZGVmaW5lcyBhZGRpdGlvbmFsIGNvbmRpdGlvbnMgZm9yIHdoZXRoZXIgdGhlIG1vZHVsZSBzaG91bGQgaW5pdGlhbGl6ZSwKCQkJKiphZnRlcioqIHJvdXRlIG1hdGNoaW5nIGhhcyBhbHJlYWR5IHN1Y2NlZWRlZC4KCgkJCSRyb3V0ZU1hdGNoIOKAkyBib29sZWFuOiBUUlVFIGlmIHRoZSBjdXJyZW50IHJvdXRlIG1hdGNoZWQgb25lIG9mIHRob3NlIHJldHVybmVkIGZyb20gaW5pdGlhbGl6ZVJvdXRlcygpLCBGQUxTRSBvdGhlcndpc2UuCgoJCQlSZXR1cm4gdmFsdWVzOgoJCQktIHRydWU6IE1vZHVsZSB3aWxsIGJlIGluaXRpYWxpemVkLgoJCQktIGZhbHNlOiBNb2R1bGUgd2lsbCBub3QgYmUgaW5pdGlhbGl6ZWQuCgoJCQlVc2UgdGhpcyBpZiB5b3Ugd2FudCB0byBjaGVjayBsb2dpbiBzdGF0ZSwgdXNlciByb2xlcywgb3IgYW55IG90aGVyIGR5bmFtaWMgY29uZGl0aW9ucy4KCQkJRG8gTk9UIHJldHVybiBhbiBhcnJheSBvciBhbnl0aGluZyBvdGhlciB0aGFuIGEgYm9vbGVhbi4KCgkJCUV4YW1wbGU6CgkJCQlpZiAoISR0aGlzLT5kb3RBcHAtPmF1dGgtPmlzTG9nZ2VkSW4oKSkgcmV0dXJuIGZhbHNlOwoJCQkJcmV0dXJuIHRydWU7CgkJKi8KCQlwdWJsaWMgZnVuY3Rpb24gaW5pdGlhbGl6ZUNvbmRpdGlvbigkcm91dGVNYXRjaCkgewoJCQlyZXR1cm4gJHJvdXRlTWF0Y2g7IC8vIEFsd2F5cyBpbml0aWFsaXplIGlmIHRoZSByb3V0ZSBtYXRjaGVkIChkZWZhdWx0IGJlaGF2aW9yKQoJCX0KCX0KCQoJbmV3IE1vZHVsZSgkZG90QXBwKTsKPz4K";
        if ($filename=="/sql.sql") return "U0VUIFNRTF9NT0RFID0gIk5PX0FVVE9fVkFMVUVfT05fWkVSTyI7ClNUQVJUIFRSQU5TQUNUSU9OOwpTRVQgdGltZV96b25lID0gIiswMDowMCI7CgpEUk9QIFRBQkxFIElGIEVYSVNUUyBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzYDsKQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc2AgKAogIGBpZGAgaW50IE5PVCBOVUxMIEFVVE9fSU5DUkVNRU5ULAogIGB1c2VybmFtZWAgdmFyY2hhcig1MCkgQ09MTEFURSB1dGY4bWI0X2dlbmVyYWxfY2kgTk9UIE5VTEwgQ09NTUVOVCAnLy8gVXppdmF0ZWxza2UgbWVubycsCiAgYGVtYWlsYCB2YXJjaGFyKDEwMCkgQ09MTEFURSB1dGY4bWI0X2dlbmVyYWxfY2kgTk9UIE5VTEwgQ09NTUVOVCAnLy8gRW1haWwsIG1vemUgc2EgcG91eml0IG5hIHByaWhsYXNlbmllIHRpZXouIE1vemUgc2EgcG91eml2YXQgbmEgZW1haWxvdmUgbm90aWZpa2FjaWUnLAogIGBwYXNzd29yZGAgdmFyY2hhcigxMDApIENPTExBVEUgdXRmOG1iNF9nZW5lcmFsX2NpIE5PVCBOVUxMIENPTU1FTlQgJy8vIEhlc2xvJywKICBgdGZhX2ZpcmV3YWxsYCBpbnQgTk9UIE5VTEwgQ09NTUVOVCAnLy8gUG91eml0IGFsZWJvIG5lcG91eml0IGZpcmV3YWxsJywKICBgdGZhX3Ntc2AgaW50IE5PVCBOVUxMIENPTU1FTlQgJy8vIFBvdXppdmFtZSAyZmFrdG9yIGNleiBTTVM/JywKICBgdGZhX3Ntc19udW1iZXJfcHJlZml4YCB2YXJjaGFyKDgpIENPTExBVEUgdXRmOG1iNF9nZW5lcmFsX2NpIE5PVCBOVUxMLAogIGB0ZmFfc21zX251bWJlcmAgdmFyY2hhcigyMCkgQ09MTEFURSB1dGY4bWI0X2dlbmVyYWxfY2kgTk9UIE5VTEwgQ09NTUVOVCAnLy8gQ2lzbG8gcHJlIHphc2xhbmllIFNNUycsCiAgYHRmYV9zbXNfbnVtYmVyX2NvbmZpcm1lZGAgaW50IE5PVCBOVUxMIENPTU1FTlQgJy8vIENpc2xvIHBvdHZyZGVuZSB6YWRhbmltIGtvZHUnLAogIGB0ZmFfYXV0aGAgaW50IE5PVCBOVUxMIENPTU1FTlQgJy8vIFBvdXppdmFtZSAyIGZha3RvciBjZXogR09PR0xFIEFVVEggPycsCiAgYHRmYV9hdXRoX3NlY3JldGAgdmFyY2hhcig1MCkgQ09MTEFURSB1dGY4bWI0X2dlbmVyYWxfY2kgTk9UIE5VTEwgQ09NTUVOVCAnLy8gQWsgYW1tZSBnb29nbGUgYXV0aCwgdGFrIHRyZWJhIGRyemF0IHVsb3plbnkgc2VjcmV0ICcsCiAgYHRmYV9hdXRoX3NlY3JldF9jb25maXJtZWRgIGludCBOT1QgTlVMTCBDT01NRU5UICcvLyBCb2xvIHBvdHZyZGVuZSAyRkEgYXV0aD8nLAogIGB0ZmFfZW1haWxgIGludCBOT1QgTlVMTCBDT01NRU5UICcvLyBQb3V6aXZhbWUgMiBmYWt0b3IgY2V6IGUtbWFpbD8nLAogIGBzdGF0dXNgIGludCBOT1QgTlVMTCBDT01NRU5UICcvLyBTdGF0dXMgcHJpaGxhc2VuaWEuIDEgLSBBa3Rpdm55LCAyLURMaHNpZSBuZWFrdGl2bnksIDMgLSBPZmZsaW5lJywKICBgY3JlYXRlZF9hdGAgdGltZXN0YW1wIE5PVCBOVUxMLAogIGB1cGRhdGVkX2F0YCB0aW1lc3RhbXAgTk9UIE5VTEwsCiAgYGxhc3RfbG9nZ2VkX2F0YCB0aW1lc3RhbXAgTk9UIE5VTEwsCiAgUFJJTUFSWSBLRVkgKGBpZGApLAogIFVOSVFVRSBLRVkgYHVzZXJuYW1lYCAoYHVzZXJuYW1lYCksCiAgVU5JUVVFIEtFWSBgZW1haWxgIChgZW1haWxgKQopIEVOR0lORT1Jbm5vREIgREVGQVVMVCBDSEFSU0VUPXV0ZjhtYjQgQ09MTEFURT11dGY4bWI0X2dlbmVyYWxfY2kgQ09NTUVOVD0nVGFidWxreSBzIHV6aXZhdGVsbWkgbW9kdWx1IHVzZXJzJzsKCgpEUk9QIFRBQkxFIElGIEVYSVNUUyBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX2ZpcmV3YWxsYDsKQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19maXJld2FsbGAgKAogIGBpZGAgYmlnaW50IE5PVCBOVUxMIEFVVE9fSU5DUkVNRU5ULAogIGB1c2VyX2lkYCBpbnQgTk9UIE5VTEwsCiAgYHJ1bGVgIHZhcmNoYXIoNTApIENPTExBVEUgdXRmOG1iNF9nZW5lcmFsX2NpIE5PVCBOVUxMIENPTU1FTlQgJy8vIFByYXZpZGxvIHByZSBmaXJld2FsbC4gQ0lEUiB0dmFyLiBOYXByaWtsYWQgMTkyLjE2OC4xLjAvMjQnLAogIGBhY3Rpb25gIGludCBOT1QgTlVMTCBDT01NRU5UICcwIC0gQmxvY2ssIDEgLSBBbGxvdycsCiAgYGFjdGl2ZWAgaW50IE5PVCBOVUxMIENPTU1FTlQgJy8vIFJ1bGUgaXMgYWN0aXZlIG9yIGluYWN0aXZlJywKICBgb3JkZXJpbmdgIGludCBOT1QgTlVMTCBDT01NRU5UICcvLyBQb3JhZGllIHByYXZpZGxhJywKICBQUklNQVJZIEtFWSAoYGlkYCksCiAgS0VZIGBvcmRlcmluZ2AgKGBvcmRlcmluZ2ApLAogIEtFWSBgdXNlcl9pZGAgKGB1c2VyX2lkYCksCiAgS0VZIGB1c2VyX2lkXzJgIChgdXNlcl9pZGAsYGFjdGl2ZWApCikgRU5HSU5FPUlubm9EQiBERUZBVUxUIENIQVJTRVQ9dXRmOG1iNCBDT0xMQVRFPXV0ZjhtYjRfZ2VuZXJhbF9jaTsKCkRST1AgVEFCTEUgSUYgRVhJU1RTIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNfcGFzc3dvcmRfcmVzZXRzYDsKQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19wYXNzd29yZF9yZXNldHNgICgKICBgaWRgIGJpZ2ludCBOT1QgTlVMTCBBVVRPX0lOQ1JFTUVOVCwKICBgdXNlcl9pZGAgaW50IE5PVCBOVUxMLAogIGB0b2tlbmAgdmFyY2hhcigyNTUpIENPTExBVEUgdXRmOG1iNF9nZW5lcmFsX2NpIE5PVCBOVUxMLAogIGBjcmVhdGVkX2F0YCB0aW1lc3RhbXAgTk9UIE5VTEwgREVGQVVMVCBDVVJSRU5UX1RJTUVTVEFNUCwKICBgZXhwaXJlc19hdGAgdGltZXN0YW1wIE5PVCBOVUxMLAogIFBSSU1BUlkgS0VZIChgaWRgKSwKICBLRVkgYHVzZXJfaWRgIChgdXNlcl9pZGApCikgRU5HSU5FPUlubm9EQiBERUZBVUxUIENIQVJTRVQ9dXRmOG1iNCBDT0xMQVRFPXV0ZjhtYjRfZ2VuZXJhbF9jaTsKCkRST1AgVEFCTEUgSUYgRVhJU1RTIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNfcmlnaHRzYDsKQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19yaWdodHNgICgKICBgaWRgIGludCBOT1QgTlVMTCBBVVRPX0lOQ1JFTUVOVCwKICBgdXNlcl9pZGAgaW50IE5PVCBOVUxMLAogIGByaWdodF9pZGAgaW50IE5PVCBOVUxMLAogIFBSSU1BUlkgS0VZIChgaWRgKSwKICBLRVkgYHVzZXJfaWRgIChgdXNlcl9pZGAsYHJpZ2h0X2lkYCksCiAgS0VZIGB1c2VyX2lkXzJgIChgdXNlcl9pZGApLAogIEtFWSBgcmlnaHRfaWRgIChgcmlnaHRfaWRgKQopIEVOR0lORT1Jbm5vREIgREVGQVVMVCBDSEFSU0VUPXV0ZjhtYjQgQ09MTEFURT11dGY4bWI0X2dlbmVyYWxfY2k7CgpEUk9QIFRBQkxFIElGIEVYSVNUUyBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX3JpZ2h0c19ncm91cHNgOwpDUkVBVEUgVEFCTEUgSUYgTk9UIEVYSVNUUyBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX3JpZ2h0c19ncm91cHNgICgKICBgaWRgIGludCBOT1QgTlVMTCBBVVRPX0lOQ1JFTUVOVCwKICBgbmFtZWAgbWVkaXVtdGV4dCBDT0xMQVRFIHV0ZjhtYjRfZ2VuZXJhbF9jaSBOT1QgTlVMTCBDT01NRU5UICcvLyBOYXpvdiBncnVweSAtIE5vcm1hbG5lIHRleHRvbScsCiAgYG9yZGVyaW5nYCBpbnQgTk9UIE5VTEwgQ09NTUVOVCAnLy8gUG9yYWRpZScsCiAgYGNyZWF0b3JgIHZhcmNoYXIoMTAwKSBDT0xMQVRFIHV0ZjhtYjRfZ2VuZXJhbF9jaSBOT1QgTlVMTCBDT01NRU5UICcvLyBLdG9yeSBtb2R1bCB0byB2eXR2b3JpbCBwcmUgb2RpbnN0YWxhY2l1LiBBayBqZSBwcmF6ZG5lIHRhayBqZSB0byB2c3RhdmFuZSBkZWZhdWx0bmUgZG8gc3lzdGVtdScsCiAgYGVkaXRhYmxlYCBpbnQgTk9UIE5VTEwgQ09NTUVOVCAnLy8gMCAtIG5lc21pZSBzYSB1cHJhdm92YXQgLyAxIC0gbW96ZSBzYSB1cHJhdm92YXQnLAogIFBSSU1BUlkgS0VZIChgaWRgKSwKICBLRVkgYG9yZGVyaW5nYCAoYG9yZGVyaW5nYCksCiAgS0VZIGBjcmVhdG9yYCAoYGNyZWF0b3JgKQopIEVOR0lORT1Jbm5vREIgREVGQVVMVCBDSEFSU0VUPXV0ZjhtYjQgQ09MTEFURT11dGY4bWI0X2dlbmVyYWxfY2k7CgpEUk9QIFRBQkxFIElGIEVYSVNUUyBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX3JpZ2h0c19saXN0YDsKQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19yaWdodHNfbGlzdGAgKAogIGBpZGAgaW50IE5PVCBOVUxMIEFVVE9fSU5DUkVNRU5ULAogIGBncm91cF9pZGAgaW50IE5PVCBOVUxMIENPTU1FTlQgJy8vIElkIHpvc2t1cGVuaWEgb3ByYXZuZW5pIGtlZHplIGthemR5bSBvZHp1bCBtb3plIG1hdCB2bGFzdG51IHNrdXBpbnUgbmVjaCB2IHRvbSBuaWUgamUgYm9yZGVsJywKICBgbmFtZWAgdGV4dCBDSEFSQUNURVIgU0VUIHV0ZjhtYjMgTk9UIE5VTEwgQ09NTUVOVCAnLy8gTmF6b3YgcHJhdmEgdiBkbGhvbSBmb3JtYXRlJywKICBgZGVzY3JpcHRpb25gIHRleHQgQ0hBUkFDVEVSIFNFVCB1dGY4bWIzIE5PVCBOVUxMIENPTU1FTlQgJy8vIFBvcGlzIG9wcmF2bmVuaWEgdiBkZXRhaWxvY2gnLAogIGBtb2R1bGVgIHZhcmNoYXIoMTAwKSBDSEFSQUNURVIgU0VUIHV0ZjhtYjMgTk9UIE5VTEwgQ09NTUVOVCAnLy8gTmF6b3YgbW9kdWx1IGt0b3J5IHByYXZvIHZ5dHZvcmlsJywKICBgcmlnaHRuYW1lYCB2YXJjaGFyKDEwMCkgQ0hBUkFDVEVSIFNFVCB1dGY4bWIzIE5PVCBOVUxMIENPTU1FTlQgJy8vIE9wcmF2bmVuaWUgJywKICBgYWN0aXZlYCBpbnQgTk9UIE5VTEwgQ09NTUVOVCAnLy8gMCBuaWUgMSBhbm8nLAogIGBvcmRlcmluZ2AgaW50IE5PVCBOVUxMIENPTU1FTlQgJy8vIFpvcmFkZW5pZScsCiAgYGNyZWF0b3JgIHZhcmNoYXIoMTAwKSBDSEFSQUNURVIgU0VUIHV0ZjhtYjMgTk9UIE5VTEwgQ09NTUVOVCAnLy8gS3RvcnkgbW9kdWwgdnl0dm9yaWwgem96bmFtIGFieSBib2xvIG1vem5lIHByaSBvZGluc3RhbGFjaWkgaG8gem1hemF0JywKICBgY3VzdG9tYCBpbnQgTk9UIE5VTEwgQ09NTUVOVCAnMCAtIG5pZSwgMSAtIGFubycsCiAgUFJJTUFSWSBLRVkgKGBpZGApLAogIEtFWSBgbW9kdWxlYCAoYG1vZHVsZWApLAogIEtFWSBgcmlnaHRuYW1lYCAoYHJpZ2h0bmFtZWApLAogIEtFWSBgbW9kdWxlXzJgIChgbW9kdWxlYCxgcmlnaHRuYW1lYCksCiAgS0VZIGBvcmRlcmluZ2AgKGBvcmRlcmluZ2ApLAogIEtFWSBgcmlnaHRuYW1lXzJgIChgcmlnaHRuYW1lYCxgYWN0aXZlYCxgb3JkZXJpbmdgKSwKICBLRVkgYGdyb3VwX2lkYCAoYGdyb3VwX2lkYCxgbW9kdWxlYCxgcmlnaHRuYW1lYCxgb3JkZXJpbmdgKSwKICBLRVkgYGlkYCAoYGlkYCxgYWN0aXZlYCkKKSBFTkdJTkU9SW5ub0RCIERFRkFVTFQgQ0hBUlNFVD11dGY4bWI0IENPTExBVEU9dXRmOG1iNF9nZW5lcmFsX2NpIENPTU1FTlQ9J1pvem5hbSBvcHJhdm5lbmkga3RvcmUgamUgbW96bmUgdXppdmF0ZWx2aSBwcmlyYWRpdCc7CgpEUk9QIFRBQkxFIElGIEVYSVNUUyBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX3JtdG9rZW5zYDsKQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19ybXRva2Vuc2AgKAogIGBpZGAgaW50IE5PVCBOVUxMIEFVVE9fSU5DUkVNRU5ULAogIGB1c2VyX2lkYCBpbnQgTk9UIE5VTEwsCiAgYHRva2VuYCB2YXJjaGFyKDI1NSkgQ09MTEFURSB1dGY4bWI0X2dlbmVyYWxfY2kgTk9UIE5VTEwsCiAgYGV4cGlyZXNfYXRgIHRpbWVzdGFtcCBOT1QgTlVMTCwKICBQUklNQVJZIEtFWSAoYGlkYCksCiAgVU5JUVVFIEtFWSBgdG9rZW5gIChgdG9rZW5gKSwKICBLRVkgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19zZXNzaW9uc19pYmZrXzFgIChgdXNlcl9pZGApCikgRU5HSU5FPUlubm9EQiBERUZBVUxUIENIQVJTRVQ9dXRmOG1iNCBDT0xMQVRFPXV0ZjhtYjRfZ2VuZXJhbF9jaTsKCkRST1AgVEFCTEUgSUYgRVhJU1RTIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNfcm9sZXNgOwpDUkVBVEUgVEFCTEUgSUYgTk9UIEVYSVNUUyBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX3JvbGVzYCAoCiAgYGlkYCBpbnQgTk9UIE5VTEwgQVVUT19JTkNSRU1FTlQsCiAgYHVzZXJfaWRgIGludCBOT1QgTlVMTCwKICBgcm9sZV9pZGAgaW50IE5PVCBOVUxMLAogIGBhc3NpZ25lZF9hdGAgdGltZXN0YW1wIE5VTEwgREVGQVVMVCBDVVJSRU5UX1RJTUVTVEFNUCwKICBQUklNQVJZIEtFWSAoYGlkYCksCiAgVU5JUVVFIEtFWSBgdW5pcXVlX3VzZXJfcm9sZWAgKGB1c2VyX2lkYCxgcm9sZV9pZGApLAogIEtFWSBgaWRfcm9seWAgKGByb2xlX2lkYCkKKSBFTkdJTkU9SW5ub0RCIERFRkFVTFQgQ0hBUlNFVD11dGY4bWI0IENPTExBVEU9dXRmOG1iNF9nZW5lcmFsX2NpOwoKRFJPUCBUQUJMRSBJRiBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19yb2xlc19saXN0YDsKQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19yb2xlc19saXN0YCAoCiAgYGlkYCBpbnQgTk9UIE5VTEwgQVVUT19JTkNSRU1FTlQsCiAgYG5hbWVgIHZhcmNoYXIoNTApIENIQVJBQ1RFUiBTRVQgdXRmMTYgTk9UIE5VTEwsCiAgYGRlc2NyaXB0aW9uYCB0ZXh0IENIQVJBQ1RFUiBTRVQgdXRmMTYsCiAgUFJJTUFSWSBLRVkgKGBpZGApLAogIFVOSVFVRSBLRVkgYG5hbWVgIChgbmFtZWApCikgRU5HSU5FPUlubm9EQiBERUZBVUxUIENIQVJTRVQ9dXRmOG1iNCBDT0xMQVRFPXV0ZjhtYjRfZ2VuZXJhbF9jaTsKCkRST1AgVEFCTEUgSUYgRVhJU1RTIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNfcm9sZXNfcmlnaHRzYDsKQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19yb2xlc19yaWdodHNgICgKICBgaWRgIGludCBOT1QgTlVMTCBBVVRPX0lOQ1JFTUVOVCwKICBgcmlnaHRfaWRgIGludCBOT1QgTlVMTCwKICBgcm9sZV9pZGAgaW50IE5PVCBOVUxMLAogIGBhc3NpZ25lZF9hdGAgdGltZXN0YW1wIE5VTEwgREVGQVVMVCBDVVJSRU5UX1RJTUVTVEFNUCwKICBQUklNQVJZIEtFWSAoYGlkYCksCiAgVU5JUVVFIEtFWSBgdW5pcV9yaWdodF9yb2xlYCAoYHJpZ2h0X2lkYCxgcm9sZV9pZGApLAogIEtFWSBgcm9sZV9pZGAgKGByb2xlX2lkYCkKKSBFTkdJTkU9SW5ub0RCIERFRkFVTFQgQ0hBUlNFVD11dGY4bWI0IENPTExBVEU9dXRmOG1iNF9nZW5lcmFsX2NpOwoKRFJPUCBUQUJMRSBJRiBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19zZXNzaW9uc2A7CkNSRUFURSBUQUJMRSBJRiBOT1QgRVhJU1RTIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNfc2Vzc2lvbnNgICgKICBgc2Vzc2lvbl9pZGAgdmFyY2hhcig2NCkgQ09MTEFURSB1dGY4bWI0X2dlbmVyYWxfY2kgTk9UIE5VTEwsCiAgYHNlc3NuYW1lYCB2YXJjaGFyKDI1NSkgQ09MTEFURSB1dGY4bWI0X2dlbmVyYWxfY2kgTk9UIE5VTEwsCiAgYHZhbHVlc2AgbG9uZ3RleHQgQ09MTEFURSB1dGY4bWI0X2dlbmVyYWxfY2kgTk9UIE5VTEwsCiAgYHZhcmlhYmxlc2AgbG9uZ3RleHQgQ09MTEFURSB1dGY4bWI0X2dlbmVyYWxfY2kgTk9UIE5VTEwsCiAgYGV4cGlyeWAgYmlnaW50IE5PVCBOVUxMLAogIGBjcmVhdGVkX2F0YCB0aW1lc3RhbXAgTk9UIE5VTEwgREVGQVVMVCBDVVJSRU5UX1RJTUVTVEFNUCwKICBgdXBkYXRlZF9hdGAgdGltZXN0YW1wIE5PVCBOVUxMIERFRkFVTFQgQ1VSUkVOVF9USU1FU1RBTVAgT04gVVBEQVRFIENVUlJFTlRfVElNRVNUQU1QLAogIFBSSU1BUlkgS0VZIChgc2Vzc2lvbl9pZGAsYHNlc3NuYW1lYCksCiAgS0VZIGBpZHhfZXhwaXJ5YCAoYGV4cGlyeWApCikgRU5HSU5FPUlubm9EQiBERUZBVUxUIENIQVJTRVQ9dXRmOG1iNCBDT0xMQVRFPXV0ZjhtYjRfZ2VuZXJhbF9jaTsKCkRST1AgVEFCTEUgSUYgRVhJU1RTIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNfdXJsX2ZpcmV3YWxsYDsKQ1JFQVRFIFRBQkxFIElGIE5PVCBFWElTVFMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc191cmxfZmlyZXdhbGxgICgKICBgaWRgIGludCBOT1QgTlVMTCwKICBgdXNlcmAgaW50IE5PVCBOVUxMLAogIGB1cmxgIHZhcmNoYXIoMjAwKSBDSEFSQUNURVIgU0VUIHV0ZjhtYjMgTk9UIE5VTEwgQ09NTUVOVCAnLy8gVXJsIG1vemUgYnl0IHMgKiBuYXByaWtsYWQgbW96ZSBieXQgKiAtIHRvIHpuYW1lbmEgdnNldGt5IGFkcmVzeSBibG9rbmVtZS4gQWxlYm8gYmxva25lbWUgbGVuICovdXppdmF0ZWxpYS8qIHRha3plIGFrIGplIHYgVVIhIC91eml2YXRlbGlhLyB0YWsgYmxva25lbWUgYWxlYm8gbmFvcGFrIHBvdm9saW1lJywKICBgYWN0aW9uYCBpbnQgTk9UIE5VTEwgQ09NTUVOVCAnMC1CbG9rbmkgLyAxIC0gUG92b2wnLAogIGBhY3RpdmVgIGludCBOT1QgTlVMTCBDT01NRU5UICcvLyBQcmF2aWRsbyBqZSBha3Rpdm92YW5lIGFsZWJvIGRlYWt0aXZvdmFuZScKKSBFTkdJTkU9SW5ub0RCIERFRkFVTFQgQ0hBUlNFVD11dGY4bWI0IENPTExBVEU9dXRmOG1iNF9nZW5lcmFsX2NpOwoKQUxURVIgVEFCTEUgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19maXJld2FsbGAKICBBREQgQ09OU1RSQUlOVCBgdXNlcnNfdnNfZmlyZXdhbGxgIEZPUkVJR04gS0VZIChgdXNlcl9pZGApIFJFRkVSRU5DRVMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc2AgKGBpZGApIE9OIERFTEVURSBDQVNDQURFIE9OIFVQREFURSBDQVNDQURFOwoKQUxURVIgVEFCTEUgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19wYXNzd29yZF9yZXNldHNgCiAgQUREIENPTlNUUkFJTlQgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19wYXNzd29yZF9yZXNldHNfaWJma18xYCBGT1JFSUdOIEtFWSAoYHVzZXJfaWRgKSBSRUZFUkVOQ0VTIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNgIChgaWRgKSBPTiBERUxFVEUgQ0FTQ0FERTsKCkFMVEVSIFRBQkxFIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNfcmlnaHRzYAogIEFERCBDT05TVFJBSU5UIGBwcmF2b19pZGAgRk9SRUlHTiBLRVkgKGByaWdodF9pZGApIFJFRkVSRU5DRVMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19yaWdodHNfbGlzdGAgKGBpZGApIE9OIERFTEVURSBDQVNDQURFIE9OIFVQREFURSBDQVNDQURFLAogIEFERCBDT05TVFJBSU5UIGB1eml2X2lkYCBGT1JFSUdOIEtFWSAoYHVzZXJfaWRgKSBSRUZFUkVOQ0VTIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNgIChgaWRgKSBPTiBERUxFVEUgQ0FTQ0FERSBPTiBVUERBVEUgQ0FTQ0FERTsKCkFMVEVSIFRBQkxFIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNfcmlnaHRzX2xpc3RgCiAgQUREIENPTlNUUkFJTlQgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19yaWdodHNfbGlzdF9pYmZrXzFgIEZPUkVJR04gS0VZIChgZ3JvdXBfaWRgKSBSRUZFUkVOQ0VTIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNfcmlnaHRzX2dyb3Vwc2AgKGBpZGApIE9OIERFTEVURSBDQVNDQURFIE9OIFVQREFURSBDQVNDQURFOwoKQUxURVIgVEFCTEUgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19ybXRva2Vuc2AKICBBREQgQ09OU1RSQUlOVCBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX3JtdG9rZW5zX2liZmtfMWAgRk9SRUlHTiBLRVkgKGB1c2VyX2lkYCkgUkVGRVJFTkNFUyBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzYCAoYGlkYCkgT04gREVMRVRFIENBU0NBREUgT04gVVBEQVRFIENBU0NBREU7CgpBTFRFUiBUQUJMRSBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX3JvbGVzYAogIEFERCBDT05TVFJBSU5UIGBpZF9yb2x5YCBGT1JFSUdOIEtFWSAoYHJvbGVfaWRgKSBSRUZFUkVOQ0VTIGBERUZBVUxURGF0YWJhc2VQcmVmaXhfdXNlcnNfcm9sZXNfbGlzdGAgKGBpZGApIE9OIERFTEVURSBDQVNDQURFLAogIEFERCBDT05TVFJBSU5UIGB1eml2YXRlbG92ZV9pZGAgRk9SRUlHTiBLRVkgKGB1c2VyX2lkYCkgUkVGRVJFTkNFUyBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzYCAoYGlkYCkgT04gREVMRVRFIENBU0NBREUgT04gVVBEQVRFIENBU0NBREU7CgpBTFRFUiBUQUJMRSBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX3JvbGVzX3JpZ2h0c2AKICBBREQgQ09OU1RSQUlOVCBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX3JvbGVzX3JpZ2h0c19pYmZrXzFgIEZPUkVJR04gS0VZIChgcm9sZV9pZGApIFJFRkVSRU5DRVMgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19yb2xlc19saXN0YCAoYGlkYCkgT04gREVMRVRFIENBU0NBREUgT04gVVBEQVRFIENBU0NBREUsCiAgQUREIENPTlNUUkFJTlQgYERFRkFVTFREYXRhYmFzZVByZWZpeF91c2Vyc19yb2xlc19yaWdodHNfaWJma18yYCBGT1JFSUdOIEtFWSAoYHJpZ2h0X2lkYCkgUkVGRVJFTkNFUyBgREVGQVVMVERhdGFiYXNlUHJlZml4X3VzZXJzX3JpZ2h0c19saXN0YCAoYGlkYCkgT04gREVMRVRFIENBU0NBREUgT04gVVBEQVRFIENBU0NBREU7CkNPTU1JVDsK";
        if ($filename=="/tests/guide.md") return "IyBHdWlkZSB0byBDcmVhdGluZyBUZXN0cyBmb3IgRG90QXBwIEZyYW1ld29yayBNb2R1bGVzCgpUaGlzIGd1aWRlIHByb3ZpZGVzIHNpbXBsZSBzdGVwcyBmb3IgY3JlYXRpbmcgdGVzdHMgZm9yIHlvdXIgbW9kdWxlcyBpbiB0aGUgRG90QXBwIEZyYW1ld29yayAodmVyc2lvbiAxLjcgRlJFRSkgdXNpbmcgdGhlIGBUZXN0ZXJgIGNsYXNzLiBJdCBpcyBkZXNpZ25lZCBmb3IgbW9kdWxlIGRldmVsb3BlcnMgZmFtaWxpYXIgd2l0aCB0aGUgZnJhbWV3b3Jr4oCZcyBtb2R1bGFyIHN0cnVjdHVyZSwgc2hvd2luZyBob3cgdG8gd3JpdGUgdGVzdHMgaW4gYGFwcC9tb2R1bGVzL01PRFVMRV9OQU1FL3Rlc3RzL2AgdXNpbmcgdGhlIGBEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXE1PRFVMRV9OQU1FXHRlc3RzYCBuYW1lc3BhY2UuIFRlc3RzIGFyZSBydW4gdXNpbmcgdGhlIGJ1aWx0LWluIGBkb3RhcHBlci5waHBgIENMSSB0b29sLgoKIyMgVGFibGUgb2YgQ29udGVudHMKCjEuIFtJbnRyb2R1Y3Rpb25dKCNpbnRyb2R1Y3Rpb24pCjIuIFtDcmVhdGluZyBUZXN0c10oI2NyZWF0aW5nLXRlc3RzKQogICAtIFtCYXNpYyBUZXN0XSgjYmFzaWMtdGVzdCkKICAgLSBbVGVzdCBSZXN1bHQgRm9ybWF0XSgjdGVzdC1yZXN1bHQtZm9ybWF0KQozLiBbT3JnYW5pemluZyBUZXN0c10oI29yZ2FuaXppbmctdGVzdHMpCjQuIFtSdW5uaW5nIFRlc3RzIHdpdGggYGRvdGFwcGVyLnBocGBdKCNydW5uaW5nLXRlc3RzLXdpdGgtZG90YXBwZXJwaHApCjUuIFtUaXBzIGFuZCBCZXN0IFByYWN0aWNlc10oI3RpcHMtYW5kLWJlc3QtcHJhY3RpY2VzKQo2LiBbVHJvdWJsZXNob290aW5nXSgjdHJvdWJsZXNob290aW5nKQoKIyMgSW50cm9kdWN0aW9uCgpUaGUgYFRlc3RlcmAgY2xhc3MgYWxsb3dzIHlvdSB0byB3cml0ZSB0ZXN0cyBmb3IgeW91ciBEb3RBcHAgRnJhbWV3b3JrIG1vZHVsZXMuIFRlc3RzIGFyZSByZWdpc3RlcmVkIHVzaW5nIGBUZXN0ZXI6OmFkZFRlc3RgIGFuZCBwbGFjZWQgaW4geW91ciBtb2R1bGXigJlzIGB0ZXN0cy9gIGRpcmVjdG9yeS4gVGhlIGZyYW1ld29ya+KAmXMgYXV0b2xvYWRlciBoYW5kbGVzIGRlcGVuZGVuY2llcywgcmVxdWlyaW5nIG9ubHkgYHVzZSBEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xUZXN0ZXI7YCBpbiB0ZXN0IGZpbGVzLiBUaGlzIGd1aWRlIHNob3dzIGhvdyB0byBjcmVhdGUgYSBzaW1wbGUgdGVzdCBmb3IgYSBtb2R1bGUgbmFtZWQgYE1PRFVMRV9OQU1FYCAoZS5nLiwgYEJsb2dgLCBgU2hvcGApIGFuZCBydW4gaXQgdXNpbmcgYGRvdGFwcGVyLnBocGAuCgojIyBDcmVhdGluZyBUZXN0cwoKIyMjIEJhc2ljIFRlc3QKClRlc3RzIGFyZSB3cml0dGVuIGFzIFBIUCBmaWxlcyBpbiBgYXBwL21vZHVsZXMvTU9EVUxFX05BTUUvdGVzdHMvYCB1c2luZyB0aGUgYERvdHN5c3RlbXNcQXBwXE1vZHVsZXNcTU9EVUxFX05BTUVcdGVzdHNgIG5hbWVzcGFjZS4gRWFjaCB0ZXN0IGlzIGEgY2FsbGJhY2sgZnVuY3Rpb24gcmVnaXN0ZXJlZCB3aXRoIGBUZXN0ZXI6OmFkZFRlc3RgLgoKRXhhbXBsZSBvZiBhIGJhc2ljIHRlc3QgKGBhcHAvbW9kdWxlcy9NT0RVTEVfTkFNRS90ZXN0cy9FeGFtcGxlVGVzdC5waHBgKToKCmBgYHBocAo8P3BocApuYW1lc3BhY2UgRG90c3lzdGVtc1xBcHBcTW9kdWxlc1xNT0RVTEVfTkFNRVx0ZXN0czsKCnVzZSBEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xUZXN0ZXI7CgpUZXN0ZXI6OmFkZFRlc3QoJ0V4YW1wbGUgdGVzdCcsIGZ1bmN0aW9uICgpIHsKICAgICRyZXN1bHQgPSAyICsgMiA9PT0gNDsKICAgIHJldHVybiBbCiAgICAgICAgJ3N0YXR1cycgPT4gJHJlc3VsdCA/IDEgOiAwLAogICAgICAgICdpbmZvJyA9PiAkcmVzdWx0ID8gJzIgKyAyIGVxdWFscyA0JyA6ICcyICsgMiBkb2VzIG5vdCBlcXVhbCA0JywKICAgICAgICAndGVzdF9uYW1lJyA9PiAnRXhhbXBsZSB0ZXN0JywKICAgICAgICAnY29udGV4dCcgPT4gWydtb2R1bGUnID0+ICdNT0RVTEVfTkFNRScsICdtZXRob2QnID0+ICdhZGRpdGlvbicsICd0ZXN0X3R5cGUnID0+ICd1bml0J10KICAgIF07Cn0pOwo/PgpgYGAKCiMjIyBUZXN0IFJlc3VsdCBGb3JtYXQKClRoZSBjYWxsYmFjayBmdW5jdGlvbiBtdXN0IHJldHVybiBhbiBhcnJheSB3aXRoOgoKLSAqKmBzdGF0dXNgKiogKGludCk6IFRlc3Qgc3RhdHVzOgogIC0gYDFgOiBQYXNzZWQgKE9LKS4KICAtIGAwYDogRmFpbGVkIChOT1QgT0spLgogIC0gYDJgOiBTa2lwcGVkIChTS0lQUEVEKS4KLSAqKmBpbmZvYCoqIChzdHJpbmcpOiBEZXNjcmlwdGlvbiBvZiB0aGUgcmVzdWx0IChlLmcuLCB3aHkgdGhlIHRlc3QgZmFpbGVkKS4KLSAqKmB0ZXN0X25hbWVgKiogKHN0cmluZyk6IFRlc3QgbmFtZSAodXN1YWxseSBtYXRjaGVzIGBhZGRUZXN0YCBuYW1lKS4KLSAqKmBjb250ZXh0YCoqIChhcnJheSwgb3B0aW9uYWwpOiBNZXRhZGF0YSAoZS5nLiwgbW9kdWxlLCBtZXRob2QsIHRlc3QgdHlwZSkuCgojIyBPcmdhbml6aW5nIFRlc3RzCgotIFBsYWNlIGFsbCB0ZXN0cyBpbiBgYXBwL21vZHVsZXMvTU9EVUxFX05BTUUvdGVzdHMvYCwgd2hlcmUgYE1PRFVMRV9OQU1FYCBpcyB5b3VyIG1vZHVsZeKAmXMgbmFtZSAoZS5nLiwgYEJsb2dgLCBgU2hvcGApLgotIFVzZSBkZXNjcmlwdGl2ZSBmaWxlIG5hbWVzLCBlLmcuLCBgRXhhbXBsZVRlc3QucGhwYCwgYE9yZGVyVGVzdC5waHBgLgotIFVzZSB0aGUgbmFtZXNwYWNlIGBEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXE1PRFVMRV9OQU1FXHRlc3RzYCBmb3IgYWxsIHRlc3QgZmlsZXMuCgojIyBSdW5uaW5nIFRlc3RzIHdpdGggYGRvdGFwcGVyLnBocGAKClRlc3RzIGFyZSBleGVjdXRlZCB1c2luZyB0aGUgYnVpbHQtaW4gYGRvdGFwcGVyLnBocGAgQ0xJIHRvb2wgZnJvbSB0aGUgcHJvamVjdOKAmXMgcm9vdCBkaXJlY3RvcnkuIFN1cHBvcnRlZCBjb21tYW5kczoKCi0gKipSdW4gYWxsIHRlc3RzIChjb3JlICsgYWxsIG1vZHVsZXMpKio6CiAgYGBgYmFzaAogIHBocCBkb3RhcHBlci5waHAgLS10ZXN0CiAgYGBgCgotICoqUnVuIGFsbCBtb2R1bGUgdGVzdHMgKG5vIGNvcmUgdGVzdHMpKio6CiAgYGBgYmFzaAogIHBocCBkb3RhcHBlci5waHAgLS10ZXN0LW1vZHVsZXMKICBgYGAKCi0gKipSdW4gdGVzdHMgZm9yIGEgc3BlY2lmaWMgbW9kdWxlKio6CiAgYGBgYmFzaAogIHBocCBkb3RhcHBlci5waHAgLS1tb2R1bGU9TU9EVUxFX05BTUUgLS10ZXN0CiAgYGBgCgpUaGUgb3V0cHV0IGluY2x1ZGVzIGZvciBlYWNoIHRlc3Q6Ci0gKipUZXN0IE5hbWUqKiAoYHRlc3RfbmFtZWApLgotICoqU3RhdHVzKiogKGBPS2AsIGBOT1QgT0tgLCBgU0tJUFBFRGApLgotICoqRGVzY3JpcHRpb24qKiAoYGluZm9gKS4KLSAqKkR1cmF0aW9uKiogKGluIHNlY29uZHMpLgotICoqTWVtb3J5IFVzYWdlKiogKGBtZW1vcnlfZGVsdGFgIGluIEtCKS4KLSAqKkNvbnRleHQqKiAoSlNPTi1lbmNvZGVkIGFycmF5KS4KCkV4YW1wbGUgb3V0cHV0OgoKYGBgClRlc3Q6IEV4YW1wbGUgdGVzdApTdGF0dXM6IE9LCkluZm86IDIgKyAyIGVxdWFscyA0CkR1cmF0aW9uOiAwLjAwMDEyM3MKTWVtb3J5IERlbHRhOiAyNTYuNTAgS0IKQ29udGV4dDogeyJtb2R1bGUiOiJNT0RVTEVfTkFNRSIsIm1ldGhvZCI6ImFkZGl0aW9uIiwidGVzdF90eXBlIjoidW5pdCJ9Ci0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0KU3VtbWFyeTogMS8xIHRlc3RzIHBhc3NlZCAoMCBza2lwcGVkLCAwIGZhaWxlZCkKYGBgCgojIyBUaXBzIGFuZCBCZXN0IFByYWN0aWNlcwoKMS4gKipVc2UgRGVzY3JpcHRpdmUgVGVzdCBOYW1lcyoqOgogICBOYW1lcyBsaWtlIGBFeGFtcGxlIHRlc3RgIG9yIGBPcmRlciBwcm9jZXNzZXMgcGF5bWVudGAgbWFrZSBpdCBlYXNpZXIgdG8gaWRlbnRpZnkgaXNzdWVzLgoKMi4gKipJbmNsdWRlIENvbnRleHQqKjoKICAgQWRkIG1ldGFkYXRhIGluIHRoZSBgY29udGV4dGAgYXJyYXksIHN1Y2ggYXMgbW9kdWxlIG5hbWUsIHRlc3RlZCBtZXRob2QsIG9yIHRlc3QgdHlwZSAoZS5nLiwgYHVuaXRgLCBgaW50ZWdyYXRpb25gKS4KCjMuICoqVGVzdCBFZGdlIENhc2VzKio6CiAgIFRlc3Qgbm9ybWFsIHNjZW5hcmlvcyBhbmQgZXJyb3IgY29uZGl0aW9ucyB3aGVuIGV4cGFuZGluZyBiZXlvbmQgc2ltcGxlIHRlc3RzLgoKNC4gKipPcHRpbWl6ZSBUZXN0IEV4ZWN1dGlvbioqOgogICBSdW4gc3BlY2lmaWMgbW9kdWxlIHRlc3RzIHdpdGggYC0tbW9kdWxlPU1PRFVMRV9OQU1FIC0tdGVzdGAgdG8gc2F2ZSB0aW1lLgoKNS4gKipJbnRlZ3JhdGUgd2l0aCBDSS9DRCoqOgogICBBZGQgYGRvdGFwcGVyLnBocGAgY29tbWFuZHMgdG8geW91ciBDSS9DRCBwaXBlbGluZSAoZS5nLiwgR2l0SHViIEFjdGlvbnMpIGZvciBhdXRvbWF0ZWQgdGVzdGluZy4KCjYuICoqTG9nIFJlc3VsdHMqKjoKICAgQ29uZmlndXJlIGBkb3RhcHBlci5waHBgIHRvIHNhdmUgcmVzdWx0cyB0byBhIGZpbGUgKGUuZy4sIGBhcHAvcnVudGltZS9sb2dzL3Rlc3RzLmxvZ2ApIGZvciBhbmFseXNpcy4KCiMjIFRyb3VibGVzaG9vdGluZwoKLSAqKlRlc3RzIE5vdCBMb2FkaW5nKio6CiAgLSBFbnN1cmUgdGVzdCBmaWxlcyBhcmUgaW4gYGFwcC9tb2R1bGVzL01PRFVMRV9OQU1FL3Rlc3RzL2AuCiAgLSBWZXJpZnkgdGhlIG5hbWVzcGFjZSBpcyBgRG90c3lzdGVtc1xBcHBcTW9kdWxlc1xNT0RVTEVfTkFNRVx0ZXN0c2AuCiAgLSBDaGVjayB0aGF0IHRoZSBtb2R1bGUgbmFtZSBpbiBgLS1tb2R1bGU9TU9EVUxFX05BTUVgIG1hdGNoZXMgZXhhY3RseS4KCi0gKipFeGNlcHRpb25zIGluIFRlc3RzKio6CiAgLSBDaGVjayB0aGUgYGluZm9gIGZpZWxkIGluIHRoZSB0ZXN0IG91dHB1dCBmb3IgdGhlIGV4Y2VwdGlvbiBtZXNzYWdlLgogIC0gRW5zdXJlIHRoZSBjYWxsYmFjayBmdW5jdGlvbiByZXR1cm5zIHRoZSBjb3JyZWN0IHJlc3VsdCBmb3JtYXQuCgotICoqSGlnaCBNZW1vcnkgVXNhZ2UqKjoKICAtIFVzZSBgZ2NfY29sbGVjdF9jeWNsZXMoKWAgd2l0aGluIHRlc3RzIHRvIGZyZWUgbWVtb3J5IGlmIG5lZWRlZC4KCi0tLQoKKipBdXRob3IqKjogxaB0ZWZhbiBNacWhxI3DrWsgIAoqKkNvbXBhbnkqKjogRG90c3lzdGVtcyBzLnIuby4gIAoqKkxpY2Vuc2UqKjogTUlUIExpY2Vuc2UgIAoqKlZlcnNpb24qKjogMS43IEZSRUUgIAoqKkRhdGUqKjogMjAxNCAtIDIwMjU=";
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

                    // Skip if file is in $filesToSkip
                    if (is_array($filesToSkip) && in_array(realpath($destination) ?: $destination, array_map(function($path) { return realpath($path) ?: $path; }, $filesToSkip))) {
                        continue;
                    }

                    // If $filesToCopy is set, only check those files
                    if (is_array($filesToCopy) && !empty($filesToCopy)) {
                        if (!in_array(realpath($destination) ?: $destination, array_map(function($path) { return realpath($path) ?: $path; }, $filesToCopy))) {
                            continue;
                        }
                    }

                    if (file_exists($destination)) {
                        // Check if the file is in $filesToSkip; if so, skip the error
                        if (is_array($filesToSkip) && in_array(realpath($destination) ?: $destination, array_map(function($path) { return realpath($path) ?: $path; }, $filesToSkip))) {
                            continue; // Skip this file as it's in $filesToSkip
                        }

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

                $sourceRealPath = realpath($sourcePath) ?: $sourcePath;
                $sourceParentRealPath = realpath($item->getPath()) ?: $item->getPath();
                if (in_array($sourceRealPath, $filesToCopy) || in_array($sourceParentRealPath, $filesToCopy)) {
                    if ($item->isDir()) {
                        if (!is_dir($destPath) && !mkdir($destPath, 0755, true)) {
                            echo "Error: Failed to create directory $destPath.\n";
                            $success = false;
                            break;
                        }
                    } else {
                        // Skip existence check for files in $filesToSkip
                        if (is_array($filesToSkip) && (in_array($sourceRealPath, $filesToSkip) || in_array($sourceParentRealPath, $filesToSkip))) {
                            continue;
                        }
                        if (file_exists($destPath) && !$overwrite) {
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
        } else {
            // Copy all files except those in $filesToSkip
            foreach ($iterator as $item) {
                $sourcePath = $item->getPathname();
                $relativePath = substr($sourcePath, strlen($effectiveSourceDir) + 1);
                $destPath = rtrim($whereToExtract, '/\\') . DIRECTORY_SEPARATOR . $relativePath;

                $sourceRealPath = realpath($sourcePath) ?: $sourcePath;
                $sourceParentRealPath = realpath($item->getPath()) ?: $item->getPath();
                if (is_array($filesToSkip) && (in_array($sourceRealPath, $filesToSkip) || in_array($sourceParentRealPath, $filesToSkip))) {
                    continue;
                }

                if ($item->isDir()) {
                    if (!is_dir($destPath) && !mkdir($destPath, 0755, true)) {
                        echo "Error: Failed to create directory $destPath.\n";
                        $success = false;
                        break;
                    }
                } else {
                    // Existence check is not needed for files in $filesToSkip as they are already skipped above
                    if (file_exists($destPath) && !$overwrite) {
                        if (is_array($filesToSkip) && in_array(realpath($destPath) ?: $destPath, array_map(function($path) { return realpath($path) ?: $path; }, $filesToSkip))) {
                            continue; // Skip this file as it's in $filesToSkip
                        }
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