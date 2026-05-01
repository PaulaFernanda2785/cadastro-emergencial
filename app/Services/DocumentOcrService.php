<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use RuntimeException;

final class DocumentOcrService
{
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const MIN_CONFIDENCE = 38.0;

    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public function extractText(array $file): array
    {
        try {
            $path = $this->prepareTempImage($file);
        } catch (RuntimeException $exception) {
            return [
                'ok' => false,
                'text' => '',
                'confidence' => 0,
                'message' => $exception->getMessage(),
            ];
        }

        try {
            $provider = strtolower((string) Env::get('OCR_PROVIDER', 'tesseract'));

            if ($provider === 'google_vision' || $provider === 'auto') {
                $googleResult = $this->extractTextWithGoogleVision($path);

                if ($googleResult['ok'] || $provider === 'google_vision') {
                    return $googleResult;
                }
            }

            $binary = $this->binaryPath();

            if ($binary === null) {
                return [
                    'ok' => false,
                    'text' => '',
                    'confidence' => 0,
                    'message' => 'OCR do servidor nao configurado.',
                ];
            }

            $best = null;

            foreach ($this->imageVariants($path) as $variant) {
                foreach ([6, 11, 4] as $pageSegMode) {
                    foreach (['por+eng', 'eng'] as $language) {
                        $result = $this->runTesseract($binary, $variant, $language, $pageSegMode);

                        if (!$result['ok']) {
                            continue;
                        }

                        $result['score'] = $this->scoreResult($result['text'], (float) $result['confidence']);

                        if ($best === null || $result['score'] > $best['score']) {
                            $best = $result;
                        }
                    }
                }
            }

            if ($best !== null && (float) $best['confidence'] >= self::MIN_CONFIDENCE) {
                return [
                    'ok' => true,
                    'text' => $best['text'],
                    'confidence' => round((float) $best['confidence'], 1),
                    'message' => 'Documento lido pelo OCR do servidor.',
                ];
            }

            if ($best !== null && $this->hasUsefulDocumentToken($best['text'])) {
                return [
                    'ok' => true,
                    'text' => $best['text'],
                    'confidence' => round((float) $best['confidence'], 1),
                    'message' => 'Documento lido parcialmente pelo OCR do servidor.',
                ];
            }

            return [
                'ok' => false,
                'text' => $best['text'] ?? '',
                'confidence' => round((float) ($best['confidence'] ?? 0), 1),
                'message' => 'OCR do servidor nao conseguiu identificar texto confiavel no documento.',
            ];
        } finally {
            foreach (glob(dirname($path) . '/' . pathinfo($path, PATHINFO_FILENAME) . '-*.png') ?: [] as $variantPath) {
                if (is_file($variantPath)) {
                    @unlink($variantPath);
                }
            }

            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function extractTextWithGoogleVision(string $path): array
    {
        $apiKey = (string) Env::get('GOOGLE_VISION_API_KEY', '');

        if ($apiKey === '') {
            return [
                'ok' => false,
                'text' => '',
                'confidence' => 0,
                'message' => 'Chave da API Google Vision nao configurada.',
            ];
        }

        $content = file_get_contents($path);

        if ($content === false || $content === '') {
            return [
                'ok' => false,
                'text' => '',
                'confidence' => 0,
                'message' => 'Nao foi possivel ler o arquivo temporario para OCR.',
            ];
        }

        $payload = [
            'requests' => [[
                'image' => [
                    'content' => base64_encode($content),
                ],
                'features' => [[
                    'type' => 'DOCUMENT_TEXT_DETECTION',
                    'maxResults' => 1,
                ]],
                'imageContext' => [
                    'languageHints' => ['pt', 'en'],
                ],
            ]],
        ];
        $response = $this->postJson(
            'https://vision.googleapis.com/v1/images:annotate?key=' . rawurlencode($apiKey),
            $payload,
            30
        );

        if (!$response['ok']) {
            return [
                'ok' => false,
                'text' => '',
                'confidence' => 0,
                'message' => $response['message'],
            ];
        }

        $body = json_decode($response['body'], true);
        $first = is_array($body) ? ($body['responses'][0] ?? null) : null;

        if (!is_array($first)) {
            return [
                'ok' => false,
                'text' => '',
                'confidence' => 0,
                'message' => 'Resposta invalida da API Google Vision.',
            ];
        }

        if (isset($first['error'])) {
            return [
                'ok' => false,
                'text' => '',
                'confidence' => 0,
                'message' => (string) ($first['error']['message'] ?? 'Erro retornado pela API Google Vision.'),
            ];
        }

        $text = trim((string) ($first['fullTextAnnotation']['text'] ?? $first['textAnnotations'][0]['description'] ?? ''));

        if ($text === '') {
            return [
                'ok' => false,
                'text' => '',
                'confidence' => 0,
                'message' => 'Google Vision nao identificou texto no documento.',
            ];
        }

        return [
            'ok' => true,
            'text' => $text,
            'confidence' => 92,
            'message' => 'Documento lido pela API Google Vision.',
        ];
    }

    private function postJson(string $url, array $payload, int $timeoutSeconds): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return [
                'ok' => false,
                'body' => '',
                'message' => 'Nao foi possivel montar a requisicao OCR.',
            ];
        }

        if (function_exists('curl_init')) {
            $curl = curl_init($url);

            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeoutSeconds,
            ]);

