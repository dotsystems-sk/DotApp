<?php
namespace Dotsystems\App\Parts;

class Validator {
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
            new DateTime($text);
            return true;
        } catch (Exception $e) {
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