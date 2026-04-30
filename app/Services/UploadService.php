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

    public function storePrivate(array $file, string $subdirectory = 'documentos'): array
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
            throw new RuntimeException('Upload invalido.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) $finfo->file($tmpPath);

        if (!array_key_exists($mimeType, self::ALLOWED)) {
            throw new RuntimeException('Tipo de arquivo nao permitido.');
        }

        $extension = self::ALLOWED[$mimeType];
        $baseDir = BASE_PATH . '/storage/private_uploads/' . trim($subdirectory, '/');

        if (!is_dir($baseDir) && !mkdir($baseDir, 0750, true)) {
            throw new RuntimeException('Nao foi possivel preparar o diretorio de upload.');
        }

        $storedName = bin2hex(random_bytes(20)) . '.' . $extension;
        $destination = $baseDir . '/' . $storedName;

        if (!move_uploaded_file($tmpPath, $destination)) {
            throw new RuntimeException('Nao foi possivel salvar o arquivo enviado.');
        }

        chmod($destination, 0640);

        return [
            'nome_original' => basename((string) ($file['name'] ?? 'arquivo')),
            'caminho_arquivo' => 'storage/private_uploads/' . trim($subdirectory, '/') . '/' . $storedName,
            'mime_type' => $mimeType,
            'extensao' => $extension,
            'tamanho_bytes' => $size,
            'hash_arquivo' => hash_file('sha256', $destination),
        ];
    }
}
