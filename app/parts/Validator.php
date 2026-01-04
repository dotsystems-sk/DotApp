<?php
namespace Dotsystems\App\Parts;

class Validator {
    /**
     * Validates the input data against the provided rules.
     *
     * This method checks each field in the input array against the specified rules.
     * Rules are provided as strings separated by pipes (|), and each rule can have parameters
     * separated by colons (:) and commas (,) for multiple parameters.
     * If a field fails any rule, an error message is added to the result array.
     *
     * Supported rules (Laravel-style standardized):
     * - required, present (old: set), email, numeric (old: number), integer, positive_number
     * - between:min,max (old: in_range), min:val (old: min_length), max:val (old: max_length)
     * - url, alpha, alpha_num (old: alphanumeric), strong_password:special
     * - phone, date, in:val1,val2... (old: one_of), json, username:min,max,dash,dot
     * - boolean, credit_card, hex_color, ip, uuid, not_empty_array, valid_file_name
     * - regex:pattern, unique:key
     *
     * @param array $input Associative array of input data to validate, e.g., ['email' => 'info@dotsystems.sk'].
     * @param array $rules Associative array of validation rules, e.g., ['email' => 'required|email'].
     * @return bool|array Returns TRUE if validation passes, otherwise returns an associative array of errors.
     * @throws \InvalidArgumentException If an unknown rule is used or if rule parameters are invalid.
     *
     * @example
     * // Example 1: Simple email and password validation
     * $input = [
     * 'email' => 'info@dotsystems.sk',
     * 'password' => 'StrongPass1',
     * ];
     * $rules = [
     * 'email' => 'required|email',
     * 'password' => 'required|min:8|strong_password',
     * ];
     * $result = Validator::validate($input, $rules);
     * if ($result === true) {
     * // Validation passed
     * } else {
     * // $result contains errors
     * }
     *
     * @example
     * // Example 2: Validation with range and unique array
     * $input = [
     * 'age' => 25,
     * 'colors' => ['red', 'blue', 'green'],
     * ];
     * $rules = [
     * 'age' => 'integer|between:18,100|positive_number',
     * 'colors' => 'not_empty_array|unique',
     * ];
     * $result = Validator::validate($input, $rules);
     *
     * @example
     * // Example 3: Handling invalid input
     * $input = ['email' => 'invalid'];
     * $rules = ['email' => 'required|email'];
     * $result = Validator::validate($input, $rules);
     * // $result is now: ['email' => ['The email must be a valid email address.']]
     */

