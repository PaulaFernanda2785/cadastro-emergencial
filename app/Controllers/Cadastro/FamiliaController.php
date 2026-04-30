<?php

declare(strict_types=1);

namespace App\Controllers\Cadastro;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Validator;
use App\Repositories\DocumentoAnexoRepository;
use App\Repositories\FamiliaRepository;
use App\Repositories\ResidenciaRepository;
use App\Services\AuditLogService;
use App\Services\IdempotenciaService;
use App\Services\UploadService;
use RuntimeException;

final class FamiliaController extends Controller
{
    public function __construct(
        private readonly FamiliaRepository $familias = new FamiliaRepository(),
        private readonly ResidenciaRepository $residencias = new ResidenciaRepository(),
        private readonly DocumentoAnexoRepository $documentos = new DocumentoAnexoRepository()
    ) {
    }

    public function index(): void
    {
        $this->view('cadastro.familias.index', [
            'title' => 'Familias cadastradas',
            'familias' => $this->familias->all(),
        ]);
    }

    public function create(string $residenciaId): void
    {
        $residencia = $this->findResidencia((int) $residenciaId);
        $this->form($residencia, $this->emptyInput(), []);
    }

    public function store(string $residenciaId): void
    {
        $residencia = $this->findResidencia((int) $residenciaId);
        $this->guardPost('cadastro.familia.store.' . (int) $residenciaId, '/cadastros/residencias/' . (int) $residenciaId . '/familias/novo');

        $data = $this->input();
        $validator = $this->validator($data);

        if ($validator->fails()) {
            $this->form($residencia, $data, $validator->errors());
            return;
        }

        $upload = new UploadService();
        $documentos = [];
        $files = is_array($_FILES['documentos'] ?? null) ? $upload->normalizeMultiple($_FILES['documentos']) : [];

        foreach ($files as $file) {
            if (!$upload->hasFile($file)) {
                continue;
            }

            try {
                $documentos[] = $upload->storePrivate($file, 'familias');
            } catch (RuntimeException $exception) {
                $errors = $validator->errors();
                $errors['documentos'][] = $exception->getMessage();
                $this->form($residencia, $data, $errors);
                return;
            }
        }

        $data['residencia_id'] = (int) $residenciaId;
        $id = $this->familias->create($data);

        foreach ($documentos as $metadata) {
            $this->documentos->create($metadata + [
                'familia_id' => $id,
                'residencia_id' => null,
                'tipo_documento' => 'documento_familia',
                'enviado_por' => (int) (current_user()['id'] ?? 0),
            ]);
        }

        (new AuditLogService())->record('criou_familia', 'familias', $id, $data['responsavel_nome']);
        Session::flash('success', 'Familia cadastrada.');

        $this->redirect('/cadastros/residencias/' . (int) $residenciaId);
    }

    private function findResidencia(int $id): array
    {
        $residencia = $this->residencias->find($id);

        if ($residencia === null) {
            $this->abort(404);
        }

        return $residencia;
    }

    private function form(array $residencia, array $familia, array $errors): void
    {
        $this->view('cadastro.familias.form', [
            'title' => 'Nova familia',
            'residencia' => $residencia,
            'familia' => $familia,
            'errors' => $errors,
            'action' => '/cadastros/residencias/' . $residencia['id'] . '/familias',
        ]);
    }

    private function emptyInput(): array
    {
        return [
            'responsavel_nome' => '',
            'responsavel_cpf' => '',
            'responsavel_rg' => '',
            'data_nascimento' => '',
            'telefone' => '',
            'email' => '',
            'quantidade_integrantes' => '1',
            'possui_criancas' => '',
            'possui_idosos' => '',
            'possui_pcd' => '',
            'representante_nome' => '',
            'representante_cpf' => '',
            'representante_rg' => '',
            'representante_telefone' => '',
        ];
    }

    private function input(): array
    {
        return [
            'responsavel_nome' => trim((string) ($_POST['responsavel_nome'] ?? '')),
            'responsavel_cpf' => trim((string) ($_POST['responsavel_cpf'] ?? '')),
            'responsavel_rg' => trim((string) ($_POST['responsavel_rg'] ?? '')),
            'data_nascimento' => trim((string) ($_POST['data_nascimento'] ?? '')),
            'telefone' => trim((string) ($_POST['telefone'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'quantidade_integrantes' => trim((string) ($_POST['quantidade_integrantes'] ?? '1')),
            'possui_criancas' => isset($_POST['possui_criancas']) ? '1' : '',
            'possui_idosos' => isset($_POST['possui_idosos']) ? '1' : '',
            'possui_pcd' => isset($_POST['possui_pcd']) ? '1' : '',
            'representante_nome' => trim((string) ($_POST['representante_nome'] ?? '')),
            'representante_cpf' => trim((string) ($_POST['representante_cpf'] ?? '')),
            'representante_rg' => trim((string) ($_POST['representante_rg'] ?? '')),
            'representante_telefone' => trim((string) ($_POST['representante_telefone'] ?? '')),
        ];
    }

    private function validator(array $data): Validator
    {
        return (new Validator())
            ->required('responsavel_nome', $data['responsavel_nome'], 'Responsavel familiar')
            ->max('responsavel_nome', $data['responsavel_nome'], 180, 'Responsavel familiar')
            ->required('responsavel_cpf', $data['responsavel_cpf'], 'CPF do responsavel')
            ->max('responsavel_cpf', $data['responsavel_cpf'], 14, 'CPF do responsavel')
            ->max('responsavel_rg', $data['responsavel_rg'], 30, 'RG')
            ->date('data_nascimento', $data['data_nascimento'], 'Data de nascimento')
            ->max('telefone', $data['telefone'], 30, 'Telefone')
            ->email('email', $data['email'], 'E-mail')
            ->max('email', $data['email'], 180, 'E-mail')
            ->integer('quantidade_integrantes', $data['quantidade_integrantes'], 'Quantidade de integrantes')
            ->minInt('quantidade_integrantes', $data['quantidade_integrantes'], 1, 'Quantidade de integrantes')
            ->max('representante_nome', $data['representante_nome'], 180, 'Representante')
            ->max('representante_cpf', $data['representante_cpf'], 14, 'CPF do representante')
            ->max('representante_rg', $data['representante_rg'], 30, 'RG do representante')
            ->max('representante_telefone', $data['representante_telefone'], 30, 'Telefone do representante');
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
