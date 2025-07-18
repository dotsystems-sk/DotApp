<?php
/**
 * CLASS HttpHelper - DotApp HTTP Request Utility
 *
 * Provides a utility for executing HTTP requests within the DotApp framework, supporting
 * various HTTP methods, authentication mechanisms, SSL configurations, and binary file downloads.
 * Designed for seamless integration with search engine APIs and other external services.
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.7 FREE
 * @license   MIT License
 * @date      2014 - 2025
 */

namespace Dotsystems\App\Parts;

use \Dotsystems\App\DotApp;

class HttpHelper {
    /**
     * Execute an HTTP request with optional authentication, SSL support, and binary file download.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param string $url Target URL
     * @param array $data Data to send (will be JSON-encoded unless rawBody is provided)
     * @param array $auth Authentication parameters ['username', 'password', 'api_key', 'ca_fingerprint', 'ca_file']
     * @param array $headers Additional HTTP headers (optional)
     * @param array $queryParams Query parameters for GET requests (optional)
     * @param string|null $rawBody Raw body data to send (e.g., NDJSON for bulk operations)
     * @param bool $binary Whether to expect binary response (e.g., for ZIP files, images)
     * @return array Response array with keys: success, http_code, response, error
     */
    public static function request(
        string $method,
        string $url,
        array $data = [],
        array $auth = [],
        array $headers = [],
        array $queryParams = [],
        ?string $rawBody = null,
        bool $binary = false
    ): array {
        $ch = curl_init();

        // Append query parameters to URL for GET requests
        if (!empty($queryParams) && strtoupper($method) === 'GET') {
            $url .= '?' . http_build_query($queryParams);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Add Follow Location for redirects

        // Explicit setting for HEAD
        if (strtoupper($method) === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
                return strlen($data); // Ignore data
            });
        }

        // Default headers
        $defaultHeaders = ['Content-Type: application/json'];
        if ($rawBody !== null) {
            $defaultHeaders = ['Content-Type: application/x-ndjson'];
        }
        if ($binary) {
            $defaultHeaders = []; // No Content-Type for binary downloads
        }
        if (strtoupper($method) === 'HEAD') {
            $defaultHeaders[] = 'Connection: close';
        }
        $headers = array_merge($defaultHeaders, $headers);

        // Authentication
        if (!empty($auth['api_key'])) {
            $headers[] = 'Authorization: Bearer ' . $auth['api_key'];
        } elseif (!empty($auth['username']) && !empty($auth['password'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $auth['username'] . ':' . $auth['password']);
        } elseif (!empty($auth['headers'])) {
            $headers = array_merge($headers, $auth['headers']);
        }

        // SSL Configuration
        if (isset($auth['ca_file']) && $auth['ca_file'] === false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        } elseif (!empty($auth['ca_file']) && file_exists($auth['ca_file'])) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_CAINFO, $auth['ca_file']);
        } elseif (!empty($auth['ca_fingerprint'])) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            DotApp::DotApp()->Logger->warning("CA fingerprint not supported by cURL, SSL verification disabled", [
                'url' => $url,
                'method' => $method
            ]);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            DotApp::DotApp()->Logger->warning("No CA file or fingerprint provided, SSL verification disabled", [
                'url' => $url,
                'method' => $method
            ]);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Set body for methods that support it (exclude GET and HEAD)
        if (!in_array(strtoupper($method), ['GET', 'HEAD'])) {
            if ($rawBody !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
            } elseif (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        // Set timeouts
        if (strtoupper($method) === 'HEAD') {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 500); // 0.5 seconds for connection
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1000);       // 1 second total
        } else {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Increased timeout for binary downloads
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $result = [
            'success' => false,
            'http_code' => $httpCode,
            'response' => null,
            'error' => null
        ];

        if ($response === false) {
            $result['error'] = "cURL error: $curlError, URL: $url, Method: $method";
            return $result;
        }

        // Handle HEAD requests
        if (strtoupper($method) === 'HEAD') {
            $result['success'] = $httpCode >= 200 && $httpCode < 300;
            if (!$result['success']) {
                $result['error'] = "HTTP error: $httpCode, URL: $url, Method: $method";
            }
            return $result;
        }

        // Handle binary response
        if ($binary) {
            $result['success'] = $httpCode >= 200 && $httpCode < 300;
            $result['response'] = $response; // Return raw binary data
            if (!$result['success']) {
                $result['error'] = "HTTP error: $httpCode, URL: $url, Method: $method";
            }
            return $result;
        }

        // Handle JSON response
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            $result['error'] = "Invalid JSON response: $response, URL: $url, Method: $method";
            return $result;
        }

        $result['response'] = $decoded;
        $result['success'] = $httpCode >= 200 && $httpCode < 300;

        if (!$result['success']) {
            $result['error'] = "HTTP error: $httpCode, Response: $response, URL: $url, Method: $method";
        }

        return $result;
    }
}
?>