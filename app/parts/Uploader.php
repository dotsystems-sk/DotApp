<?php

/**
 * DotApp Framework
 *
 * This class receives uploaded files for the DotApp Framework. It decides what
 * may be stored, checks every file by its real content and not by what the
 * browser claims, and stores it under a name the visitor cannot choose.
 *
 * @package   DotApp Framework
 * @category  Framework Parts
 * @author    Štefan Miščík <stefan@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   2.0 FREE
 * @date      2014 - 2026
 * @license   MIT License
 *
 * License Notice:
 * Permission is granted to use, modify, and distribute this code under the MIT License,
 * provided this header is retained in all copies or substantial portions of the file,
 * including author and company information.
 */

/*
    The Uploader class is the single safe entry point for files that
    come from a visitor. A module never touches $_FILES directly.

    Key Features:
    - Allow-list of extensions, each with the MIME types its bytes may have.
      Everything that is not listed is refused.
    - Executable and script extensions are refused in every part of the
      file name ( photo.php.jpg, photo.jpg.php ), with no way to allow them.
    - The MIME type is read from the bytes of the file ( finfo ). The type
      sent by the browser is never used.
    - Images are decoded and encoded again. Only the pixels survive, so code
      hidden in comments, EXIF or appended after the image ( a polyglot ) is gone.
    - Files that cannot be encoded again are scanned for script signatures.
    - Stored names are random. The original name is returned only as text.
    - The storage folder is created outside of the public web by default
      and is protected by .htaccess.

    Usage:

        $result = Uploader::use('Shop')
            ->images()
            ->maxSize(5 * 1024 * 1024)
            ->maxFiles(6)
            ->receive();

        if ($result['status'] === 1) {
            foreach ($result['files'] as $file) {
                // $file['name'], $file['path'], $file['original'], $file['mime'], $file['size'], $file['width'], $file['height']
            }
        } else {
            // $result['errors'][0]['message']
        }
*/


namespace Dotsystems\App\Parts;

class Uploader {
    private static $instances = []; // Available Uploader instances ( one per name, the same way as Cache::use )
    private $uploaderName;
    private $folder;          // Absolute folder where files are stored
    private $allowed;         // extension => array of MIME types the bytes may have
    private $maxSize;         // Max size of one file in bytes
    private $maxFiles;        // Max number of files in one request
    private $maxWidth;        // Max image width in pixels
    private $maxHeight;       // Max image height in pixels
    private $maxPixels;       // Max width * height. Protects memory against decompression bombs.
    private $reencode;        // Bool. Decode and encode images again ( removes everything except pixels )
    private $quality;         // JPEG / WEBP quality used when encoding again
    private $field;           // Name of the form field to read, null = every field

    // Error codes returned in $result['errors'][]['code']
    const ERR_UPLOAD = 1;     // PHP upload error ( partial upload, no tmp dir ... )
    const ERR_SIZE = 2;       // File is empty or bigger than maxSize
    const ERR_EXTENSION = 3;  // Extension is not on the allow-list or is forbidden
    const ERR_MIME = 4;       // Bytes of the file are not what the extension says
    const ERR_IMAGE = 5;      // Not a readable image or bigger than allowed dimensions
    const ERR_CONTENT = 6;    // Script signature found inside of the file
    const ERR_STORE = 7;      // File could not be written
    const ERR_COUNT = 8;      // More files than maxFiles

