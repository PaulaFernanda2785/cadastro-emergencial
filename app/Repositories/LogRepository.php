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
}
