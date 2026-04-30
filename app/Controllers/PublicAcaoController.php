<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\AcaoEmergencialRepository;

final class PublicAcaoController extends Controller
{
    public function show(string $token): void
    {
        $acao = (new AcaoEmergencialRepository())->findByPublicToken($token);

        if ($acao === null) {
            $this->abort(404);
        }

        $this->view('public.acao', [
            'title' => 'Acao emergencial',
            'acao' => $acao,
        ]);
    }
}
