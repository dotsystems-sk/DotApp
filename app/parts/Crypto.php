<?php
/**
 * Class Crypto
 *
 * This class provides a static interface for encryption and decryption operations
 * within the DotApp framework. It simplifies secure data handling by offering methods
 * to encrypt and decrypt single values or arrays, including recursive array processing.
 *
 * The Crypto class acts as a wrapper around the core encryption methods, making it
 * easier to secure sensitive data throughout the application.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.7 FREE
 * @license   MIT License
 * @date      2014 - 2025
 *
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the
 * following condition: You **must** retain this header in all copies or
 * substantial portions of the code, including the author and company information.
 */

/*
    Crypto Class Usage:

    The Crypto class is essential for securing data within the DotApp framework.
    It provides methods for encrypting and decrypting both single values and arrays,
    with support for recursive array processing.

    Example:
    - Encrypt a string:
      `Crypto::encrypt('sensitive data');`
    - Decrypt an array recursively:
      `Crypto::decryptArray($encryptedArray);`
*/

namespace Dotsystems\App\Parts;
use \Dotsystems\App\DotApp;

class Crypto {

    public static function encrypt($text, $key2 = "") {
        return DotApp::dotApp()->encrypt($text, $key2);
    }

    public static function encrypta($array, $key2 = "") {
        return DotApp::dotApp()->encrypta($array, $key2);
    }

    public static function encryptArray($array, $key2 = "") {
        return DotApp::dotApp()->encrypta($array, $key2);
    }

    public static function decrypt($text, $key2 = "") {
        return DotApp::dotApp()->decrypt($text, $key2);
    }

    public static function decrypta($array, $key2 = "") {
        return DotApp::dotApp()->decrypta($array, $key2);
    }

    public static function decryptArray($array, $key2 = "") {
        return DotApp::dotApp()->decrypta($array, $key2);
    }
}

?>