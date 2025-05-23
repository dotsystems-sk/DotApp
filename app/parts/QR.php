<?php
namespace Dotsystems\App\Parts;
use \Dotsystems\App\Parts\Config;

class QR {
    private $data;
    private $size;
    private $qrMatrix;
    const RETURN_IMGDATA = 1; // Vráti obrazový zdroj ($image)
    const RETURN_IMG = 2;     // Vypíše obrázok do prehliadača

    public function __construct($data) {
        if (empty($data)) {
            throw new InvalidArgumentException('QR code data cannot be empty');
        }
        $this->data = $data;
        $this->size = 21; // Veľkosť pre QR kód verzie 1 (Level L)
        $this->qrMatrix = array_fill(0, $this->size, array_fill(0, $this->size, 0));
    }

    /**
     * Statická metóda na vytvorenie a generovanie QR kódu
     * @param string $data Dáta pre QR kód
     * @return self Vráti inštanciu pre reťazové volanie
     * @throws InvalidArgumentException
     */
    public static function generate($data) {
        $instance = new self($data);
        $instance->addFinderPatterns();
        $instance->addAlignmentPatterns();
        $instance->addTimingPatterns();
        $instance->encodeData();
        $instance->applyMask();
        return $instance;
    }

    private function addFinderPatterns() {
        $finder = [
            [1, 1, 1, 1, 1, 1, 1],
            [1, 0, 0, 0, 0, 0, 1],
            [1, 0, 1, 1, 1, 0, 1],
            [1, 0, 1, 0, 1, 0, 1],
            [1, 0, 1, 1, 1, 0, 1],
            [1, 0, 0, 0, 0, 0, 1],
            [1, 1, 1, 1, 1, 1, 1]
        ];

        for ($i = 0; $i < 7; $i++) {
            for ($j = 0; $j < 7; $j++) {
                $this->qrMatrix[$i][$j] = $finder[$i][$j];
                $this->qrMatrix[$i][$this->size - 7 + $j] = $finder[$i][$j];
                $this->qrMatrix[$this->size - 7 + $i][$j] = $finder[$i][$j];
            }
        }

        for ($i = 0; $i < 8; $i++) {
            $this->qrMatrix[$i][7] = 0;
            $this->qrMatrix[$i][$this->size - 8] = 0;
            $this->qrMatrix[7][$i] = 0;
            $this->qrMatrix[$this->size - 8][$i] = 0;
            $this->qrMatrix[$this->size - 8 + $i][7] = 0;
            $this->qrMatrix[$i][8] = 0;
            $this->qrMatrix[$this->size - 8 + $i][8] = 0;
        }
    }

    private function addAlignmentPatterns() {
        // Pre verziu 1 sa alignment patterny nepridávajú
    }

    private function addTimingPatterns() {
        for ($i = 8; $i < $this->size - 8; $i++) {
            $this->qrMatrix[6][$i] = ($i % 2 == 0) ? 1 : 0;
            $this->qrMatrix[$i][6] = ($i % 2 == 0) ? 1 : 0;
        }
    }

    private function encodeData() {
        $dataBits = $this->getDataBits();
        $bitIndex = 0;
        $col = $this->size - 1;
        $row = $this->size - 1;
        $up = true;

        while ($col >= 0) {
            if ($col == 6) $col--;
            for ($i = 0; $i < $this->size; $i++) {
                $r = $up ? $row-- : $row++;
                if ($r < 0 || $r >= $this->size) break;

                for ($c = 0; $c < 2; $c++) {
                    $currentCol = $col - $c;
                    if ($this->isFunctionModule($r, $currentCol)) continue;

                    if ($bitIndex < count($dataBits)) {
                        $this->qrMatrix[$r][$currentCol] = $dataBits[$bitIndex];
                        $bitIndex++;
                    }
                }
            }
            $col -= 2;
            $up = !$up;
            $row = $up ? $this->size - 1 : 0;
        }
    }

    private function getDataBits() {
        $bits = [];
        $bits = array_merge($bits, [0, 1, 0, 0]); // Mode indicator (alphanumeric)

        $length = strlen($this->data);
        if ($length > 13) { // Verzia 1, Level L: max 13 znakov pre alfanumerické
            throw new InvalidArgumentException('Data too long for QR code version 1');
        }
        $lengthBits = str_pad(decbin($length), 9, '0', STR_PAD_LEFT);
        for ($i = 0; $i < 9; $i++) {
            $bits[] = (int)$lengthBits[$i];
        }

        $chars = strtoupper($this->data);
        $alphaNum = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';
        for ($i = 0; $i < $length; $i += 2) {
            if ($i + 1 < $length) {
                $val = strpos($alphaNum, $chars[$i]) * 45 + strpos($alphaNum, $chars[$i + 1]);
                $bin = str_pad(decbin($val), 11, '0', STR_PAD_LEFT);
                for ($j = 0; $j < 11; $j++) {
                    $bits[] = (int)$bin[$j];
                }
            } else {
                $val = strpos($alphaNum, $chars[$i]);
                $bin = str_pad(decbin($val), 6, '0', STR_PAD_LEFT);
                for ($j = 0; $j < 6; $j++) {
                    $bits[] = (int)$bin[$j];
                }
            }
        }

        $bits = array_slice($bits, 0, 152);
        return $bits;
    }

    private function isFunctionModule($row, $col) {
        if (($row < 8 && $col < 8) || ($row < 8 && $col >= $this->size - 8) || ($row >= $this->size - 8 && $col < 8)) {
            return true;
        }
        if ($row == 6 || $col == 6) {
            return true;
        }
        return false;
    }

    private function applyMask() {
        for ($i = 0; $i < $this->size; $i++) {
            for ($j = 0; $j < $this->size; $j++) {
                if ($this->isFunctionModule($i, $j)) continue;
                if (($i + $j) % 2 == 0) {
                    $this->qrMatrix[$i][$j] = $this->qrMatrix[$i][$j] ^ 1;
                }
            }
        }
    }

    /**
     * Vytvorí PNG obrázok QR kódu
     * @param int $pixelSize Veľkosť modulu v pixeloch
     * @param int $margin Okraj v moduloch
     * @param int|null $returnType Typ návratu (RETURN_IMGDATA, RETURN_IMG, alebo null pre výstup)
     * @return resource|GdImage|null Obrazový zdroj alebo null
     * @throws RuntimeException
     */
    public function outputPNG($pixelSize = 10, $margin = 4, $returnType = null) {
        $imageSize = ($this->size + $margin * 2) * $pixelSize;
        $image = imagecreate($imageSize, $imageSize);
        if (!$image) {
            throw new RuntimeException('Failed to create image');
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);

        for ($i = 0; $i < $this->size; $i++) {
            for ($j = 0; $j < $this->size; $j++) {
                if ($this->qrMatrix[$i][$j]) {
                    $x = ($j + $margin) * $pixelSize;
                    $y = ($i + $margin) * $pixelSize;
                    imagefilledrectangle($image, $x, $y, $x + $pixelSize - 1, $y + $pixelSize - 1, $black);
                }
            }
        }

        if ($returnType === self::RETURN_IMGDATA) {
            return $image; // Vráti obrazový zdroj bez zničenia
        } else {
            if (headers_sent()) {
                imagedestroy($image);
                throw new RuntimeException('Headers already sent, cannot output image');
            }
            header('Content-Type: image/png');
            imagepng($image);
            imagedestroy($image);
            return null;
        }
    }
}

?>