<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class EntregaAjudaRepository
{
    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.id, e.quantidade, e.data_entrega, e.comprovante_codigo, e.observacao,
                    f.id AS familia_id, f.responsavel_nome, f.responsavel_cpf, f.responsavel_rg,
                    f.telefone, f.quantidade_integrantes,
                    t.nome AS tipo_ajuda_nome, t.unidade_medida,
                    r.id AS residencia_id, r.protocolo, r.bairro_comunidade, r.endereco, r.complemento,
                    r.imovel, r.condicao_residencia,
                    a.localidade, a.tipo_evento, a.data_evento,
                    m.nome AS municipio_nome, m.uf,
                    u.nome AS entregue_por_nome, u.cpf AS entregue_por_cpf
             FROM entregas_ajuda e
             INNER JOIN familias f ON f.id = e.familia_id
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             INNER JOIN tipos_ajuda t ON t.id = e.tipo_ajuda_id
             INNER JOIN usuarios u ON u.id = e.entregue_por
             WHERE e.id = :id
                AND e.deleted_at IS NULL
                AND f.deleted_at IS NULL
                AND r.deleted_at IS NULL
                AND a.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $entrega = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($entrega) ? $entrega : null;
    }

    public function all(): array
    {
        return Database::connection()
            ->query(
                'SELECT e.id, e.quantidade, e.data_entrega, e.comprovante_codigo, e.observacao,
                        f.responsavel_nome, f.responsavel_cpf,
                        t.nome AS tipo_ajuda_nome, t.unidade_medida,
                        r.id AS residencia_id, r.protocolo, r.bairro_comunidade,
                        a.localidade, a.tipo_evento,
                        m.nome AS municipio_nome, m.uf,
                        u.nome AS entregue_por_nome
                 FROM entregas_ajuda e
                 INNER JOIN familias f ON f.id = e.familia_id
                 INNER JOIN residencias r ON r.id = f.residencia_id
                 INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
                 INNER JOIN municipios m ON m.id = r.municipio_id
                 INNER JOIN tipos_ajuda t ON t.id = e.tipo_ajuda_id
                 INNER JOIN usuarios u ON u.id = e.entregue_por
                 WHERE e.deleted_at IS NULL
                    AND f.deleted_at IS NULL
                    AND r.deleted_at IS NULL
                    AND a.deleted_at IS NULL
                 ORDER BY e.data_entrega DESC, e.id DESC'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function byFamilia(int $familiaId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.id, e.quantidade, e.data_entrega, e.comprovante_codigo, e.observacao,
                    t.nome AS tipo_ajuda_nome, t.unidade_medida,
                    u.nome AS entregue_por_nome
             FROM entregas_ajuda e
             INNER JOIN tipos_ajuda t ON t.id = e.tipo_ajuda_id
             INNER JOIN usuarios u ON u.id = e.entregue_por
             WHERE e.familia_id = :familia_id AND e.deleted_at IS NULL
             ORDER BY e.data_entrega DESC, e.id DESC'
        );
        $stmt->bindValue(':familia_id', $familiaId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO entregas_ajuda
                (familia_id, tipo_ajuda_id, quantidade, entregue_por, comprovante_codigo, observacao)
             VALUES
                (:familia_id, :tipo_ajuda_id, :quantidade, :entregue_por, :comprovante_codigo, :observacao)'
        );
        $stmt->bindValue(':familia_id', (int) $data['familia_id'], PDO::PARAM_INT);
        $stmt->bindValue(':tipo_ajuda_id', (int) $data['tipo_ajuda_id'], PDO::PARAM_INT);
        $stmt->bindValue(':quantidade', number_format((float) $data['quantidade'], 2, '.', ''));
        $stmt->bindValue(':entregue_por', (int) $data['entregue_por'], PDO::PARAM_INT);
        $stmt->bindValue(':comprovante_codigo', $data['comprovante_codigo']);
        $stmt->bindValue(':observacao', $data['observacao'] !== '' ? $data['observacao'] : null);
        $stmt->execute();

        return (int) Database::connection()->lastInsertId();
    }

    public function count(): int
    {
        return (int) Database::connection()
            ->query('SELECT COUNT(*) FROM entregas_ajuda WHERE deleted_at IS NULL')
            ->fetchColumn();
    }
}