            $body = curl_exec($curl);
            $error = curl_error($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);

            if ($body === false || $status < 200 || $status >= 300) {
                return [
                    'ok' => false,
                    'body' => is_string($body) ? $body : '',
                    'message' => $error !== '' ? $error : 'Falha HTTP ' . $status . ' na API Google Vision.',
                ];
            }

            return [
                'ok' => true,
                'body' => (string) $body,
                'message' => '',
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $json,
                'timeout' => $timeoutSeconds,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            return [
                'ok' => false,
                'body' => '',
                'message' => 'Falha ao chamar a API Google Vision.',
            ];
        }

        return [
            'ok' => true,
            'body' => $body,
            'message' => '',
        ];
    }

    private function binaryPath(): ?string
    {
        $configured = (string) Env::get('TESSERACT_PATH', '');
        $candidates = array_filter([
            $configured,
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe',
            'tesseract',
        ]);

        foreach ($candidates as $candidate) {
            if ($candidate === 'tesseract' || is_file($candidate)) {
                $probe = $this->runProcess([$candidate, '--version'], 5);

                if ($probe['exit_code'] === 0) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function prepareTempImage(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha ao receber o documento para OCR.');
        }

        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new RuntimeException('Documento fora do tamanho permitido para OCR.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Upload invalido para OCR.');
        }

        $mimeType = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmpPath);

        if (!isset(self::ALLOWED[$mimeType])) {
            throw new RuntimeException('OCR do servidor aceita apenas imagens JPG ou PNG.');
        }

        $dir = BASE_PATH . '/storage/cache/ocr';

        if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
            throw new RuntimeException('Nao foi possivel preparar o diretorio temporario do OCR.');
        }

        $path = $dir . '/' . bin2hex(random_bytes(16)) . '.' . self::ALLOWED[$mimeType];

        if (!move_uploaded_file($tmpPath, $path)) {
            throw new RuntimeException('Nao foi possivel preparar o documento para OCR.');
        }

        return $path;
    }

    private function imageVariants(string $path): array
    {
        $variants = [$path];
        $image = $this->loadImage($path);

        if (!$image instanceof \GdImage) {
            return $variants;
        }

        $image = $this->resizeForOcr($this->fixOrientation($image, $path));
        $base = dirname($path) . '/' . pathinfo($path, PATHINFO_FILENAME);
        $enhanced = $this->cloneImage($image);
        $threshold = $this->cloneImage($image);
        $enhancedPath = $base . '-enhanced.png';
        $thresholdPath = $base . '-threshold.png';

        imagefilter($enhanced, IMG_FILTER_GRAYSCALE);
        imagefilter($enhanced, IMG_FILTER_CONTRAST, -28);
        imagefilter($enhanced, IMG_FILTER_SMOOTH, -2);

        if (imagepng($enhanced, $enhancedPath)) {
            $variants[] = $enhancedPath;
        }

        imagefilter($threshold, IMG_FILTER_GRAYSCALE);
        imagefilter($threshold, IMG_FILTER_CONTRAST, -40);
        $this->thresholdImage($threshold);

        if (imagepng($threshold, $thresholdPath)) {
            $variants[] = $thresholdPath;
        }

        imagedestroy($image);
        imagedestroy($enhanced);
        imagedestroy($threshold);

        return $variants;
    }

    private function loadImage(string $path): ?\GdImage
    {
        $type = @exif_imagetype($path);

        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path) ?: null,
            IMAGETYPE_PNG => @imagecreatefrompng($path) ?: null,
            default => null,
        };
    }

    private function fixOrientation(\GdImage $image, string $path): \GdImage
    {
        if (!function_exists('exif_read_data') || @exif_imagetype($path) !== IMAGETYPE_JPEG) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof \GdImage ? $rotated : $image;
    }

    private function resizeForOcr(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longSide = max($width, $height);
        $targetLongSide = min(2600, max(1800, $longSide));
        $scale = $targetLongSide / max(1, $longSide);

        if (abs($scale - 1.0) < 0.08) {
            return $image;
        }

        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $resized = imagecreatetruecolor($newWidth, $newHeight);

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $resized;
    }

    private function cloneImage(\GdImage $image): \GdImage
    {
        $copy = imagecreatetruecolor(imagesx($image), imagesy($image));

        imagecopy($copy, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return $copy;
    }

    private function thresholdImage(\GdImage $image): void
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $sum = 0;
        $count = max(1, $width * $height);

        for ($y = 0; $y < $height; $y += 1) {
            for ($x = 0; $x < $width; $x += 1) {
                $sum += imagecolorat($image, $x, $y) & 0xFF;
            }
        }

        $threshold = max(92, min(188, (int) round(($sum / $count) * 0.92)));
        $black = imagecolorallocate($image, 0, 0, 0);
        $white = imagecolorallocate($image, 255, 255, 255);

        for ($y = 0; $y < $height; $y += 1) {
            for ($x = 0; $x < $width; $x += 1) {
                imagesetpixel($image, $x, $y, ((imagecolorat($image, $x, $y) & 0xFF) < $threshold) ? $black : $white);
            }
        }
    }

    private function runTesseract(string $binary, string $imagePath, string $language, int $pageSegMode): array
    {
        $command = [
            $binary,
            $imagePath,
            'stdout',
            '--psm',
            (string) $pageSegMode,
            '--oem',
            '1',
            '--dpi',
            '300',
        ];
        $tessdataDir = $this->tessdataDir();

        if ($tessdataDir !== null) {
            $command[] = '--tessdata-dir';
            $command[] = $tessdataDir;
        }

        $command[] = '-l';
        $command[] = $language;
        $command[] = '-c';
        $command[] = 'preserve_interword_spaces=1';
        $command[] = '-c';
        $command[] = 'tessedit_create_tsv=1';

        $result = $this->runProcess($command, 24);
        $parsed = $this->parseTsv($result['stdout']);

        return [
            'ok' => $result['exit_code'] === 0 && trim($parsed['text']) !== '',
            'text' => $parsed['text'],
            'confidence' => $parsed['confidence'],
            'error' => $result['stderr'],
        ];
    }

    private function parseTsv(string $tsv): array
    {
        $rows = preg_split('/\r\n|\r|\n/', trim($tsv)) ?: [];
        $header = str_getcsv(array_shift($rows) ?: '', "\t", '"', '\\');
        $columns = array_flip($header);
        $lines = [];
        $confidences = [];

        foreach ($rows as $row) {
            if (trim($row) === '') {
                continue;
            }

            $values = str_getcsv($row, "\t", '"', '\\');
            $text = trim((string) ($values[$columns['text'] ?? -1] ?? ''));
            $confidence = (float) ($values[$columns['conf'] ?? -1] ?? -1);

            if ($text === '' || $confidence < 0) {
                continue;
            }

            $key = implode(':', [
                $values[$columns['block_num'] ?? -1] ?? '0',
                $values[$columns['par_num'] ?? -1] ?? '0',
                $values[$columns['line_num'] ?? -1] ?? '0',
            ]);

            $lines[$key][] = $text;
            $confidences[] = $confidence;
        }

        $text = implode("\n", array_map(
            static fn (array $words): string => preg_replace('/\s+/', ' ', implode(' ', $words)) ?? '',
            $lines
        ));

        return [
            'text' => trim($text),
            'confidence' => $confidences === [] ? 0.0 : array_sum($confidences) / count($confidences),
        ];
    }

    private function scoreResult(string $text, float $confidence): float
    {
        $normalized = $this->normalizeText($text);
        $score = $confidence;

        foreach (['NOME', 'CPF', 'REGISTRO', 'IDENTIDADE', 'NASCIMENTO', 'EXPEDIDOR', 'SSP'] as $token) {
            if (str_contains($normalized, $token)) {
                $score += 8;
            }
        }

        if (preg_match('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', $text) === 1) {
            $score += 18;
        }

        if (preg_match('/\b\d{2}[\/.-]\d{2}[\/.-]\d{4}\b/', $text) === 1) {
            $score += 10;
        }

        $score -= substr_count($text, '�') * 12;

        return $score;
    }

    private function hasUsefulDocumentToken(string $text): bool
    {
        $normalized = $this->normalizeText($text);

        return preg_match('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', $text) === 1
            || preg_match('/\b\d{2}[\/.-]\d{2}[\/.-]\d{4}\b/', $text) === 1
            || str_contains($normalized, 'NOME')
            || str_contains($normalized, 'CPF')
            || str_contains($normalized, 'IDENTIDADE');
    }

    private function normalizeText(string $text): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return strtoupper((string) $normalized);
    }

    private function tessdataDir(): ?string
    {
        $configured = (string) Env::get('TESSDATA_DIR', '');
        $default = BASE_PATH . '/storage/tessdata';
        $dir = $configured !== '' ? $configured : $default;

        if ($dir !== '' && !preg_match('/^(?:[A-Za-z]:[\\\\\\/]|[\\\\\\/])/', $dir)) {
            $dir = BASE_PATH . '/' . str_replace('\\', '/', $dir);
        }

        return is_dir($dir) ? $dir : null;
    }

    private function runProcess(array $command, int $timeoutSeconds): array
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = @proc_open($command, $descriptorSpec, $pipes, BASE_PATH);

        if (!is_resource($process)) {
            return [
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => 'Nao foi possivel iniciar o processo de OCR.',
            ];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $startedAt = time();
        $exitCode = null;

        do {
            $stdout .= stream_get_contents($pipes[1]) ?: '';
            $stderr .= stream_get_contents($pipes[2]) ?: '';
            $status = proc_get_status($process);

            if (!$status['running']) {
                $exitCode = is_int($status['exitcode']) ? $status['exitcode'] : null;
                break;
            }

            if ((time() - $startedAt) > $timeoutSeconds) {
                proc_terminate($process);
                $stderr .= 'Tempo limite do OCR excedido.';
                break;
            }

            usleep(100000);
        } while (true);

        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        $closedExitCode = proc_close($process);

        if ($exitCode === null || $exitCode === -1) {
            $exitCode = $closedExitCode;
        }

        return [
            'exit_code' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }
}
