<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class UploadService
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/pdf' => 'pdf',
    ];

    public function storePrivate(array $file, string $subdirectory = 'documentos', ?array $allowedMimeTypes = null): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha ao receber o arquivo enviado.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new RuntimeException('Arquivo fora do tamanho permitido.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Upload inválido.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) $finfo->file($tmpPath);
        $allowed = $allowedMimeTypes === null
            ? self::ALLOWED
            : array_intersect_key(self::ALLOWED, array_flip($allowedMimeTypes));

        if (!array_key_exists($mimeType, $allowed)) {
            throw new RuntimeException('Tipo de arquivo não permitido.');
        }

        $extension = $allowed[$mimeType];
        $subdirectory = $this->safeSubdirectory($subdirectory);
        $baseDir = BASE_PATH . '/storage/private_uploads/' . $subdirectory;

        if (!is_dir($baseDir) && !mkdir($baseDir, 0750, true)) {
            throw new RuntimeException('Não foi possível preparar o diretório de upload.');
        }

        $storedName = bin2hex(random_bytes(20)) . '.' . $extension;
        $destination = $baseDir . '/' . $storedName;

        if (!move_uploaded_file($tmpPath, $destination)) {
            throw new RuntimeException('Não foi possível salvar o arquivo enviado.');
        }

        chmod($destination, 0640);

        return [
            'nome_original' => basename((string) ($file['name'] ?? 'arquivo')),
            'caminho_arquivo' => 'storage/private_uploads/' . $subdirectory . '/' . $storedName,
            'mime_type' => $mimeType,
            'extensao' => $extension,
            'tamanho_bytes' => $size,
            'hash_arquivo' => hash_file('sha256', $destination),
        ];
    }

    public function hasFile(array $file): bool
    {
        return ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    public function normalizeMultiple(array $files): array
    {
        $normalized = [];
        $names = $files['name'] ?? [];

        if (!is_array($names)) {
            return [$files];
        }

        foreach ($names as $index => $name) {
            $normalized[] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? null,
                'tmp_name' => $files['tmp_name'][$index] ?? null,
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];
        }

        return $normalized;
    }

    private function safeSubdirectory(string $subdirectory): string
    {
        $subdirectory = trim(str_replace('\\', '/', $subdirectory), '/');

        if ($subdirectory === '' || str_contains($subdirectory, '..') || !preg_match('#^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$#', $subdirectory)) {
            throw new RuntimeException('Diretório de upload inválido.');
        }

        return $subdirectory;
    }
}
