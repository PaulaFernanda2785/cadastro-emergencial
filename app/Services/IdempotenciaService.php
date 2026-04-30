<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use PDO;
use PDOException;

final class IdempotenciaService
{
    public function generate(string $scope): string
    {
        $token = bin2hex(random_bytes(32));
        $tokens = Session::get('_idempotency_tokens', []);

        if (!is_array($tokens)) {
            $tokens = [];
        }

        $tokens[$scope][$token] = time();
        Session::put('_idempotency_tokens', $tokens);

        return $token;
    }

    public function validateAndReserve(?string $token, string $route): array
    {
        if (!is_string($token) || $token === '') {
            return [
                'ok' => false,
                'duplicate' => false,
                'message' => 'Formulario invalido. Recarregue a pagina e tente novamente.',
            ];
        }

        $tokens = Session::get('_idempotency_tokens', []);

        if (!is_array($tokens) || !$this->existsInSession($tokens, $token)) {
            return [
                'ok' => false,
                'duplicate' => true,
                'message' => 'Esta solicitacao ja foi recebida. Aguarde alguns segundos antes de tentar novamente.',
            ];
        }

        $window = (int) (require BASE_PATH . '/config/security.php')['idempotency_window_seconds'];
        $pdo = Database::connection();

        $check = $pdo->prepare(
            'SELECT id FROM tokens_idempotencia
             WHERE token = :token
               AND rota = :rota
               AND processado_em >= (NOW() - INTERVAL :seconds SECOND)
             LIMIT 1'
        );
        $check->bindValue(':token', $token);
        $check->bindValue(':rota', $route);
        $check->bindValue(':seconds', $window, PDO::PARAM_INT);
        $check->execute();

        if ($check->fetch(PDO::FETCH_ASSOC)) {
            return [
                'ok' => false,
                'duplicate' => true,
                'message' => 'Operacao ja processada. Nenhuma acao duplicada foi executada.',
            ];
        }

        try {
            $insert = $pdo->prepare(
                'INSERT INTO tokens_idempotencia (token, usuario_id, rota, ip_origem)
                 VALUES (:token, :usuario_id, :rota, :ip_origem)'
            );
            $userId = current_user()['id'] ?? null;
            $insert->bindValue(':token', $token);
            $insert->bindValue(':usuario_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $insert->bindValue(':rota', $route);
            $insert->bindValue(':ip_origem', $_SERVER['REMOTE_ADDR'] ?? null);
            $insert->execute();
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                return [
                    'ok' => false,
                    'duplicate' => true,
                    'message' => 'Operacao ja processada. Nenhuma acao duplicada foi executada.',
                ];
            }

            throw $exception;
        }

        $this->removeFromSession($tokens, $token);
        Session::put('_idempotency_tokens', $tokens);

        return ['ok' => true, 'duplicate' => false, 'message' => null];
    }

    private function existsInSession(array $tokens, string $token): bool
    {
        foreach ($tokens as $scopeTokens) {
            if (is_array($scopeTokens) && array_key_exists($token, $scopeTokens)) {
                return true;
            }
        }

        return false;
    }

    private function removeFromSession(array &$tokens, string $token): void
    {
        foreach ($tokens as $scope => $scopeTokens) {
            if (is_array($scopeTokens) && array_key_exists($token, $scopeTokens)) {
                unset($tokens[$scope][$token]);
            }
        }
    }
}
