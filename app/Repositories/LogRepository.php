<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class LogRepository
{
    public function create(array $data): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO logs_sistema
                (usuario_id, acao, entidade, entidade_id, descricao, ip_origem, user_agent)
             VALUES
                (:usuario_id, :acao, :entidade, :entidade_id, :descricao, :ip_origem, :user_agent)'
        );

        $stmt->bindValue(':usuario_id', $data['usuario_id'], $data['usuario_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':acao', $data['acao']);
        $stmt->bindValue(':entidade', $data['entidade']);
        $stmt->bindValue(':entidade_id', $data['entidade_id'], $data['entidade_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':descricao', $data['descricao']);
        $stmt->bindValue(':ip_origem', $data['ip_origem']);
        $stmt->bindValue(':user_agent', $data['user_agent']);
        $stmt->execute();
    }

    public function latestForEntityAction(string $action, string $entity, int $entityId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT l.id, l.usuario_id, l.acao, l.entidade, l.entidade_id, l.descricao,
                    l.ip_origem, l.user_agent, l.criado_em,
                    u.nome AS usuario_nome, u.cpf AS usuario_cpf,
                    u.graduacao AS usuario_graduacao, u.nome_guerra AS usuario_nome_guerra,
                    u.matricula_funcional AS usuario_matricula_funcional
             FROM logs_sistema l
             LEFT JOIN usuarios u ON u.id = l.usuario_id
             WHERE l.acao = :acao
               AND l.entidade = :entidade
               AND l.entidade_id = :entidade_id
             ORDER BY l.criado_em DESC, l.id DESC
             LIMIT 1'
        );
        $stmt->bindValue(':acao', $action);
        $stmt->bindValue(':entidade', $entity);
        $stmt->bindValue(':entidade_id', $entityId, PDO::PARAM_INT);
        $stmt->execute();

        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($log) ? $log : null;
    }

    public function latestForEntityActions(array $actions, string $entity, int $entityId): ?array
    {
        $normalizedActions = array_values(array_unique(array_filter(array_map(
            static fn (mixed $action): string => trim((string) $action),
            $actions
        ))));

        if ($normalizedActions === []) {
            return null;
        }

        $placeholders = [];
        foreach ($normalizedActions as $index => $action) {
            $placeholders[] = ':acao_' . $index;
        }

        $stmt = Database::connection()->prepare(
            'SELECT l.id, l.usuario_id, l.acao, l.entidade, l.entidade_id, l.descricao,
                    l.ip_origem, l.user_agent, l.criado_em,
                    u.nome AS usuario_nome, u.cpf AS usuario_cpf,
                    u.graduacao AS usuario_graduacao, u.nome_guerra AS usuario_nome_guerra,
                    u.matricula_funcional AS usuario_matricula_funcional
             FROM logs_sistema l
             LEFT JOIN usuarios u ON u.id = l.usuario_id
             WHERE l.acao IN (' . implode(', ', $placeholders) . ')
               AND l.entidade = :entidade
               AND l.entidade_id = :entidade_id
             ORDER BY l.criado_em DESC, l.id DESC
             LIMIT 1'
        );

        foreach ($normalizedActions as $index => $action) {
            $stmt->bindValue(':acao_' . $index, $action);
        }

        $stmt->bindValue(':entidade', $entity);
        $stmt->bindValue(':entidade_id', $entityId, PDO::PARAM_INT);
        $stmt->execute();

        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($log) ? $log : null;
    }
}
