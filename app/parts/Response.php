<?php
/**
 * Class Response
 * 
 * This class handles HTTP responses within the DotApp framework.
 * It provides both instance-based and static facade methods for convenient response manipulation.
 * When a middleware returns an instance of Response, execution is halted.
 * 
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.8 FREE
 * @license   MIT License
 * @date      2014 - 2026
 * 
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the 
 * following condition: You **must** retain this header in all copies or 
 * substantial portions of the code, including the author and company information.
 */

/*
    Response Class Usage:

    Instance-based (for middleware termination):
    - `return new Response(200, "Success");`
    - `return new Response(404, ["error" => "Not found"]);`

    Static facade methods:
    - `Response::code(404);`
    - `Response::header("X-Custom-Header", "value");`
    - `Response::json(["status" => "ok"]);`
    - `Response::redirect("/login");`
    - `Response::body("Hello World");`
*/

namespace Dotsystems\App\Parts;
use Dotsystems\App\DotApp;

class Response {
    /**
     * Reference to DotApp instance (lowercase alias)
     * @var \Dotsystems\App\DotApp
     */
    public $dotapp;
    
    /**
     * Reference to DotApp instance (camelCase alias)
     * @var \Dotsystems\App\DotApp
     */
    public $dotApp;
    
    /**
     * Reference to DotApp instance (PascalCase alias)
     * @var \Dotsystems\App\DotApp
     */
    public $DotApp;

    /**
     * Reference to the response object from Request
     * Contains: status, body, headers, contentType, redirect, cookies, isSent, data
     * @var \stdClass
     */
    public $response;

    /**
     * Shared static instance for facade methods
     * All static facade methods share the same instance pointer for memory efficiency
     * @var Response|null
     */
    private static $instance = null;

    /**
     * Response constructor
     * 
     * Creates a new Response instance that wraps the request's response object.
     * When used in middleware, returning an instance of Response will halt execution.
     * 
     * @param int|null $responseCode HTTP status code (e.g., 200, 404, 500). If null, only creates wrapper without setting code.
     * @param string|array $responseBody Response body content as string, or associative array to set multiple response properties.
     *                                   If array is provided, keys matching response properties will be set directly,
     *                                   other keys will be stored in response->data array.
     * 
     * @example new Response(200, "Success message");
     * @example new Response(404, ["error" => "Not found", "message" => "Resource does not exist"]);
     * @example new Response(); // Creates wrapper without setting code/body
     */
    function __construct($responseCode = null, $responseBody = "") {
        $this->dotapp = DotApp::dotApp();
        $this->dotApp = $this->dotapp;
        $this->DotApp = $this->dotapp;
        $this->response = &$this->dotApp->request->response;
    
        // Only set response code and body if provided (for backward compatibility)
        if ($responseCode !== null) {
            if (is_array($responseBody)) {
                $this->response->status = $responseCode;
        
                foreach ($responseBody as $key => $value) {
                    if (property_exists($this->response, $key)) {
                        $this->response->$key = $value;
                    } else {
                        $this->response->data[$key] = $value;
                    }
                }
            } else {
                $this->response->body = $responseBody;
                $this->response->status = $responseCode;
            }
        }
    }

