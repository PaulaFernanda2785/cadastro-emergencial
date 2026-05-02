<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Repositories\AcaoEmergencialRepository;
use App\Repositories\EntregaAjudaRepository;
use App\Repositories\FamiliaRepository;
use App\Repositories\ResidenciaRepository;
use App\Repositories\TipoAjudaRepository;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->view('dashboard.index', [
            'title' => 'Painel situacional',
            'user' => current_user(),
            'indicators' => $this->indicators(),
        ]);
    }

    public function admin(): void
    {
        $this->view('dashboard.index', [
            'title' => 'Area administrativa',
            'user' => current_user(),
            'indicators' => $this->indicators(),
        ]);
    }

    private function indicators(): array
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
            'residencias' => (new ResidenciaRepository())->count($ownerId, $token),
            'familias' => (new FamiliaRepository())->count($ownerId, $token),
            'entregas' => (new EntregaAjudaRepository())->count($ownerId, $token),
            'tipos_ajuda' => (new TipoAjudaRepository())->countActive(),
            'acoes_abertas' => $isCadastrador && $token === null
                ? 0
                : (new AcaoEmergencialRepository())->countOpen($isCadastrador ? $token : null),
        ];
    }
}
