<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\AcaoEmergencialRepository;
use App\Repositories\MunicipioRepository;
use App\Services\AcaoEmergencialService;
use App\Services\AuditLogService;
use App\Services\IdempotenciaService;

final class AcaoEmergencialController extends Controller
{
    private const STATUS = ['aberta', 'encerrada', 'cancelada'];

    public function __construct(
        private readonly AcaoEmergencialRepository $acoes = new AcaoEmergencialRepository(),
        private readonly MunicipioRepository $municipios = new MunicipioRepository()
    ) {
    }

    public function index(): void
    {
        $this->view('admin.acoes.index', [
            'title' => 'Acoes emergenciais',
            'acoes' => $this->acoes->all(),
        ]);
    }

    public function create(): void
    {
        $this->form('Nova acao emergencial', [
            'municipio_id' => '',
            'localidade' => '',
            'tipo_evento' => '',
            'data_evento' => date('Y-m-d'),
            'status' => 'aberta',
        ], [], '/admin/acoes');
    }

    public function store(): void
    {
        $this->guardPost('admin.acoes.store', '/admin/acoes/novo');

        $data = $this->input();
        $validator = $this->validator($data);

        if ($validator->fails() || $this->municipios->find((int) $data['municipio_id']) === null) {
            $errors = $validator->errors();
            if ($this->municipios->find((int) $data['municipio_id']) === null) {
                $errors['municipio_id'][] = 'Municipio nao encontrado.';
            }
            $this->form('Nova acao emergencial', $data, $errors, '/admin/acoes');
            return;
        }

        $id = (new AcaoEmergencialService())->create($data);
        (new AuditLogService())->record('criou_acao_emergencial', 'acoes_emergenciais', $id, $data['localidade']);
        Session::flash('success', 'Acao emergencial cadastrada.');

        $this->redirect('/admin/acoes');
    }

    public function edit(string $id): void
    {
        $acao = $this->acoes->find((int) $id);

        if ($acao === null) {
            $this->abort(404);
        }

        $this->form('Editar acao emergencial', $acao, [], '/admin/acoes/' . (int) $id);
    }

    public function update(string $id): void
    {
        $acao = $this->acoes->find((int) $id);

        if ($acao === null) {
            $this->abort(404);
        }

        $this->guardPost('admin.acoes.update.' . (int) $id, '/admin/acoes/' . (int) $id . '/editar');

        $data = $this->input();
        $validator = $this->validator($data);

        if ($validator->fails() || $this->municipios->find((int) $data['municipio_id']) === null) {
            $errors = $validator->errors();
            if ($this->municipios->find((int) $data['municipio_id']) === null) {
                $errors['municipio_id'][] = 'Municipio nao encontrado.';
            }
            $data['id'] = (int) $id;
            $data['token_publico'] = $acao['token_publico'];
            $this->form('Editar acao emergencial', $data, $errors, '/admin/acoes/' . (int) $id);
            return;
        }

        $this->acoes->update((int) $id, $data);
        (new AuditLogService())->record('atualizou_acao_emergencial', 'acoes_emergenciais', (int) $id, $data['localidade']);
        Session::flash('success', 'Acao emergencial atualizada.');

        $this->redirect('/admin/acoes');
    }

    private function form(string $title, array $acao, array $errors, string $action): void
    {
        $this->view('admin.acoes.form', [
            'title' => $title,
            'acao' => $acao,
            'municipios' => $this->municipios->all('PA'),
            'statuses' => self::STATUS,
            'errors' => $errors,
            'action' => $action,
        ]);
    }

    private function input(): array
    {
        return [
            'municipio_id' => trim((string) ($_POST['municipio_id'] ?? '')),
            'localidade' => trim((string) ($_POST['localidade'] ?? '')),
            'tipo_evento' => trim((string) ($_POST['tipo_evento'] ?? '')),
            'data_evento' => trim((string) ($_POST['data_evento'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? 'aberta')),
        ];
    }

    private function validator(array $data): Validator
    {
        return (new Validator())
            ->required('municipio_id', $data['municipio_id'], 'Municipio')
            ->integer('municipio_id', $data['municipio_id'], 'Municipio')
            ->required('localidade', $data['localidade'], 'Localidade')
            ->max('localidade', $data['localidade'], 180, 'Localidade')
            ->required('tipo_evento', $data['tipo_evento'], 'Tipo de evento')
            ->max('tipo_evento', $data['tipo_evento'], 180, 'Tipo de evento')
            ->required('data_evento', $data['data_evento'], 'Data do evento')
            ->date('data_evento', $data['data_evento'], 'Data do evento')
            ->in('status', $data['status'], self::STATUS, 'Status');
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
