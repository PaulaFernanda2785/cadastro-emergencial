<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
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
        return [
            'residencias' => (new ResidenciaRepository())->count(),
            'familias' => (new FamiliaRepository())->count(),
            'entregas' => (new EntregaAjudaRepository())->count(),
            'tipos_ajuda' => (new TipoAjudaRepository())->countActive(),
            'acoes_abertas' => (new AcaoEmergencialRepository())->countOpen(),
        ];
    }
}
