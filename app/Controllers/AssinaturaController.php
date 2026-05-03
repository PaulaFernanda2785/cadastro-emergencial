<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use App\Repositories\CoassinaturaRepository;
use App\Repositories\DocumentoAnexoRepository;
use App\Repositories\FamiliaRepository;
use App\Repositories\ResidenciaRepository;
use App\Repositories\UsuarioRepository;
use App\Services\AuditLogService;
use App\Services\IdempotenciaService;

final class AssinaturaController extends Controller
{
    public function __construct(
        private readonly CoassinaturaRepository $coassinaturas = new CoassinaturaRepository(),
        private readonly UsuarioRepository $usuarios = new UsuarioRepository()
    ) {
    }

    public function index(): void
    {
        $userId = (int) (current_user()['id'] ?? 0);
        $isAdmin = $this->isAdmin();
        $this->coassinaturas->repairPrincipalHistoryFromAuditLogs($userId, $isAdmin);
        $this->coassinaturas->markRequesterNotified($userId);
        $filters = $this->signatureFilters();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $signatures = $this->coassinaturas->paginatedForUser($userId, $filters, $page, 10, $isAdmin);

        $this->view('assinaturas.index', [
            'title' => 'Assinaturas',
            'pendentes' => $this->coassinaturas->pendingForUser($userId),
            'summary' => $this->coassinaturas->summaryForUser($userId, $isAdmin),
            'filters' => $filters,
            'assinaturas' => $signatures['data'],
            'pagination' => $signatures['pagination'],
            'isAdmin' => $isAdmin,
        ]);
    }

    public function show(string $id): void
    {
        $request = $this->findRequest((int) $id);
        $coSignatureStatus = $this->coassinaturas->statusSummary(
            (string) ($request['documento_tipo'] ?? ''),
            (string) ($request['documento_chave'] ?? '')
        );

        $this->view('assinaturas.show', [
            'title' => 'Assinatura de documento',
            'assinatura' => $request,
            'payload' => $this->decodePayload($request),
            'coSignatureStatus' => $coSignatureStatus,
            'printReady' => (bool) ($coSignatureStatus['impressao_liberada'] ?? false),
            'inlineDocumentHtml' => $this->inlineDocumentHtml($request, $coSignatureStatus),
        ]);
    }

