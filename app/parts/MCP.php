<?php

/**
 * MCP (Model Context Protocol) Library
 * 
 * This class provides a robust implementation of a Model Context Protocol (MCP) server 
 * within the DotApp framework. It allows developers to define and manage tools, resources, 
 * and prompts that can be accessed by AI models or other clients via standardized JSON-RPC 
 * requests. Each entity (tool, resource, prompt) is registered with metadata and a callback 
 * function, enabling seamless integration with external systems for automation, data access, 
 * or device control.
 * 
 * The library is designed to be extensible, secure, and easy to integrate into the 
 * DotApp architecture. It leverages static storage for tools, resources, and prompts, 
 * ensuring global accessibility and runtime registration. The library does not define 
 * endpoints directly, leaving routing to the developer.
 * 
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.8
 * @license   MIT License
 * @date      2014 - 2026
 * 
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the 
 * following condition: You **must** retain this header in all copies or 
 * substantial portions of the code, including the author and company information.
 */

namespace Dotsystems\App\Parts;

use Dotsystems\App\DotApp;
use Exception;

class MCP {
    /**
     * Static storage for registered tools, resources, and prompts.
     * 
     * @var array
     */
    private static $tools = [];
    private static $resources = [];
    private static $prompts = [];

    /**
     * Server configuration.
     * 
     * @var array
     */
    private static $serverConfig = [
        'name' => 'mcp-dotapp-server',
        'version' => '1.8',
        'protocolVersion' => '2025-06-18'
    ];

