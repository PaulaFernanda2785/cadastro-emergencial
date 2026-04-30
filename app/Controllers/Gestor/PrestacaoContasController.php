<?php

declare(strict_types=1);

namespace App\Controllers\Gestor;

use App\Core\Controller;
use App\Repositories\AcaoEmergencialRepository;
use App\Repositories\PrestacaoContasRepository;
use App\Repositories\TipoAjudaRepository;

final class PrestacaoContasController extends Controller
{
    public function __construct(
        private readonly PrestacaoContasRepository $prestacao = new PrestacaoContasRepository(),
        private readonly AcaoEmergencialRepository $acoes = new AcaoEmergencialRepository(),
        private readonly TipoAjudaRepository $tipos = new TipoAjudaRepository()
    ) {
    }

    public function index(): void
    {
        $filters = $this->filters();

        $this->view('gestor.prestacao_contas.index', [
            'title' => 'Prestacao de contas',
            'filters' => $filters,
            'acoes' => $this->acoes->all(),
            'tipos' => $this->tipos->all(),
            'indicators' => $this->prestacao->indicators($filters),
            'totalsByType' => $this->prestacao->totalsByType($filters),
            'details' => $this->prestacao->details($filters),
            'generatedAt' => new \DateTimeImmutable(),
        ]);
    }

    private function filters(): array
    {
        return [
            'acao_id' => $this->positiveInt($_GET['acao_id'] ?? null),
            'tipo_ajuda_id' => $this->positiveInt($_GET['tipo_ajuda_id'] ?? null),
            'data_inicio' => $this->date($_GET['data_inicio'] ?? null),
            'data_fim' => $this->date($_GET['data_fim'] ?? null),
        ];
    }

    private function positiveInt(mixed $value): string
    {
        if (!is_string($value) && !is_int($value)) {
            return '';
        }

        $value = trim((string) $value);

        return filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value > 0 ? $value : '';
    }

    private function date(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = trim($value);
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return '';
        }

        return $value;
    }
}
