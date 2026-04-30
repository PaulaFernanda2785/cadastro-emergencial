<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AcaoEmergencialRepository;

final class AcaoEmergencialService
{
    public function __construct(
        private readonly AcaoEmergencialRepository $acoes = new AcaoEmergencialRepository()
    ) {
    }

    public function create(array $data): int
    {
        $data['token_publico'] = $this->generatePublicToken();
        $data['status'] = $data['status'] ?? 'aberta';
        $data['criado_por'] = (int) (current_user()['id'] ?? 0);

        return $this->acoes->create($data);
    }

    private function generatePublicToken(): string
    {
        do {
            $token = bin2hex(random_bytes(24));
        } while ($this->acoes->findByPublicToken($token) !== null);

        return $token;
    }
}
