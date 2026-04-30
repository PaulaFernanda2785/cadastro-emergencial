<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class DocumentoAnexoRepository
{
    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO documentos_anexos
                (familia_id, residencia_id, tipo_documento, nome_original, caminho_arquivo,
                 mime_type, extensao, tamanho_bytes, hash_arquivo, enviado_por)
             VALUES
                (:familia_id, :residencia_id, :tipo_documento, :nome_original, :caminho_arquivo,
                 :mime_type, :extensao, :tamanho_bytes, :hash_arquivo, :enviado_por)'
        );
        $stmt->bindValue(':familia_id', $data['familia_id'] ?? null, empty($data['familia_id']) ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':residencia_id', $data['residencia_id'] ?? null, empty($data['residencia_id']) ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':tipo_documento', $data['tipo_documento']);
        $stmt->bindValue(':nome_original', $data['nome_original']);
        $stmt->bindValue(':caminho_arquivo', $data['caminho_arquivo']);
        $stmt->bindValue(':mime_type', $data['mime_type']);
        $stmt->bindValue(':extensao', $data['extensao']);
        $stmt->bindValue(':tamanho_bytes', (int) $data['tamanho_bytes'], PDO::PARAM_INT);
        $stmt->bindValue(':hash_arquivo', $data['hash_arquivo']);
        $stmt->bindValue(':enviado_por', (int) $data['enviado_por'], PDO::PARAM_INT);
        $stmt->execute();

        return (int) Database::connection()->lastInsertId();
    }

    public function byResidencia(int $residenciaId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT d.id, d.tipo_documento, d.nome_original, d.mime_type, d.tamanho_bytes,
                    d.criado_em, d.residencia_id, d.familia_id, f.responsavel_nome
             FROM documentos_anexos d
             LEFT JOIN familias f ON f.id = d.familia_id
             WHERE d.deleted_at IS NULL
               AND (
                    d.residencia_id = :residencia_id_documento
                    OR f.residencia_id = :residencia_id_familia
               )
             ORDER BY d.criado_em DESC'
        );
        $stmt->bindValue(':residencia_id_documento', $residenciaId, PDO::PARAM_INT);
        $stmt->bindValue(':residencia_id_familia', $residenciaId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function byFamilia(int $familiaId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT d.id, d.tipo_documento, d.nome_original, d.mime_type, d.extensao, d.tamanho_bytes,
                    d.criado_em, d.residencia_id, d.familia_id
             FROM documentos_anexos d
             WHERE d.familia_id = :familia_id
               AND d.deleted_at IS NULL
             ORDER BY d.criado_em DESC'
        );
        $stmt->bindValue(':familia_id', $familiaId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findForResidencia(int $documentoId, int $residenciaId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT d.id, d.tipo_documento, d.nome_original, d.caminho_arquivo, d.mime_type,
                    d.tamanho_bytes, d.criado_em, d.residencia_id, d.familia_id
             FROM documentos_anexos d
             LEFT JOIN familias f ON f.id = d.familia_id
             WHERE d.id = :documento_id
               AND d.deleted_at IS NULL
               AND (
                    d.residencia_id = :residencia_id_documento
                    OR f.residencia_id = :residencia_id_familia
               )
             LIMIT 1'
        );
        $stmt->bindValue(':documento_id', $documentoId, PDO::PARAM_INT);
        $stmt->bindValue(':residencia_id_documento', $residenciaId, PDO::PARAM_INT);
        $stmt->bindValue(':residencia_id_familia', $residenciaId, PDO::PARAM_INT);
        $stmt->execute();

        $documento = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($documento) ? $documento : null;
    }

    public function findForFamilia(int $documentoId, int $familiaId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT d.id, d.tipo_documento, d.nome_original, d.caminho_arquivo, d.mime_type,
                    d.tamanho_bytes, d.criado_em, d.residencia_id, d.familia_id
             FROM documentos_anexos d
             WHERE d.id = :documento_id
               AND d.familia_id = :familia_id
               AND d.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->bindValue(':documento_id', $documentoId, PDO::PARAM_INT);
        $stmt->bindValue(':familia_id', $familiaId, PDO::PARAM_INT);
        $stmt->execute();

        $documento = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($documento) ? $documento : null;
    }

    public function softDeleteByFamiliaAndIds(int $familiaId, array $documentoIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $documentoIds
        ), static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return;
        }

        $placeholders = [];
        $params = [
            ':familia_id' => $familiaId,
        ];

        foreach ($ids as $index => $id) {
            $key = ':documento_id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE documentos_anexos
             SET deleted_at = NOW()
             WHERE familia_id = :familia_id
               AND id IN (' . implode(', ', $placeholders) . ')
               AND deleted_at IS NULL'
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }

        $stmt->execute();
    }
}
