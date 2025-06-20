<?php
namespace Dotsystems\App\Parts;
/*
	Uz nepotrebna kniznica, niekedy sa starala o posielanie assetov, pouzivala sa ak slo o prava k nim ale nechavam ju tu ak by niekto chcel cast kodu pouzit moze
*/

class Assets {
    private string $path = '';
    private string $reqVars = '';
    private bool $removeScriptPath = true;
    private string $method = '';
    private string $modulesDir = '../modules/';
    private array $allowedExtensions = [
        'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'woff', 'woff2', 'ttf', 'otf', 'txt'
    ];

    private array $allowedMimeTypes = [
        'text/css' => 'css',
        'application/javascript' => 'js',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
        'image/webp' => 'webp',
        'font/woff' => 'woff',
        'font/woff2' => 'woff2',
        'font/ttf' => 'ttf',
        'font/otf' => 'otf',
        'text/plain' => 'txt'
    ];


    public function process() {
        $this->method = $this->getMethod();
        $this->path = $this->getPath();
		$extensionToMime = array_flip($this->allowedMimeTypes);

        $assetPath = ltrim(str_replace('/assets/modules/', '', $this->path), '/');

        $pathParts = explode('/', $assetPath, 2);
        if (empty($pathParts[0])) {
            http_response_code(400);
            echo 'Missing module name';
            exit;
        }

        $moduleName = $pathParts[0];
        $filePath = $pathParts[1] ?? '';

        if (empty($filePath)) {
            http_response_code(403);
            exit;
        }

        $filePath = str_replace(['../', './'], '', $filePath);

        if (strpos($filePath, '..') !== false || strpos($filePath, './') !== false) {
            http_response_code(403);
            exit;
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        if (!in_array(strtolower($extension), $this->allowedExtensions)) {
            http_response_code(403);
            exit;
        }

        $moduleDir = $this->modulesDir . $moduleName;
        if (!is_dir($moduleDir)) {
            http_response_code(404);
            exit;
        }

        $file = $moduleDir . '/assets/' . $filePath;

        if (is_dir($file)) {
            http_response_code(403);
            exit;
        }

        if (!is_file($file) || !is_readable($file)) {
            http_response_code(404);
            exit;
        }

        $mimeType = mime_content_type($file);
        if ($mimeType == "text/plain") {
            // Opravne podla pripony
            if (isSet($extensionToMime[$extension])) $mimeType = $extensionToMime[$extension];
        }        
        if ($mimeType === false) {
            $mimeType = 'application/octet-stream';
        }

        if (!array_key_exists($mimeType, $this->allowedMimeTypes)) {
            http_response_code(403);
            exit;
        }

        header('Content-Type: ' . $mimeType);
        header('Cache-Control: public, max-age=31536000');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($file)) . ' GMT');
        header('Content-Length: ' . filesize($file));

        readfile($file);
        exit;
    }

    private function getPath(): string {
        if (!empty($this->path)) {
            return $this->path;
        }

        $path = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $path = str_replace('\\', '/', $path);

        if ($this->removeScriptPath) {
            $scriptName = dirname($_SERVER['SCRIPT_NAME']);
            $scriptName = str_replace('\\', '/', $scriptName);
            $escapedScriptName = preg_quote($scriptName, '/');
            $path = '/' . preg_replace('/^' . $escapedScriptName . '/', '', $path, 1);
        }

        $queryPos = strpos($path, '?');
        if ($queryPos === false) {
            $this->path = $path;
            return $path;
        }

        $pathParts = explode('?', $path);
        $this->path = $pathParts[0];
        $this->reqVars = $pathParts[1] ?? '';
        return $pathParts[0];
    }

    private function getMethod(): string {
        $method = strtolower($_SERVER['REQUEST_METHOD']);

        $allowedMethods = ['get'];

        if (!in_array($method, $allowedMethods)) {
            http_response_code(405);
            echo "Method '$method' is not allowed. Use only standard HTTP methods: " . implode(', ', $allowedMethods);
            exit;
        }

        return $method;
    }
}

$assets = new Assets();
$assets->process();
?>