    /**
     * Validates input data against rules. 
     * * Mode 1 (Bulk): Validator::validate(['name' => 'John'], ['name' => 'required|min:3'])
     * Mode 2 (Single): Validator::validate("John", "required|min:3")
     *
     * @param mixed $input Associative array OR a single scalar value.
     * @param mixed $rules Associative array of rules OR a single rule string.
     * @return bool|array Returns TRUE if valid, otherwise an array of errors.
     * @throws \InvalidArgumentException If parameters are mismatched.
     */
    public static function validate($input,$rules) {
        if (!is_array($input) && is_string($rules)) {
            $input = ['data' => $input];
            $rules = ['data' => $rules];
        } 
        elseif (is_array($input) && is_array($rules)) {
            // OK, continue
        } 
        else {
            throw new \InvalidArgumentException(
                "Validator mismatch: Use either (array, array) for bulk validation or (scalar, string) for single validation."
            );
        }
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $value = $input[$field] ?? null;
            $subRules = explode('|', $ruleString);

            foreach ($subRules as $subRule) {
                $parts = explode(':', $subRule, 2);
                $ruleName = trim($parts[0]);
                $params = isset($parts[1]) ? array_map('trim', explode(',', $parts[1])) : [];

                $isValid = false;
                $message = null;

                switch ($ruleName) {
                    case 'required':
                        $isValid = self::isRequired($value);
                        $message = "The {$field} field is required.";
                        break;

                    case 'present': // Laravel style
                    case 'set':     // Keep for backward compatibility
                        $isValid = self::isSet($value);
                        $message = "The {$field} field must be present.";
                        break;

                    case 'email':
                        $isValid = self::isEmail($value);
                        $message = "The {$field} must be a valid email address.";
                        break;

                    case 'numeric': // Laravel style
                    case 'number':  // Keep for backward compatibility
                        $isValid = self::isNumber($value);
                        $message = "The {$field} must be a number.";
                        break;

                    case 'integer':
                        $isValid = self::isInteger($value);
                        $message = "The {$field} must be an integer.";
                        break;

                    case 'positive_number':
                        $isValid = self::isPositiveNumber($value);
                        $message = "The {$field} must be a positive number.";
                        break;

                    case 'between':  // Laravel style
                    case 'in_range': // Keep for backward compatibility
                        if (count($params) !== 2 || !is_numeric($params[0]) || !is_numeric($params[1])) {
                            throw new \InvalidArgumentException("between/in_range rule for {$field} requires exactly two numeric parameters: min,max.");
                        }
                        $min = (float)$params[0];
                        $max = (float)$params[1];
                        $isValid = self::isInRange($value, $min, $max);
                        $message = "The {$field} must be between {$min} and {$max}.";
                        break;

                    case 'min':        // Laravel style
                    case 'min_length': // Keep for backward compatibility
                        if (count($params) !== 1 || !is_numeric($params[0])) {
                            throw new \InvalidArgumentException("min rule for {$field} requires one integer parameter.");
                        }
                        $min = (int)$params[0];
                        $isValid = self::isMinLength($value, $min);
                        $message = "The {$field} must be at least {$min} characters long.";
                        break;

                    case 'max':        // Laravel style
                    case 'max_length': // Keep for backward compatibility
                        if (count($params) !== 1 || !is_numeric($params[0])) {
                            throw new \InvalidArgumentException("max rule for {$field} requires one integer parameter.");
                        }
                        $max = (int)$params[0];
                        $isValid = self::isMaxLength($value, $max);
                        $message = "The {$field} must not exceed {$max} characters.";
                        break;

                    case 'url':
                        $isValid = self::isUrl($value);
                        $message = "The {$field} must be a valid URL.";
                        break;

                    case 'alpha':
                        $isValid = self::isAlpha($value);
                        $message = "The {$field} must contain only alphabetic characters.";
                        break;

                    case 'alpha_num':   // Laravel style
                    case 'alphanumeric': // Keep for backward compatibility
                        $isValid = self::isAlphanumeric($value);
                        $message = "The {$field} must contain only alphanumeric characters.";
                        break;

                    case 'strong_password':
                        $special = count($params) > 0 && filter_var($params[0], FILTER_VALIDATE_BOOLEAN);
                        $isValid = self::isStrongPassword($value, $special);
                        $message = "The {$field} must be a strong password" . ($special ? " with special characters." : ".");
                        break;

                    case 'phone':
                        $isValid = self::isPhoneNumber($value);
                        $message = "The {$field} must be a valid phone number.";
                        break;

                    case 'date':
                        $isValid = self::isDate($value);
                        $message = "The {$field} must be a valid date.";
                        break;

                    case 'in':     // Laravel style
                    case 'one_of': // Keep for backward compatibility
                        if (count($params) < 1) {
                            throw new \InvalidArgumentException("in/one_of rule for {$field} requires at least one parameter.");
                        }
                        $isValid = self::isOneOf($value, $params);
                        $message = "The {$field} must be one of: " . implode(', ', $params) . ".";
                        break;

                    case 'json':
                        $isValid = self::isJson($value);
                        $message = "The {$field} must be valid JSON.";
                        break;

                    case 'username':
                        $minLength = count($params) > 0 && is_numeric($params[0]) ? (int)$params[0] : 3;
                        $maxLength = count($params) > 1 && is_numeric($params[1]) ? (int)$params[1] : 20;
                        $allowDash = count($params) > 2 ? filter_var($params[2], FILTER_VALIDATE_BOOLEAN) : false;
                        $allowDot = count($params) > 3 ? filter_var($params[3], FILTER_VALIDATE_BOOLEAN) : false;
                        $isValid = self::isUsername($value, $minLength, $maxLength, $allowDash, $allowDot);
                        $message = "The {$field} must be a valid username.";
                        break;

                    case 'boolean':
                        $isValid = self::isBoolean($value);
                        $message = "The {$field} must be a boolean.";
                        break;

                    case 'credit_card':
                        $isValid = self::isCreditCard($value);
                        $message = "The {$field} must be a valid credit card number.";
                        break;

                    case 'hex_color':
                        $isValid = self::isHexColor($value);
                        $message = "The {$field} must be a valid hex color code.";
                        break;

                    case 'ip':
                        $isValid = self::isIpAddress($value);
                        $message = "The {$field} must be a valid IP address.";
                        break;

                    case 'uuid':
                        $isValid = self::isUuid($value);
                        $message = "The {$field} must be a valid UUID.";
                        break;

                    case 'not_empty_array':
                        $isValid = self::isNotEmptyArray($value);
                        $message = "The {$field} must be a non-empty array.";
                        break;

                    case 'valid_file_name':
                        $isValid = self::isValidFileName($value);
                        $message = "The {$field} must be a valid file name.";
                        break;

                    case 'regex':
                        if (count($params) !== 1) {
                            throw new \InvalidArgumentException("regex rule for {$field} requires exactly one parameter: pattern.");
                        }
                        $regex = $params[0];
                        $isValid = self::isMatchingRegex($value, $regex);
                        $message = "The {$field} must match the regex pattern: {$regex}.";
                        break;

                    case 'unique':
                        if (count($params) > 1) {
                            throw new \InvalidArgumentException("unique rule for {$field} accepts at most one parameter: key.");
                        }
                        $key = count($params) > 0 ? $params[0] : null;
                        $isValid = self::isUniqueInArray($value, $key);
                        $message = "The {$field} must contain unique values" . ($key ? " by key '{$key}'." : ".");
                        break;

                    default:
                        throw new \InvalidArgumentException("Unknown validation rule '{$ruleName}' for field '{$field}'.");
                }

                if (!$isValid) {
                    $errors[$field][] = $message ?? "Validation failed for {$field} with rule '{$ruleName}'.";
                }
            }
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Checks if the provided text is a valid email address.
     *
     * @param mixed $text The text to validate.
     * @return bool True if the text is a valid email, false otherwise.
     */
    public static function isEmail($text): bool {
        return is_string($text) && preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $text) === 1;
    }

