<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Repositories\AcaoEmergencialRepository;

final class PublicAcaoController extends Controller
{
    public function show(string $token): void
    {
        $acao = (new AcaoEmergencialRepository())->findByPublicToken($token);

        if ($acao === null) {
            $this->abort(404);
        }

        if (($acao['status'] ?? null) === 'aberta') {
            Session::put('active_action_token', $token);
            Session::put('intended_url', '/acao/' . rawurlencode($token) . '/residencias/novo');

            if (is_authenticated()) {
                $this->redirect('/acao/' . rawurlencode($token) . '/residencias/novo');
            }

            $this->redirect('/login?intended=1');
        } elseif (Session::get('active_action_token') === $token) {
            Session::forget('active_action_token');
        }

        $this->view('public.acao', [
            'title' => 'Ação emergencial',
            'acao' => $acao,
        ]);
    }
}
