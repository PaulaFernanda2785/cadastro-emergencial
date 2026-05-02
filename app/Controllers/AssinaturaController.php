<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Repositories\CoassinaturaRepository;
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
            Session::flash('warning', 'Esta solicitacao ja foi respondida.');
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

        Session::flash('success', 'Assinatura autorizada. O solicitante sera avisado da sua decisao.');
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
            Session::flash('warning', 'Esta solicitacao ja foi respondida.');
            $this->redirect('/assinaturas/' . (int) $id);
        }

        $reason = mb_substr(trim((string) ($_POST['motivo_negativa'] ?? '')), 0, 500);

        if ($reason === '') {
            Session::flash('error', 'Informe o motivo para nao autorizar a assinatura.');
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

        Session::flash('success', 'Assinatura nao autorizada. O solicitante sera avisado da sua decisao.');
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
            Session::flash('warning', 'Esta solicitacao nao esta marcada como nao autorizada.');
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

        Session::flash('success', 'Solicitacao retornada para assinatura. Voce pode autorizar ou registrar nova decisao.');
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

    private function guardPost(string $scope, string $failureRedirect): void
    {
        $this->guardCsrf($failureRedirect);
        $this->reserveIdempotency($scope, $failureRedirect);
    }

    private function guardCsrf(string $failureRedirect): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Sessao expirada ou formulario invalido.');
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
        if (!in_array($documentType, ['', 'dti', 'prestacao_contas'], true)) {
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
