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
 * @version   1.8 FREE
 * @license   MIT License
 * @date      2014 - 2026
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
     * @param array $auth Authentication parameters ['username', 'password', 'api_key', 'ca_fingerprint', 'ca_file',
     *                  'timeout' => max. seconds for the **entire** cURL transfer (connect + waiting for response body),
     *                  'connect_timeout' => seconds only for establishing TCP/TLS (default **2**). If `timeout` is omitted,
     *                  it defaults to **connect_timeout + 30** so roughly **30 s** remain for the API response after connect.]
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
            $connectTimeout = isset($auth['connect_timeout'])
                ? max(1, (int) $auth['connect_timeout'])
                : 2;

            if (isset($auth['timeout']) && (int) $auth['timeout'] > 0) {
                $timeoutSec = max(1, (int) $auth['timeout']);
            } else {
                // CURLOPT_TIMEOUT = celý transfer od štartu; connect má vlastný limit → rezerva ~30 s na telo odpovede
                $timeoutSec = $connectTimeout + 30;
            }

            if (defined('CURLOPT_CONNECTTIMEOUT_MS')) {
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $connectTimeout * 1000);
            } else {
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
            }
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSec);
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

    /**
     * Same as {@see request()}, but repeats the call when the outcome looks transient (timeouts, connection errors,
     * HTTP 429 / 502 / 503 / 504, invalid JSON body with those statuses).
     *
     * @param int $maxAttempts   Minimum 1, capped at 15
     * @param int $initialDelayMs Delay before second attempt (then multiplied by ~1.8x, capped at 8000 ms)
     */
    public static function requestWithRetries(
        string $method,
        string $url,
        array $data = [],
        array $auth = [],
        array $headers = [],
        array $queryParams = [],
        ?string $rawBody = null,
        bool $binary = false,
        int $maxAttempts = 3,
        int $initialDelayMs = 500
    ): array {
        $maxAttempts = max(1, min(15, $maxAttempts));
        $delayMs = max(0, $initialDelayMs);
        $last = null;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $last = self::request($method, $url, $data, $auth, $headers, $queryParams, $rawBody, $binary);
            if (($last['error'] === null && $last['success']) || !self::isRetryableTransportOrOverload($last)) {
                return $last;
            }
            if ($attempt < $maxAttempts && $delayMs > 0) {
                usleep($delayMs * 1000);
                $delayMs = min((int) round($delayMs * 1.8), 8000);
            }
        }

        return $last;
    }

    /**
     * @param array{success?: bool, http_code?: int, response?: mixed, error?: string|null} $result
     */
    public static function isRetryableTransportOrOverload(array $result): bool
    {
        $code = (int) ($result['http_code'] ?? 0);
        if (in_array($code, [429, 502, 503, 504], true)) {
            return true;
        }
        $err = isset($result['error']) ? (string) $result['error'] : '';
        if ($err !== '' && stripos($err, 'cURL error:') === 0) {
            return self::curlErrorLooksTransient($err);
        }
        if (
            stripos($err, 'Invalid JSON response') !== false
            && $code >= 502
            && $code <= 504
        ) {
            return true;
        }

        return false;
    }

    private static function curlErrorLooksTransient(string $err): bool
    {
        $lower = strtolower($err);
        $needles = [
            'timed out',
            'timeout',
            'operation timed out',
            'connection refused',
            'could not resolve host',
            'couldn\'t connect',
            'couldn\'t resolve host',
            'failed to connect',
            'empty reply from server',
            'connection reset',
            'recv failure',
            'ssl_read:',
            'got nothing',
            'ssl connection',
            'network is unreachable',
            'connection aborted',
        ];
        foreach ($needles as $n) {
            if (strpos($lower, $n) !== false) {
                return true;
            }
        }

        return false;
    }
}
?>
