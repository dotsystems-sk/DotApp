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
    public function __construct(array $args)
    {
        $this->args = $args;
        $this->options = []; // Inicializácia $options v konštruktore
        $this->parseArguments();
    }

    /**
     * Spustí hlavnú logiku skriptu.
     */
    public function run(): void
    {
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
                case 'list-routes':
                    $this->printRoutes();
                    break;
                case 'list-modules':
                    $this->printModules($this->listModules());
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
                default:
                    echo "Unknown option: --$key\n";
                    exit(1);
            }
        }
    }

    private function printRoutes() {
        // Simulácia $_SERVER premenných
        $_SERVER['REQUEST_URI'] = '/'; // Nastav cestu, ktorú chceš simulovať
        $_SERVER['SERVER_NAME'] = 'localhost'; // Nastav názov servera
        $_SERVER['REQUEST_METHOD'] = 'get'; // Nastav metódu požiadavky
        $_SERVER['HTTP_HOST'] = 'localhost'; // Host
        $_SERVER['SCRIPT_NAME'] = '/index.php'; // Skript, ktorý sa spúšťa
        include("./index.php");
        $dotApp->dotapper = $dotApp->dotapper;
        print_r($dotApp->dotapper['routes']);
    }

    /**
     * Spracuje argumenty a uloží ich do $options.
     */
    private function parseArguments(): void
    {
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

    /**
     * Vytvorí nový modul s daným názvom.
     *
     * @param string $moduleName Názov modulu
     */
    private function createModule(string $moduleName): void
    {
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
        if ($filename=="/module.init.php") return "PD9waHAKCW5hbWVzcGFjZSBEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXCNtb2R1bGVuYW1lOwogICAgdXNlIERvdHN5c3RlbXNcQXBwXERvdEFwcDsKCgljbGFzcyBNb2R1bGUgZXh0ZW5kcyBcRG90c3lzdGVtc1xBcHBcUGFydHNcTW9kdWxlIHsKCQkKCQlwdWJsaWMgZnVuY3Rpb24gaW5pdGlhbGl6ZSgkZG90QXBwKSB7CgkJCS8qCgkJCQlEZWZpbmUgeW91ciByb3V0ZXMsIEFQSSBwb2ludHMsIGFuZCBzaW1pbGFyIGNvbmZpZ3VyYXRpb25zIGhlcmUuIEV4YW1wbGVzOgoKCQkJCSRkb3RBcHAtPnJvdXRlci0+YXBpUG9pbnQoIjEiLCAiI21vZHVsZW5hbWVsb3dlciIsICJBcGlcQXBpQGFwaURpc3BhdGNoIik7IC8vIEF1dG9tYXRpYyBkaXNwYXRjaGVyLCBzZWUgZGV0YWlscyBpbiBBcGkvQXBpLnBocAoKCQkJCUV4YW1wbGUgd2l0aG91dCBhdXRvbWF0aWMgZGlzcGF0Y2hpbmc6CgkJCQkkZG90QXBwLT5yb3V0ZXItPmFwaVBvaW50KCIxIiwgIiNtb2R1bGVuYW1lbG93ZXIvdXNlcnMiLCAiQXBpXFVzZXJzQGFwaSIpOwoJCQkJVGhpcyBjYWxscyB0aGUgYGFwaWAgbWV0aG9kIGluIHRoZSBgVXNlcnNgIGNsYXNzIGxvY2F0ZWQgaW4gdGhlIGBBcGlgIGZvbGRlci4KCQkJCQoJCQkJY2FsbCBjb250cm9sbGVycyBleGFtcGxlOgoJCQkJCgkJCQkkZG90QXBwLT5yb3V0ZXItPmdldCgiI21vZHVsZW5hbWVsb3dlci9ob21lIiwiQ29udHJvbGxlckBob21lIik7CgkJCQkKCQkJCXVzZSBicmlkZ2UgZXhhbXBsZToKCQkJCSRkb3RBcHAtPmJyaWRnZS0+Zm4oIm5ld3NsZXR0ZXIiLCJDb250cm9sbGVyQG5ld3NsZXR0ZXIiKTsKCQkJKi8KCQkJCiAgICAgICAgICAgIC8vIEFkZCB5b3VyIHJvdXRlcyBhbmQgbG9naWMgaGVyZQoJCQkKCQl9CgoJCXB1YmxpYyBmdW5jdGlvbiBpbml0aWFsaXplQ29uZGl0aW9uKCkgewoJCQkvKgoJCQkJVG8gb3B0aW1pemUgcmVzb3VyY2UgdXNhZ2UsIGl0J3MgcmVjb21tZW5kZWQgdG8gaW5pdGlhbGl6ZSB0aGUgbW9kdWxlIG9ubHkgd2hlbiBpdHMgc3BlY2lmaWMgVVJMIGlzIGFjY2Vzc2VkLCBmb3IgZXhhbXBsZTogZG9tYWluLmNvbS9ibG9nL2Fub3RoZXItcGFydC1vZi11cmwuCgoJCQkJUmVjb21tZW5kZWQgYXBwcm9hY2hlczoKCgkJCQkxLiBBdXRvbWF0aWMgY29uZmlndXJhdGlvbiAtIHVuY29tbWVudCB0aGUgZm9sbG93aW5nIGluIHRoZSByZWdpc3RlcigpIGZ1bmN0aW9uIGluIG1vZHVsZS5saXN0ZW5lcnMucGhwOgoJCQkJCSRkb3RBcHAtPm9uKCJkb3RhcHAubW9kdWxlLiIuJHRoaXMtPm1vZHVsZW5hbWUuIi5pbml0LnN0YXJ0IiwgZnVuY3Rpb24oJG1vZHVsZU9iaikgdXNlICgkZG90QXBwKSB7CgkJCQkJCSRtb2R1bGVPYmotPnNldERhdGEoImJhc2VfdXJsIiwiLyNtb2R1bGVuYW1lbG93ZXIvIik7CgkJCQkJfSk7CgkJCQkJCgkJCQkJVGhlbiwgaW4gdGhpcyBpbml0aWFsaXplQ29uZGl0aW9uKCkgZnVuY3Rpb246CgkJCQkJCXJldHVybiAkdGhpcy0+ZG90YXBwLT5yb3V0ZXItPm1hdGNoX3VybCgkdGhpcy0+Z2V0RGF0YSgiYmFzZVVybCIpIC4gIioiKSAhPT0gZmFsc2U7IC8vIEluaXRpYWxpemUgdGhlIG1vZHVsZSBpZiB0aGUgVVJMIHN0YXJ0cyB3aXRoIHRoZSBiYXNlIFVSTC4KCgkJCQkyLiBNYW51YWwgY29uZmlndXJhdGlvbgoJCQkJCUluIHRoaXMgaW5pdGlhbGl6ZUNvbmRpdGlvbigpIGZ1bmN0aW9uOgoJCQkJCQlyZXR1cm4gJHRoaXMtPmRvdGFwcC0+cm91dGVyLT5tYXRjaF91cmwoIi8jbW9kdWxlbmFtZWxvd2VyLyoiKSAhPT0gZmFsc2U7IC8vIEluaXRpYWxpemUgdGhlIG1vZHVsZSBpZiB0aGUgVVJMIHN0YXJ0cyB3aXRoIHRoZSBzcGVjaWZpZWQgYmFzZSBVUkwuCgkJCSovCgkJCXJldHVybiB0cnVlOyAvLyBBbHdheXMgaW5pdGlhbGl6ZSB0aGUgbW9kdWxlCgkJfQoJCQoJfQoJCgluZXcgTW9kdWxlKCRkb3RBcHApOwo/Pg==";
        if ($filename=="/module.listeners.php") return "PD9waHAKCW5hbWVzcGFjZSBEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXCNtb2R1bGVuYW1lOwogICAgdXNlIERvdHN5c3RlbXNcQXBwXERvdEFwcDsKCgljbGFzcyBMaXN0ZW5lcnMgZXh0ZW5kcyBcRG90c3lzdGVtc1xBcHBcUGFydHNcTGlzdGVuZXJzIHsKCgkJcHVibGljIGZ1bmN0aW9uIHJlZ2lzdGVyKCRkb3RBcHApIHsKCQkJCgkJCS8qCgkJCQlFeGFtcGxlcyBvZiBiZXN0IHByYWN0aWNlcyBmb3IgbW9kdWxlIGNvbmZpZ3VyYXRpb246CgoJCQkJLy8gU2V0IHRoZSBiYXNlIFVSTCBmb3IgdGhpcyBtb2R1bGUgdG8gZW5hYmxlIGF1dG9tYXRpYyBVUkwgaGFuZGxpbmcgLSBzZWUgbW9kdWxlLmluaXQucGhwIGZvciBkZXRhaWxzCgkJCQkkZG90QXBwLT5vbigiZG90YXBwLm1vZHVsZS4iLiR0aGlzLT5tb2R1bGVuYW1lLiIuaW5pdC5zdGFydCIsIGZ1bmN0aW9uKCRtb2R1bGVPYmopIHVzZSAoJGRvdEFwcCkgewoJCQkJCSRtb2R1bGVPYmotPnNldERhdGEoImJhc2VVcmwiLCAiLyNtb2R1bGVuYW1lbG93ZXIvIik7CgkJCQl9KTsKCgkJCQkvLyBDb25maWd1cmUgdGhlIG1vZHVsZSB0byBzZXJ2ZSB0aGUgZGVmYXVsdCAiLyIgcm91dGUgaWYgbm8gb3RoZXIgbW9kdWxlIGhhcyBjbGFpbWVkIGl0CgkJCQkvLyBXYWl0IHVudGlsIGFsbCBtb2R1bGVzIGFyZSBsb2FkZWQsIHRoZW4gY2hlY2sgaWYgdGhlICIvIiByb3V0ZSBpcyBkZWZpbmVkCgkJCQkkZG90QXBwLT5vbigiZG90YXBwLm1vZHVsZXMubG9hZGVkIiwgZnVuY3Rpb24oJG1vZHVsZU9iaikgdXNlICgkZG90QXBwKSB7CgkJCQkJaWYgKCEkZG90QXBwLT5yb3V0ZXItPmhhc1JvdXRlKCJnZXQiLCAiLyIpKSB7CgkJCQkJCS8vIE5vIGRlZmF1bHQgcm91dGUgaXMgZGVmaW5lZCwgc28gc2V0IHRoaXMgbW9kdWxlJ3Mgcm91dGUgYXMgdGhlIGRlZmF1bHQKCQkJCQkJJGRvdEFwcC0+cm91dGVyLT5nZXQoIi8iLCBmdW5jdGlvbigpIHsKCQkJCQkJCWhlYWRlcigiTG9jYXRpb246IC8jbW9kdWxlbmFtZWxvd2VyLyIsIHRydWUsIDMwMSk7CgkJCQkJCQlleGl0KCk7CgkJCQkJCX0pOwoJCQkJCX0KCQkJCX0pOwoJCQkqLwoJCQkKCQkJLy8gQWRkIHlvdXIgY3VzdG9tIGxvZ2ljIGhlcmUKCQkJCgkJfQoJCQoJfQoJCgluZXcgTGlzdGVuZXJzKCRkb3RBcHApOwo/Pg==";
        if ($filename=="/assets/howtouse.txt") return "IyBIb3cgdG8gVXNlIEFzc2V0cyBpbiBUaGlzIE1vZHVsZQoKQWxsIGZpbGVzIHBsYWNlZCBpbiB0aGlzIGZvbGRlciBhcmUgcHVibGljbHkgYWNjZXNzaWJsZSB2aWEgdGhlIGZvbGxvd2luZyBVUkwgc3RydWN0dXJlOgoKL2Fzc2V0cy9tb2R1bGVzLyNtb2R1bGVuYW1lLwoKRm9yIGV4YW1wbGU6Ci0gSWYgeW91IHBsYWNlIGEgZmlsZSBuYW1lZCBgc2NyaXB0LmpzYCBpbiB0aGUgYGpzYCBzdWJmb2xkZXIsIHlvdSBjYW4gaW5jbHVkZSBpdCBpbiB5b3VyIEhUTUwgbGlrZSB0aGlzOgogIGBgYGh0bWwKICA8c2NyaXB0IHNyYz0iL2Fzc2V0cy9tb2R1bGVzLyNtb2R1bGVuYW1lL2pzL3NjcmlwdC5qcyI+PC9zY3JpcHQ+CiAgYGBgCgotIElmIHlvdSBhZGQgYSBmaWxlIG5hbWVkIGBzdHlsZXMuY3NzYCBpbiB0aGUgYGNzc2Agc3ViZm9sZGVyLCB5b3UgY2FuIGxpbmsgaXQgbGlrZSB0aGlzOgogIGBgYGh0bWwKICA8bGluayByZWw9InN0eWxlc2hlZXQiIGhyZWY9Ii9hc3NldHMvbW9kdWxlcy8jbW9kdWxlbmFtZS9jc3Mvc3R5bGVzLmNzcyI+CiAgYGBgCgotIElmIHlvdSBpbmNsdWRlIGFuIGltYWdlIG5hbWVkIGBiYW5uZXIuanBnYCBpbiB0aGUgYGltYWdlc2Agc3ViZm9sZGVyLCB5b3UgY2FuIHVzZSBpdCBhcyBmb2xsb3dzOgogIGBgYGh0bWwKICA8aW1nIHNyYz0iL2Fzc2V0cy9tb2R1bGVzLyNtb2R1bGVuYW1lL2ltYWdlcy9iYW5uZXIuanBnIiBhbHQ9IkJhbm5lciI+CiAgYGBgCgotIElmIHlvdSBwbGFjZSBhIGZvbnQgZmlsZSBuYW1lZCBgbXlmb250LndvZmYyYCBpbiB0aGUgYGZvbnRzYCBzdWJmb2xkZXIsIHlvdSBjYW4gcmVmZXJlbmNlIGl0IGluIHlvdXIgQ1NTIGxpa2UgdGhpczoKICBgYGBodG1sCiAgPHN0eWxlPgogICAgQGZvbnQtZmFjZSB7CiAgICAgIGZvbnQtZmFtaWx5OiAnTXlGb250JzsKICAgICAgc3JjOiB1cmwoJy9hc3NldHMvbW9kdWxlcy8jbW9kdWxlbmFtZS9mb250cy9teWZvbnQud29mZjInKSBmb3JtYXQoJ3dvZmYyJyk7CiAgICB9CiAgPC9zdHlsZT4KICBgYGA=";
        if ($filename=="/Api/Api.php") return "PD9waHAJCgluYW1lc3BhY2UgRG90c3lzdGVtc1xBcHBcTW9kdWxlc1wjbW9kdWxlbmFtZVxBcGk7Cgl1c2UgRG90c3lzdGVtc1xBcHBcRG90QXBwOwoJCgljbGFzcyBBcGkgZXh0ZW5kcyBcRG90c3lzdGVtc1xBcHBcUGFydHNcQ29udHJvbGxlciB7CgkJCgkJLyoKCQkJSWYgeW91IHVzZSB0aGUgYXV0b21hdGljIHJvdXRlciBkaXNwYXRjaGVyIGluIHRoZSBjb250cm9sbGVyIChlLmcuLCBpbiBtb2R1bGUuaW5pdC5waHApIHdpdGg6CgkJCSRkb3RBcHAtPnJvdXRlci0+YXBpUG9pbnQoIjEiLCAiI21vZHVsZW5hbWVsb3dlciIsICJEb3RzeXN0ZW1zXEFwcFxNb2R1bGVzXCNtb2R1bGVuYW1lXEFwaVxBcGlAYXBpRGlzcGF0Y2giKTsKCQkJCgkJCVRoZSBmb2xsb3dpbmcgcm91dGVzIHdpbGwgYmUgY3JlYXRlZDoKCQkJLSBHRVQgL2FwaS92MS8jbW9kdWxlbmFtZWxvd2VyL3Rlc3QgLSBDYWxscyB0aGUgZ2V0VGVzdCBtZXRob2QuCgkJCS0gUE9TVCAvYXBpL3YxLyNtb2R1bGVuYW1lbG93ZXIvdGVzdCAtIENhbGxzIHRoZSBwb3N0VGVzdCBtZXRob2QuCgoJCQlEZXBlbmRlbmN5IGluamVjdGlvbiBpcyBzdXBwb3J0ZWQgYnkgZGVmYXVsdC4gRXhhbXBsZSB3aXRoIERvdEFwcCBpbmplY3Rpb246CgkJCQoJCQlwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIGdldFRlc3QoJHJlcXVlc3QsIERvdEFwcCAkZG90QXBwKSB7CgkJCQkvLyBIYW5kbGVzIEdFVCAvYXBpL3YxLyNtb2R1bGVuYW1lbG93ZXIvdGVzdAoJCQl9CgkJCQoJCQlwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIHBvc3RUZXN0KCRyZXF1ZXN0LCBEb3RBcHAgJGRvdEFwcCkgewoJCQkJLy8gSGFuZGxlcyBQT1NUIC9hcGkvdjEvI21vZHVsZW5hbWVsb3dlci90ZXN0CgkJCX0KCQkqLwkJCgkJCQkKCX0KPz4=";
        if ($filename=="/Controllers/Controller.php") return "PD9waHAJCiAgICBuYW1lc3BhY2UgRG90c3lzdGVtc1xBcHBcTW9kdWxlc1wjbW9kdWxlbmFtZVxDb250cm9sbGVyczsKICAgIHVzZSBEb3RzeXN0ZW1zXEFwcFxEb3RBcHA7CiAgICAKICAgIGNsYXNzIENvbnRyb2xsZXIgZXh0ZW5kcyBcRG90c3lzdGVtc1xBcHBcUGFydHNcQ29udHJvbGxlciB7CiAgICAgICAgCiAgICAgICAgLyoKICAgICAgICAgICAgLy8gRXhhbXBsZSB3aXRoIGRlcGVuZGVuY3kgaW5qZWN0aW9uIAogICAgICAgICAgICBwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIGhvbWUoJHJlcXVlc3QsIERvdEFwcCAkZG90QXBwKSB7CiAgICAgICAgICAgICAgICAvLyBIYW5kbGVzIEdFVCAvYXBpL3YxLyNtb2R1bGVuYW1lbG93ZXIvdGVzdAogICAgICAgICAgICB9CiAgICAgICAgICAgIAogICAgICAgICAgICAvLyBEb3RBcHAgaXMgYXZhaWxhYmxlIGluIHRoZSByZXF1ZXN0IGV2ZW4gd2l0aG91dCBESQogICAgICAgICAgICBwdWJsaWMgc3RhdGljIGZ1bmN0aW9uIGhvbWUoJHJlcXVlc3QpIHsKICAgICAgICAgICAgICAgICRkb3RBcHAgPSAkcmVxdWVzdC0+ZG90QXBwOwogICAgICAgICAgICAgICAgJHZpZXdWYXJzWydzZW8nXVsnZGVzY3JpcHRpb24nXSA9ICJUaGlzIGlzIGEgaG9tZSBleGFtcGxlIHBhZ2UgZm9yIHRoZSBFeGFtcGxlIFBIUCBmcmFtZXdvcmsuIjsKICAgICAgICAgICAgICAgICR2aWV3VmFyc1snc2VvJ11bJ2tleXdvcmRzJ10gPSAiZXhhbXBsZSwgUEhQIGZyYW1ld29yaywgaG9tZSwgZGVtbyI7CiAgICAgICAgICAgICAgICAkdmlld1ZhcnNbJ3NlbyddWyd0aXRsZSddID0gIkhvbWUgLSBFeGFtcGxlIFBIUCBGcmFtZXdvcmsiOwoKICAgICAgICAgICAgICAgIHJldHVybiAkZG90QXBwLT5yb3V0ZXItPnJlbmRlcmVyLT5tb2R1bGUoIiNtb2R1bGVuYW1lIiktPnNldFZpZXcoImhvbWUiKS0+c2V0Vmlld1ZhcigidmFyaWFibGVzIiwgJHZpZXdWYXJzKS0+cmVuZGVyVmlldygpOwogICAgICAgICAgICB9CiAgICAgICAgKi8JCQogICAgICAgICAgICAgICAgCiAgICB9Cj8+";
        if ($filename=="/views/clean.view.php") return base64_encode("{{content}}");
        if ($filename=="/views/layouts/example.layout.php") return "PCEtLSBFeGFtcGxlIG9mIGxheW91dCAtLT4KPHA+UHJpbnQgdmFyaWJhbGUgdmFsdWUgaW4gbW9kdWxlICNtb2R1bGVuYW1lPC9wPgo8cD4KCXt7IHZhcjogJHZhcmlhYmxlc1snYXJ0aWNsZSddWydhcnRpY2xlJ10gfX0KPC9wPgo=";
    }

    /**
     * Rekurzívne vytvorí adresárovú štruktúru.
     *
     * @param string $path Cesta k adresáru (napr. /nieco/subnieco/subsubnieco)
     * @param int $permissions Práva pre adresár (predvolené 0755)
     * @return bool True, ak bol adresár vytvorený alebo už existuje
     */
    private function createDir(string $path, int $permissions = 0755): bool
    {
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
    private function createFile(string $filePath, string $base64Content): bool
    {
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
    private function printHelp(): void
    {
        $this->versionPrint();
        echo "Usage: php dotapper.php [options]\n";
        echo "Options:\n";
        echo "  --create-module=<name> -> Create a new module (e.g., --create-module=MyModule)\n";
        echo "  --modules -> list all modules\n";
        echo "  --module=<module_number or module_name> --create-controller=ControllerName -> Create new controller in selected module\n";
        echo "  --list-routes -> List all defined routes\n\n";
    }

    private function versionPrint() {
        echo "\nDotApper 1.0 (c) 2025\n";
        echo "Author: Stefan Miscik\n";
        echo "Web: https://dotsystems.sk/\n";
        echo "Email: dotapp@dotsystems.sk\n\n";
    }
}

// Hlavné spustenie skriptu
$args = $argv;
array_shift($args); // Odstráni názov skriptu (dotapper.php)

$dotApper = new DotApper($args);
$dotApper->run();

?>