<?php

/**
 * MCP (Model Context Protocol) Library
 * 
 * This class provides a robust implementation of a Model Context Protocol (MCP) server 
 * within the DotApp framework. It allows developers to define and manage tools that can 
 * be accessed by AI models or other clients via standardized JSON-RPC requests. Each tool 
 * is registered with a callback function that defines its logic, enabling seamless 
 * integration with external systems for automation, data access, or device control.
 * 
 * The library is designed to be extensible, secure, and easy to integrate into the 
 * DotApp architecture. It leverages static storage for tools, ensuring that tools and 
 * their callbacks are globally accessible and can be registered at runtime. The library 
 * does not define endpoints directly, leaving routing to the developer.
 * 
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.7
 * @license   MIT License
 * @date      2025
 * 
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the 
 * following condition: You **must** retain this header in all copies or 
 * substantial portions of the code, including the author and company information.
 */

namespace Dotsystems\App\Parts;

use Dotsystems\App\DotApp;

class MCP {
    /**
     * Static storage for registered tools.
     * 
     * This array holds all tools registered via the addTool method. Each tool is stored 
     * as an associative array with details such as name, description, parameters, 
     * authentication requirements, and the callback function to execute the tool's logic.
     * 
     * @var array
     */
    private static $tools = [];

    /**
     * Adds a tool to the MCP server.
     * 
     * This static method registers a new tool with its details and a callback function 
     * that defines the tool's logic. The tool is stored in the static $tools array and 
     * can be retrieved via the discovery method or executed via the execute method.
     * 
     * @param string $name Unique name of the tool (e.g., "send_email", "turn_on_light").
     * @param string $description Description of what the tool does.
     * @param array $parameters Associative array of parameters (name => details).
     *                          Each parameter should have 'type' and 'description'.
     *                          Example: ["room" => ["type" => "string", "description" => "Room name"]]
     * @param callable $callback Callback function to execute the tool's logic.
     *                          Signature: function(array $params): array
     * @param array|null $authentication Optional authentication details (e.g., type, description).
     *                                   Example: ["type" => "bearer", "description" => "Requires Bearer token"]
     * @return bool True on successful addition, false if tool already exists or invalid.
     * 
     * Example:
     * MCP::addTool(
     *     "send_email",
     *     "Sends an email to a specified recipient",
     *     [
     *         "to_email" => ["type" => "string", "description" => "Recipient email address"],
     *         "subject" => ["type" => "string", "description" => "Email subject"],
     *         "password" => ["type" => "string", "description" => "Password for authentication"]
     *     ],
     *     function(array $params) {
     *         if ($params['password'] !== '888') {
     *             throw new Exception('Invalid password');
     *         }
     *         // Logic to send email
     *         return ['status' => 'success', 'message' => 'Email sent to ' . $params['to_email']];
     *     },
     *     ["type" => "password", "description" => "Prompt user for password. Password is 888."]
     * );
     */
    public static function addTool($name, $description, $parameters, $callback, $authentication = null) {
        if (!is_callable($callback)) $callback = DotApp::DotApp()->stringToCallable($callback);
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
     * Returns the list of registered tools.
     * 
     * This method resolves to the /mcp/discovery endpoint and returns a JSON-compatible 
     * array containing metadata for all registered tools (excluding callbacks). The 
     * response follows the MCP discovery format, enabling AI clients to understand 
     * available tools and their requirements.
     * 
     * @return array JSON-compatible array with tools, resources, and prompts.
     * 
     * Example Response:
     * {
     *     "tools": [
     *         {
     *             "name": "send_email",
     *             "description": "Sends an email to a specified recipient",
     *             "parameters": {
     *                 "to_email": {"type": "string", "description": "Recipient email address"},
     *                 "subject": {"type": "string", "description": "Email subject"},
     *                 "password": {"type": "string", "description": "Password for authentication"}
     *             },
     *             "authentication": {
     *                 "type": "password",
     *                 "description": "Prompt user for password. Password is 888."
     *             }
     *         }
     *     ],
     *     "resources": [],
     *     "prompts": []
     * }
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

        return [
            'tools' => array_values($tools),
            'resources' => [],
            'prompts' => []
        ];
    }

    /**
     * Executes a tool based on the provided JSON-RPC request.
     * 
     * This method resolves to the /mcp endpoint and processes incoming JSON-RPC requests. 
     * It validates the request structure, checks if the requested tool exists, and executes 
     * the tool's registered callback function with the provided parameters. If the tool 
     * requires authentication, the callback is responsible for validating it.
     * 
     * @param array $request JSON-RPC request array with 'jsonrpc', 'id', 'method', and 'params'.
     * @return array JSON-RPC response with 'result' or 'error'.
     * 
     * Example Request:
     * {
     *     "jsonrpc": "2.0",
     *     "id": "req-1",
     *     "method": "tool.send_email",
     *     "params": {
     *         "to_email": "user@example.com",
     *         "subject": "Test",
     *         "password": "888"
     *     }
     * }
     * 
     * Example Response (Success):
     * {
     *     "jsonrpc": "2.0",
     *     "id": "req-1",
     *     "result": {
     *         "status": "success",
     *         "message": "Email sent to user@example.com"
     *     }
     * }
     * 
     * Example Response (Error):
     * {
     *     "jsonrpc": "2.0",
     *     "id": "req-1",
     *     "error": {
     *         "code": -32601,
     *         "message": "Method not found"
     *     }
     * }
     */
    public static function execute($request) {
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

        $toolName = str_replace('tool.', '', $method);
        if (!isset(self::$tools[$toolName])) {
            $response['error'] = [
                'code' => -32601,
                'message' => 'Method not found'
            ];
            return $response;
        }

        $tool = self::$tools[$toolName];

        foreach ($tool['parameters'] as $paramName => $paramDetails) {
            if (!array_key_exists($paramName, $params)) {
                $response['error'] = [
                    'code' => -32602,
                    'message' => "Missing required parameter: $paramName"
                ];
                return $response;
            }

            $expectedType = $paramDetails['type'];
            $actualValue = $params[$paramName];
            if (($expectedType === 'string' && !is_string($actualValue)) ||
                ($expectedType === 'integer' && !is_int($actualValue)) ||
                ($expectedType === 'boolean' && !is_bool($actualValue))) {
                $response['error'] = [
                    'code' => -32602,
                    'message' => "Invalid type for parameter: $paramName (expected $expectedType)"
                ];
                return $response;
            }
        }

        try {
            $callback = $tool['callback'];
            $result = call_user_func($callback, $params);
            $response['result'] = $result;
        } catch (Exception $e) {
            $response['error'] = [
                'code' => -32001,
                'message' => $e->getMessage()
            ];
        }

        return $response;
    }

    /**
     * Retrieves all registered tools (for internal use or debugging).
     * 
     * @return array Array of all tools stored in the static $tools variable (including callbacks).
     */
    public static function getTools() {
        return self::$tools;
    }
}
?>