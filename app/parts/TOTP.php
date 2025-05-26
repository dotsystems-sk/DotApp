<?php
namespace Dotsystems\App\Parts;
use Dotsystems\App\Parts\Config;

class TOTP
{
    /**
     * Generates a TOTP code
     * @param string $secret Base32 encoded secret key
     * @param int|null $timeSlice Time slice (default: current time / 30)
     * @param int $digits Number of digits in the code (default: 6)
     * @param string $hashAlgorithm Hash algorithm (default: sha1)
     * @return string TOTP code
     * @throws \InvalidArgumentException
     */
    public static function generate($secret, $timeSlice = null, $digits = 6, $hashAlgorithm = 'sha1') {
        // Input validation
        if (empty($secret)) {
            throw new \InvalidArgumentException('Secret key cannot be empty');
        }
        if (!in_array($hashAlgorithm, hash_hmac_algos())) {
            throw new \InvalidArgumentException('Unsupported hash algorithm: ' . $hashAlgorithm);
        }
        if ($digits < 6 || $digits > 8) {
            throw new \InvalidArgumentException('Number of digits must be between 6 and 8');
        }

        // Store original timezone
        $originalTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');

        try {
            if ($timeSlice === null) {
                $timeSlice = floor(time() / 30);
            }

            $secretKey = self::base32_decode($secret);
            if ($secretKey === false) {
                throw new \InvalidArgumentException('Invalid Base32 secret key');
            }

            $time = pack('N*', 0, $timeSlice);
            $hash = hash_hmac($hashAlgorithm, $time, $secretKey, true);
            $offset = ord(substr($hash, -1)) & 0x0F;
            $code = unpack('N', substr($hash, $offset, 4))[1];
            $code = $code & 0x7FFFFFFF;
            $code = str_pad($code % pow(10, $digits), $digits, '0', STR_PAD_LEFT);

            return $code;
        } finally {
            // Always restore original timezone
            date_default_timezone_set($originalTimezone);
        }
    }

    /**
     * Creates an otpauth URI for TOTP
     * @param string $name Account name
     * @param string $secret Base32 encoded secret key
     * @return string otpauth URI
     * @throws \InvalidArgumentException
     */
    public static function otpauth($name, $secret) {
        if (empty($name) || empty($secret)) {
            throw new \InvalidArgumentException('Account name and secret key must not be empty');
        }
        $issuer = is_string(Config::totp('issuer')) && !empty(Config::totp('issuer')) ? Config::totp('issuer') : 'DefaultIssuer';
        return 'otpauth://totp/' . urlencode($name) . '?secret=' . urlencode($secret) . '&issuer=' . urlencode($issuer);
    }

    /**
     * Generates a new Base32 encoded secret key
     * @param int $length Length of the key in bytes before encoding (default: 20)
     * @return string Base32 encoded secret key
     * @throws \RuntimeException
     */
    public static function newSecret($length = 20) {
        if ($length < 16) {
            throw new \InvalidArgumentException('Secret key length must be at least 16 bytes');
        }
        try {
            $bytes = random_bytes($length);
            return self::base32_encode($bytes);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to generate random secret key: ' . $e->getMessage());
        }
    }

    /**
     * Decodes a Base32 string
     * @param string $base32 Base32 encoded string
     * @return string|bool Decoded string or false on error
     */
    private static function base32_decode($base32) {
        if (empty($base32) || !preg_match('/^[A-Z2-7]+$/', $base32) || strlen($base32) % 8 !== 0) {
            return false;
        }

        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32charsFlipped = array_flip(str_split($base32chars));
        $output = '';
        $buffer = 0;
        $bufferSize = 0;

        foreach (str_split($base32) as $char) {
            $value = $base32charsFlipped[$char];
            $buffer = ($buffer << 5) | $value;
            $bufferSize += 5;
            if ($bufferSize >= 8) {
                $output .= chr(($buffer >> ($bufferSize - 8)) & 0xFF);
                $bufferSize -= 8;
            }
        }

        return $output;
    }

    /**
     * Encodes a string to Base32
     * @param string $data Input string
     * @return string Base32 encoded string
     */
    private static function base32_encode($data) {
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $buffer = 0;
        $bufferSize = 0;

        foreach (str_split($data) as $char) {
            $buffer = ($buffer << 8) | ord($char);
            $bufferSize += 8;
            while ($bufferSize >= 5) {
                $output .= $base32chars[($buffer >> ($bufferSize - 5)) & 0x1F];
                $bufferSize -= 5;
            }
        }

        if ($bufferSize > 0) {
            $output .= $base32chars[($buffer << (5 - $bufferSize)) & 0x1F];
        }

        return $output;
    }
}

?>