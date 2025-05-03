#!/usr/bin/env php
<?php
namespace Dotsystems\DotApper;

define('__DOTAPPER_RUN__',1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'Access denied !';
    exit(1);
}

class DotApper
{
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

        // Spracuj rozpoznané možnosti
        foreach ($this->options as $key => $value) {
            switch ($key) {
                case 'create-module':
                    $this->createModule($value);
                    break;
                case 'create-modules':
                    $this->createModules();
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
                case 'module':
                    $moduly = $this->listModules();
                    if (is_numeric($value)) {
                        if (isSet($moduly[intval($value)-1])) {
                            $this->modul = $moduly[intval($value)-1];
                        } else {
                            echo "Unknown module number: $value\n";
                        }
                    } else {
                        if (in_array($value,$moduly)) {
                            $this->modul = $value;
                        } else echo "Unknown module: $value\n";
                    }
                    break;
                case 'create-controller':
                    if ($this->modul == "") {
                        echo "Select module first.\n\nUse:\n  php dotapper.php --modules\n  to list modules.\n\nThen use\n\nphp dotapper.php --module=<name or number> --create-controller=NameOfController\n\nTo create new controller";
                    } else {
                        if ($value != "") $this->createController($value); else echo "Specify controller name ! --create-controller=NAME\n\n";
                    }
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
                default:
                    echo "Unknown option: --$key\n";
                    exit(1);
            }
        }
    }

    private function confirmAction(string $message): bool {
        echo "$message [Y/n]: ";
        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);
        
        return in_array(strtolower($input), ['y', 'yes', '']);
    }

    private function printRoutes($route = null) {
        // Simulácia $_SERVER premenných
        $_SERVER['REQUEST_URI'] = '/'; // Nastav cestu, ktorú chceš simulovať
        $_SERVER['SERVER_NAME'] = 'localhost'; // Nastav názov servera
        $_SERVER['REQUEST_METHOD'] = 'dotapper'; // Nastav metódu požiadavky
        $_SERVER['HTTP_HOST'] = 'localhost'; // Host
        $_SERVER['SCRIPT_NAME'] = '/index.php'; // Skript, ktorý sa spúšťa
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
        // Simulácia $_SERVER premenných
        $_SERVER['REQUEST_URI'] = '/'; // Nastav cestu, ktorú chceš simulovať
        $_SERVER['SERVER_NAME'] = 'localhost'; // Nastav názov servera
        $_SERVER['REQUEST_METHOD'] = 'get'; // Nastav metódu požiadavky
        $_SERVER['HTTP_HOST'] = 'localhost'; // Host
        $_SERVER['SCRIPT_NAME'] = '/index.php'; // Skript, ktorý sa spúšťa
        include("./index.php");
        file_put_contents(__ROOTDIR__ . "/app/modules/modulesAutoLoader.php", "<?php\n\$modules = " . var_export($dotApp->dotapper['optimizeModules'], true) . ";\n ?>");
        echo "Optimized loader ".__ROOTDIR__ . "/app/modules/modulesAutoLoader.php sucesfully created !";
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
     * Spracuje argumenty a uloží ich do $options.
     */
    private function parseArguments() {
        foreach ($this->args as $arg) {
            // Ak je argument --help alebo ?, vypíš help a ukonči
            if ($arg === '--help' || $arg === '?') {
                $this->printHelp();
                exit(0);
            }

            // Skontroluj formát --key=value alebo --key
            if (preg_match('/^--([\w-]+)(?:=(.+))?$/', $arg, $matches)) {
                $key = $matches[1];
                $value = isset($matches[2]) ? $matches[2] : ''; // Ak nie je hodnota, priradí prázdny reťazec
                $this->options[$key] = $value;
            } else {
                echo "Invalid argument format: $arg\n";
                echo "Use: --key=value, --key, --help, or ?\n";
                exit(1);
            }
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
        $this->createDir($modulePath."/translations");
        $this->createDir($modulePath."/views");
        $this->createDir($modulePath."/views/layouts");

        $file_body = base64_decode($this->file_base("/module.init2.php"));
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
        if ($filename=="/module.init.php") return "PD9waHAKCW5hbWVzcGFjZSBEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXCNtb2R1bGVuYW1lOwogICAgdXNlIFxEb3RzeXN0ZW1zXEFwcFxEb3RBcHA7Cgl1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXFJvdXRlcjsKCXVzZSBcRG90c3lzdGVtc1xBcHBcUGFydHNcUm91dGU7Cgl1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXFJlcXVlc3Q7Cgl1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXE1pZGRsZXdhcmU7Cgl1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXFJlc3BvbnNlOwoJdXNlIFxEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xJbnB1dDsKCXVzZSBcRG90c3lzdGVtc1xBcHBcUGFydHNcREI7CiAgICAKCQoKCWNsYXNzIE1vZHVsZSBleHRlbmRzIFxEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xNb2R1bGUgewoJCQoJCXB1YmxpYyBmdW5jdGlvbiBpbml0aWFsaXplKCRkb3RBcHApIHsKCQkJLyoKCQkJCURlZmluZSB5b3VyIHJvdXRlcywgQVBJIHBvaW50cywgYW5kIHNpbWlsYXIgY29uZmlndXJhdGlvbnMgaGVyZS4gRXhhbXBsZXM6CgoJCQkJJGRvdEFwcC0+cm91dGVyLT5hcGlQb2ludCgiMSIsICIjbW9kdWxlbmFtZWxvd2VyIiwgIkFwaVxBcGlAYXBpRGlzcGF0Y2giKTsgLy8gQXV0b21hdGljIGRpc3BhdGNoZXIsIHNlZSBkZXRhaWxzIGluIEFwaS9BcGkucGhwCgoJCQkJRXhhbXBsZSB3aXRob3V0IGF1dG9tYXRpYyBkaXNwYXRjaGluZzoKCQkJCSRkb3RBcHAtPnJvdXRlci0+YXBpUG9pbnQoIjEiLCAiI21vZHVsZW5hbWVsb3dlci91c2VycyIsICJBcGlcVXNlcnNAYXBpIik7CgkJCQlUaGlzIGNhbGxzIHRoZSBgYXBpYCBtZXRob2QgaW4gdGhlIGBVc2Vyc2AgY2xhc3MgbG9jYXRlZCBpbiB0aGUgYEFwaWAgZm9sZGVyLgoJCQkJCgkJCQlFeGFtcGxlIG9mIGNhbGxpbmcgY29udHJvbGxlcnM6CgkJCQkkZG90QXBwLT5yb3V0ZXItPmdldCgiLyNtb2R1bGVuYW1lbG93ZXIvaG9tZSIsICJDb250cm9sbGVyQGhvbWUiKTsKCgkJCQlFeGFtcGxlIHVzaW5nIHRoZSBicmlkZ2U6CgkJCQkkZG90QXBwLT5icmlkZ2UtPmZuKCJuZXdzbGV0dGVyIiwgIkNvbnRyb2xsZXJAbmV3c2xldHRlciIpOwoJCQkqLwoJCQkKICAgICAgICAgICAgLy8gQWRkIHlvdXIgcm91dGVzIGFuZCBsb2dpYyBoZXJlCgkJCQoJCX0KCQkKCQkvKgoJCQlUaGlzIGZ1bmN0aW9uIGRlZmluZXMgdGhlIHNwZWNpZmljIFVSTCByb3V0ZXMgd2hlcmUgdGhlIG1vZHVsZSBzaG91bGQgYmUgaW5pdGlhbGl6ZWQuCgoJCQnwn5SnIEhvdyBpdCB3b3JrczoKCQkJLSBSZXR1cm4gYW4gYXJyYXkgb2Ygcm91dGVzIChlLmcuLCBbIi9ibG9nLyoiLCAiL25ld3Mve2lkOml9Il0pIHRvIHNwZWNpZnkgd2hlcmUgdGhlIG1vZHVsZSBzaG91bGQgaW5pdGlhbGl6ZS4KCQkJLSBSZXR1cm4gWycqJ10gdG8gYWx3YXlzIGluaXRpYWxpemUgdGhlIG1vZHVsZSBvbiBldmVyeSByb3V0ZSAobm90IHJlY29tbWVuZGVkIGluIGxhcmdlIGFwcHMpLgoKCQkJVGhpcyByb3V0aW5nIGxvZ2ljIGlzIHVzZWQgaW50ZXJuYWxseSBieSB0aGUgRG90YXBwZXIgYXV0by1pbml0aWFsaXphdGlvbiBzeXN0ZW0KCQkJdGhyb3VnaCB0aGUgYGF1dG9Jbml0aWFsaXplQ29uZGl0aW9uKClgIG1ldGhvZC4KCgkJCeKchSBSZWNvbW1lbmRlZDogSWYgeW91IGFyZSB1c2luZyBhIGxhcmdlIG51bWJlciBvZiBtb2R1bGVzIGFuZCB3YW50IHRvIG9wdGltaXplIHBlcmZvcm1hbmNlLCAKCQkJYWx3YXlzIHJldHVybiBhbiBhcnJheSBvZiByZWxldmFudCByb3V0ZXMgdG8gYWxsb3cgbG9hZGVyIG9wdGltaXphdGlvbi4KCgkJCUV4YW1wbGU6CgkJCQlyZXR1cm4gWyIvYWRtaW4vKiIsICIvZGFzaGJvYXJkIl07CgoJCQnimqDvuI8gSW1wb3J0YW50OiBJZiB5b3UgYWRkLCByZW1vdmUsIG9yIGNoYW5nZSBhbnkgbW9kdWxlcyBvciB0aGVpciByb3V0ZXMsIGFsd2F5cyByZWdlbmVyYXRlIHRoZSBvcHRpbWl6ZWQgbG9hZGVyOgoJCQkJQ29tbWFuZDogcGhwIGRvdGFwcGVyLnBocCAtLW9wdGltaXplLW1vZHVsZXMKCQkqLwoJCXB1YmxpYyBmdW5jdGlvbiBpbml0aWFsaXplUm91dGVzKCkgewoJCQlyZXR1cm4gWycqJ107IC8vIEFsd2F5cyBtYXRjaGVzIGFsbCBVUkxzLCBidXQgbGVzcyBlZmZpY2llbnQKCQl9CgoJCS8qCgkJCVRoaXMgZnVuY3Rpb24gZGVmaW5lcyBhZGRpdGlvbmFsIGNvbmRpdGlvbnMgZm9yIHdoZXRoZXIgdGhlIG1vZHVsZSBzaG91bGQgaW5pdGlhbGl6ZSwKCQkJKiphZnRlcioqIHJvdXRlIG1hdGNoaW5nIGhhcyBhbHJlYWR5IHN1Y2NlZWRlZC4KCgkJCSRyb3V0ZU1hdGNoIOKAkyBib29sZWFuOiBUUlVFIGlmIHRoZSBjdXJyZW50IHJvdXRlIG1hdGNoZWQgb25lIG9mIHRob3NlIHJldHVybmVkIGZyb20gaW5pdGlhbGl6ZVJvdXRlcygpLCBGQUxTRSBvdGhlcndpc2UuCgoJCQlSZXR1cm4gdmFsdWVzOgoJCQktIHRydWU6IE1vZHVsZSB3aWxsIGJlIGluaXRpYWxpemVkLgoJCQktIGZhbHNlOiBNb2R1bGUgd2lsbCBub3QgYmUgaW5pdGlhbGl6ZWQuCgoJCQlVc2UgdGhpcyBpZiB5b3Ugd2FudCB0byBjaGVjayBsb2dpbiBzdGF0ZSwgdXNlciByb2xlcywgb3IgYW55IG90aGVyIGR5bmFtaWMgY29uZGl0aW9ucy4KCQkJRG8gTk9UIHJldHVybiBhbiBhcnJheSBvciBhbnl0aGluZyBvdGhlciB0aGFuIGEgYm9vbGVhbi4KCgkJCUV4YW1wbGU6CgkJCQlpZiAoISR0aGlzLT5kb3RBcHAtPmF1dGgtPmlzTG9nZ2VkSW4oKSkgcmV0dXJuIGZhbHNlOwoJCQkJcmV0dXJuIHRydWU7CgkJKi8KCQlwdWJsaWMgZnVuY3Rpb24gaW5pdGlhbGl6ZUNvbmRpdGlvbigkcm91dGVNYXRjaCkgewoJCQlyZXR1cm4gJHJvdXRlTWF0Y2g7IC8vIEFsd2F5cyBpbml0aWFsaXplIGlmIHRoZSByb3V0ZSBtYXRjaGVkIChkZWZhdWx0IGJlaGF2aW9yKQoJCX0KCX0KCQoJbmV3IE1vZHVsZSgkZG90QXBwKTsKPz4K";
        if ($filename=="/module.listeners.php") return "PD9waHAKCW5hbWVzcGFjZSBEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXCNtb2R1bGVuYW1lOwoJdXNlIFxEb3RzeXN0ZW1zXEFwcFxEb3RBcHA7Cgl1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXFJvdXRlcjsKCXVzZSBcRG90c3lzdGVtc1xBcHBcUGFydHNcUm91dGU7Cgl1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXFJlcXVlc3Q7Cgl1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXE1pZGRsZXdhcmU7Cgl1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXFJlc3BvbnNlOwoJdXNlIFxEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xJbnB1dDsKCXVzZSBcRG90c3lzdGVtc1xBcHBcUGFydHNcREI7CgoJY2xhc3MgTGlzdGVuZXJzIGV4dGVuZHMgXERvdHN5c3RlbXNcQXBwXFBhcnRzXExpc3RlbmVycyB7CgoJCXB1YmxpYyBmdW5jdGlvbiByZWdpc3RlcigkZG90QXBwKSB7CgkJCQoJCQkvKgoJCQkJVGlwczoKCQkJCQoJCQkJRG8gbm90IGZvcmdldCB0byByZWdpc3RlciB5b3VyIG1pZGRsZXdhcmUgISBGb3IgZXhhbXBsZToKCQkJCU1pZGRsZXdhcmVcTWlkZGxld2FyZTo6cmVnaXN0ZXIoKTsKCQkJCQoJCQkJLy8gQ29uZmlndXJlIHRoZSBtb2R1bGUgdG8gc2VydmUgdGhlIGRlZmF1bHQgIi8iIHJvdXRlIGlmIG5vIG90aGVyIG1vZHVsZSBoYXMgY2xhaW1lZCBpdAoJCQkJLy8gV2FpdCB1bnRpbCBhbGwgbW9kdWxlcyBhcmUgbG9hZGVkLCB0aGVuIGNoZWNrIGlmIHRoZSAiLyIgcm91dGUgaXMgZGVmaW5lZAoJCQkJJGRvdEFwcC0+b24oImRvdGFwcC5tb2R1bGVzLmxvYWRlZCIsIGZ1bmN0aW9uKCRtb2R1bGVPYmopIHVzZSAoJGRvdEFwcCkgewoJCQkJCWlmICghJGRvdEFwcC0+cm91dGVyLT5oYXNSb3V0ZSgiZ2V0IiwgIi8iKSkgewoJCQkJCQkvLyBObyBkZWZhdWx0IHJvdXRlIGlzIGRlZmluZWQsIHNvIHNldCB0aGlzIG1vZHVsZSdzIHJvdXRlIGFzIHRoZSBkZWZhdWx0CgkJCQkJCSRkb3RBcHAtPnJvdXRlci0+Z2V0KCIvIiwgZnVuY3Rpb24oKSB7CgkJCQkJCQloZWFkZXIoIkxvY2F0aW9uOiAvI21vZHVsZW5hbWVsb3dlci8iLCB0cnVlLCAzMDEpOwoJCQkJCQkJZXhpdCgpOwoJCQkJCQl9KTsKCQkJCQl9CgkJCQl9KTsKCQkJKi8KCQkJCgkJCS8vIEFkZCB5b3VyIGN1c3RvbSBsb2dpYyBoZXJlCgkJCQoJCX0KCQkKCX0KCQoJbmV3IExpc3RlbmVycygkZG90QXBwKTsKPz4=";
        if ($filename=="/assets/howtouse.txt") return "IyBIb3cgdG8gVXNlIEFzc2V0cyBpbiBUaGlzIE1vZHVsZQoKQWxsIGZpbGVzIHBsYWNlZCBpbiB0aGlzIGZvbGRlciBhcmUgcHVibGljbHkgYWNjZXNzaWJsZSB2aWEgdGhlIGZvbGxvd2luZyBVUkwgc3RydWN0dXJlOgoKL2Fzc2V0cy9tb2R1bGVzLyNtb2R1bGVuYW1lLwoKRm9yIGV4YW1wbGU6Ci0gSWYgeW91IHBsYWNlIGEgZmlsZSBuYW1lZCBgc2NyaXB0LmpzYCBpbiB0aGUgYGpzYCBzdWJmb2xkZXIsIHlvdSBjYW4gaW5jbHVkZSBpdCBpbiB5b3VyIEhUTUwgbGlrZSB0aGlzOgogIGBgYGh0bWwKICA8c2NyaXB0IHNyYz0iL2Fzc2V0cy9tb2R1bGVzLyNtb2R1bGVuYW1lL2pzL3NjcmlwdC5qcyI+PC9zY3JpcHQ+CiAgYGBgCgotIElmIHlvdSBhZGQgYSBmaWxlIG5hbWVkIGBzdHlsZXMuY3NzYCBpbiB0aGUgYGNzc2Agc3ViZm9sZGVyLCB5b3UgY2FuIGxpbmsgaXQgbGlrZSB0aGlzOgogIGBgYGh0bWwKICA8bGluayByZWw9InN0eWxlc2hlZXQiIGhyZWY9Ii9hc3NldHMvbW9kdWxlcy8jbW9kdWxlbmFtZS9jc3Mvc3R5bGVzLmNzcyI+CiAgYGBgCgotIElmIHlvdSBpbmNsdWRlIGFuIGltYWdlIG5hbWVkIGBiYW5uZXIuanBnYCBpbiB0aGUgYGltYWdlc2Agc3ViZm9sZGVyLCB5b3UgY2FuIHVzZSBpdCBhcyBmb2xsb3dzOgogIGBgYGh0bWwKICA8aW1nIHNyYz0iL2Fzc2V0cy9tb2R1bGVzLyNtb2R1bGVuYW1lL2ltYWdlcy9iYW5uZXIuanBnIiBhbHQ9IkJhbm5lciI+CiAgYGBgCgotIElmIHlvdSBwbGFjZSBhIGZvbnQgZmlsZSBuYW1lZCBgbXlmb250LndvZmYyYCBpbiB0aGUgYGZvbnRzYCBzdWJmb2xkZXIsIHlvdSBjYW4gcmVmZXJlbmNlIGl0IGluIHlvdXIgQ1NTIGxpa2UgdGhpczoKICBgYGBodG1sCiAgPHN0eWxlPgogICAgQGZvbnQtZmFjZSB7CiAgICAgIGZvbnQtZmFtaWx5OiAnTXlGb250JzsKICAgICAgc3JjOiB1cmwoJy9hc3NldHMvbW9kdWxlcy8jbW9kdWxlbmFtZS9mb250cy9teWZvbnQud29mZjInKSBmb3JtYXQoJ3dvZmYyJyk7CiAgICB9CiAgPC9zdHlsZT4KICBgYGA=";
        if ($filename=="/Api/Api.php") return "PD9waHAJCgluYW1lc3BhY2UgRG90c3lzdGVtc1xBcHBcTW9kdWxlc1wjbW9kdWxlbmFtZVxBcGk7Cgl1c2UgRG90c3lzdGVtc1xBcHBcRG90QXBwOwoJCgljbGFzcyBBcGkgZXh0ZW5kcyBcRG90c3lzdGVtc1xBcHBcUGFydHNcQ29udHJvbGxlciB7CgkJCgkJLyoKCQkJSWYgeW91IHVzZSB0aGUgYXV0b21hdGljIHJvdXRlciBkaXNwYXRjaGVyIGluIHRoZSBjb250cm9sbGVyIChlLmcuLCBpbiBtb2R1bGUuaW5pdC5waHApIHdpdGg6CgkJCSRkb3RBcHAtPnJvdXRlci0+YXBpUG9pbnQoIjEiLCAiI21vZHVsZW5hbWVsb3dlciIsICJEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXCNtb2R1bGVuYW1lXEFwaVxBcGlAYXBpRGlzcGF0Y2giKTsKCQkJCgkJCVRoZSBmb2xsb3dpbmcgcm91dGVzIHdpbGwgYmUgY3JlYXRlZDoKCQkJLSBHRVQgL2FwaS92MS8jbW9kdWxlbmFtZWxvd2VyL3Rlc3QgLSBDYWxscyB0aGUgZ2V0VGVzdCBtZXRob2QuCgkJCS0gUE9TVCAvYXBpL3YxLyNtb2R1bGVuYW1lbG93ZXIvdGVzdCAtIENhbGxzIHRoZSBwb3N0VGVzdCBtZXRob2QuCgoJCQlEZXBlbmRlbmN5IGluamVjdGlvbiBpcyBzdXBwb3J0ZWQgYnkgZGVmYXVsdC4gRXhhbXBsZSB3aXRoIERvdEFwcCBpbmplY3Rpb246CgkJCQoJCQlwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIGdldFRlc3QoJHJlcXVlc3QsIERvdEFwcCAkZG90QXBwKSB7CgkJCQkvLyBIYW5kbGVzIEdFVCAvYXBpL3YxLyNtb2R1bGVuYW1lbG93ZXIvdGVzdAoJCQl9CgkJCQoJCQlwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIHBvc3RUZXN0KCRyZXF1ZXN0LCBEb3RBcHAgJGRvdEFwcCkgewoJCQkJLy8gSGFuZGxlcyBQT1NUIC9hcGkvdjEvI21vZHVsZW5hbWVsb3dlci90ZXN0CgkJCX0KCQkqLwkJCgkJCQkKCX0KPz4=";
        if ($filename=="/Controllers/Controller.php") return "PD9waHAJCiAgICBuYW1lc3BhY2UgRG90c3lzdGVtc1xBcHBcTW9kdWxlc1wjbW9kdWxlbmFtZVxDb250cm9sbGVyczsKICAgIHVzZSBEb3RzeXN0ZW1zXEFwcFxEb3RBcHA7Cgl1c2UgRG90c3lzdGVtc1xBcHBcUGFydHNcTWlkZGxld2FyZTsKCXVzZSBEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xSZXNwb25zZTsKICAgIAogICAgY2xhc3MgQ29udHJvbGxlciBleHRlbmRzIFxEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xDb250cm9sbGVyIHsKICAgICAgICAKICAgICAgICAvKgogICAgICAgICAgICAvLyBFeGFtcGxlIHdpdGggZGVwZW5kZW5jeSBpbmplY3Rpb24gCiAgICAgICAgICAgIHB1YmxpYyBzdGF0aWMgZnVuY3Rpb24gaG9tZSgkcmVxdWVzdCwgRG90QXBwICRkb3RBcHApIHsKICAgICAgICAgICAgICAgIC8vIEhhbmRsZXMgR0VUIC9hcGkvdjEvI21vZHVsZW5hbWVsb3dlci90ZXN0CiAgICAgICAgICAgIH0KICAgICAgICAgICAgCiAgICAgICAgICAgIC8vIERvdEFwcCBpcyBhdmFpbGFibGUgaW4gdGhlIHJlcXVlc3QgZXZlbiB3aXRob3V0IERJCiAgICAgICAgICAgIHB1YmxpYyBzdGF0aWMgZnVuY3Rpb24gaG9tZSgkcmVxdWVzdCkgewogICAgICAgICAgICAgICAgJGRvdEFwcCA9ICRyZXF1ZXN0LT5kb3RBcHA7CiAgICAgICAgICAgICAgICAkdmlld1ZhcnNbJ3NlbyddWydkZXNjcmlwdGlvbiddID0gIlRoaXMgaXMgYSBob21lIGV4YW1wbGUgcGFnZSBmb3IgdGhlIEV4YW1wbGUgUEhQIGZyYW1ld29yay4iOwogICAgICAgICAgICAgICAgJHZpZXdWYXJzWydzZW8nXVsna2V5d29yZHMnXSA9ICJleGFtcGxlLCBQSFAgZnJhbWV3b3JrLCBob21lLCBkZW1vIjsKICAgICAgICAgICAgICAgICR2aWV3VmFyc1snc2VvJ11bJ3RpdGxlJ10gPSAiSG9tZSAtIEV4YW1wbGUgUEhQIEZyYW1ld29yayI7CgogICAgICAgICAgICAgICAgcmV0dXJuICRkb3RBcHAtPnJvdXRlci0+cmVuZGVyZXItPm1vZHVsZSgiI21vZHVsZW5hbWUiKS0+c2V0VmlldygiaG9tZSIpLT5zZXRWaWV3VmFyKCJ2YXJpYWJsZXMiLCAkdmlld1ZhcnMpLT5yZW5kZXJWaWV3KCk7CiAgICAgICAgICAgIH0KICAgICAgICAqLwkJCiAgICAgICAgICAgICAgICAKICAgIH0KPz4=";
        if ($filename=="/Middleware/Middleware.php") return "PD9waHAJCm5hbWVzcGFjZSBEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXCNtb2R1bGVuYW1lXE1pZGRsZXdhcmU7Cgp1c2UgRG90c3lzdGVtc1xBcHBcRG90QXBwOwp1c2UgRG90c3lzdGVtc1xBcHBcUGFydHNcTWlkZGxld2FyZTsKdXNlIERvdHN5c3RlbXNcQXBwXFBhcnRzXFJlc3BvbnNlOwoKY2xhc3MgTWlkZGxld2FyZSBleHRlbmRzIFxEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xNb2R1bGVNaWRkbGV3YXJlIHsKCglwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIHJlZ2lzdGVyKCkgewoJCS8qCgkJc2VsZjo6bWlkZGxld2FyZSgibmFtZSIsIGNhbGxiYWNrKTsgaXMgZXF1aXZhbGVudCB0byBuZXcgTWlkZGxld2FyZSgibmFtZSIsIGNhbGxiYWNrKTsKCQkoIHNlbGY6Om1pZGRsZXdhcmUoIm5hbWUiKSB3aXRob3V0IHRoZSBjYWxsYmFjayBhY3RzIGFzIGEgZ2V0dGVyICkgCgkJCgkJVGhlIGNhbGxiYWNrIGNhbiBiZToKCQktIGFuIGFub255bW91cyBmdW5jdGlvbiwKCQktIGFub3RoZXIgbWlkZGxld2FyZSwKCQktIGEgY29udHJvbGxlciBjYWxsIGluIHRoZSBmb3JtICJtb2R1bGU6Q29udHJvbGxlckBmdW5jdGlvbiIKCiAgICAgICAgRXhhbXBsZSB1c2FnZToKICAgICAgICAKICAgICAgICBzZWxmOjptaWRkbGV3YXJlKCJuYW1lT2ZNaWRkbGV3YXJlIiwgZnVuY3Rpb24oJHJlcXVlc3QsICRuZXh0KSB7CiAgICAgICAgICAgIC8vIFlvdXIgY3VzdG9tIGxvZ2ljIGhlcmUuLi4KCiAgICAgICAgICAgIC8vIElmIHNvbWV0aGluZyBpcyB3cm9uZyDigJMgc3RvcCB0aGUgcGlwZWxpbmUgYW5kIHJldHVybiBhIHJlc3BvbnNlCiAgICAgICAgICAgIHJldHVybiBuZXcgUmVzcG9uc2UoNDAzLCAiWW91IG11c3QgYmUgbG9nZ2VkIGluISIpOwoKICAgICAgICAgICAgLy8gSWYgZXZlcnl0aGluZyBpcyBPSyDigJMgY29udGludWUgdGhlIHBpcGVsaW5lCiAgICAgICAgICAgIHJldHVybiAkbmV4dCgkcmVxdWVzdCk7CiAgICAgICAgfSk7CgogICAgICAgIHNlbGY6Om1pZGRsZXdhcmUoIm5hbWVPZk1pZGRsZXdhcmUiLCBmdW5jdGlvbigkcmVxdWVzdCwgJG5leHQpIHsKICAgICAgICAgICAgLy8gWW91ciBjdXN0b20gbG9naWMgaGVyZS4uLgoKICAgICAgICAgICAgLy8gU3RvcCB0aGUgcGlwZWxpbmUgYW5kIHJldHVybiBhIGRldGFpbGVkIHJlc3BvbnNlIHVzaW5nIGFuIGFycmF5CiAgICAgICAgICAgICRyZXNwb25zZSA9IFtdOwogICAgICAgICAgICAkcmVzcG9uc2VbJ2JvZHknXSA9ICJZb3UgbXVzdCBiZSBsb2dnZWQgaW4hIjsKICAgICAgICAgICAgJHJlc3BvbnNlWydjb250ZW50VHlwZSddID0gInRleHQvaHRtbCI7CiAgICAgICAgICAgICRyZXNwb25zZVsnaGVhZGVycyddID0gWyJDb250ZW50LVR5cGUiID0+ICJ0ZXh0L2h0bWwiXTsKICAgICAgICAgICAgcmV0dXJuIG5ldyBSZXNwb25zZSg0MDMsICRyZXNwb25zZSk7CgogICAgICAgICAgICAvLyBDb250aW51ZSB0aGUgcGlwZWxpbmUKICAgICAgICAgICAgcmV0dXJuICRuZXh0KCRyZXF1ZXN0KTsKICAgICAgICB9KTsKCiAgICAgICAgbmV3IE1pZGRsZXdhcmUoIm5hbWVPZk1pZGRsZXdhcmUyIiwgZnVuY3Rpb24oJHJlcXVlc3QsICRuZXh0KSB7CiAgICAgICAgICAgIC8vIFlvdXIgY3VzdG9tIGxvZ2ljIGhlcmUuLi4KCiAgICAgICAgICAgIC8vIFN0b3AgdGhlIHBpcGVsaW5lIHdpdGggYW4gYXJyYXktYmFzZWQgcmVzcG9uc2UKICAgICAgICAgICAgJHJlc3BvbnNlID0gW107CiAgICAgICAgICAgICRyZXNwb25zZVsnYm9keSddID0gIllvdSBtdXN0IGJlIGxvZ2dlZCBpbiEiOwogICAgICAgICAgICAkcmVzcG9uc2VbJ2NvbnRlbnRUeXBlJ10gPSAidGV4dC9odG1sIjsKICAgICAgICAgICAgJHJlc3BvbnNlWydoZWFkZXJzJ10gPSBbIkNvbnRlbnQtVHlwZSIgPT4gInRleHQvaHRtbCJdOwogICAgICAgICAgICByZXR1cm4gbmV3IFJlc3BvbnNlKDQwMywgJHJlc3BvbnNlKTsKCiAgICAgICAgICAgIC8vIENvbnRpbnVlIHRoZSBwaXBlbGluZQogICAgICAgICAgICByZXR1cm4gJG5leHQoJHJlcXVlc3QpOwogICAgICAgIH0pOwogICAgICAgICovCgl9Cn0KPz4K";
        if ($filename=="/views/clean.view.php") return base64_encode("{{content}}");
        if ($filename=="/views/layouts/example.layout.php") return "PCEtLSBFeGFtcGxlIG9mIGxheW91dCAtLT4KPHA+UHJpbnQgdmFyaWJhbGUgdmFsdWUgaW4gbW9kdWxlICNtb2R1bGVuYW1lPC9wPgo8cD4KCXt7IHZhcjogJHZhcmlhYmxlc1snYXJ0aWNsZSddWydhcnRpY2xlJ10gfX0KPC9wPgo=";
        if ($filename=="/.htaccess") return "IyBOYXN0YXZlbmllIGtvZG92YW5pYSBhIGphenlrYQpBZGREZWZhdWx0Q2hhcnNldCBVVEYtOApEZWZhdWx0TGFuZ3VhZ2Ugc2sKCiMgUHJpZGF0IGhsYXZpY2t5IHByZSBkb3RhcHAKPElmTW9kdWxlIG1vZF9oZWFkZXJzLmM+CiAgICBIZWFkZXIgYWx3YXlzIHNldCBYLVBvd2VyZWQtQnkgImRvdGFwcDsgd3d3LmRvdHN5c3RlbXMuc2siCiAgICBIZWFkZXIgYWx3YXlzIHNldCBYLUZyYW1ld29yayAiZG90YXBwIgo8L0lmTW9kdWxlPgoKIyBLb21wcmVzaWEgc3Vib3JvdiAtIG1vZF9kZWZsYXRlIChub3ZzaSBzcG9zb2IpCjxJZk1vZHVsZSBtb2RfZGVmbGF0ZS5jPgogICAgU2V0T3V0cHV0RmlsdGVyIERFRkxBVEUKICAgIEFkZE91dHB1dEZpbHRlckJ5VHlwZSBERUZMQVRFIHRleHQvaHRtbCB0ZXh0L3BsYWluIHRleHQveG1sIHRleHQvY3NzIHRleHQvamF2YXNjcmlwdAogICAgQWRkT3V0cHV0RmlsdGVyQnlUeXBlIERFRkxBVEUgYXBwbGljYXRpb24vamF2YXNjcmlwdCBhcHBsaWNhdGlvbi94LWphdmFzY3JpcHQKICAgIEJyb3dzZXJNYXRjaCBeTW96aWxsYS80IGd6aXAtb25seS10ZXh0L2h0bWwKICAgIEJyb3dzZXJNYXRjaCBeTW96aWxsYS80XC4wWzY3OF0gbm8tZ3ppcAogICAgQnJvd3Nlck1hdGNoIFxiTVNJRSAhbm8tZ3ppcCAhZ3ppcC1vbmx5LXRleHQvaHRtbAo8L0lmTW9kdWxlPgoKIyBLb21wcmVzaWEgc3Vib3JvdiAtIG1vZF9nemlwIChzdGFyc2lhIHZlcnppYSBhayBuZW5pIGRlZmxhdGUpCjxJZk1vZHVsZSAhbW9kX2RlZmxhdGUuYz4KCTxJZk1vZHVsZSBtb2RfZ3ppcC5jPgoJCW1vZF9nemlwX29uIFllcwoJCW1vZF9nemlwX2RlY2h1bmsgWWVzCgkJbW9kX2d6aXBfaXRlbV9pbmNsdWRlIGZpbGUgXC4oaHRtbD98dHh0fGNzc3xqc3xwaHB8cGwpJAoJCW1vZF9nemlwX2l0ZW1faW5jbHVkZSBoYW5kbGVyIF5jZ2ktc2NyaXB0JAoJCW1vZF9nemlwX2l0ZW1faW5jbHVkZSBtaW1lIF50ZXh0Ly4qCgkJbW9kX2d6aXBfaXRlbV9pbmNsdWRlIG1pbWUgXmFwcGxpY2F0aW9uL3gtamF2YXNjcmlwdC4qCgkJbW9kX2d6aXBfaXRlbV9leGNsdWRlIG1pbWUgXmltYWdlLy4qCgkJbW9kX2d6aXBfaXRlbV9leGNsdWRlIHJzcGhlYWRlciBeQ29udGVudC1FbmNvZGluZzouKmd6aXAuKgoJPC9JZk1vZHVsZT4KPC9JZk1vZHVsZT4KCiMgUG92b2xpdCBwcmlzdHUga3UgdnNldGtlbXUgLSBub3ZzaSBhcGFjaGUKPElmTW9kdWxlIG1vZF9hdXRoel9ob3N0LmM+CiAgICBSZXF1aXJlIGFsbCBncmFudGVkCjwvSWZNb2R1bGU+CgojIFBvdm9saXQgcHJpc3R1IC0gc3RhcnNpIGFwYWNoZQo8SWZNb2R1bGUgIW1vZF9hdXRoel9ob3N0LmM+CiAgICBPcmRlciBBbGxvdyxEZW55CiAgICBBbGxvdyBmcm9tIGFsbAo8L0lmTW9kdWxlPgoKIyBOYXN0YXZlbmllIHR5cG92IHN1Ym9yb3YKQWRkVHlwZSBmb250L3dvZmYgLndvZmYKQWRkVHlwZSBhcHBsaWNhdGlvbi9mb250LXdvZmYyIC53b2ZmMgpBZGRUeXBlIGFwcGxpY2F0aW9uL2phdmFzY3JpcHQgLmpzCkFkZFR5cGUgdGV4dC9jc3MgLmNzcwoKIyBaYXBudXQgcHJlcGlzb3ZhbmllIHVybApSZXdyaXRlRW5naW5lIE9uClJld3JpdGVCYXNlIC8KCiMgWmFibG9rb3ZhdCBwcmlzdHUgayBkb3RhcHBlcnUKUmV3cml0ZUNvbmQgJXtSRVFVRVNUX1VSSX0gXi9kb3RhcHBlciQKUmV3cml0ZVJ1bGUgXiAtIFtGLExdCgojIFByZXNrb2NpdCBwcmVwaXMgcHJlIHNwZWNpZmlja2Ugc3Vib3J5ClJld3JpdGVSdWxlIF4oc2l0ZW1hcFwueG1sfHJvYm90c1wudHh0KSQgLSBbTkMsTF0KCiMgWmFibG9rb3ZhdCAvYXBwLyBva3JlbSBhc3NldHMgdiBtb2R1bG9jaApSZXdyaXRlQ29uZCAle1JFUVVFU1RfVVJJfSAhXi9hcHAvbW9kdWxlcy8oW14vXSspL2Fzc2V0cy8KUmV3cml0ZVJ1bGUgXmFwcCgvfCQpIC0gW0YsTF0KCiMgQWsgc3Vib3IgdiAvYXNzZXRzL21vZHVsZXMvIG5lZXhpc3R1amUsIHNrdXMgaG8gbmFjaXRhdCB6IC9hcHAvbW9kdWxlcy8KUmV3cml0ZUNvbmQgJXtSRVFVRVNUX0ZJTEVOQU1FfSAhLWYKUmV3cml0ZUNvbmQgJXtSRVFVRVNUX0ZJTEVOQU1FfSAhLWQKUmV3cml0ZVJ1bGUgXmFzc2V0cy9tb2R1bGVzLyhbXi9dKykvKC4qKSQgL2FwcC9tb2R1bGVzLyQxL2Fzc2V0cy8kMiBbTF0KCiMgQWsgc3Vib3IgdiAvYXNzZXRzLyBleGlzdHVqZSwgbmVwcmVwaXN1agpSZXdyaXRlQ29uZCAle1JFUVVFU1RfRklMRU5BTUV9IC1mClJld3JpdGVSdWxlIF5hc3NldHMvLiokIC0gW05DLExdCgojIFNwZWNpYWxuZSBzcHJhY292YW5pZSBwcmUgZG90YXBwLmpzClJld3JpdGVDb25kICV7UkVRVUVTVF9GSUxFTkFNRX0gIS1mClJld3JpdGVSdWxlIF5hc3NldHMvZG90YXBwL2RvdGFwcFwuanMkIGluZGV4LnBocCBbTkMsTF0KCiMgTmVwcmVwaXNvdmF0IG9icmF6a3kKUmV3cml0ZVJ1bGUgXC4oaWNvfHBuZ3xqcGU/Z3xnaWZ8c3ZnfHdlYnB8Ym1wKSQgLSBbTkMsTF0KCiMgTmVwcmVwaXNvdmF0IGRvdGFwcGVyIHBvemlhZGF2a3kKUmV3cml0ZUNvbmQgJXtSRVFVRVNUX1VSSX0gIV4vZG90YXBwZXIkCgojIFZzZXRreSBvc3RhdG5lIHBvemlhZGF2a3kgaWR1IG5hIGluZGV4LnBocCwgb2tyZW0gdHljaCBjbyB1eiBib2xpIHNwcmFjb3ZhbmUKUmV3cml0ZUNvbmQgJXtSRVFVRVNUX1VSSX0gIV4vYXBwL21vZHVsZXMvKFteL10rKS9hc3NldHMvClJld3JpdGVSdWxlIF4uKiQgaW5kZXgucGhwIFtOQyxMXQ==";
        if ($filename=="/module.init2.php") return "PD9waHAKCW5hbWVzcGFjZSBEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXCNtb2R1bGVuYW1lOwogICAgdXNlIFxEb3RzeXN0ZW1zXEFwcFxEb3RBcHA7CiAgICB1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXE1pZGRsZXdhcmU7Cgl1c2UgXERvdHN5c3RlbXNcQXBwXFBhcnRzXFJlc3BvbnNlOwoKCWNsYXNzIE1vZHVsZSBleHRlbmRzIFxEb3RzeXN0ZW1zXEFwcFxQYXJ0c1xNb2R1bGUgewoJCQoJCXB1YmxpYyBmdW5jdGlvbiBpbml0aWFsaXplKCRkb3RBcHApIHsKCQkJLyoKCQkJCURlZmluZSB5b3VyIHJvdXRlcywgQVBJIHBvaW50cywgYW5kIHNpbWlsYXIgY29uZmlndXJhdGlvbnMgaGVyZS4gRXhhbXBsZXM6CgoJCQkJJGRvdEFwcC0+cm91dGVyLT5hcGlQb2ludCgiMSIsICIjbW9kdWxlbmFtZWxvd2VyIiwgIkFwaVxBcGlAYXBpRGlzcGF0Y2giKTsgLy8gQXV0b21hdGljIGRpc3BhdGNoZXIsIHNlZSBkZXRhaWxzIGluIEFwaS9BcGkucGhwCgoJCQkJRXhhbXBsZSB3aXRob3V0IGF1dG9tYXRpYyBkaXNwYXRjaGluZzoKCQkJCSRkb3RBcHAtPnJvdXRlci0+YXBpUG9pbnQoIjEiLCAiI21vZHVsZW5hbWVsb3dlci91c2VycyIsICJBcGlcVXNlcnNAYXBpIik7CgkJCQlUaGlzIGNhbGxzIHRoZSBgYXBpYCBtZXRob2QgaW4gdGhlIGBVc2Vyc2AgY2xhc3MgbG9jYXRlZCBpbiB0aGUgYEFwaWAgZm9sZGVyLgoJCQkJCgkJCQlFeGFtcGxlIG9mIGNhbGxpbmcgY29udHJvbGxlcnM6CgkJCQkkZG90QXBwLT5yb3V0ZXItPmdldCgiLyNtb2R1bGVuYW1lbG93ZXIvaG9tZSIsICJDb250cm9sbGVyQGhvbWUiKTsKCgkJCQlFeGFtcGxlIHVzaW5nIHRoZSBicmlkZ2U6CgkJCQkkZG90QXBwLT5icmlkZ2UtPmZuKCJuZXdzbGV0dGVyIiwgIkNvbnRyb2xsZXJAbmV3c2xldHRlciIpOwoJCQkqLwoJCQkKICAgICAgICAgICAgLy8gQWRkIHlvdXIgcm91dGVzIGFuZCBsb2dpYyBoZXJlCgkJCQoJCX0KCQkKCQkvKgoJCQlUaGlzIGZ1bmN0aW9uIGRlZmluZXMgdGhlIHNwZWNpZmljIFVSTCByb3V0ZXMgd2hlcmUgdGhlIG1vZHVsZSBzaG91bGQgYmUgaW5pdGlhbGl6ZWQuCgoJCQnwn5SnIEhvdyBpdCB3b3JrczoKCQkJLSBSZXR1cm4gYW4gYXJyYXkgb2Ygcm91dGVzIChlLmcuLCBbIi9ibG9nLyoiLCAiL25ld3Mve2lkOml9Il0pIHRvIHNwZWNpZnkgd2hlcmUgdGhlIG1vZHVsZSBzaG91bGQgaW5pdGlhbGl6ZS4KCQkJLSBSZXR1cm4gWycqJ10gdG8gYWx3YXlzIGluaXRpYWxpemUgdGhlIG1vZHVsZSBvbiBldmVyeSByb3V0ZSAobm90IHJlY29tbWVuZGVkIGluIGxhcmdlIGFwcHMpLgoKCQkJVGhpcyByb3V0aW5nIGxvZ2ljIGlzIHVzZWQgaW50ZXJuYWxseSBieSB0aGUgRG90YXBwZXIgYXV0by1pbml0aWFsaXphdGlvbiBzeXN0ZW0KCQkJdGhyb3VnaCB0aGUgYGF1dG9Jbml0aWFsaXplQ29uZGl0aW9uKClgIG1ldGhvZC4KCgkJCeKchSBSZWNvbW1lbmRlZDogSWYgeW91IGFyZSB1c2luZyBhIGxhcmdlIG51bWJlciBvZiBtb2R1bGVzIGFuZCB3YW50IHRvIG9wdGltaXplIHBlcmZvcm1hbmNlLCAKCQkJYWx3YXlzIHJldHVybiBhbiBhcnJheSBvZiByZWxldmFudCByb3V0ZXMgdG8gYWxsb3cgbG9hZGVyIG9wdGltaXphdGlvbi4KCgkJCUV4YW1wbGU6CgkJCQlyZXR1cm4gWyIvYWRtaW4vKiIsICIvZGFzaGJvYXJkIl07CgoJCQnimqDvuI8gSW1wb3J0YW50OiBJZiB5b3UgYWRkLCByZW1vdmUsIG9yIGNoYW5nZSBhbnkgbW9kdWxlcyBvciB0aGVpciByb3V0ZXMsIGFsd2F5cyByZWdlbmVyYXRlIHRoZSBvcHRpbWl6ZWQgbG9hZGVyOgoJCQkJQ29tbWFuZDogcGhwIGRvdGFwcGVyLnBocCAtLW9wdGltaXplLW1vZHVsZXMKCQkqLwoJCXB1YmxpYyBmdW5jdGlvbiBpbml0aWFsaXplUm91dGVzKCkgewoJCQlyZXR1cm4gWycvZG9jdW1lbnRhdGlvbi9pbnRyby8jbW9kdWxlbnVtYmVyJ107IC8vIEFsd2F5cyBtYXRjaGVzIGFsbCBVUkxzLCBidXQgbGVzcyBlZmZpY2llbnQKCQl9CgoJCS8qCgkJCVRoaXMgZnVuY3Rpb24gZGVmaW5lcyBhZGRpdGlvbmFsIGNvbmRpdGlvbnMgZm9yIHdoZXRoZXIgdGhlIG1vZHVsZSBzaG91bGQgaW5pdGlhbGl6ZSwKCQkJKiphZnRlcioqIHJvdXRlIG1hdGNoaW5nIGhhcyBhbHJlYWR5IHN1Y2NlZWRlZC4KCgkJCSRyb3V0ZU1hdGNoIOKAkyBib29sZWFuOiBUUlVFIGlmIHRoZSBjdXJyZW50IHJvdXRlIG1hdGNoZWQgb25lIG9mIHRob3NlIHJldHVybmVkIGZyb20gaW5pdGlhbGl6ZVJvdXRlcygpLCBGQUxTRSBvdGhlcndpc2UuCgoJCQlSZXR1cm4gdmFsdWVzOgoJCQktIHRydWU6IE1vZHVsZSB3aWxsIGJlIGluaXRpYWxpemVkLgoJCQktIGZhbHNlOiBNb2R1bGUgd2lsbCBub3QgYmUgaW5pdGlhbGl6ZWQuCgoJCQlVc2UgdGhpcyBpZiB5b3Ugd2FudCB0byBjaGVjayBsb2dpbiBzdGF0ZSwgdXNlciByb2xlcywgb3IgYW55IG90aGVyIGR5bmFtaWMgY29uZGl0aW9ucy4KCQkJRG8gTk9UIHJldHVybiBhbiBhcnJheSBvciBhbnl0aGluZyBvdGhlciB0aGFuIGEgYm9vbGVhbi4KCgkJCUV4YW1wbGU6CgkJCQlpZiAoISR0aGlzLT5kb3RBcHAtPmF1dGgtPmlzTG9nZ2VkSW4oKSkgcmV0dXJuIGZhbHNlOwoJCQkJcmV0dXJuIHRydWU7CgkJKi8KCQlwdWJsaWMgZnVuY3Rpb24gaW5pdGlhbGl6ZUNvbmRpdGlvbigkcm91dGVNYXRjaCkgewoJCQlyZXR1cm4gJHJvdXRlTWF0Y2g7IC8vIEFsd2F5cyBpbml0aWFsaXplIGlmIHRoZSByb3V0ZSBtYXRjaGVkIChkZWZhdWx0IGJlaGF2aW9yKQoJCX0KCX0KCQoJbmV3IE1vZHVsZSgkZG90QXBwKTsKPz4K";
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
     * Vypíše help správu.
     */
    private function printHelp() {
        $this->clrScr();
        $this->versionPrint();
        echo $this->bgColorText("green",$this->colorText("bold_white","Usage: php dotapper.php [options]\n"));
        echo "Options:\n";
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

    private function colorText(string $color, string $text): string {
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
        ];

        $code = $colors[strtolower($color)] ?? '0';
        // Odstránime koncový reset, aby sme mohli pridať nové štýly
        $text = rtrim($text, "\033[0m");
        return "\033[" . $code . "m" . $text . "\033[0m";
    }

    private function bgColorText(string $bgColor, string $text): string {
        $bgColors = [
            'black' => '40',
            'red' => '41',
            'green' => '42',
            'yellow' => '43',
            'blue' => '44',
            'magenta' => '45',
            'cyan' => '46',
            'white' => '47',
        ];

        $code = $bgColors[strtolower($bgColor)] ?? '0';

        // Rozdelíme text na segmenty podľa ANSI kódov
        $pattern = '/(\033\[(?:[0-9;]*m))/';
        $segments = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $result = '';
        $currentStyles = [];

        foreach ($segments as $segment) {
            if (preg_match('/^\033\[([0-9;]*m)$/', $segment, $matches)) {
                // Segment je ANSI kód
                $codes = explode(';', rtrim($matches[1], 'm'));
                $currentStyles = array_filter($codes, function ($c) {
                    return $c !== '0'; // Odstránime reset
                });
                $result .= $segment;
            } else {
                // Segment je text
                $hasBg = false;
                foreach ($currentStyles as $style) {
                    if ($style >= 40 && $style <= 47) {
                        $hasBg = true;
                        break;
                    }
                }
                // Ak segment nemá pozadie, pridáme nové
                if (!$hasBg) {
                    $result .= "\033[{$code}m" . $segment . "\033[0m";
                    // Po pridaní pozadia obnovíme pôvodné štýly (okrem pozadia)
                    $nonBgStyles = array_filter($currentStyles, function ($c) {
                        return $c < 40 || $c > 47;
                    });
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