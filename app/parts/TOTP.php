<?php
namespace Dotsystems\App\Parts;
use Dotsystems\App\Parts\Config;

class TOTP
{
    /**
     * Generuje TOTP kód
     * @param string $secret Base32 zakódovaný tajný kľúč
     * @param int|null $timeSlice Časový úsek (predvolené: aktuálny čas / 30)
     * @param int $digits Počet číslic v kóde (predvolené: 6)
     * @param string $hashAlgorithm Hashovací algoritmus (predvolené: sha1)
     * @return string TOTP kód
     */
    public static function generate($secret, $timeSlice = null, $digits = 6, $hashAlgorithm = 'sha1') {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        $secretKey = self::base32_decode($secret);
        $time = pack('N*', 0, $timeSlice);
        $hash = hash_hmac($hashAlgorithm, $time, $secretKey, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $code = unpack('N', substr($hash, $offset, 4))[1];
        $code = $code & 0x7FFFFFFF;
        $code = str_pad($code % pow(10, $digits), $digits, '0', STR_PAD_LEFT);

        return $code;
    }

    /**
     * Vytvorí otpauth URI pre TOTP
     * @param string $name Názov účtu
     * @param string $secret Base32 zakódovaný tajný kľúč
     * @return string otpauth URI
     */
    public static function otpauth($name, $secret) {
        return "otpauth://totp/" . urlencode($name) . "?secret=" . $secret . "&issuer=" . urlencode(Config::totp("issuer"));
    }

    /**
     * Generuje nový base32 zakódovaný tajný kľúč
     * @param int $length Dĺžka kľúča v bajtoch pred kódovaním (predvolené: 20)
     * @return string Base32 zakódovaný tajný kľúč
     * @throws RuntimeException
     */
    public static function newSecret($length = 20) {
        $validChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';

        try {
            for ($i = 0; $i < $length; $i++) {
                $secret .= $validChars[random_int(0, strlen($validChars) - 1)];
            }
        } catch (Exception $e) {
            throw new RuntimeException('Failed to generate random secret: ' . $e->getMessage());
        }

        return self::base32_encode($secret);
    }

    /**
     * Dekóduje base32 reťazec
     * @param string $base32 Base32 zakódovaný reťazec
     * @return string Dekódovaný reťazec
     */
    private static function base32_decode($base32) {
        $base32chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
        $base32charsFlipped = array_flip(str_split($base32chars));
        $output = '';
        $buffer = 0;
        $bufferSize = 0;

        foreach (str_split($base32) as $char) {
            if (!isset($base32charsFlipped[$char])) {
                continue;
            }
            $buffer = ($buffer << 5) | $base32charsFlipped[$char];
            $bufferSize += 5;
            if ($bufferSize >= 8) {
                $output .= chr(($buffer >> ($bufferSize - 8)) & 255);
                $bufferSize -= 8;
            }
        }

        return $output;
    }

    /**
     * Kóduje reťazec do base32
     * @param string $data Vstupný reťazec
     * @return string Base32 zakódovaný reťazec
     */
    private static function base32_encode($data) {
        $base32chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
        $base32 = "";
        $binary = "";

        for ($i = 0; $i < strlen($data); $i++) {
            $binary .= str_pad(base_convert(bin2hex($data[$i]), 16, 2), 8, '0', STR_PAD_LEFT);
        }

        $binary = str_pad($binary, ceil(strlen($binary) / 5) * 5, '0');
        for ($i = 0; $i < strlen($binary); $i += 5) {
            $base32 .= $base32chars[bindec(substr($binary, $i, 5))];
        }

        return $base32;
    }
}
?>