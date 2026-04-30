<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ResidenciaRepository;

final class ProtocoloService
{
    public function __construct(
        private readonly ResidenciaRepository $residencias = new ResidenciaRepository()
    ) {
    }

    public function generate(array $acao): string
    {
        $sequence = $this->residencias->nextSequenceForAction((int) $acao['id']);
        $year = date('Y', strtotime((string) $acao['data_evento']));
        $localidade = $this->slug((string) $acao['localidade']);
        $evento = $this->slug((string) $acao['tipo_evento']);

        return sprintf('%04d/%s/%s/%s', $sequence, $year, $localidade, $evento);
    }

    private function slug(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?: '';
        $value = trim($value, '-');

        return strtoupper(substr($value !== '' ? $value : 'GERAL', 0, 30));
    }
}
