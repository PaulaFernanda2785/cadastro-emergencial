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
        $this->coassinaturas->markRequesterNotified($userId);

        $this->view('assinaturas.index', [
            'title' => 'Assinaturas',
            'pendentes' => $this->coassinaturas->pendingForUser($userId),
            'minhasAssinaturas' => $this->coassinaturas->allForUser($userId),
            'solicitadasPorMim' => $this->coassinaturas->requestedByUser($userId),
        ]);
    }

    public function show(string $id): void
    {
        $request = $this->findRequest((int) $id);

        $this->view('assinaturas.show', [
            'title' => 'Assinatura de documento',
            'assinatura' => $request,
            'payload' => $this->decodePayload($request),
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
        $this->guardPost('assinaturas.reject.' . (int) $id, '/assinaturas/' . (int) $id);

        if ((int) ($request['coautor_usuario_id'] ?? 0) !== (int) (current_user()['id'] ?? 0)) {
            $this->abort(403);
        }

        if ((string) ($request['status'] ?? '') !== 'pendente') {
            Session::flash('warning', 'Esta solicitacao ja foi respondida.');
            $this->redirect('/assinaturas/' . (int) $id);
        }

        $reason = mb_substr(trim((string) ($_POST['motivo_negativa'] ?? '')), 0, 500);
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

    private function findRequest(int $id): array
    {
        $request = $this->coassinaturas->findForUser($id, (int) (current_user()['id'] ?? 0));

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