    public function authorize(string $id): void
    {
        $request = $this->findRequest((int) $id);
        $this->guardPost('assinaturas.authorize.' . (int) $id, '/assinaturas/' . (int) $id);

        if ((int) ($request['coautor_usuario_id'] ?? 0) !== (int) (current_user()['id'] ?? 0)) {
            $this->abort(403);
        }

        if ((string) ($request['status'] ?? '') !== 'pendente') {
            Session::flash('warning', 'Esta solicitação já foi respondida.');
            $this->redirect('/assinaturas/' . (int) $id);
        }

        $user = $this->usuarios->find((int) (current_user()['id'] ?? 0)) ?? (current_user() ?? []);
        $this->coassinaturas->authorize((int) $id, (int) ($user['id'] ?? 0), $user);

        (new AuditLogService())->record(
            'autorizou_coassinatura',
            'coassinaturas_documentos',
            (int) $id,
            json_encode([
                'documento_tipo' => $request['documento_tipo'] ?? '',
                'documento_chave' => $request['documento_chave'] ?? '',
                'titulo' => $request['titulo'] ?? '',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        Session::flash('success', 'Assinatura autorizada. O solicitante será avisado da sua decisão.');
        $this->redirect('/assinaturas/' . (int) $id);
    }

    public function reject(string $id): void
    {
        $request = $this->findRequest((int) $id);
        $failureRedirect = '/assinaturas/' . (int) $id;
        $this->guardCsrf($failureRedirect);

        if ((int) ($request['coautor_usuario_id'] ?? 0) !== (int) (current_user()['id'] ?? 0)) {
            $this->abort(403);
        }

        if ((string) ($request['status'] ?? '') !== 'pendente') {
            Session::flash('warning', 'Esta solicitação já foi respondida.');
            $this->redirect('/assinaturas/' . (int) $id);
        }

        $reason = mb_substr(trim((string) ($_POST['motivo_negativa'] ?? '')), 0, 500);

        if ($reason === '') {
            Session::flash('error', 'Informe o motivo para não autorizar a assinatura.');
            $this->redirect($failureRedirect);
        }

        $this->reserveIdempotency('assinaturas.reject.' . (int) $id, $failureRedirect);
        $this->coassinaturas->reject((int) $id, (int) (current_user()['id'] ?? 0), $reason);

        (new AuditLogService())->record(
            'negou_coassinatura',
            'coassinaturas_documentos',
            (int) $id,
            json_encode([
                'documento_tipo' => $request['documento_tipo'] ?? '',
                'documento_chave' => $request['documento_chave'] ?? '',
                'titulo' => $request['titulo'] ?? '',
                'motivo' => $reason,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        Session::flash('success', 'Assinatura não autorizada. O solicitante será avisado da sua decisão.');
        $this->redirect('/assinaturas/' . (int) $id);
    }

    public function returnToSignature(string $id): void
    {
        $request = $this->findRequest((int) $id);
        $failureRedirect = '/assinaturas/' . (int) $id;
        $this->guardPost('assinaturas.return.' . (int) $id, $failureRedirect);

        if ((int) ($request['coautor_usuario_id'] ?? 0) !== (int) (current_user()['id'] ?? 0)) {
            $this->abort(403);
        }

        if ((string) ($request['status'] ?? '') !== 'negado') {
            Session::flash('warning', 'Esta solicitação não está marcada como não autorizada.');
            $this->redirect($failureRedirect);
        }

        $this->coassinaturas->returnToSignature((int) $id, (int) (current_user()['id'] ?? 0));

        (new AuditLogService())->record(
            'retornou_coassinatura_para_assinatura',
            'coassinaturas_documentos',
            (int) $id,
            json_encode([
                'documento_tipo' => $request['documento_tipo'] ?? '',
                'documento_chave' => $request['documento_chave'] ?? '',
                'titulo' => $request['titulo'] ?? '',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        Session::flash('success', 'Solicitação retornada para assinatura. Você pode autorizar ou registrar nova decisão.');
        $this->redirect($failureRedirect);
    }

    private function findRequest(int $id): array
    {
        $request = $this->coassinaturas->findForUser($id, (int) (current_user()['id'] ?? 0), $this->isAdmin());

        if ($request === null) {
            $this->abort(404);
        }

        return $request;
    }

    private function decodePayload(array $request): array
    {
        $payload = json_decode((string) ($request['payload_json'] ?? ''), true);

        return is_array($payload) ? $payload : [];
    }

    private function inlineDocumentHtml(array $request, array $coSignatureStatus): string
    {
        if ((string) (current_user()['perfil'] ?? '') !== 'cadastrador') {
            return '';
        }

        if ((string) ($request['documento_tipo'] ?? '') !== 'dti') {
            return '';
        }

        return $this->inlineDtiDocumentHtml($request, $coSignatureStatus);
    }

    private function inlineDtiDocumentHtml(array $request, array $coSignatureStatus): string
    {
        $residenciaId = (int) ($request['entidade_id'] ?? 0);

        if ($residenciaId <= 0 && preg_match('/^dti:(\d+)$/', (string) ($request['documento_chave'] ?? ''), $matches) === 1) {
            $residenciaId = (int) $matches[1];
        }

        if ($residenciaId <= 0) {
            return '';
        }

        $residencia = (new ResidenciaRepository())->find($residenciaId);

        if ($residencia === null) {
            return '';
        }

        $documentos = (new DocumentoAnexoRepository())->byResidencia($residenciaId);

        ob_start();
        View::render('cadastro.residencias.dti', [
            'title' => 'DTI ' . (string) ($residencia['protocolo'] ?? ''),
            'residencia' => $residencia,
            'familias' => (new FamiliaRepository())->byResidencia($residenciaId),
            'documentos' => $documentos,
            'fotos' => $this->dtiImageDocuments($documentos),
            'fotosResidencia' => $this->dtiResidenceImageDocuments($documentos),
            'fotosDocumentos' => $this->dtiFamilyDocumentImages($documentos),
            'signature' => $this->dtiSignatureFromCoSignatures($request, $coSignatureStatus),
            'coSignatureStatus' => $coSignatureStatus,
            'signatureUsers' => [],
            'generatedAt' => new \DateTimeImmutable('now'),
            'embedDocument' => true,
        ], '');

        return (string) ob_get_clean();
    }

    private function dtiSignatureFromCoSignatures(array $request, array $coSignatureStatus): ?array
    {
        $requests = is_array($coSignatureStatus['solicitacoes'] ?? null) ? $coSignatureStatus['solicitacoes'] : [];
        $principalRequest = null;

        foreach ($requests as $signatureRequest) {
            if ((int) ($signatureRequest['coautor_usuario_id'] ?? 0) === (int) ($signatureRequest['solicitante_usuario_id'] ?? 0)) {
                $principalRequest = $signatureRequest;
                break;
            }
        }

        if ($principalRequest === null) {
            $principalUserId = (int) ($request['solicitante_usuario_id'] ?? 0);
            $principalUser = $principalUserId > 0 ? ($this->usuarios->find($principalUserId) ?? []) : [];
            $principalRequest = $request + [
                'coautor_usuario_id' => $principalUserId,
                'coautor_nome' => $principalUser['nome'] ?? ($request['solicitante_nome'] ?? ''),
                'coautor_cpf' => $principalUser['cpf'] ?? ($request['solicitante_cpf'] ?? ''),
                'coautor_email' => $principalUser['email'] ?? '',
                'coautor_telefone' => $principalUser['telefone'] ?? '',
                'coautor_graduacao' => $principalUser['graduacao'] ?? '',
                'coautor_nome_guerra' => $principalUser['nome_guerra'] ?? '',
                'coautor_matricula_funcional' => $principalUser['matricula_funcional'] ?? '',
                'coautor_orgao' => $principalUser['orgao'] ?? '',
                'coautor_unidade_setor' => $principalUser['unidade_setor'] ?? '',
            ];
        }

        $snapshot = json_decode((string) ($principalRequest['coautor_snapshot_json'] ?? ''), true);
        $principal = is_array($snapshot) ? $snapshot : [
            'usuario_id' => (int) ($principalRequest['coautor_usuario_id'] ?? $principalRequest['solicitante_usuario_id'] ?? 0),
            'nome' => (string) ($principalRequest['coautor_nome'] ?? $principalRequest['solicitante_nome'] ?? ''),
            'cpf' => (string) ($principalRequest['coautor_cpf'] ?? $principalRequest['solicitante_cpf'] ?? ''),
            'email' => (string) ($principalRequest['coautor_email'] ?? ''),
            'telefone' => (string) ($principalRequest['coautor_telefone'] ?? ''),
            'graduacao' => (string) ($principalRequest['coautor_graduacao'] ?? ''),
            'nome_guerra' => (string) ($principalRequest['coautor_nome_guerra'] ?? ''),
            'matricula_funcional' => (string) ($principalRequest['coautor_matricula_funcional'] ?? ''),
            'orgao' => (string) ($principalRequest['coautor_orgao'] ?? ''),
            'unidade_setor' => (string) ($principalRequest['coautor_unidade_setor'] ?? ''),
        ];
        $principal['tipo'] = 'assinante_principal';

        return [
            'nome' => (string) ($principal['nome'] ?? ''),
            'cpf' => (string) ($principal['cpf'] ?? ''),
            'graduacao' => (string) ($principal['graduacao'] ?? ''),
            'nome_guerra' => (string) ($principal['nome_guerra'] ?? ''),
            'matricula_funcional' => (string) ($principal['matricula_funcional'] ?? ''),
            'orgao' => (string) ($principal['orgao'] ?? ''),
            'unidade_setor' => (string) ($principal['unidade_setor'] ?? ''),
            'usuario_id' => (int) ($principal['usuario_id'] ?? $principal['id'] ?? $principalRequest['coautor_usuario_id'] ?? 0),
            'signed_at' => (string) ($principalRequest['autorizado_em'] ?? $principalRequest['solicitado_em'] ?? ''),
            'hash' => (string) ($principalRequest['hash_autorizacao'] ?? ''),
            'documento' => 'DTI - Descrição Técnica de Imóvel',
            'assinantes' => array_merge(
                [$principal],
                $this->coassinaturas->authorizedSignerPayloads('dti', (string) ($request['documento_chave'] ?? ''))
            ),
            'coassinatura_status' => $coSignatureStatus,
            'impressao_liberada' => (bool) ($coSignatureStatus['impressao_liberada'] ?? true),
        ];
    }

    private function dtiImageDocuments(array $documentos): array
    {
        return array_values(array_filter(
            $documentos,
            static fn (array $documento): bool => str_starts_with((string) ($documento['mime_type'] ?? ''), 'image/')
        ));
    }

    private function dtiResidenceImageDocuments(array $documentos): array
    {
        return array_values(array_filter(
            $this->dtiImageDocuments($documentos),
            static fn (array $documento): bool =>
                in_array((string) ($documento['tipo_documento'] ?? ''), ['foto_georreferenciada', 'foto_residencia_extra'], true)
                && empty($documento['familia_id'])
        ));
    }

    private function dtiFamilyDocumentImages(array $documentos): array
    {
        return array_values(array_filter(
            $this->dtiImageDocuments($documentos),
            static fn (array $documento): bool =>
                (int) ($documento['familia_id'] ?? 0) > 0
                || (string) ($documento['tipo_documento'] ?? '') === 'documento_familia'
        ));
    }

    private function guardPost(string $scope, string $failureRedirect): void
    {
        $this->guardCsrf($failureRedirect);
        $this->reserveIdempotency($scope, $failureRedirect);
    }

    private function guardCsrf(string $failureRedirect): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Sessão expirada ou formulário inválido.');
            $this->redirect($failureRedirect);
        }
    }

    private function reserveIdempotency(string $scope, string $failureRedirect): void
    {
        $idempotency = (new IdempotenciaService())->validateAndReserve($_POST['_idempotency_token'] ?? null, $scope);

        if (!$idempotency['ok']) {
            Session::flash('warning', $idempotency['message']);
            $this->redirect($failureRedirect);
        }
    }

    private function signatureFilters(): array
    {
        $scope = (string) ($_GET['escopo'] ?? 'todas');
        if (!in_array($scope, ['todas', 'para_mim', 'solicitadas'], true)) {
            $scope = 'todas';
        }

        $status = (string) ($_GET['status'] ?? '');
        if (!in_array($status, ['', 'pendente', 'autorizado', 'negado'], true)) {
            $status = '';
        }

        $documentType = (string) ($_GET['documento_tipo'] ?? '');
        if (!in_array($documentType, ['', 'dti', 'prestacao_contas', 'recomecar'], true)) {
            $documentType = '';
        }

        $dateStart = $this->normalizedDate((string) ($_GET['data_inicio'] ?? ''));
        $dateEnd = $this->normalizedDate((string) ($_GET['data_fim'] ?? ''));

        if ($dateStart !== '' && $dateEnd !== '' && $dateStart > $dateEnd) {
            [$dateStart, $dateEnd] = [$dateEnd, $dateStart];
        }

        return [
            'escopo' => $scope,
            'status' => $status,
            'documento_tipo' => $documentType,
            'data_inicio' => $dateStart,
            'data_fim' => $dateEnd,
            'busca' => mb_substr(trim((string) ($_GET['busca'] ?? '')), 0, 120),
        ];
    }

    private function normalizedDate(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : '';
    }

    private function isAdmin(): bool
    {
        return (string) (current_user()['perfil'] ?? '') === 'administrador';
    }
}