    // These extensions are never stored, in any part of the file name. There is no method to allow them.
    private static $forbidden = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'pht', 'phps', 'phar', 'inc',
        'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'exe', 'com', 'bat', 'cmd', 'msi', 'dll', 'scr', 'vbs', 'ps1', 'jar',
        'asp', 'aspx', 'jsp', 'jspx', 'cfm', 'shtml', 'html', 'htm', 'xhtml', 'js', 'mjs', 'svg', 'svgz', 'xml', 'xsl',
        'htaccess', 'htpasswd', 'ini', 'user'
    ];

    // Signatures that must not be inside of a file which is stored without encoding it again
    private static $signatures = ['<?php', '<?=', '<script', '__halt_compiler', '<%', '<html', '<svg', 'javascript:'];

    // Preset used by images()
    private static $imageTypes = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
    ];

    /**
     * Constructor: Sets safe defaults. Nothing is allowed until images() or allow() is called.
     *
     * @param string $uploaderName The name of the uploader instance ( usually the module name )
     */
    function __construct($uploaderName) {
        $this->uploaderName = $uploaderName;
        $this->allowed = [];
        $this->maxSize = 2 * 1024 * 1024;
        $this->maxFiles = 1;
        $this->maxWidth = 8000;
        $this->maxHeight = 8000;
        $this->maxPixels = 40000000;
        $this->reencode = true;
        $this->quality = 85;
        $this->field = null;
        $this->folder = __ROOTDIR__ . "/app/runtime/uploads/" . preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $uploaderName);
        self::$instances[$uploaderName] = $this;
    }

    /**
     * Creates or retrieves an Uploader instance for the specified name.
     *
     * @param string $uploaderName The name of the uploader instance ( usually the module name )
     * @return Uploader The Uploader instance.
     */
    public static function use($uploaderName = "default") {
        if (isset(self::$instances[$uploaderName])) {
            return self::$instances[$uploaderName];
        }
        return new self($uploaderName);
    }

    /**
     * Returns the folder where this instance stores files.
     *
     * @return string Absolute path of the storage folder.
     */
    public function folder() {
        return $this->folder;
    }

    public function name() {
        return $this->uploaderName;
    }

    /**
     * Allows the common image types: jpg, jpeg, png, gif, webp.
     *
     * @return Uploader
     */
    public function images() {
        foreach (self::$imageTypes as $extension => $mimes) {
            $this->allowed[$extension] = $mimes;
        }
        return $this;
    }

    /**
     * Allows one more extension together with the MIME types its bytes may have.
     * Forbidden ( executable, script ) extensions cannot be allowed, an exception is thrown.
     *
     * @param string $extension Extension without the dot ( pdf )
     * @param array|string $mimes MIME type(s) read from the bytes ( application/pdf )
     * @return Uploader
     */
    public function allow($extension, $mimes) {
        $extension = strtolower(trim((string) $extension, ". \t\n\r"));
        if ($extension === '' || in_array($extension, self::$forbidden, true)) {
            throw new \InvalidArgumentException("Uploader: extension '" . $extension . "' can not be allowed.");
        }
        $this->allowed[$extension] = array_map('strtolower', (array) $mimes);
        return $this;
    }

    /**
     * Max size of one file in bytes. PHP limits ( upload_max_filesize, post_max_size ) still apply.
     */
    public function maxSize($bytes) {
        $this->maxSize = max(1, (int) $bytes);
        return $this;
    }

    /**
     * Max number of files accepted in one request.
     */
    public function maxFiles($count) {
        $this->maxFiles = max(1, (int) $count);
        return $this;
    }

    /**
     * Max image dimensions in pixels. Bigger images are refused.
     */
    public function maxDimensions($width, $height) {
        $this->maxWidth = max(1, (int) $width);
        $this->maxHeight = max(1, (int) $height);
        return $this;
    }

    /**
     * Turns encoding images again on or off. Keep it on unless you have a strong reason.
     * When it is off, images are stored byte for byte and are only scanned for script signatures.
     */
    public function reencode($setting, $quality = 85) {
        $this->reencode = ($setting === true);
        $this->quality = min(100, max(1, (int) $quality));
        return $this;
    }

    /**
     * Reads only one form field. Without this call every uploaded field is read.
     */
    public function field($fieldName) {
        $this->field = (string) $fieldName;
        return $this;
    }

    /**
     * Sets the storage folder, relative to the root of the application ( /app/runtime/uploads/Shop ).
     * The folder is created with .htaccess which denies direct access.
     */
    public function to($relativeFolder) {
        $relativeFolder = str_replace(["\\", ".."], ["/", ""], (string) $relativeFolder);
        $this->folder = rtrim(__ROOTDIR__ . "/" . ltrim($relativeFolder, "/"), "/");
        return $this;
    }

    /**
     * Receives the uploaded files of the current request, checks and stores them.
     *
     * @return array ['status' => 1|0, 'files' => array of stored files, 'errors' => array of refused files]
     *               status is 1 only when at least one file was stored and nothing was refused.
     */
    public function receive() {
        $files = [];
        $errors = [];
        $incoming = $this->incoming();

        if (count($incoming) > $this->maxFiles) {
            return ['status' => 0, 'files' => [], 'errors' => [$this->error('', self::ERR_COUNT, "Too many files. The limit is " . $this->maxFiles . ".")]];
        }
        if (empty($this->allowed)) {
            throw new \RuntimeException("Uploader: nothing is allowed. Call images() or allow() first.");
        }

        foreach ($incoming as $file) {
            $stored = $this->receiveOne($file);
            if (isset($stored['code'])) {
                $errors[] = $stored;
            } else {
                $files[] = $stored;
            }
        }

        return [
            'status' => (count($files) > 0 && count($errors) === 0) ? 1 : 0,
            'files' => $files,
            'errors' => $errors
        ];
    }

    /**
     * Deletes a stored file. Only a name created by this class is accepted, so a path can not be smuggled in.
     *
     * @param string $storedName Value of $file['name'] returned by receive()
     * @return bool
     */
    public function delete($storedName) {
        if (!self::isStoredName($storedName)) return false;
        $path = $this->folder . "/" . $storedName;
        return is_file($path) ? @unlink($path) : false;
    }

    /**
     * Returns the absolute path of a stored file or false when the name is not valid or the file does not exist.
     */
    public function path($storedName) {
        if (!self::isStoredName($storedName)) return false;
        $path = $this->folder . "/" . $storedName;
        return is_file($path) ? $path : false;
    }

    /**
     * Stored names have exactly one shape: 32 hex characters, a dot and an allowed image / file extension.
     */
    public static function isStoredName($name) {
        return is_string($name) && preg_match('/^[a-f0-9]{32}\.[a-z0-9]{2,5}$/', $name) === 1;
    }

    /* ---------------------------------------------------------------- internals */

    // Flattens $_FILES into a simple list. Fields with name[] are supported.
    private function incoming() {
        $list = [];
        foreach ($_FILES as $fieldName => $info) {
            if ($this->field !== null && $fieldName !== $this->field) continue;
            if (is_array($info['name'])) {
                foreach ($info['name'] as $i => $name) {
                    if ($info['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                    $list[] = ['field' => $fieldName, 'name' => $name, 'tmp_name' => $info['tmp_name'][$i], 'error' => $info['error'][$i], 'size' => $info['size'][$i]];
                }
            } else {
                if ($info['error'] === UPLOAD_ERR_NO_FILE) continue;
                $list[] = ['field' => $fieldName, 'name' => $info['name'], 'tmp_name' => $info['tmp_name'], 'error' => $info['error'], 'size' => $info['size']];
            }
        }
        return $list;
    }

    private function error($original, $code, $message) {
        return ['original' => self::cleanName($original), 'code' => $code, 'message' => $message];
    }

    // The original name is only text for the user. Control characters and path parts are removed.
    private static function cleanName($name) {
        $name = basename(str_replace("\\", "/", (string) $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name);
        return function_exists('mb_substr') ? mb_substr($name, 0, 150) : substr($name, 0, 150);
    }

    // Checks one file in the order: upload -> size -> name -> bytes -> image -> content -> store
    private function receiveOne($file) {
        $original = $file['name'];

        // 1. PHP must say the upload is complete and the file must really come from an upload
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return $this->error($original, self::ERR_UPLOAD, "The file was not uploaded completely.");
        }

        // 2. Size from the disk, not the number sent by the browser
        $size = (int) @filesize($file['tmp_name']);
        if ($size <= 0 || $size > $this->maxSize) {
            return $this->error($original, self::ERR_SIZE, "The file is empty or bigger than " . round($this->maxSize / 1048576, 1) . " MB.");
        }

        // 3. Name: every part between dots is checked. photo.php.jpg and photo.jpg.php both end here.
        $parts = explode('.', strtolower(self::cleanName($original)));
        $extension = count($parts) > 1 ? (string) end($parts) : '';
        foreach (array_slice($parts, 1) as $part) {
            if (in_array(trim($part), self::$forbidden, true)) {
                return $this->error($original, self::ERR_EXTENSION, "This file type is not allowed.");
            }
        }
        if ($extension === '' || !isset($this->allowed[$extension])) {
            return $this->error($original, self::ERR_EXTENSION, "This file type is not allowed.");
        }

        // 4. MIME from the bytes. $_FILES[...]['type'] comes from the browser and is never read.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower((string) $finfo->file($file['tmp_name']));
        if (!in_array($mime, $this->allowed[$extension], true)) {
            return $this->error($original, self::ERR_MIME, "The content of the file does not match its type.");
        }

        // 5. + 6. Images are decoded. The rest is scanned.
        $width = null;
        $height = null;
        $isImage = isset(self::$imageTypes[$extension]) && strpos($mime, 'image/') === 0;
        $storeExtension = ($extension === 'jpeg') ? 'jpg' : $extension;
        $storedName = bin2hex(random_bytes(16)) . "." . $storeExtension;

        if (!$this->prepareFolder()) {
            return $this->error($original, self::ERR_STORE, "The file could not be stored.");
        }
        $target = $this->folder . "/" . $storedName;

        if ($isImage) {
            $info = @getimagesize($file['tmp_name']);
            if ($info === false || strtolower((string) $info['mime']) !== $mime) {
                return $this->error($original, self::ERR_IMAGE, "The file is not a valid image.");
            }
            $width = (int) $info[0];
            $height = (int) $info[1];
            if ($width < 1 || $height < 1 || $width > $this->maxWidth || $height > $this->maxHeight || ($width * $height) > $this->maxPixels) {
                return $this->error($original, self::ERR_IMAGE, "The image is bigger than " . $this->maxWidth . " x " . $this->maxHeight . " pixels.");
            }

            if ($this->reencode && function_exists('imagecreatefromstring')) {
                // Decode and encode again: only pixels are written. Comments, EXIF and bytes appended after the image do not survive.
                if (!$this->encodeAgain($file['tmp_name'], $target, $mime)) {
                    return $this->error($original, self::ERR_IMAGE, "The file is not a valid image.");
                }
            } else {
                if ($this->hasSignature($file['tmp_name'])) {
                    return $this->error($original, self::ERR_CONTENT, "The file contains content that is not allowed.");
                }
                if (!@move_uploaded_file($file['tmp_name'], $target)) {
                    return $this->error($original, self::ERR_STORE, "The file could not be stored.");
                }
            }
        } else {
            if ($this->hasSignature($file['tmp_name'])) {
                return $this->error($original, self::ERR_CONTENT, "The file contains content that is not allowed.");
            }
            if (!@move_uploaded_file($file['tmp_name'], $target)) {
                return $this->error($original, self::ERR_STORE, "The file could not be stored.");
            }
        }

        @chmod($target, 0644);
        clearstatcache(true, $target);

        return [
            'field' => $file['field'],
            'original' => self::cleanName($original),
            'name' => $storedName,
            'path' => $target,
            'extension' => $storeExtension,
            'mime' => $mime,
            'size' => (int) @filesize($target),
            'width' => $width,
            'height' => $height
        ];
    }

    // Writes a clean copy of the image. Returns false when the image can not be decoded.
    private function encodeAgain($source, $target, $mime) {
        $image = @imagecreatefromstring((string) @file_get_contents($source));
        if ($image === false) return false;

        $ok = false;
        if ($mime === 'image/jpeg') {
            $ok = @imagejpeg($image, $target, $this->quality);
        } elseif ($mime === 'image/png') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $ok = @imagepng($image, $target, 6);
        } elseif ($mime === 'image/gif') {
            $ok = @imagegif($image, $target); // First frame only, animation is not kept
        } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
            $ok = @imagewebp($image, $target, $this->quality);
        }
        imagedestroy($image);

        if ($ok !== true || !is_file($target)) {
            if (is_file($target)) @unlink($target);
            return false;
        }
        return true;
    }

    // Looks for script signatures. Read in blocks with an overlap, so a signature split by a block border is found too.
    private function hasSignature($path) {
        $handle = @fopen($path, 'rb');
        if ($handle === false) return true; // Can not read = not trusted
        $tail = '';
        $found = false;
        while (!feof($handle)) {
            $block = (string) fread($handle, 65536);
            $haystack = strtolower($tail . $block);
            foreach (self::$signatures as $signature) {
                if (strpos($haystack, $signature) !== false) {
                    $found = true;
                    break 2;
                }
            }
            $tail = substr($block, -32);
        }
        fclose($handle);
        return $found;
    }

    // Creates the storage folder with .htaccess which denies direct access and switches PHP off.
    private function prepareFolder() {
        if (!is_dir($this->folder)) {
            if (!@mkdir($this->folder, 0755, true) && !is_dir($this->folder)) return false;
        }
        $htaccess = $this->folder . "/.htaccess";
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Deny from all\n<IfModule mod_php7.c>\n    php_flag engine off\n</IfModule>\n<IfModule mod_php.c>\n    php_flag engine off\n</IfModule>\n");
        }
        return is_writable($this->folder);
    }
}

?>