    /**
     * Checks if the provided text is a non-empty string after trimming.
     *
     * @param mixed $text The text to validate.
     * @return bool True if the text is a non-empty string, false otherwise.
     */
    public static function isRequired($text): bool {
        return is_string($text) && strlen(trim($text)) > 0;
    }

    /**
     * Checks if the provided value is a valid number (not NaN).
     *
     * @param mixed $value The value to validate.
     * @return bool True if the value is a number, false otherwise.
     */
    public static function isNumber($value): bool {
        return is_numeric($value) && is_float($value + 0) && !is_nan($value + 0);
    }

    /**
     * Checks if the provided value is an integer.
     *
     * @param mixed $value The value to validate.
     * @return bool True if the value is an integer, false otherwise.
     */
    public static function isInteger($value): bool
    {
        return is_int($value) || (is_numeric($value) && (int)$value == $value);
    }

    /**
     * Checks if the provided value is a number within the specified range.
     *
     * @param mixed $value The value to validate.
     * @param float $min The minimum value (inclusive).
     * @param float $max The maximum value (inclusive).
     * @return bool True if the value is a number in range, false otherwise.
     */
    public static function isInRange($value, float $min, float $max): bool
    {
        return is_numeric($value) && !is_nan($value + 0) && $value >= $min && $value <= $max;
    }

    /**
     * Checks if the provided text meets the minimum length requirement after trimming.
     *
     * @param mixed $text The text to validate.
     * @param int $min The minimum length.
     * @return bool True if the text length is at least $min, false otherwise.
     */
    public static function isMinLength($text, int $min): bool
    {
        return is_string($text) && strlen(trim($text)) >= $min;
    }

    /**
     * Checks if the provided text does not exceed the maximum length after trimming.
     *
     * @param mixed $text The text to validate.
     * @param int $max The maximum length.
     * @return bool True if the text length is at most $max, false otherwise.
     */
    public static function isMaxLength($text, int $max): bool
    {
        return is_string($text) && strlen(trim($text)) <= $max;
    }

