<?php
/**
 * CLASS Installer - DotApp Module Installation
 *
 * This class provides a robust framework for managing module installations and uninstallations
 * within the DotApp ecosystem. It supports versioned migrations, installation from GitHub or DotHub,
 * and handles both CLI and application-based usage. It ensures compatibility with the DotApp framework
 * without external dependencies and gracefully manages module-specific migration logic.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <stefan@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.8 FREE
 * @date      2014 - 2025
 * @license   MIT License
 */

/*
ERROR CODES:
1: Invalid GitHub URL
2: Branch does not exist
3: Failed to validate branch
4: Failed to download or extract module
5: Failed to run migrations
6: Invalid DotHub module URL
7: index.php not found
8: Failed to create modules directory
9: Modules directory not writable
10: Failed to download ZIP
11: HTTP error during ZIP download
12: Failed to save ZIP file
13: Failed to open ZIP archive
14: Failed to create temporary extraction directory
15: Failed to extract ZIP
16: Module directory not found in ZIP
17: Module directory already exists
18: Failed to create target directory
19: Failed to copy files
20: Installation canceled by user
*/

namespace Dotsystems\App\Parts;

use \Dotsystems\App\DotApp;
use \Dotsystems\App\Parts\DB;
use \ZipArchive;

class Installer {
    private static $installers;
    private $moduleName;

    /**
     * Get an installer instance for a specific module.
     *
     * @param string $module Module name
     * @return static
     */
    public static function module($module) {
        if (isset(self::$installers[$module])) {
            return self::$installers[$module];
        }
        self::$installers[$module] = new static();
        self::$installers[$module]->moduleName = $module;
        return self::$installers[$module];
    }

    /**
     * Installs a module from a GitHub repository.
     *
     * @param string $gitUrl The GitHub repository URL (e.g., https://github.com/dotsystems-sk/moduleUsers)
     * @param string|null $version Optional version (tag or branch, e.g., v1.0.0 or main)
     * @param array $options Additional options (force, github_token)
     * @param object|null $dotApper DotApper instance for CLI confirmations and output (optional)
     * @return array ['success' => bool, 'error_code' => int, 'error_message' => string, 'module_name' => string]
     */
    public function installFromGit($gitUrl, $version = null, $options = [], $dotApper = null) {
        // Validate GitHub URL
        if (!preg_match('#https?://github\.com/([^/]+)/([^/]+)#', $gitUrl, $matches)) {
            $errorMsg = "Invalid GitHub URL: $gitUrl. Expected format: https://github.com/owner/repository";
            if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                $dotApper->colorText('red', $errorMsg . "\n");
            }
            return [
                'success' => false,
                'error_code' => 1,
                'error_message' => $errorMsg,
                'module_name' => null
            ];
        }

        $owner = $matches[1];
        $repo = $matches[2];
        $force = !empty($options['force']);
        $githubToken = $options['github_token'] ?? null;

        // Prepare headers
        $headers = ['Accept: application/vnd.github.v3+json', 'User-Agent: DotApp-Installer'];
        if ($githubToken) {
            $headers[] = "Authorization: Bearer $githubToken";
        }

