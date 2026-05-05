<?php

declare(strict_types=1);

namespace App\Services;

final class QrCodeService
{
    private const VERSION = 6;
    private const SIZE = 41;
    private const DATA_CODEWORDS = 108;
    private const EC_CODEWORDS_PER_BLOCK = 16;
    private const BLOCK_COUNT = 4;

    /** @var array<int, array<int, bool|null>> */
    private array $modules = [];

    /** @var array<int, array<int, bool>> */
    private array $reserved = [];

    public function png(string $text, int $scale = 6, int $quietZone = 4): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        $bytes = array_values(unpack('C*', $text) ?: []);

        if (count($bytes) > 100) {
            return null;
        }

        $this->buildMatrix($this->finalCodewords($bytes));

        $pixels = (self::SIZE + $quietZone * 2) * $scale;
        $image = imagecreatetruecolor($pixels, $pixels);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 17, 17, 17);
        imagefill($image, 0, 0, $white);

        for ($y = 0; $y < self::SIZE; $y++) {
            for ($x = 0; $x < self::SIZE; $x++) {
                if (($this->modules[$y][$x] ?? false) !== true) {
                    continue;
                }

                imagefilledrectangle(
                    $image,
                    ($x + $quietZone) * $scale,
                    ($y + $quietZone) * $scale,
                    ($x + $quietZone + 1) * $scale - 1,
                    ($y + $quietZone + 1) * $scale - 1,
                    $black
                );
            }
        }

        ob_start();
        imagepng($image);
        $png = ob_get_clean();

        return is_string($png) ? $png : null;
    }

    /**
     * @param array<int, int> $bytes
     * @return array<int, int>
     */
    private function finalCodewords(array $bytes): array
    {
        $data = $this->dataCodewords($bytes);
        $blocks = array_chunk($data, self::DATA_CODEWORDS / self::BLOCK_COUNT);
        $ecBlocks = array_map(fn (array $block): array => $this->reedSolomonRemainder($block, self::EC_CODEWORDS_PER_BLOCK), $blocks);
        $result = [];

        for ($i = 0; $i < count($blocks[0]); $i++) {
            foreach ($blocks as $block) {
                $result[] = $block[$i];
            }
        }

        for ($i = 0; $i < self::EC_CODEWORDS_PER_BLOCK; $i++) {
            foreach ($ecBlocks as $block) {
                $result[] = $block[$i];
            }
        }

        return $result;
    }

    /**
     * @param array<int, int> $bytes
     * @return array<int, int>
     */
    private function dataCodewords(array $bytes): array
    {
        $bits = [0, 1, 0, 0];
        $this->appendBits($bits, count($bytes), 8);

        foreach ($bytes as $byte) {
            $this->appendBits($bits, $byte, 8);
        }

        $capacityBits = self::DATA_CODEWORDS * 8;
        $this->appendBits($bits, 0, min(4, $capacityBits - count($bits)));

        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        $codewords = [];

        foreach (array_chunk($bits, 8) as $chunk) {
            $value = 0;

            foreach ($chunk as $bit) {
                $value = ($value << 1) | $bit;
            }

            $codewords[] = $value;
        }

        for ($pad = 0; count($codewords) < self::DATA_CODEWORDS; $pad++) {
            $codewords[] = $pad % 2 === 0 ? 0xec : 0x11;
        }

        return $codewords;
    }

    /**
     * @param array<int, int> $bits
     */
    private function appendBits(array &$bits, int $value, int $length): void
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    /**
     * @param array<int, int> $data
     * @return array<int, int>
     */
    private function reedSolomonRemainder(array $data, int $degree): array
    {
        $generator = $this->reedSolomonGenerator($degree);
        $result = array_fill(0, $degree, 0);

        foreach ($data as $byte) {
            $factor = $byte ^ $result[0];
            array_shift($result);
            $result[] = 0;

            foreach ($generator as $i => $coef) {
                $result[$i] ^= $this->gfMultiply($coef, $factor);
            }
        }

        return $result;
    }

    /**
     * @return array<int, int>
     */
    private function reedSolomonGenerator(int $degree): array
    {
        $result = [1];
        $root = 1;

        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($result) + 1, 0);

            foreach ($result as $j => $coef) {
                $next[$j] ^= $coef;
                $next[$j + 1] ^= $this->gfMultiply($coef, $root);
            }

            $result = $next;
            $root = $this->gfMultiply($root, 0x02);
        }

        array_shift($result);

        return $result;
    }

    private function gfMultiply(int $x, int $y): int
    {
        $result = 0;

        for ($i = 7; $i >= 0; $i--) {
            $result = (($result << 1) ^ (($result >> 7) * 0x11d)) & 0xff;

            if ((($y >> $i) & 1) !== 0) {
                $result ^= $x;
            }
        }

        return $result;
    }

    /**
     * @param array<int, int> $codewords
     */
    private function buildMatrix(array $codewords): void
    {
        $this->modules = array_fill(0, self::SIZE, array_fill(0, self::SIZE, null));
        $this->reserved = array_fill(0, self::SIZE, array_fill(0, self::SIZE, false));
        $this->drawFunctionPatterns();
        $this->drawCodewords($codewords);
        $this->applyMask();
        $this->drawFormatBits();
    }

    private function drawFunctionPatterns(): void
    {
        $this->drawFinder(3, 3);
        $this->drawFinder(self::SIZE - 4, 3);
        $this->drawFinder(3, self::SIZE - 4);
        $this->drawAlignment(34, 34);

        for ($i = 8; $i < self::SIZE - 8; $i++) {
            $this->setFunction(6, $i, $i % 2 === 0);
            $this->setFunction($i, 6, $i % 2 === 0);
        }

        $this->setFunction(8, 4 * self::VERSION + 9, true);

        for ($i = 0; $i < 9; $i++) {
            $this->reserve(8, $i);
            $this->reserve($i, 8);
        }

        for ($i = 0; $i < 8; $i++) {
            $this->reserve(self::SIZE - 1 - $i, 8);
        }

        for ($i = 0; $i < 7; $i++) {
            $this->reserve(8, self::SIZE - 1 - $i);
        }
    }

    private function drawFinder(int $cx, int $cy): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $cx + $dx;
                $y = $cy + $dy;

                if ($x < 0 || $x >= self::SIZE || $y < 0 || $y >= self::SIZE) {
                    continue;
                }

                $dark = max(abs($dx), abs($dy)) !== 4 && (max(abs($dx), abs($dy)) !== 2);
                $this->setFunction($x, $y, $dark);
            }
        }
    }

    private function drawAlignment(int $cx, int $cy): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->setFunction($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    /**
     * @param array<int, int> $codewords
     */
    private function drawCodewords(array $codewords): void
    {
        $bits = [];

        foreach ($codewords as $codeword) {
            $this->appendBits($bits, $codeword, 8);
        }

        $bitIndex = 0;
        $direction = -1;

        for ($right = self::SIZE - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right--;
            }

            for ($vert = 0; $vert < self::SIZE; $vert++) {
                $y = $direction === 1 ? $vert : self::SIZE - 1 - $vert;

                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;

                    if ($this->reserved[$y][$x]) {
                        continue;
                    }

                    $this->modules[$y][$x] = ($bits[$bitIndex] ?? 0) === 1;
                    $bitIndex++;
                }
            }

            $direction *= -1;
        }
    }

    private function applyMask(): void
    {
        for ($y = 0; $y < self::SIZE; $y++) {
            for ($x = 0; $x < self::SIZE; $x++) {
                if (!$this->reserved[$y][$x] && (($x + $y) % 2 === 0)) {
                    $this->modules[$y][$x] = !($this->modules[$y][$x] ?? false);
                }
            }
        }
    }

    private function drawFormatBits(): void
    {
        $bits = $this->formatBits(0);

        for ($i = 0; $i <= 5; $i++) {
            $this->setFunction(8, $i, (($bits >> $i) & 1) !== 0);
        }

        $this->setFunction(8, 7, (($bits >> 6) & 1) !== 0);
        $this->setFunction(8, 8, (($bits >> 7) & 1) !== 0);
        $this->setFunction(7, 8, (($bits >> 8) & 1) !== 0);

        for ($i = 9; $i < 15; $i++) {
            $this->setFunction(14 - $i, 8, (($bits >> $i) & 1) !== 0);
        }

        for ($i = 0; $i < 8; $i++) {
            $this->setFunction(self::SIZE - 1 - $i, 8, (($bits >> $i) & 1) !== 0);
        }

        for ($i = 8; $i < 15; $i++) {
            $this->setFunction(8, self::SIZE - 15 + $i, (($bits >> $i) & 1) !== 0);
        }

        $this->setFunction(8, self::SIZE - 8, true);
    }

    private function formatBits(int $mask): int
    {
        $data = $mask; // EC level M is 00.
        $rem = $data;

        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
        }

        return (($data << 10) | $rem) ^ 0x5412;
    }

    private function setFunction(int $x, int $y, bool $dark): void
    {
        if ($x < 0 || $x >= self::SIZE || $y < 0 || $y >= self::SIZE) {
            return;
        }

        $this->modules[$y][$x] = $dark;
        $this->reserved[$y][$x] = true;
    }

    private function reserve(int $x, int $y): void
    {
        if ($x >= 0 && $x < self::SIZE && $y >= 0 && $y < self::SIZE) {
            $this->reserved[$y][$x] = true;
        }
    }
}
