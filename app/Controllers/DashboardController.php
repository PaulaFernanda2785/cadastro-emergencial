<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Repositories\DashboardRepository;
use App\Repositories\TipoAjudaRepository;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $payload = $this->dashboardPayload('Painel situacional');

        $this->view('dashboard.index', $payload);
    }

    public function admin(): void
    {
        $payload = $this->dashboardPayload('Area administrativa');

        $this->view('dashboard.index', $payload);
    }

    private function dashboardPayload(string $title): array
    {
        $repository = new DashboardRepository();
        $scope = $this->scope();
        $filters = $this->filters();

        return [
            'title' => $title,
            'user' => current_user(),
            'filters' => $filters,
            'actions' => $repository->actions($scope['owner_id'], $scope['active_action_token']),
            'tiposAjudaAtivos' => (new TipoAjudaRepository())->countActive(),
            'indicators' => $repository->indicators($scope['owner_id'], $scope['active_action_token'], $filters),
            'conditionBreakdown' => $repository->conditionBreakdown($scope['owner_id'], $scope['active_action_token'], $filters),
            'neighborhoodRanking' => $repository->neighborhoodRanking($scope['owner_id'], $scope['active_action_token'], $filters),
            'mapResidences' => $repository->mapResidences($scope['owner_id'], $scope['active_action_token'], $filters),
            'recentResidences' => $repository->recentResidences($scope['owner_id'], $scope['active_action_token'], $filters),
            'scope' => $scope,
            'generatedAt' => date('d/m/Y H:i'),
        ];
    }

    private function scope(): array
    {
        $user = current_user();
        $isCadastrador = ($user['perfil'] ?? null) === 'cadastrador';
        $ownerId = $isCadastrador ? (int) ($user['id'] ?? 0) : null;
        $token = null;

        if ($isCadastrador) {
            $activeActionToken = Session::get('active_action_token');
            $token = is_string($activeActionToken) && $activeActionToken !== '' ? $activeActionToken : null;
        }

        return [
            'is_cadastrador' => $isCadastrador,
            'owner_id' => $ownerId,
            'active_action_token' => $token,
        ];
    }

    private function filters(): array
    {
        $acaoId = (int) ($_GET['acao_id'] ?? 0);

        return [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'acao_id' => $acaoId > 0 ? $acaoId : '',
            'acao_busca' => trim((string) ($_GET['acao_busca'] ?? '')),
            'condicao' => $this->allowed((string) ($_GET['condicao'] ?? ''), ['perda_total', 'perda_parcial', 'nao_atingida']),
            'imovel' => $this->allowed((string) ($_GET['imovel'] ?? ''), ['proprio', 'alugado', 'cedido']),
            'geo' => $this->allowed((string) ($_GET['geo'] ?? ''), ['com_geo', 'sem_geo']),
            'entregas' => $this->allowed((string) ($_GET['entregas'] ?? ''), ['com_entrega', 'sem_entrega']),
            'cadastro' => $this->allowed((string) ($_GET['cadastro'] ?? ''), ['concluido', 'pendente']),
            'data_inicio' => $this->dateInput((string) ($_GET['data_inicio'] ?? '')),
            'data_fim' => $this->dateInput((string) ($_GET['data_fim'] ?? '')),
        ];
    }

    private function allowed(string $value, array $allowed): string
    {
        return in_array($value, $allowed, true) ? $value : '';
    }

    private function dateInput(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';
    }
}