        // Fetch tags if no version specified
        if ($version === null) {
            $apiUrl = "https://api.github.com/repos/$owner/$repo/tags";
            if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                $dotApper->colorText('white', "Fetching tags from $apiUrl...\n");
            }
            try {
                $response = $this->httpGet($apiUrl, $headers);
                $tags = json_decode($response, true);
                if (is_array($tags) && !empty($tags)) {
                    $version = $tags[0]['name'];
                    if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                        $dotApper->colorText('green', "Selected version: $version\n");
                    }
                } else {
                    $version = 'main';
                    if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                        $dotApper->colorText('yellow', "No tags found for repository $gitUrl. Defaulting to branch: $version\n");
                    }
                }
            } catch (\Exception $e) {
                $errorMsg = "Failed to fetch tags from GitHub API: {$e->getMessage()}. Defaulting to branch: main";
                if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                    $dotApper->colorText('yellow', $errorMsg . "\n");
                }
                $version = 'main';
            }
        }

        // Construct ZIP URL
        $zipUrl = "https://github.com/$owner/$repo/archive/refs/tags/$version.zip";
        if (!preg_match('/^v?\d+\.\d+\.\d+$/', $version)) {
            $zipUrl = "https://github.com/$owner/$repo/archive/refs/heads/$version.zip";
            // Validate branch existence
            $branchUrl = "https://api.github.com/repos/$owner/$repo/branches/$version";
            if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                $dotApper->colorText('white', "Validating branch $version at $branchUrl...\n");
            }
            try {
                $response = $this->httpGet($branchUrl, $headers);
                $branchData = json_decode($response, true);
                if (!is_array($branchData) || !isset($branchData['name'])) {
                    $errorMsg = "Branch $version does not exist in repository $gitUrl.";
                    if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                        $dotApper->colorText('red', $errorMsg . "\n");
                    }
                    return [
                        'success' => false,
                        'error_code' => 2,
                        'error_message' => $errorMsg,
                        'module_name' => null
                    ];
                }
                if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                    $dotApper->colorText('green', "Branch $version validated successfully.\n");
                }
            } catch (\Exception $e) {
                $errorMsg = "Failed to validate branch $version: {$e->getMessage()}";
                if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                    $dotApper->colorText('red', $errorMsg . "\n");
                }
                return [
                    'success' => false,
                    'error_code' => 3,
                    'error_message' => $errorMsg,
                    'module_name' => null
                ];
            }
        }

        if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
            $dotApper->colorText('white', "Downloading module from $zipUrl...\n");
        }

        // Download and extract, get module name
        $result = $this->downloadAndExtract($zipUrl, $force, $headers, $dotApper);
        if (!$result['success']) {
            return $result;
        }
        $moduleName = $result['module_name'];

        // Run migrations
        try {
            if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                $dotApper->colorText('white', "Running migrations for module $moduleName...\n");
            }
            $this->moduleName = $moduleName; // Update moduleName for migrations
            $this->install($version);
        } catch (\Exception $e) {
            $errorMsg = "Failed to run migrations for module $moduleName: {$e->getMessage()}";
            if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                $dotApper->colorText('red', $errorMsg . "\n");
            }
            return [
                'success' => false,
                'error_code' => 5,
                'error_message' => $errorMsg,
                'module_name' => $moduleName
            ];
        }

        if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
            $dotApper->colorText('green', "Module '$moduleName' successfully installed from GitHub.\n");
        }
        return [
            'success' => true,
            'error_code' => 0,
            'error_message' => null,
            'module_name' => $moduleName
        ];
    }

    /**
     * Installs a module from DotHub.
     *
     * @param string $moduleName The module name on DotHub
     * @param string|null $version Optional version (tag or branch)
     * @param array $options Additional options (force, github_token)
     * @param object|null $dotApper DotApper instance for CLI confirmations and output (optional)
     * @return array ['success' => bool, 'error_code' => int, 'error_message' => string, 'module_name' => string]
     */
    public function installFromDotHub($moduleName, $version = null, $options = [], $dotApper = null) {
        $force = !empty($options['force']);
        $githubToken = $options['github_token'] ?? null;

        // Tu nacitame URL z dothub repozitara... A dalej pokracujeme uz klasicky
        $gitUrl = "https://github.com/dotsystems-sk/$moduleName"; // Placeholder

        // Validate pseudo-DotHub URL
        if (!preg_match('#https?://github\.com/([^/]+)/([^/]+)#', $gitUrl, $matches)) {
            $errorMsg = "Invalid DotHub module URL derived for module: $moduleName";
            if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                $dotApper->colorText('red', $errorMsg . "\n");
            }
            return [
                'success' => false,
                'error_code' => 6,
                'error_message' => $errorMsg,
                'module_name' => null
            ];
        }

        // Delegate to installFromGit
        return $this->installFromGit($gitUrl, $version, $options, $dotApper);
    }

    /**
     * Installs a module from a GitHub repository or DotHub.
     *
     * @param string $value GitHub URL or module name
     * @param string|null $version Optional version (tag or branch)
     * @param array $options Additional options (force, github_token)
     * @param object|null $dotApper DotApper instance for CLI confirmations and output (optional)
     * @return array ['success' => bool, 'error_code' => int, 'error_message' => string, 'module_name' => string]
     */
    public function installModule($value, $version = null, $options = [], $dotApper = null) {
        $isGitUrl = filter_var($value, FILTER_VALIDATE_URL) && preg_match('#https?://github\.com/[^/]+/[^/]+#', $value);

        if ($isGitUrl) {
            $repoName = basename($value);
            if (preg_match('#https?://github\.com/[^/]+/([^/]+)#', $value, $matches)) {
                $repoName = $matches[1];
            }

            // Confirm installation in CLI mode
            if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                $confirm = $dotApper->confirmAction("Install module from repository '$repoName' via GitHub URL '$value'" . ($version ? " (version: $version)" : "") . "?");
                if (!$confirm) {
                    $dotApper->colorText('yellow', "Installation canceled by the user.\n");
                    return [
                        'success' => false,
                        'error_code' => 20,
                        'error_message' => "Installation canceled by the user.",
                        'module_name' => null
                    ];
                }
            }

            // Check prerequisites
            $result = $this->checkPrerequisites();
            if (!$result['success']) {
                if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                    $dotApper->colorText('red', $result['error_message'] . "\n");
                }
                return $result;
            }

            // Install from Git
            if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                $dotApper->colorText('white', "Initiating GitHub module installation from repository '$repoName'...\n");
            }
            return $this->installFromGit($value, $version, $options, $dotApper);
        } else {
            // Confirm installation in CLI mode
            if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                $confirm = $dotApper->confirmAction("Install module '$value' from DotHub" . ($version ? " (version: $version)" : "") . "?");
                if (!$confirm) {
                    $dotApper->colorText('yellow', "Installation canceled by the user.\n");
                    return [
                        'success' => false,
                        'error_code' => 20,
                        'error_message' => "Installation canceled by the user.",
                        'module_name' => null
                    ];
                }
            }

            // Check prerequisites
            $result = $this->checkPrerequisites();
            if (!$result['success']) {
                if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                    $dotApper->colorText('red', $result['error_message'] . "\n");
                }
                return $result;
            }

            // Install from DotHub
            if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                $dotApper->colorText('white', "Initiating DotHub module installation for '$value'...\n");
            }
            return $this->installFromDotHub($value, $version, $options, $dotApper);
        }
    }

    /**
     * Checks prerequisites for module installation (index.php, modules directory, permissions).
     *
     * @return array ['success' => bool, 'error_code' => int, 'error_message' => string]
     */
    private function checkPrerequisites(): array {
        $indexPath = __ROOTDIR__ . "/index.php";
        if (!file_exists($indexPath)) {
            return [
                'success' => false,
                'error_code' => 7,
                'error_message' => "Error: index.php not found in project root. Please run --install first."
            ];
        }

        $modulesDir = __ROOTDIR__ . "/app/modules";
        if (!is_dir($modulesDir)) {
            if (!mkdir($modulesDir, 0755, true)) {
                return [
                    'success' => false,
                    'error_code' => 8,
                    'error_message' => "Error: Failed to create directory $modulesDir."
                ];
            }
        }
        if (!is_writable($modulesDir)) {
            return [
                'success' => false,
                'error_code' => 9,
                'error_message' => "Error: Directory $modulesDir is not writable."
            ];
        }

        return [
            'success' => true,
            'error_code' => 0,
            'error_message' => null
        ];
    }

    /**
     * Downloads and extracts a ZIP archive, moving only the module directory to the target location.
     *
     * @param string $zipUrl The URL of the ZIP archive
     * @param bool $force Whether to overwrite the target directory
     * @param array $headers HTTP headers for the download request
     * @param object|null $dotApper DotApper instance for CLI confirmations and output (optional)
     * @return array ['success' => bool, 'error_code' => int, 'error_message' => string, 'module_name' => string]
     */
    private function downloadAndExtract($zipUrl, $force, $headers = [], $dotApper = null) {
        $tempFile = tempnam(sys_get_temp_dir(), 'dotapp_module_') . '.zip';
        $tempExtractDir = sys_get_temp_dir() . '/dotapp_extract_' . uniqid();

        // Download ZIP
        if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
            $dotApper->colorText('white', "Attempting to download ZIP from $zipUrl (SSL verification disabled)...\n");
        }
        $ch = curl_init($zipUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $zipContent = curl_exec($ch);
        if ($zipContent === false) {
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $errorMsg = "Failed to download ZIP: $error (HTTP code: $httpCode) for URL $zipUrl";
            if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                $dotApper->colorText('red', $errorMsg . "\n");
            }
            return [
                'success' => false,
                'error_code' => 10,
                'error_message' => $errorMsg,
                'module_name' => null
            ];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            $errorMsg = "Failed to download ZIP: HTTP status $httpCode for URL $zipUrl";
            if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                $dotApper->colorText('red', $errorMsg . "\n");
            }
            return [
                'success' => false,
                'error_code' => 11,
                'error_message' => $errorMsg,
                'module_name' => null
            ];
        }

        if (!file_put_contents($tempFile, $zipContent)) {
            $errorMsg = "Failed to save ZIP file to $tempFile.";
            if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                $dotApper->colorText('red', $errorMsg . "\n");
            }
            return [
                'success' => false,
                'error_code' => 12,
                'error_message' => $errorMsg,
                'module_name' => null
            ];
        }
        if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
            $dotApper->colorText('green', "Downloaded ZIP to $tempFile.\n");
        }

        // Extract ZIP to temporary directory
        $zip = new ZipArchive();
        if ($zip->open($tempFile) !== true) {
            unlink($tempFile);
            $errorMsg = "Failed to open ZIP archive $tempFile.";
            if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                $dotApper->colorText('red', $errorMsg . "\n");
            }
            return [
                'success' => false,
                'error_code' => 13,
                'error_message' => $errorMsg,
                'module_name' => null
            ];
        }

        if (!mkdir($tempExtractDir, 0755, true)) {
            $zip->close();
            unlink($tempFile);
            $errorMsg = "Failed to create temporary extraction directory $tempExtractDir.";
            if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                $dotApper->colorText('red', $errorMsg . "\n");
            }
            return [
                'success' => false,
                'error_code' => 14,
                'error_message' => $errorMsg,
                'module_name' => null
            ];
        }

        if (!$zip->extractTo($tempExtractDir)) {
            $zip->close();
            unlink($tempFile);
            $this->rrmdir($tempExtractDir);
            $errorMsg = "Failed to extract ZIP archive to $tempExtractDir.";
            if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                $dotApper->colorText('red', $errorMsg . "\n");
            }
            return [
                'success' => false,
                'error_code' => 15,
                'error_message' => $errorMsg,
                'module_name' => null
            ];
        }

        $zip->close();
        unlink($tempFile);
        if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
            $dotApper->colorText('green', "Extracted ZIP to temporary directory $tempExtractDir.\n");
        }

        // Find the module directory (e.g., 'Users') in the extracted content
        $moduleName = $this->getInstalledModuleName($tempExtractDir);
        if (!$moduleName) {
            $this->rrmdir($tempExtractDir);
            $errorMsg = "Module directory not found in ZIP archive from $zipUrl.";
            if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                $dotApper->colorText('red', $errorMsg . "\n");
            }
            return [
                'success' => false,
                'error_code' => 16,
                'error_message' => $errorMsg,
                'module_name' => null
            ];
        }

        // Set target directory with module name
        $targetDir = __ROOTDIR__ . "/app/modules/$moduleName";
        if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
            $dotApper->colorText('white', "Found module directory '$moduleName'. Installing to $targetDir\n");
        }

        // Check if target directory exists and handle overwrite
        if (is_dir($targetDir) && !$force) {
            if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                $confirm = $dotApper->confirmAction("Overwrite existing module '$moduleName'?");
                if (!$confirm) {
                    $this->rrmdir($tempExtractDir);
                    $dotApper->colorText('yellow', "Installation canceled: Module directory '$moduleName' already exists.\n");
                    return [
                        'success' => false,
                        'error_code' => 17,
                        'error_message' => "Module directory '$moduleName' already exists.",
                        'module_name' => $moduleName
                    ];
                }
            } else {
                $errorMsg = "Module directory '$moduleName' already exists at $targetDir.";
                $this->rrmdir($tempExtractDir);
                return [
                    'success' => false,
                    'error_code' => 17,
                    'error_message' => $errorMsg,
                    'module_name' => $moduleName
                ];
            }
            $this->rrmdir($targetDir);
        }

        // Create target directory
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                $this->rrmdir($tempExtractDir);
                $errorMsg = "Failed to create target directory $targetDir.";
                if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                    $dotApper->colorText('red', $errorMsg . "\n");
                }
                return [
                    'success' => false,
                    'error_code' => 18,
                    'error_message' => $errorMsg,
                    'module_name' => $moduleName
                ];
            }
        }

        // Move contents of module directory to target directory
        $moduleDirPath = "$tempExtractDir/$moduleName";
        if (!is_dir($moduleDirPath)) {
            $rootDir = glob("$tempExtractDir/*", GLOB_ONLYDIR);
            if (!empty($rootDir)) {
                $moduleDirPath = $rootDir[0]; // Assume single root directory (e.g., moduleUsers-main)
                $files = scandir($moduleDirPath);
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..' && is_dir("$moduleDirPath/$file")) {
                        $moduleDirPath = "$moduleDirPath/$file";
                        break;
                    }
                }
            }
        }

        $filesToMove = scandir($moduleDirPath);
        foreach ($filesToMove as $file) {
            if ($file !== '.' && $file !== '..') {
                $src = "$moduleDirPath/$file";
                $dest = "$targetDir/$file";
                if (is_dir($src)) {
                    $this->copyDirectory($src, $dest);
                } else {
                    if (!copy($src, $dest)) {
                        $this->rrmdir($tempExtractDir);
                        $errorMsg = "Failed to copy $src to $dest.";
                        if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
                            $dotApper->colorText('red', $errorMsg . "\n");
                        }
                        return [
                            'success' => false,
                            'error_code' => 19,
                            'error_message' => $errorMsg,
                            'module_name' => $moduleName
                        ];
                    }
                }
            }
        }

        // Clean up temporary extraction directory
        $this->rrmdir($tempExtractDir);
        if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1 && $dotApper) {
            $dotApper->colorText('green', "Module installed to $targetDir.\n");
        }

        return [
            'success' => true,
            'error_code' => 0,
            'error_message' => null,
            'module_name' => $moduleName
        ];
    }

    /**
     * Gets the module name from the extracted directory.
     *
     * @param string $modulesDir Directory containing the extracted module
     * @return string|null The module name or null if not found
     */
    private function getInstalledModuleName($modulesDir): ?string {
        $dirs = array_filter(glob($modulesDir . '/*', GLOB_ONLYDIR));
        if (empty($dirs)) {
            return null;
        }
        $latestDir = array_reduce($dirs, function($carry, $item) {
            $itemTime = filemtime($item);
            $carryTime = $carry ? filemtime($carry) : 0;
            return $itemTime > $carryTime ? $item : $carry;
        });
        $moduleDir = basename($latestDir);
        // Check if there's a nested module directory
        $subDirs = array_filter(glob($latestDir . '/*', GLOB_ONLYDIR));
        if (!empty($subDirs)) {
            foreach ($subDirs as $subDir) {
                $moduleDir = basename($subDir);
                break; // Take the first valid module directory
            }
        }
        return $moduleDir;
    }

    /**
     * Copies a directory recursively.
     *
     * @param string $src Source directory
     * @param string $dest Destination directory
     * @throws \Exception If copying fails
     */
    private function copyDirectory($src, $dest) {
        if (!is_dir($dest)) {
            if (!mkdir($dest, 0755, true)) {
                throw new \Exception("Failed to create directory $dest.");
            }
        }
        $files = scandir($src);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $srcPath = "$src/$file";
                $destPath = "$dest/$file";
                if (is_dir($srcPath)) {
                    $this->copyDirectory($srcPath, $destPath);
                } else {
                    if (!copy($srcPath, $destPath)) {
                        throw new \Exception("Failed to copy $srcPath to $destPath.");
                    }
                }
            }
        }
    }

    /**
     * Performs an HTTP GET request using curl.
     *
     * @param string $url The URL to request
     * @param array $headers HTTP headers to include in the request
     * @return string The response body
     * @throws \Exception If the request fails
     */
    private function httpGet($url, $headers = []) {
        if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1) {
            echo "Attempting HTTP GET for $url (SSL verification disabled)...\n";
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            throw new \Exception("HTTP request failed: $error (HTTP code: $httpCode) for URL $url");
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception("HTTP request failed with status $httpCode for URL $url");
        }

        if (defined('__DOTAPPER_RUN__') && __DOTAPPER_RUN__ === 1) {
            echo "HTTP GET successful for $url (HTTP code: $httpCode).\n";
        }
        return $response;
    }

    /**
     * Recursively removes a directory.
     *
     * @param string $dir Directory to remove
     */
    private function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->rrmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }

    /**
     * Installs migrations for the module.
     *
     * @param string|null $version Optional version to install up to
     */
    public function install($version = null) {
        $migrations = static::installer();
        ksort($migrations);
        if ($version === null) {
            foreach ($migrations as $ver => $migration) {
                $migration();
            }
        } else {
            foreach ($migrations as $ver => $migration) {
                if (version_compare($ver, $version, '<=')) {
                    $migration();
                } else {
                    break;
                }
            }
        }
    }

    /**
     * Uninstalls migrations for the module.
     *
     * @param string|null $version Optional version to uninstall down to
     */
    public function uninstall($version = null) {
        $migrations = static::uninstaller();
        krsort($migrations);
        if ($version === null) {
            foreach ($migrations as $ver => $migration) {
                $migration();
            }
        } else {
            foreach ($migrations as $ver => $migration) {
                if (version_compare($ver, $version, '>=')) {
                    $migration();
                } else {
                    break;
                }
            }
        }
    }

    /**
     * Returns the installer migrations.
     *
     * @return array
     */
    public static function installer() {
        return [];
    }

    /**
     * Returns the uninstaller migrations.
     *
     * @return array
     */
    public static function uninstaller() {
        return [];
    }
}