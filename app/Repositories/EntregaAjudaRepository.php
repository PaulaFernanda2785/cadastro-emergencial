<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class EntregaAjudaRepository
{
    private const GROUP_CODE_SQL = 'COALESCE(e.grupo_comprovante_codigo, e.comprovante_codigo)';

    private const BASE_GROUP_SELECT = 'SELECT MIN(e.id) AS id,
                    ' . self::GROUP_CODE_SQL . ' AS comprovante_codigo,
                    COUNT(*) AS total_itens,
                    COALESCE(SUM(e.quantidade), 0) AS quantidade_total,
                    MAX(e.data_entrega) AS data_entrega,
                    MAX(e.observacao) AS observacao,
                    f.id AS familia_id, f.responsavel_nome, f.responsavel_cpf,
                    GROUP_CONCAT(CONCAT(t.nome, " (", FORMAT(e.quantidade, 0, "de_DE"), " ", t.unidade_medida, ")") ORDER BY t.nome SEPARATOR " | ") AS itens_resumo,
                    r.id AS residencia_id, r.protocolo, r.bairro_comunidade,
                    a.id AS acao_id, a.localidade, a.tipo_evento,
                    m.nome AS municipio_nome, m.uf,
                    u.nome AS entregue_por_nome
             FROM entregas_ajuda e
             INNER JOIN familias f ON f.id = e.familia_id
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             INNER JOIN tipos_ajuda t ON t.id = e.tipo_ajuda_id
             INNER JOIN usuarios u ON u.id = e.entregue_por';

    public function find(int $id): ?array
    {
        $groupStmt = Database::connection()->prepare(
            'SELECT COALESCE(grupo_comprovante_codigo, comprovante_codigo) AS codigo, familia_id
             FROM entregas_ajuda
             WHERE id = :id AND deleted_at IS NULL
             LIMIT 1'
        );
        $groupStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $groupStmt->execute();
        $group = $groupStmt->fetch(PDO::FETCH_ASSOC);
        $groupCode = is_array($group) ? (string) ($group['codigo'] ?? '') : '';
        $familiaId = is_array($group) ? (int) ($group['familia_id'] ?? 0) : 0;

        if ($groupCode === '' || $familiaId <= 0) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT MIN(e.id) AS id,
                    ' . self::GROUP_CODE_SQL . ' AS comprovante_codigo,
                    COALESCE(SUM(e.quantidade), 0) AS quantidade_total,
                    COUNT(*) AS total_itens,
                    MAX(e.data_entrega) AS data_entrega,
                    MAX(e.observacao) AS observacao,
                    f.id AS familia_id, f.responsavel_nome, f.responsavel_cpf, f.responsavel_rg,
                    f.telefone, f.quantidade_integrantes,
                    MIN(t.nome) AS tipo_ajuda_nome, MIN(t.unidade_medida) AS unidade_medida,
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
             WHERE ' . self::GROUP_CODE_SQL . ' = :codigo
                AND e.familia_id = :familia_id
                AND e.deleted_at IS NULL
                AND f.deleted_at IS NULL
                AND r.deleted_at IS NULL
                AND a.deleted_at IS NULL
             GROUP BY ' . self::GROUP_CODE_SQL . ',
                    f.id, f.responsavel_nome, f.responsavel_cpf, f.responsavel_rg, f.telefone, f.quantidade_integrantes,
                    r.id, r.protocolo, r.bairro_comunidade, r.endereco, r.complemento, r.imovel, r.condicao_residencia,
                    a.localidade, a.tipo_evento, a.data_evento, m.nome, m.uf, u.nome, u.cpf
             LIMIT 1'
        );
        $stmt->bindValue(':codigo', $groupCode);
        $stmt->bindValue(':familia_id', $familiaId, PDO::PARAM_INT);
        $stmt->execute();

        $entrega = $stmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($entrega)) {
            $entrega['itens'] = $this->itemsByGroupCode($groupCode, $familiaId);
        }

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

    public function search(array $filters, int $limit = 10, int $offset = 0): array
    {
        [$where, $params] = $this->buildSearchWhere($filters);
        $stmt = Database::connection()->prepare(
            self::BASE_GROUP_SELECT . '
             WHERE ' . $where . '
             GROUP BY ' . self::GROUP_CODE_SQL . ',
                    f.id, f.responsavel_nome, f.responsavel_cpf,
                    r.id, r.protocolo, r.bairro_comunidade,
                    a.id, a.localidade, a.tipo_evento,
                    m.nome, m.uf, u.nome
             ORDER BY MAX(e.data_entrega) DESC, MIN(e.id) DESC
             LIMIT :limit OFFSET :offset'
        );
        $this->bindParams($stmt, $params);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countSearch(array $filters): int
    {
        [$where, $params] = $this->buildSearchWhere($filters);
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM (
                SELECT ' . self::GROUP_CODE_SQL . ' AS codigo, e.familia_id
                FROM entregas_ajuda e
                INNER JOIN familias f ON f.id = e.familia_id
                INNER JOIN residencias r ON r.id = f.residencia_id
                INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
                INNER JOIN municipios m ON m.id = r.municipio_id
                INNER JOIN tipos_ajuda t ON t.id = e.tipo_ajuda_id
                INNER JOIN usuarios u ON u.id = e.entregue_por
                WHERE ' . $where . '
                GROUP BY ' . self::GROUP_CODE_SQL . ', e.familia_id
            ) grouped'
        );
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function summary(array $filters): array
    {
        [$where, $params] = $this->buildSearchWhere($filters);
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(DISTINCT CONCAT(' . self::GROUP_CODE_SQL . ', "#", e.familia_id)) AS total_entregas,
                    COUNT(DISTINCT e.familia_id) AS familias_atendidas,
                    COALESCE(SUM(e.quantidade), 0) AS total_quantidade,
                    MAX(e.data_entrega) AS ultima_entrega
             FROM entregas_ajuda e
             INNER JOIN familias f ON f.id = e.familia_id
             INNER JOIN residencias r ON r.id = f.residencia_id
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             INNER JOIN tipos_ajuda t ON t.id = e.tipo_ajuda_id
             INNER JOIN usuarios u ON u.id = e.entregue_por
             WHERE ' . $where
        );
        $this->bindParams($stmt, $params);
        $stmt->execute();

        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($summary) ? $summary : [];
    }

    public function byFamilia(int $familiaId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT MIN(e.id) AS id,
                    ' . self::GROUP_CODE_SQL . ' AS comprovante_codigo,
                    COALESCE(SUM(e.quantidade), 0) AS quantidade_total,
                    COUNT(*) AS total_itens,
                    MAX(e.data_entrega) AS data_entrega,
                    MAX(e.observacao) AS observacao,
                    GROUP_CONCAT(CONCAT(t.nome, " (", FORMAT(e.quantidade, 0, "de_DE"), " ", t.unidade_medida, ")") ORDER BY t.nome SEPARATOR " | ") AS itens_resumo,
                    u.nome AS entregue_por_nome
             FROM entregas_ajuda e
             INNER JOIN tipos_ajuda t ON t.id = e.tipo_ajuda_id
             INNER JOIN usuarios u ON u.id = e.entregue_por
             WHERE e.familia_id = :familia_id AND e.deleted_at IS NULL
             GROUP BY ' . self::GROUP_CODE_SQL . ', u.nome
             ORDER BY MAX(e.data_entrega) DESC, MIN(e.id) DESC'
        );
        $stmt->bindValue(':familia_id', $familiaId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO entregas_ajuda
                (familia_id, tipo_ajuda_id, quantidade, entregue_por, comprovante_codigo, grupo_comprovante_codigo, observacao)
             VALUES
                (:familia_id, :tipo_ajuda_id, :quantidade, :entregue_por, :comprovante_codigo, :grupo_comprovante_codigo, :observacao)'
        );
        $stmt->bindValue(':familia_id', (int) $data['familia_id'], PDO::PARAM_INT);
        $stmt->bindValue(':tipo_ajuda_id', (int) $data['tipo_ajuda_id'], PDO::PARAM_INT);
        $stmt->bindValue(':quantidade', number_format((float) $data['quantidade'], 2, '.', ''));
        $stmt->bindValue(':entregue_por', (int) $data['entregue_por'], PDO::PARAM_INT);
        $stmt->bindValue(':comprovante_codigo', $data['comprovante_codigo']);
        $stmt->bindValue(':grupo_comprovante_codigo', $data['grupo_comprovante_codigo'] ?? $data['comprovante_codigo']);
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

    public function itemsByGroupCode(string $groupCode, ?int $familiaId = null): array
    {
        $familyWhere = $familiaId !== null ? ' AND e.familia_id = :familia_id' : '';
        $stmt = Database::connection()->prepare(
            'SELECT e.id, e.quantidade, e.comprovante_codigo, t.nome AS tipo_ajuda_nome, t.unidade_medida
             FROM entregas_ajuda e
             INNER JOIN tipos_ajuda t ON t.id = e.tipo_ajuda_id
             WHERE ' . self::GROUP_CODE_SQL . ' = :codigo
                ' . $familyWhere . '
                AND e.deleted_at IS NULL
             ORDER BY t.nome ASC, e.id ASC'
        );
        $stmt->bindValue(':codigo', $groupCode);
        if ($familiaId !== null) {
            $stmt->bindValue(':familia_id', $familiaId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildSearchWhere(array $filters): array
    {
        $where = [
            'e.deleted_at IS NULL',
            'f.deleted_at IS NULL',
            'r.deleted_at IS NULL',
            'a.deleted_at IS NULL',
        ];
        $params = [];

        if (($filters['q'] ?? '') !== '') {
            $where[] = '(e.comprovante_codigo LIKE :q_comprovante
                OR e.grupo_comprovante_codigo LIKE :q_grupo
                OR f.responsavel_nome LIKE :q_nome
                OR f.responsavel_cpf LIKE :q_cpf
                OR r.protocolo LIKE :q_protocolo
                OR r.bairro_comunidade LIKE :q_bairro
                OR a.localidade LIKE :q_localidade
                OR t.nome LIKE :q_tipo)';
            $search = '%' . $filters['q'] . '%';
            $params['q_comprovante'] = $search;
            $params['q_grupo'] = $search;
            $params['q_nome'] = $search;
            $params['q_cpf'] = $search;
            $params['q_protocolo'] = $search;
            $params['q_bairro'] = $search;
            $params['q_localidade'] = $search;
            $params['q_tipo'] = $search;
        }

        if (($filters['acao_busca'] ?? '') !== '') {
            $where[] = '(a.localidade LIKE :acao_busca_localidade
                OR a.tipo_evento LIKE :acao_busca_evento
                OR CONCAT(a.localidade, " - ", a.tipo_evento) LIKE :acao_busca_completa)';
            $actionSearch = '%' . $filters['acao_busca'] . '%';
            $params['acao_busca_localidade'] = $actionSearch;
            $params['acao_busca_evento'] = $actionSearch;
            $params['acao_busca_completa'] = $actionSearch;
        } elseif (($filters['acao_id'] ?? '') !== '') {
            $where[] = 'a.id = :acao_id';
            $params['acao_id'] = (int) $filters['acao_id'];
        }

        if (($filters['residencia_id'] ?? '') !== '') {
            $where[] = 'r.id = :residencia_id';
            $params['residencia_id'] = (int) $filters['residencia_id'];
        } elseif (($filters['residencia_busca'] ?? '') !== '') {
            $where[] = '(r.protocolo LIKE :residencia_busca_protocolo
                OR r.bairro_comunidade LIKE :residencia_busca_bairro
                OR r.endereco LIKE :residencia_busca_endereco)';
            $residenceSearch = '%' . $filters['residencia_busca'] . '%';
            $params['residencia_busca_protocolo'] = $residenceSearch;
            $params['residencia_busca_bairro'] = $residenceSearch;
            $params['residencia_busca_endereco'] = $residenceSearch;
        }

        if (($filters['familia_id'] ?? '') !== '') {
            $where[] = 'f.id = :familia_id';
            $params['familia_id'] = (int) $filters['familia_id'];
        } elseif (($filters['familia_busca'] ?? '') !== '') {
            $where[] = '(f.responsavel_nome LIKE :familia_busca_nome
                OR f.responsavel_cpf LIKE :familia_busca_cpf)';
            $familySearch = '%' . $filters['familia_busca'] . '%';
            $params['familia_busca_nome'] = $familySearch;
            $params['familia_busca_cpf'] = $familySearch;
        }

        if (($filters['tipo_ajuda_id'] ?? '') !== '') {
            $where[] = 't.id = :tipo_ajuda_id';
            $params['tipo_ajuda_id'] = (int) $filters['tipo_ajuda_id'];
        }

        if (($filters['data_inicio'] ?? '') !== '') {
            $where[] = 'e.data_entrega >= :data_inicio';
            $params['data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
        }

        if (($filters['data_fim'] ?? '') !== '') {
            $where[] = 'e.data_entrega <= :data_fim';
            $params['data_fim'] = $filters['data_fim'] . ' 23:59:59';
        }

        return [implode(' AND ', $where), $params];
    }

    private function bindParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }
}
