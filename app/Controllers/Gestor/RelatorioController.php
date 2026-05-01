<?php

declare(strict_types=1);

namespace App\Controllers\Gestor;

use App\Core\Controller;
use App\Repositories\AcaoEmergencialRepository;
use App\Repositories\RelatorioRepository;

final class RelatorioController extends Controller
{
    public function __construct(
        private readonly RelatorioRepository $relatorios = new RelatorioRepository(),
        private readonly AcaoEmergencialRepository $acoes = new AcaoEmergencialRepository()
    ) {
    }

    public function index(): void
    {
        $filters = $this->filters();

        $this->view('gestor.relatorios.index', [
            'title' => 'Relatorios operacionais',
            'filters' => $filters,
            'acoes' => $this->acoes->all(),
            'indicators' => $this->relatorios->indicators($filters),
            'byAction' => $this->relatorios->byAction($filters),
            'byNeighborhood' => $this->relatorios->byNeighborhood($filters),
            'byHousingType' => $this->relatorios->byHousingType($filters),
            'byResidenceCondition' => $this->relatorios->byResidenceCondition($filters),
            'deliveriesByType' => $this->relatorios->deliveriesByType($filters),
            'pendingFamilies' => $this->relatorios->pendingFamilies($filters),
            'generatedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function export(): void
    {
        $filters = $this->filters();
        $rows = $this->relatorios->exportRows($filters);
        $filename = 'relatorio-cadastros-entregas-' . date('Ymd-His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            return;
        }

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, [
            'Protocolo',
            'Municipio',
            'UF',
            'Bairro/Comunidade',
            'Endereco',
            'Imovel',
            'Condicao da residencia',
            'Localidade da acao',
            'Tipo de evento',
            'Data do cadastro',
            'Responsavel familiar',
            'CPF',
            'Telefone',
            'Integrantes',
            'Status da entrega',
        ], ';');

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['protocolo'] ?? '',
                $row['municipio'] ?? '',
                $row['uf'] ?? '',
                $row['bairro_comunidade'] ?? '',
                $row['endereco'] ?? '',
                residencia_imovel_label($row['imovel'] ?? null),
                residencia_condicao_label($row['condicao_residencia'] ?? null),
                $row['localidade'] ?? '',
                $row['tipo_evento'] ?? '',
                !empty($row['data_cadastro']) ? date('d/m/Y H:i', strtotime((string) $row['data_cadastro'])) : '',
                $row['responsavel_nome'] ?? '',
                $row['responsavel_cpf'] ?? '',
                $row['telefone'] ?? '',
                $row['quantidade_integrantes'] ?? '',
                $row['status_entrega'] ?? '',
            ], ';');
        }

        fclose($output);
    }

    private function filters(): array
    {
        return [
            'acao_id' => $this->positiveInt($_GET['acao_id'] ?? null),
            'bairro' => $this->text($_GET['bairro'] ?? null, 180),
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

    private function text(mixed $value, int $max): string
    {
        if (!is_string($value)) {
            return '';
        }

        return mb_substr(trim($value), 0, $max);
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
