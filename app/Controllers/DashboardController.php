<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $this->view('dashboard.index', [
            'title' => 'Painel situacional',
            'user' => current_user(),
        ]);
    }

    public function admin(): void
    {
        $this->view('dashboard.index', [
            'title' => 'Area administrativa',
            'user' => current_user(),
        ]);
    }
}