    /**
     * Validates and converts a value to boolean.
     * 
     * @param mixed $value The input value to validate/convert
     * @return bool Returns converted boolean
     */
    private static function validateAndConvertBoolean($value) {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === 'true') {
                return true;
            }
            if ($normalized === 'false') {
                return false;
            }
            if ($normalized === '1') {
                return true;
            }
            if ($normalized === '0') {
                return false;
            }
        }

        if (is_int($value)) {
            if ($value === 1) {
                return true;
            }
            if ($value === 0) {
                return false;
            }
        }

        if (is_bool($value)) {
            return $value;
        }
        return false; // Default to false for any other invalid value
    }

    /**
     * Generates dynamic server info with capabilities based on registered entities.
     * 
     * @return array Server info including dynamic capabilities.
     */
    private static function getServerInfo() {
        $capabilities = [
            'tools' => [
                'list' => !empty(self::$tools),
                'call' => !empty(self::$tools)
            ],
            'resources' => [
                'list' => !empty(self::$resources),
                'read' => !empty(self::$resources)
            ],
            'prompts' => [
                'list' => !empty(self::$prompts),
                'get' => !empty(self::$prompts)
            ]
        ];

        return [
            'name' => self::$serverConfig['name'],
            'version' => self::$serverConfig['version'],
            'protocolVersion' => self::$serverConfig['protocolVersion'],
            'capabilities' => $capabilities
        ];
    }

    /**
     * Adds a tool to the MCP server.
     * 
     * @param string $name Unique name of the tool (e.g., "send_email").
     * @param string $description Description of what the tool does.
     * @param array $parameters Associative array of parameters (name => details).
     * @param callable $callback Callback function to execute the tool's logic.
     * @param array|null $authentication Optional authentication details.
     * @return bool True on success, false if invalid or tool exists.
     */
    public static function addTool($name, $description, $parameters, $callback, $authentication = null) {
        if (!is_callable($callback)) {
            $callback = DotApp::DotApp()->stringToCallable($callback);
        }
        if (!is_string($name) || !is_string($description) || !is_array($parameters) || !is_callable($callback)) {
            return false;
        }

        if (isset(self::$tools[$name])) {
            return false;
        }

        foreach ($parameters as $paramName => $paramDetails) {
            if (!is_string($paramName) || !is_array($paramDetails) || 
                !isset($paramDetails['type'], $paramDetails['description'])) {
                return false;
            }
        }

        if ($authentication !== null && (!is_array($authentication) || 
            !isset($authentication['type'], $authentication['description']))) {
            return false;
        }

        self::$tools[$name] = [
            'name' => $name,
            'description' => $description,
            'parameters' => $parameters,
            'callback' => $callback,
            'authentication' => $authentication
        ];

        return true;
    }

    /**
     * Adds a resource to the MCP server.
     * 
     * @param string $name Unique name of the resource (e.g., "config/app").
     * @param string $description Description of the resource.
     * @param string $uri URI of the resource (e.g., "config://app").
     * @param string $mimeType MIME type of the resource data.
     * @param array $arguments Associative array of arguments (name => details).
     * @param callable $callback Callback function to read the resource.
     * @return bool True on success, false if invalid or resource exists.
     */
    public static function addResource($name, $description, $uri, $mimeType, $arguments, $callback) {
        if (!is_callable($callback)) {
            $callback = DotApp::DotApp()->stringToCallable($callback);
        }
        if (!is_string($name) || !is_string($description) || !is_string($uri) || 
            !is_string($mimeType) || !is_array($arguments) || !is_callable($callback)) {
            return false;
        }

        if (isset(self::$resources[$name])) {
            return false;
        }

        foreach ($arguments as $argName => $argDetails) {
            if (!is_string($argName) || !is_array($argDetails) || 
                !isset($argDetails['type'], $argDetails['description'])) {
                return false;
            }
        }

        self::$resources[$name] = [
            'name' => $name,
            'description' => $description,
            'uri' => $uri,
            'mimeType' => $mimeType,
            'arguments' => $arguments,
            'callback' => $callback
        ];

        return true;
    }

    /**
     * Adds a prompt to the MCP server.
     * 
     * @param string $name Unique name of the prompt (e.g., "review-code").
     * @param string $description Description of the prompt.
     * @param array $parameters Associative array of parameters (name => details).
     * @param callable $callback Callback function to generate the prompt.
     * @return bool True on success, false if invalid or prompt exists.
     */
    public static function addPrompt($name, $description, $parameters, $callback) {
        if (!is_callable($callback)) {
            $callback = DotApp::DotApp()->stringToCallable($callback);
        }
        if (!is_string($name) || !is_string($description) || !is_array($parameters) || !is_callable($callback)) {
            return false;
        }

        if (isset(self::$prompts[$name])) {
            return false;
        }

        foreach ($parameters as $paramName => $paramDetails) {
            if (!is_string($paramName) || !is_array($paramDetails) || 
                !isset($paramDetails['type'], $paramDetails['description'])) {
                return false;
            }
        }

        self::$prompts[$name] = [
            'name' => $name,
            'description' => $description,
            'parameters' => $parameters,
            'callback' => $callback
        ];

        return true;
    }

    /**
     * Handles JSON-RPC initialization request.
     * 
     * @param array $request JSON-RPC request array.
     * @return array JSON-RPC response.
     */
    private static function handleInitialize($request) {
        $response = [
            'jsonrpc' => '2.0',
            'id' => isset($request['id']) ? $request['id'] : null
        ];

        if (!isset($request['params']['protocolVersion']) || 
            $request['params']['protocolVersion'] !== self::$serverConfig['protocolVersion']) {
            $response['error'] = [
                'code' => -32602,
                'message' => 'Incompatible protocol version'
            ];
            return $response;
        }

        $serverInfo = self::getServerInfo();
        $response['result'] = [
            'serverInfo' => [
                'name' => $serverInfo['name'],
                'version' => $serverInfo['version']
            ],
            'protocolVersion' => $serverInfo['protocolVersion'],
            'capabilities' => $serverInfo['capabilities']
        ];

        return $response;
    }

    /**
     * Returns the list of registered tools, resources, and prompts.
     * 
     * @return array JSON-compatible array with discovery data.
     */
    public static function discovery() {
        $tools = array_map(function($tool) {
            return [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'parameters' => $tool['parameters'],
                'authentication' => $tool['authentication']
            ];
        }, self::$tools);

        $resources = array_map(function($resource) {
            return [
                'name' => $resource['name'],
                'description' => $resource['description'],
                'uri' => $resource['uri'],
                'mimeType' => $resource['mimeType'],
                'arguments' => $resource['arguments']
            ];
        }, self::$resources);

        $prompts = array_map(function($prompt) {
            return [
                'name' => $prompt['name'],
                'description' => $prompt['description'],
                'parameters' => $prompt['parameters']
            ];
        }, self::$prompts);

        return [
            'tools' => array_values($tools),
            'resources' => array_values($resources),
            'prompts' => array_values($prompts)
        ];
    }

    /**
     * Executes a JSON-RPC request.
     * 
     * @param array $request JSON-RPC request array.
     * @return array JSON-RPC response.
     */
    public static function execute($request) {
        $data = $request->data(true);
        
        if (is_array($data)) {
            $request = $data;
        } else {
            $request = null;
        }

        if (!is_array($request)) {
            return [
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid Request'
                ]
            ];
        }

        $response = [
            'jsonrpc' => '2.0',
            'id' => isset($request['id']) ? $request['id'] : null
        ];

        if (!isset($request['jsonrpc']) || $request['jsonrpc'] !== '2.0' ||
            !isset($request['method']) || !is_string($request['method']) ||
            !isset($request['params']) || !is_array($request['params'])) {
            $response['error'] = [
                'code' => -32600,
                'message' => 'Invalid Request'
            ];
            return $response;
        }

        $method = $request['method'];
        $params = $request['params'];

        switch ($method) {
            case 'initialize':
                return self::handleInitialize($request);
            
            case 'tools/list':
                $response['result'] = ['tools' => self::discovery()['tools']];
                return $response;

            case 'resources/list':
                $response['result'] = ['resources' => self::discovery()['resources']];
                return $response;

            case 'prompts/list':
                $response['result'] = ['prompts' => self::discovery()['prompts']];
                return $response;

            case 'tools/call':
                if (!isset($params['name']) || !isset(self::$tools[$params['name']])) {
                    $response['error'] = [
                        'code' => -32601,
                        'message' => 'Tool not found'
                    ];
                    return $response;
                }

                $tool = self::$tools[$params['name']];
                $toolParams = $params['arguments'] ?? [];

                foreach ($tool['parameters'] as $paramName => $paramDetails) {
                    if (!array_key_exists($paramName, $toolParams)) {
                        $response['error'] = [
                            'code' => -32602,
                            'message' => "Missing required parameter: $paramName"
                        ];
                        return $response;
                    }

                    $expectedType = $paramDetails['type'];
                    $actualValue = $toolParams[$paramName];

                    if ($expectedType === 'boolean') {
                        $convertedValue = self::validateAndConvertBoolean($actualValue);
                        $toolParams[$paramName] = $convertedValue;
                    } elseif (($expectedType === 'string' && !is_string($actualValue)) ||
                             ($expectedType === 'integer' && !is_int($actualValue))) {
                        $response['error'] = [
                            'code' => -32602,
                            'message' => "Invalid type for parameter: $paramName (expected $expectedType)"
                        ];
                        return $response;
                    }
                }

                try {
                    $callback = $tool['callback'];
                    $response['result'] = call_user_func($callback, $toolParams);
                } catch (Exception $e) {
                    $response['error'] = [
                        'code' => -32001,
                        'message' => $e->getMessage()
                    ];
                }
                return $response;

            case 'resources/read':
                if (!isset($params['uri']) || !isset($params['arguments'])) {
                    $response['error'] = [
                        'code' => -32602,
                        'message' => 'Missing required parameters: uri or arguments'
                    ];
                    return $response;
                }

                $resource = null;
                foreach (self::$resources as $res) {
                    if ($res['uri'] === $params['uri']) {
                        $resource = $res;
                        break;
                    }
                }

                if (!$resource) {
                    $response['error'] = [
                        'code' => -32601,
                        'message' => 'Resource not found'
                    ];
                    return $response;
                }

                $resParams = $params['arguments'];
                foreach ($resource['arguments'] as $argName => $argDetails) {
                    if ($argDetails['required'] && !array_key_exists($argName, $resParams)) {
                        $response['error'] = [
                            'code' => -32602,
                            'message' => "Missing required argument: $argName"
                        ];
                        return $response;
                    }

                    if (array_key_exists($argName, $resParams)) {
                        $expectedType = $argDetails['type'];
                        $actualValue = $resParams[$argName];

                        if ($expectedType === 'boolean') {
                            $convertedValue = self::validateAndConvertBoolean($actualValue);
                            $resParams[$argName] = $convertedValue;
                        } elseif (($expectedType === 'string' && !is_string($actualValue)) ||
                                 ($expectedType === 'integer' && !is_int($actualValue))) {
                            $response['error'] = [
                                'code' => -32602,
                                'message' => "Invalid type for argument: $argName (expected $expectedType)"
                            ];
                            return $response;
                        }
                    }
                }

                try {
                    $callback = $resource['callback'];
                    $response['result'] = [
                        'data' => call_user_func($callback, $resParams),
                        'mimeType' => $resource['mimeType']
                    ];
                } catch (Exception $e) {
                    $response['error'] = [
                        'code' => -32001,
                        'message' => $e->getMessage()
                    ];
                }
                return $response;

            case 'prompts/get':
                if (!isset($params['name']) || !isset(self::$prompts[$params['name']])) {
                    $response['error'] = [
                        'code' => -32601,
                        'message' => 'Prompt not found'
                    ];
                    return $response;
                }

                $prompt = self::$prompts[$params['name']];
                $promptParams = $params['arguments'] ?? [];

                foreach ($prompt['parameters'] as $paramName => $paramDetails) {
                    if (!array_key_exists($paramName, $promptParams)) {
                        $response['error'] = [
                            'code' => -32602,
                            'message' => "Missing required parameter: $paramName"
                        ];
                        return $response;
                    }

                    $expectedType = $paramDetails['type'];
                    $actualValue = $promptParams[$paramName];

                    if ($expectedType === 'boolean') {
                        $convertedValue = self::validateAndConvertBoolean($actualValue);
                        $promptParams[$paramName] = $convertedValue;
                    } elseif (($expectedType === 'string' && !is_string($actualValue)) ||
                             ($expectedType === 'integer' && !is_int($actualValue))) {
                        $response['error'] = [
                            'code' => -32602,
                            'message' => "Invalid type for parameter: $paramName (expected $expectedType)"
                        ];
                        return $response;
                    }
                }

                try {
                    $callback = $prompt['callback'];
                    $response['result'] = call_user_func($callback, $promptParams);
                } catch (Exception $e) {
                    $response['error'] = [
                        'code' => -32001,
                        'message' => $e->getMessage()
                    ];
                }
                return $response;

            default:
                $response['error'] = [
                    'code' => -32601,
                    'message' => 'Method not found'
                ];
                return $response;
        }
    }

    /**
     * Retrieves all registered tools, resources, and prompts (for internal use or debugging).
     * 
     * @return array Array of all registered entities.
     */
    public static function getAll() {
        return [
            'tools' => self::$tools,
            'resources' => self::$resources,
            'prompts' => self::$prompts
        ];
    }
}
?>