    /**
     * Gets or creates the shared static instance
     * All static facade methods share the same instance pointer
     * 
     * @return Response
     */
    private static function getInstance(): Response {
        if (self::$instance === null || !(self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ==================== STATIC FACADE METHODS ====================

    /**
     * Sets the HTTP response status code
     * 
     * @param int $code HTTP status code (e.g., 200, 404, 500)
     * @return Response
     * 
     * @example Response::code(404);
     */
    public static function code(int $code): Response {
        $instance = self::getInstance();
        $instance->response->status = $code;
        return $instance;
    }

    /**
     * Sets a response header
     * 
     * @param string $key Header name
     * @param string $value Header value
     * @return Response
     * 
     * @example Response::header("X-Custom-Header", "value");
     * @example Response::header("Cache-Control", "no-cache");
     */
    public static function header(string $key, string $value): Response {
        $instance = self::getInstance();
        $instance->response->headers[$key] = $value;
        return $instance;
    }

    /**
     * Sets multiple response headers at once
     * 
     * @param array $headers Associative array of headers
     * @return Response
     * 
     * @example Response::headers(["X-Header-1" => "value1", "X-Header-2" => "value2"]);
     */
    public static function headers(array $headers): Response {
        $instance = self::getInstance();
        foreach ($headers as $key => $value) {
            $instance->response->headers[$key] = $value;
        }
        return $instance;
    }

    /**
     * Sets the response body content
     * 
     * @param string $content Body content
     * @return Response
     * 
     * @example Response::body("Hello World");
     */
    public static function body2(string $content): Response {
        $instance = self::getInstance();
        $instance->response->body = $content;
        return $instance;
    }

    /**
     * Appends content to the response body
     * 
     * @param string $content Content to append
     * @return Response
     * 
     * @example Response::append("<p>Additional content</p>");
     */
    public static function append(string $content): Response {
        $instance = self::getInstance();
        $instance->response->body .= $content;
        return $instance;
    }

    /**
     * Sets response as JSON with proper Content-Type header
     * 
     * @param mixed $data Data to encode as JSON
     * @param int $code Optional HTTP status code (default: 200)
     * @param int $flags JSON encoding flags (default: JSON_UNESCAPED_UNICODE)
     * @return Response
     * 
     * @example Response::json(["status" => "ok", "data" => $result]);
     * @example Response::json(["error" => "Not found"], 404);
     */
    public static function json($data, int $code = 200, int $flags = JSON_UNESCAPED_UNICODE): Response {
        $instance = self::getInstance();
        $instance->response->status = $code;
        $instance->response->headers["Content-Type"] = "application/json; charset=utf-8";
        $instance->response->contentType = "application/json";
        $instance->response->body = json_encode($data, $flags);
        return $instance;
    }

    /**
     * Sets a redirect response
     * 
     * @param string $url URL to redirect to
     * @param int $code HTTP redirect code (default: 302)
     * @return Response
     * 
     * @example Response::redirect("/login");
     * @example Response::redirect("/new-page", 301); // Permanent redirect
     */
    public static function redirect(string $url, int $code = 302): Response {
        $instance = self::getInstance();
        $instance->response->status = $code;
        $instance->response->redirect = $url;
        $instance->response->headers["Location"] = $url;
        return $instance;
    }

    /**
     * Sets the Content-Type header
     * 
     * @param string $contentType MIME type
     * @return Response
     * 
     * @example Response::contentType("text/plain");
     * @example Response::contentType("application/xml");
     */
    public static function contentType(string $contentType): Response {
        $instance = self::getInstance();
        $instance->response->contentType = $contentType;
        $instance->response->headers["Content-Type"] = $contentType;
        return $instance;
    }

    /**
     * Sets a cookie
     * 
     * @param string $name Cookie name
     * @param string $value Cookie value
     * @param array $options Cookie options (expires, path, domain, secure, httponly, samesite)
     * @return Response
     * 
     * @example Response::cookie("session_id", "abc123", ["expires" => time() + 3600]);
     */
    public static function cookie(string $name, string $value, array $options = []): Response {
        $instance = self::getInstance();
        $instance->response->cookies[$name] = [
            'value' => $value,
            'options' => array_merge([
                'expires' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ], $options)
        ];
        return $instance;
    }

    /**
     * Removes a cookie by setting its expiration to the past
     * 
     * @param string $name Cookie name
     * @param string $path Cookie path (default: "/")
     * @return Response
     * 
     * @example Response::removeCookie("session_id");
     */
    public static function removeCookie(string $name, string $path = '/'): Response {
        return self::cookie($name, '', [
            'expires' => time() - 3600,
            'path' => $path
        ]);
    }

    /**
     * Sets response data (accessible via response->data)
     * 
     * @param string|array $key Key name or associative array of data
     * @param mixed $value Value (only when $key is string)
     * @return Response
     * 
     * @example Response::data("user_id", 123);
     * @example Response::data(["user_id" => 123, "role" => "admin"]);
     */
    public static function data($key, $value = null): Response {
        $instance = self::getInstance();
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $instance->response->data[$k] = $v;
            }
        } else {
            $instance->response->data[$key] = $value;
        }
        return $instance;
    }

    /**
     * Sets Cache-Control header for caching
     * 
     * @param int $seconds Cache duration in seconds (0 = no-cache)
     * @param bool $public Whether cache is public or private
     * @return Response
     * 
     * @example Response::cache(3600); // Cache for 1 hour
     * @example Response::cache(0); // No cache
     */
    public static function cache(int $seconds, bool $public = false): Response {
        $instance = self::getInstance();
        if ($seconds <= 0) {
            $instance->response->headers["Cache-Control"] = "no-store, no-cache, must-revalidate, max-age=0";
            $instance->response->headers["Pragma"] = "no-cache";
            $instance->response->headers["Expires"] = "0";
        } else {
            $visibility = $public ? "public" : "private";
            $instance->response->headers["Cache-Control"] = "{$visibility}, max-age={$seconds}";
            $instance->response->headers["Expires"] = gmdate("D, d M Y H:i:s", time() + $seconds) . " GMT";
        }
        return $instance;
    }

    /**
     * Sets no-cache headers
     * 
     * @return Response
     * 
     * @example Response::noCache();
     */
    public static function noCache(): Response {
        return self::cache(0);
    }

    /**
     * Sends a file download response
     * 
     * @param string $filename Filename for Content-Disposition header
     * @param string $content File content
     * @param string $mimeType MIME type (default: application/octet-stream)
     * @return Response
     * 
     * @example Response::download("report.pdf", $pdfContent, "application/pdf");
     */
    public static function download(string $filename, string $content, string $mimeType = "application/octet-stream"): Response {
        $instance = self::getInstance();
        $instance->response->headers["Content-Type"] = $mimeType;
        $instance->response->headers["Content-Disposition"] = "attachment; filename=\"{$filename}\"";
        $instance->response->headers["Content-Length"] = strlen($content);
        $instance->response->body = $content;
        return $instance;
    }

    /**
     * Sets CORS headers for cross-origin requests
     * 
     * @param string $origin Allowed origin (default: "*")
     * @param array $methods Allowed methods
     * @param array $headers Allowed headers
     * @return Response
     * 
     * @example Response::cors("https://example.com");
     * @example Response::cors("*", ["GET", "POST"], ["Content-Type", "Authorization"]);
     */
    public static function cors(
        string $origin = "*", 
        array $methods = ["GET", "POST", "PUT", "DELETE", "OPTIONS"],
        array $headers = ["Content-Type", "Authorization", "X-Requested-With"]
    ): Response {
        $instance = self::getInstance();
        $instance->response->headers["Access-Control-Allow-Origin"] = $origin;
        $instance->response->headers["Access-Control-Allow-Methods"] = implode(", ", $methods);
        $instance->response->headers["Access-Control-Allow-Headers"] = implode(", ", $headers);
        return $instance;
    }

    /**
     * Returns the current status code
     * 
     * @return int
     */
    public static function getCode(): int {
        return self::getInstance()->response->status;
    }

    /**
     * Returns the current response body
     * 
     * @return string
     */
    public static function getBody(): string {
        return self::getInstance()->response->body;
    }

    /**
     * Returns all response headers
     * 
     * @return array
     */
    public static function getHeaders(): array {
        return self::getInstance()->response->headers;
    }

    /**
     * Checks if response has been sent
     * 
     * @return bool
     */
    public static function isSent(): bool {
        return self::getInstance()->response->isSent ?? false;
    }

    /**
     * Creates and returns a new Response instance (for middleware termination)
     * This is useful when you need to return a Response from middleware to stop execution
     * 
     * @param int $code HTTP status code
     * @param mixed $body Response body (string or array)
     * @return Response
     * 
     * @example return Response::make(403, "Forbidden");
     * @example return Response::make(200, ["status" => "ok"]);
     */
    public static function make(int $code, $body = ""): Response {
        return new self($code, $body);
    }

    /**
     * Alias for make() - creates and returns a new Response instance (for middleware termination)
     * Provides a more natural syntax for middleware responses
     * 
     * @param int $code HTTP status code
     * @param mixed $body Response body (string or array)
     * @return Response
     * 
     * @example return Response::answer(403, "Forbidden");
     * @example return Response::answer(200, ["status" => "ok"]);
     */
    public static function answer(int $code, $body = ""): Response {
        return self::make($code, $body);
    }

    /**
     * Creates a JSON response and returns the instance (for middleware termination)
     * 
     * @param mixed $data Data to encode as JSON
     * @param int $code HTTP status code
     * @return Response
     * 
     * @example return Response::jsonResponse(["error" => "Unauthorized"], 401);
     */
    public static function jsonResponse($data, int $code = 200): Response {
        $instance = new self($code);
        $instance->response->headers["Content-Type"] = "application/json; charset=utf-8";
        $instance->response->contentType = "application/json";
        $instance->response->body = json_encode($data, JSON_UNESCAPED_UNICODE);
        return $instance;
    }

    // ==================== INSTANCE METHODS ====================

    /**
     * Magic getter for accessing response properties
     * 
     * Allows accessing response object properties directly through the Response instance.
     * Returns null if the property doesn't exist.
     * 
     * @param string $name Property name to access (e.g., 'status', 'body', 'headers')
     * @return mixed Property value or null if not set
     * 
     * @example $response->status; // Returns response status code
     * @example $response->body; // Returns response body
     * @example $response->headers; // Returns response headers array
     */
    public function __get($name) {
		return $this->response->$name ?? null;
	}

    /**
     * Magic setter for setting response properties
     * 
     * Allows setting response object properties directly through the Response instance.
     * 
     * @param string $name Property name to set (e.g., 'status', 'body', 'headers')
     * @param mixed $value Value to set
     * @return void
     * 
     * @example $response->status = 404;
     * @example $response->body = "Not Found";
     * @example $response->headers["X-Custom"] = "value";
     */
	public function __set($name, $value) {
		$this->response->$name = $value;
	}

    /**
     * Magic isset checker for response properties
     * 
     * Checks if a response property is set and not null.
     * 
     * @param string $name Property name to check
     * @return bool True if property exists and is set, false otherwise
     * 
     * @example if (isset($response->status)) { ... }
     * @example if (isset($response->redirect)) { ... }
     */
	public function __isset($name) {
		return isset($this->response->$name);
	}
	
}

?>