    /**
     * Checks if the provided text is a valid URL.
     *
     * @param mixed $text The text to validate.
     * @return bool True if the text is a valid URL, false otherwise.
     */
    public static function isUrl($text): bool
    {
        return is_string($text) && preg_match('/^(https?:\/\/)?([\da-z.-]+)\.([a-z.]{2,6})([\/\w .-]*)*\/?$/', $text) === 1;
    }

    /**
     * Checks if the provided text contains only alphabetic characters.
     *
     * @param mixed $text The text to validate.
     * @return bool True if the text is alphabetic, false otherwise.
     */
    public static function isAlpha($text): bool
    {
        return is_string($text) && preg_match('/^[a-zA-Z]+$/', $text) === 1;
    }

    /**
     * Checks if the provided text contains only alphanumeric characters.
     *
     * @param mixed $text The text to validate.
     * @return bool True if the text is alphanumeric, false otherwise.
     */
    public static function isAlphanumeric($text): bool
    {
        return is_string($text) && preg_match('/^[a-zA-Z0-9]+$/', $text) === 1;
    }

    /**
     * Checks if the provided text is a strong password (min 8 chars, with uppercase and digit).
     *
     * @param mixed $text The password to validate.
     * @param bool $special Whether special characters are required.
     * @return bool True if the password meets the criteria, false otherwise.
     */
    public static function isStrongPassword($text, bool $special = false): bool {
        if (!is_string($text)) {
            return false;
        }
        $basePattern = '/^(?=.*[A-Z])(?=.*\d).{8,}$/';
        $specialPattern = '/^(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/';
        return preg_match($special ? $specialPattern : $basePattern, $text) === 1;
    }

    /**
     * Checks if the provided text is a valid phone number.
     *
     * @param mixed $text The text to validate.
     * @return bool True if the text is a valid phone number, false otherwise.
     */
    public static function isPhoneNumber($text): bool
    {
        return is_string($text) && preg_match('/^\+?[\d\s-]{9,}$/', $text) === 1;
    }

