<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ResidenciaRepository
{
    private const FAMILIAS_COUNT_SQL = '(
        SELECT COUNT(*)
        FROM familias f
        WHERE f.residencia_id = r.id AND f.deleted_at IS NULL
    )';

    public function all(?int $cadastradoPor = null): array
    {
        $sql = 'SELECT r.id, r.protocolo, r.bairro_comunidade, r.endereco, r.imovel, r.condicao_residencia, r.quantidade_familias,
                       r.data_cadastro, a.localidade, a.tipo_evento, m.nome AS municipio_nome, m.uf,
                       u.nome AS cadastrador_nome,
                       (
                           SELECT COUNT(*)
                           FROM familias f
                           WHERE f.residencia_id = r.id AND f.deleted_at IS NULL
                       ) AS familias_cadastradas
                FROM residencias r
                INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
                INNER JOIN municipios m ON m.id = r.municipio_id
                INNER JOIN usuarios u ON u.id = r.cadastrado_por
                WHERE r.deleted_at IS NULL';

        if ($cadastradoPor !== null) {
            $sql .= ' AND r.cadastrado_por = :cadastrado_por';
        }

        $sql .= ' ORDER BY r.data_cadastro DESC';

        $stmt = Database::connection()->prepare($sql);

        if ($cadastradoPor !== null) {
            $stmt->bindValue(':cadastrado_por', $cadastradoPor, PDO::PARAM_INT);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function search(?int $cadastradoPor, array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildSearchWhere($cadastradoPor, $filters);
        $sql = 'SELECT r.id, r.protocolo, r.bairro_comunidade, r.endereco, r.imovel, r.condicao_residencia,
                       r.quantidade_familias, r.data_cadastro, a.localidade, a.tipo_evento, a.status AS acao_status,
                       m.nome AS municipio_nome, m.uf, u.nome AS cadastrador_nome,
                       ' . self::FAMILIAS_COUNT_SQL . ' AS familias_cadastradas
                FROM residencias r
                INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
                INNER JOIN municipios m ON m.id = r.municipio_id
                INNER JOIN usuarios u ON u.id = r.cadastrado_por
                WHERE ' . $where . '
                ORDER BY r.data_cadastro DESC
                LIMIT :limit OFFSET :offset';

        $stmt = Database::connection()->prepare($sql);
        $this->bindSearchParams($stmt, $params);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countSearch(?int $cadastradoPor, array $filters): int
    {
        [$where, $params] = $this->buildSearchWhere($cadastradoPor, $filters);
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM residencias r
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             INNER JOIN usuarios u ON u.id = r.cadastrado_por
             WHERE ' . $where
        );
        $this->bindSearchParams($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function summary(?int $cadastradoPor, array $filters): array
    {
        [$where, $params] = $this->buildSearchWhere($cadastradoPor, $filters);
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) AS total_residencias,
                    COALESCE(SUM(' . self::FAMILIAS_COUNT_SQL . '), 0) AS total_familias,
                    COALESCE(SUM(r.quantidade_familias), 0) AS total_capacidade,
                    MAX(r.data_cadastro) AS ultima_atualizacao,
                    SUM(CASE WHEN r.condicao_residencia = "perda_total" THEN 1 ELSE 0 END) AS perda_total,
                    SUM(CASE WHEN r.condicao_residencia = "perda_parcial" THEN 1 ELSE 0 END) AS perda_parcial,
                    SUM(CASE WHEN r.condicao_residencia = "nao_atingida" THEN 1 ELSE 0 END) AS nao_atingida
             FROM residencias r
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             INNER JOIN usuarios u ON u.id = r.cadastrado_por
             WHERE ' . $where
        );
        $this->bindSearchParams($stmt, $params);
        $stmt->execute();

        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($summary) ? $summary : [];
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.*, a.localidade, a.tipo_evento, a.token_publico, a.status AS acao_status,
                    m.nome AS municipio_nome, m.uf, u.nome AS cadastrador_nome
             FROM residencias r
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             INNER JOIN municipios m ON m.id = r.municipio_id
             INNER JOIN usuarios u ON u.id = r.cadastrado_por
             WHERE r.id = :id AND r.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $residencia = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($residencia) ? $residencia : null;
    }

    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO residencias
                (acao_id, protocolo, municipio_id, bairro_comunidade, endereco, complemento, imovel, condicao_residencia,
                 latitude, longitude, foto_georreferenciada, quantidade_familias, cadastrado_por)
             VALUES
                (:acao_id, :protocolo, :municipio_id, :bairro_comunidade, :endereco, :complemento, :imovel, :condicao_residencia,
                 :latitude, :longitude, :foto_georreferenciada, :quantidade_familias, :cadastrado_por)'
        );
        $stmt->bindValue(':acao_id', (int) $data['acao_id'], PDO::PARAM_INT);
        $stmt->bindValue(':protocolo', $data['protocolo']);
        $stmt->bindValue(':municipio_id', (int) $data['municipio_id'], PDO::PARAM_INT);
        $stmt->bindValue(':bairro_comunidade', $data['bairro_comunidade']);
        $stmt->bindValue(':endereco', $data['endereco']);
        $stmt->bindValue(':complemento', $data['complemento'] !== '' ? $data['complemento'] : null);
        $stmt->bindValue(':imovel', $data['imovel'] !== '' ? $data['imovel'] : null);
        $stmt->bindValue(':condicao_residencia', $data['condicao_residencia'] !== '' ? $data['condicao_residencia'] : null);
        $stmt->bindValue(':latitude', $data['latitude'] !== '' ? $data['latitude'] : null);
        $stmt->bindValue(':longitude', $data['longitude'] !== '' ? $data['longitude'] : null);
        $stmt->bindValue(':foto_georreferenciada', $data['foto_georreferenciada'] ?? null);
        $stmt->bindValue(':quantidade_familias', (int) $data['quantidade_familias'], PDO::PARAM_INT);
        $stmt->bindValue(':cadastrado_por', (int) $data['cadastrado_por'], PDO::PARAM_INT);
        $stmt->execute();

        return (int) Database::connection()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE residencias
             SET bairro_comunidade = :bairro_comunidade,
                 endereco = :endereco,
                 complemento = :complemento,
                 imovel = :imovel,
                 condicao_residencia = :condicao_residencia,
                 latitude = :latitude,
                 longitude = :longitude,
                 foto_georreferenciada = :foto_georreferenciada,
                 quantidade_familias = :quantidade_familias
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':bairro_comunidade', $data['bairro_comunidade']);
        $stmt->bindValue(':endereco', $data['endereco']);
        $stmt->bindValue(':complemento', $data['complemento'] !== '' ? $data['complemento'] : null);
        $stmt->bindValue(':imovel', $data['imovel'] !== '' ? $data['imovel'] : null);
        $stmt->bindValue(':condicao_residencia', $data['condicao_residencia'] !== '' ? $data['condicao_residencia'] : null);
        $stmt->bindValue(':latitude', $data['latitude'] !== '' ? $data['latitude'] : null);
        $stmt->bindValue(':longitude', $data['longitude'] !== '' ? $data['longitude'] : null);
        $stmt->bindValue(':foto_georreferenciada', $data['foto_georreferenciada'] ?? null);
        $stmt->bindValue(':quantidade_familias', (int) $data['quantidade_familias'], PDO::PARAM_INT);
        $stmt->execute();
    }

    public function nextSequenceForAction(int $acaoId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) + 1 FROM residencias WHERE acao_id = :acao_id AND deleted_at IS NULL'
        );
        $stmt->bindValue(':acao_id', $acaoId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function neighborhoodsByMunicipalityId(int $municipioId, ?int $acaoId = null, ?int $cadastradoPor = null): array
    {
        $where = [
            'municipio_id = :municipio_id',
            'deleted_at IS NULL',
            'bairro_comunidade IS NOT NULL',
            'bairro_comunidade <> ""',
        ];

        if ($acaoId !== null && $acaoId > 0) {
            $where[] = 'acao_id = :acao_id';
        }

        if ($cadastradoPor !== null) {
            $where[] = 'cadastrado_por = :cadastrado_por';
        }

        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT bairro_comunidade
             FROM residencias
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY bairro_comunidade'
        );
        $stmt->bindValue(':municipio_id', $municipioId, PDO::PARAM_INT);

        if ($acaoId !== null && $acaoId > 0) {
            $stmt->bindValue(':acao_id', $acaoId, PDO::PARAM_INT);
        }

        if ($cadastradoPor !== null) {
            $stmt->bindValue(':cadastrado_por', $cadastradoPor, PDO::PARAM_INT);
        }

        $stmt->execute();

        return array_map(
            static fn (array $row): string => (string) $row['bairro_comunidade'],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function optionsByOpenActions(): array
    {
        return Database::connection()
            ->query(
                "SELECT r.id, r.protocolo, r.bairro_comunidade, r.endereco,
                        a.id AS acao_id, a.localidade, a.tipo_evento
                 FROM residencias r
                 INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
                 WHERE r.deleted_at IS NULL
                    AND a.deleted_at IS NULL
                    AND a.status = 'aberta'
                 ORDER BY a.localidade ASC, r.protocolo ASC"
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function optionsAll(): array
    {
        return Database::connection()
            ->query(
                'SELECT r.id, r.protocolo, r.bairro_comunidade, r.endereco,
                        a.id AS acao_id, a.localidade, a.tipo_evento
                 FROM residencias r
                 INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
                 WHERE r.deleted_at IS NULL
                    AND a.deleted_at IS NULL
                 ORDER BY a.localidade ASC, r.protocolo ASC'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(?int $cadastradoPor = null, ?string $activeActionToken = null): int
    {
        $where = ['r.deleted_at IS NULL'];
        $params = [];

        if ($cadastradoPor !== null) {
            $where[] = 'r.cadastrado_por = :cadastrado_por';
            $params['cadastrado_por'] = $cadastradoPor;
        }

        if ($activeActionToken !== null && $activeActionToken !== '') {
            $where[] = 'a.token_publico = :active_action_token';
            $params['active_action_token'] = $activeActionToken;
        }

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM residencias r
             INNER JOIN acoes_emergenciais a ON a.id = r.acao_id
             WHERE ' . implode(' AND ', $where)
        );

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    private function buildSearchWhere(?int $cadastradoPor, array $filters): array
    {
        $where = ['r.deleted_at IS NULL'];
        $params = [];

        if ($cadastradoPor !== null) {
            $where[] = 'r.cadastrado_por = :cadastrado_por';
            $params['cadastrado_por'] = $cadastradoPor;
        }

        if (($filters['active_action_token'] ?? '') !== '') {
            $where[] = 'a.token_publico = :active_action_token';
            $params['active_action_token'] = (string) $filters['active_action_token'];
        }

        if (($filters['q'] ?? '') !== '') {
            $where[] = '(r.protocolo LIKE :q_protocolo
                OR r.bairro_comunidade LIKE :q_bairro
                OR r.endereco LIKE :q_endereco
                OR a.localidade LIKE :q_localidade
                OR a.tipo_evento LIKE :q_evento
                OR m.nome LIKE :q_municipio
                OR u.nome LIKE :q_cadastrador)';
            $query = '%' . $filters['q'] . '%';
            $params['q_protocolo'] = $query;
            $params['q_bairro'] = $query;
            $params['q_endereco'] = $query;
            $params['q_localidade'] = $query;
            $params['q_evento'] = $query;
            $params['q_municipio'] = $query;
            $params['q_cadastrador'] = $query;
        }

        if (($filters['imovel'] ?? '') !== '') {
            $where[] = 'r.imovel = :imovel';
            $params['imovel'] = $filters['imovel'];
        }

        if (($filters['condicao'] ?? '') !== '') {
            $where[] = 'r.condicao_residencia = :condicao';
            $params['condicao'] = $filters['condicao'];
        }

        if (($filters['familias'] ?? '') === 'completas') {
            $where[] = self::FAMILIAS_COUNT_SQL . ' >= r.quantidade_familias';
        } elseif (($filters['familias'] ?? '') === 'pendentes') {
            $where[] = self::FAMILIAS_COUNT_SQL . ' < r.quantidade_familias';
        } elseif (($filters['familias'] ?? '') === 'sem_familias') {
            $where[] = self::FAMILIAS_COUNT_SQL . ' = 0';
        }

        if (($filters['data_inicio'] ?? '') !== '') {
            $where[] = 'r.data_cadastro >= :data_inicio';
            $params['data_inicio'] = $filters['data_inicio'] . ' 00:00:00';
        }

        if (($filters['data_fim'] ?? '') !== '') {
            $where[] = 'r.data_cadastro <= :data_fim';
            $params['data_fim'] = $filters['data_fim'] . ' 23:59:59';
        }

        return [implode(' AND ', $where), $params];
    }

    private function bindSearchParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }
}
