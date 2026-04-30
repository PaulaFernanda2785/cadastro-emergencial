<?php

declare(strict_types=1);

namespace App\Controllers\Cadastro;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\AcaoEmergencialRepository;
use App\Repositories\FamiliaRepository;
use App\Repositories\ResidenciaRepository;
use App\Services\AuditLogService;
use App\Services\IdempotenciaService;
use App\Services\ProtocoloService;

final class ResidenciaController extends Controller
{
    public function __construct(
        private readonly ResidenciaRepository $residencias = new ResidenciaRepository(),
        private readonly FamiliaRepository $familias = new FamiliaRepository(),
        private readonly AcaoEmergencialRepository $acoes = new AcaoEmergencialRepository()
    ) {
    }

    public function index(): void
    {
        $this->view('cadastro.residencias.index', [
            'title' => 'Cadastros de residencias',
            'residencias' => $this->residencias->all(),
        ]);
    }

    public function show(string $id): void
    {
        $residencia = $this->residencias->find((int) $id);

        if ($residencia === null) {
            $this->abort(404);
        }

        $this->view('cadastro.residencias.show', [
            'title' => 'Residencia ' . $residencia['protocolo'],
            'residencia' => $residencia,
            'familias' => $this->familias->byResidencia((int) $id),
        ]);
    }

    public function createFromAction(string $token): void
    {
        $acao = $this->openAction($token);

        $this->form($acao, [
            'bairro_comunidade' => '',
            'endereco' => '',
            'complemento' => '',
            'latitude' => '',
            'longitude' => '',
            'quantidade_familias' => '1',
        ], []);
    }

    public function storeFromAction(string $token): void
    {
        $acao = $this->openAction($token);
        $this->guardPost('cadastro.residencia.store.' . $token, '/acao/' . $token . '/residencias/novo');

        $data = $this->input();
        $validator = $this->validator($data);

        if ($validator->fails()) {
            $this->form($acao, $data, $validator->errors());
            return;
        }

        $data['acao_id'] = (int) $acao['id'];
        $data['municipio_id'] = (int) $acao['municipio_id'];
        $data['protocolo'] = (new ProtocoloService())->generate($acao);
        $data['cadastrado_por'] = (int) (current_user()['id'] ?? 0);

        $id = $this->residencias->create($data);
        (new AuditLogService())->record('criou_residencia', 'residencias', $id, $data['protocolo']);
        Session::flash('success', 'Residencia cadastrada. Agora cadastre as familias vinculadas.');

        $this->redirect('/cadastros/residencias/' . $id);
    }

    private function form(array $acao, array $residencia, array $errors): void
    {
        $this->view('cadastro.residencias.form', [
            'title' => 'Nova residencia',
            'acao' => $acao,
            'residencia' => $residencia,
            'errors' => $errors,
            'action' => '/acao/' . $acao['token_publico'] . '/residencias',
        ]);
    }

    private function openAction(string $token): array
    {
        $acao = $this->acoes->findByPublicToken($token);

        if ($acao === null) {
            $this->abort(404);
        }

        if ($acao['status'] !== 'aberta') {
            Session::flash('warning', 'Esta acao nao esta aberta para novos cadastros.');
            $this->redirect('/acao/' . $token);
        }

        return $acao;
    }

    private function input(): array
    {
        return [
            'bairro_comunidade' => trim((string) ($_POST['bairro_comunidade'] ?? '')),
            'endereco' => trim((string) ($_POST['endereco'] ?? '')),
            'complemento' => trim((string) ($_POST['complemento'] ?? '')),
            'latitude' => trim((string) ($_POST['latitude'] ?? '')),
            'longitude' => trim((string) ($_POST['longitude'] ?? '')),
            'quantidade_familias' => trim((string) ($_POST['quantidade_familias'] ?? '1')),
        ];
    }

    private function validator(array $data): Validator
    {
        return (new Validator())
            ->required('bairro_comunidade', $data['bairro_comunidade'], 'Bairro/comunidade')
            ->max('bairro_comunidade', $data['bairro_comunidade'], 180, 'Bairro/comunidade')
            ->required('endereco', $data['endereco'], 'Endereco')
            ->max('endereco', $data['endereco'], 255, 'Endereco')
            ->max('complemento', $data['complemento'], 180, 'Complemento')
            ->integer('quantidade_familias', $data['quantidade_familias'], 'Quantidade de familias')
            ->minInt('quantidade_familias', $data['quantidade_familias'], 1, 'Quantidade de familias')
            ->decimalRange('latitude', $data['latitude'], -90, 90, 'Latitude')
            ->decimalRange('longitude', $data['longitude'], -180, 180, 'Longitude');
    }

    private function guardPost(string $scope, string $failureRedirect): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Sessao expirada ou formulario invalido.');
            $this->redirect($failureRedirect);
        }

        $idempotency = (new IdempotenciaService())->validateAndReserve($_POST['_idempotency_token'] ?? null, $scope);

        if (!$idempotency['ok']) {
            Session::flash('warning', $idempotency['message']);
            $this->redirect($failureRedirect);
        }
    }
}