    /**
     * Checks if the provided text is a valid date string.
     *
     * @param mixed $text The text to validate.
     * @return bool True if the text is a valid date, false otherwise.
     */
    public static function isDate($text): bool
    {
        if (!is_string($text)) {
            return false;
        }
        try {
            new \DateTime($text);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Checks if the provided value is one of the allowed values.
     *
     * @param mixed $value The value to check.
     * @param array $allowedValues The array of allowed values.
     * @return bool True if the value is in the allowed list, false otherwise.
     */
    public static function isOneOf($value, array $allowedValues): bool
    {
        return in_array($value, $allowedValues, true);
    }

    /**
     * Checks if the provided text is a valid JSON string.
     *
     * @param mixed $text The text to validate.
     * @return bool True if the text is valid JSON, false otherwise.
     */
    public static function isJson($text): bool
    {
        if (!is_string($text)) {
            return false;
        }
        json_decode($text);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Checks if the provided text is a valid username.
     *
     * @param mixed $text The text to validate.
     * @param int $minLength Minimum length of the username (default: 3).
     * @param int $maxLength Maximum length of the username (default: 20).
     * @param bool $allowDash Whether dashes are allowed (default: false).
     * @param bool $allowDot Whether dots are allowed (default: false).
     * @return bool True if the text is a valid username, false otherwise.
     */
    public static function isUsername($text, int $minLength = 3, int $maxLength = 20, bool $allowDash = false, bool $allowDot = false): bool
    {
        if (!is_string($text)) {
            return false;
        }
        $pattern = '/^[a-zA-Z0-9_';
        if ($allowDash && $allowDot) {
            $pattern .= '.-';
        } elseif ($allowDash) {
            $pattern .= '-';
        } elseif ($allowDot) {
            $pattern .= '.';
        }
        $pattern .= ']+$/';
        return strlen($text) >= $minLength &&
               strlen($text) <= $maxLength &&
               preg_match($pattern, $text) === 1 &&
               !preg_match('/^[_.-]/', $text) &&
               !preg_match('/[_.-]$/', $text);
    }

    /**
     * Checks if the provided value is a boolean.
     *
     * @param mixed $value The value to validate.
     * @return bool True if the value is a boolean, false otherwise.
     */
    public static function isBoolean($value): bool
    {
        return is_bool($value);
    }

    /**
     * Checks if the provided text is a valid credit card number using the Luhn algorithm.
     *
     * @param mixed $text The text to validate.
     * @return bool True if the text is a valid credit card number, false otherwise.
     */
    public static function isCreditCard($text): bool
    {
        if (!is_string($text)) {
            return false;
        }
        $cleaned = preg_replace('/\D/', '', $text);
        if (!preg_match('/^\d{13,19}$/', $cleaned)) {
            return false;
        }
        $sum = 0;
        $isEven = false;
        for ($i = strlen($cleaned) - 1; $i >= 0; $i--) {
            $digit = (int)$cleaned[$i];
            if ($isEven) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
            $isEven = !$isEven;
        }
        return $sum % 10 === 0;
    }

    /**
     * Checks if the provided text is a valid hex color code.
     *
     * @param mixed $text The text to validate.
     * @return bool True if the text is a valid hex color, false otherwise.
     */
    public static function isHexColor($text): bool
    {
        return is_string($text) && preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', $text) === 1;
    }

    /**
     * Checks if the provided text is a valid IPv4 or IPv6 address.
     *
     * @param mixed $text The text to validate.
     * @return bool True if the text is a valid IP address, false otherwise.
     */
    public static function isIpAddress($text): bool
    {
        if (!is_string($text)) {
            return false;
        }
        $ipv4Pattern = '/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/';
        $ipv6Pattern = '/^([0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}$/';
        return preg_match($ipv4Pattern, $text) === 1 || preg_match($ipv6Pattern, $text) === 1;
    }

    /**
     * Checks if the provided text is a valid UUID (version 4).
     *
     * @param mixed $text The text to validate.
     * @return bool True if the text is a valid UUID, false otherwise.
     */
    public static function isUuid($text): bool
    {
        return is_string($text) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $text) === 1;
    }

    /**
     * Checks if the provided value is a non-empty array.
     *
     * @param mixed $value The value to validate.
     * @return bool True if the value is a non-empty array, false otherwise.
     */
    public static function isNotEmptyArray($value): bool
    {
        return is_array($value) && count($value) > 0;
    }

    /**
     * Checks if the provided text is a valid file name.
     *
     * @param mixed $text The text to validate.
     * @return bool True if the text is a valid file name, false otherwise.
     */
    public static function isValidFileName($text): bool
    {
        return is_string($text) &&
               preg_match('/^[a-zA-Z0-9._-]+$/', $text) === 1 &&
               !preg_match('/^\./', $text) &&
               !preg_match('/[\/\\\\:*?"<>|]/', $text);
    }

    /**
     * Checks if the provided value is a positive number.
     *
     * @param mixed $value The value to validate.
     * @return bool True if the value is a positive number, false otherwise.
     */
    public static function isPositiveNumber($value): bool
    {
        return is_numeric($value) && !is_nan($value + 0) && $value > 0;
    }

    /**
     * Checks if the provided text matches the given regular expression.
     *
     * @param mixed $text The text to validate.
     * @param string $regex The regular expression pattern.
     * @return bool True if the text matches the regex, false otherwise.
     */
    public static function isMatchingRegex($text, string $regex): bool
    {
        if (!is_string($text)) {
            return false;
        }
        return preg_match($regex, $text) === 1;
    }

    /**
     * Checks if all values in the array are unique, optionally by a key.
     *
     * @param mixed $array The array to check.
     * @param string|null $key The key to check for uniqueness (optional).
     * @return bool True if all values are unique, false otherwise.
     */
    public static function isUniqueInArray($array, ?string $key = null): bool
    {
        if (!is_array($array)) {
            return false;
        }
        $values = $key !== null ? array_column($array, $key) : $array;
        return count($values) === count(array_unique($values));
    }

    /**
     * Checks if the provided value is set (not empty, null, or undefined).
     *
     * @param mixed $value The value to validate.
     * @return bool True if the value is set, false otherwise.
     */
    public static function isSet($value): bool
    {
        return $value !== "" && $value !== null && isset($value);
    }
}

?>
