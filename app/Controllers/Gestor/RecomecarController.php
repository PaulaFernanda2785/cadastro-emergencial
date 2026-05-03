<?php

declare(strict_types=1);

namespace App\Controllers\Gestor;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Repositories\AcaoEmergencialRepository;
use App\Repositories\CoassinaturaRepository;
use App\Repositories\LogRepository;
use App\Repositories\RecomecarRepository;
use App\Repositories\UsuarioRepository;
use App\Services\AuditLogService;
use App\Services\IdempotenciaService;

final class RecomecarController extends Controller
{
    private const PER_PAGE = 10;
    private const DOCUMENT_TYPE = 'recomecar';

    public function __construct(
        private readonly RecomecarRepository $recomecar = new RecomecarRepository(),
        private readonly AcaoEmergencialRepository $acoes = new AcaoEmergencialRepository(),
        private readonly UsuarioRepository $usuarios = new UsuarioRepository()
    ) {
    }

    public function index(): void
    {
        $filters = $this->filters();
        $embedDocument = (string) ($_GET['embed_document'] ?? '') === '1';
        $hasAppliedFilters = $this->hasAppliedFilters($filters);
        $page = max(1, (int) ($_GET['pagina'] ?? 1));
        $total = $hasAppliedFilters ? $this->recomecar->countDetails($filters) : 0;
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $totalPages);
        $generatedAt = new \DateTimeImmutable();
        $currentUser = $this->currentUserRecord();
        $documentIdentity = $hasAppliedFilters ? $this->documentIdentity($filters) : null;
        $showSignature = $hasAppliedFilters && (string) ($_GET['assinatura'] ?? '') === '1';
        $signature = ($documentIdentity !== null && $showSignature) ? $this->latestSignature($documentIdentity) : null;
        $coSignatureStatus = $documentIdentity !== null
            ? (new CoassinaturaRepository())->statusSummary(self::DOCUMENT_TYPE, (string) $documentIdentity['document_key'])
            : $this->emptyCoSignatureStatus();

        $this->view('gestor.recomecar.index', [
            'title' => 'Recomeçar',
            'filters' => $filters,
            'acoes' => $this->acoes->all(),
            'signatureUsers' => $this->signatureUsers((int) ($currentUser['id'] ?? 0)),
            'hasAppliedFilters' => $hasAppliedFilters,
            'indicators' => $hasAppliedFilters ? $this->recomecar->indicators($filters) : $this->emptyIndicators(),
            'details' => $hasAppliedFilters ? $this->recomecar->details($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE) : [],
            'documentContext' => $hasAppliedFilters ? $this->recomecar->documentContext($filters) : [],
            'currentUser' => $currentUser,
            'signature' => $signature,
            'coSignatureStatus' => $coSignatureStatus,
            'documentIdentity' => $documentIdentity,
            'pagination' => [
                'page' => $page,
                'per_page' => self::PER_PAGE,
                'total' => $total,
                'pages' => $totalPages,
            ],
            'generatedAt' => $generatedAt,
            'embedDocument' => $embedDocument,
        ], $embedDocument ? 'embed' : 'app');
    }

    public function sign(): void
    {
        $filters = $this->filtersFromArray($_POST);

        if (!$this->hasAppliedFilters($filters)) {
            Session::flash('warning', 'Aplique pelo menos um filtro antes de assinar o documento do Programa Recomeçar.');
            $this->redirect('/gestor/recomecar');
        }

        if (($filters['aptidao'] ?? 'apta') !== 'apta') {
            Session::flash('warning', 'Somente a relação de famílias aptas pode ser assinada para pagamento do Programa Recomeçar.');
            $this->redirect($this->filterUrl($filters));
        }

        $identity = $this->documentIdentity($filters);
        $this->guardPost('gestor.recomecar.sign.' . $identity['entity_id'], $this->filterUrl($filters));
        $coSigners = $this->selectedCoSigners();
        $signature = $this->buildDocumentSignature($filters, $identity, new \DateTimeImmutable(), $this->currentUserRecord(), $coSigners);

        (new AuditLogService())->record(
            'assinou_recomecar',
            self::DOCUMENT_TYPE,
            (int) $identity['entity_id'],
            json_encode($signature, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        $this->createCoSignatureRequests($filters, $identity, $signature, $coSigners);

        Session::flash(
            'success',
            $coSigners === []
                ? 'Documento do Programa Recomeçar assinado digitalmente. A impressão está liberada.'
                : 'Documento do Programa Recomeçar assinado pelo usuário principal. A impressão será liberada após autorização dos responsáveis pela conferência.'
        );
        $this->redirect($this->filterUrl($filters, true));
    }

    public function removeSignature(): void
    {
        $filters = $this->filtersFromArray($_POST);

        if (!$this->hasAppliedFilters($filters)) {
            Session::flash('warning', 'Aplique pelo menos um filtro antes de remover assinatura.');
            $this->redirect('/gestor/recomecar');
        }

        $identity = $this->documentIdentity($filters);
        $this->guardPost('gestor.recomecar.remove_signature.' . $identity['entity_id'], $this->filterUrl($filters));
        $signature = $this->latestSignature($identity);

        if ($signature === null) {
            Session::flash('warning', 'Este documento não possui assinatura ativa para remover.');
            $this->redirect($this->filterUrl($filters));
        }

        $principalUserId = (int) ($signature['usuario_id'] ?? 0);
        if ($principalUserId <= 0 || $principalUserId !== (int) (current_user()['id'] ?? 0)) {
            $this->abort(403);
        }

        (new AuditLogService())->record(
            'removeu_assinatura_recomecar',
            self::DOCUMENT_TYPE,
            (int) $identity['entity_id'],
            json_encode([
                'document_key' => $identity['document_key'],
                'removido_por' => $principalUserId,
                'removed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        (new CoassinaturaRepository())->cancelDocument(self::DOCUMENT_TYPE, (string) $identity['document_key']);

        Session::flash('success', 'Assinatura do documento Recomeçar removida. O documento pode ser assinado novamente.');
        $this->redirect($this->filterUrl($filters));
    }

    private function filters(): array
    {
        return $this->filtersFromArray($_GET);
    }

    private function filtersFromArray(array $source): array
    {
        $acaoBusca = mb_substr(trim((string) ($source['acao_busca'] ?? '')), 0, 120);
        $acaoId = $this->positiveInt($source['acao_id'] ?? null);
        $aptidao = (string) ($source['aptidao'] ?? 'apta');

        if (!in_array($aptidao, ['apta', 'inapta', 'todas'], true)) {
            $aptidao = 'apta';
        }

        $statusEntrega = (string) ($source['status_entrega'] ?? '');
        if (!in_array($statusEntrega, ['', 'entregue', 'nao_entregue'], true)) {
            $statusEntrega = '';
        }

        if ($acaoId === '' && preg_match('/A[cç][aã]o\s+#(\d+)/iu', $acaoBusca, $matches) === 1) {
            $acaoId = $this->positiveInt($matches[1]);
        }

        return [
            'q' => mb_substr(trim((string) ($source['q'] ?? '')), 0, 120),
            'acao_id' => $acaoId,
            'acao_busca' => $acaoBusca,
            'localidade_busca' => mb_substr(trim((string) ($source['localidade_busca'] ?? '')), 0, 120),
            'aptidao' => $aptidao,
            'status_entrega' => $statusEntrega,
            'data_inicio' => $this->date($source['data_inicio'] ?? null),
            'data_fim' => $this->date($source['data_fim'] ?? null),
        ];
    }

    private function hasAppliedFilters(array $filters): bool
    {
        foreach (['q', 'acao_id', 'acao_busca', 'localidade_busca', 'status_entrega', 'data_inicio', 'data_fim'] as $key) {
            if ((string) ($filters[$key] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    private function emptyIndicators(): array
    {
        return [
            'total_familias' => 0,
            'familias_aptas' => 0,
            'familias_inaptas' => 0,
        ];
    }

    private function emptyCoSignatureStatus(): array
    {
        return [
            'total' => 0,
            'pendentes' => 0,
            'autorizados' => 0,
            'negados' => 0,
            'impressao_liberada' => true,
            'solicitacoes' => [],
        ];
    }

    private function documentIdentity(array $filters): array
    {
        $details = $this->recomecar->details($filters);
        $summary = $this->recomecar->indicators($filters);
        $payload = [
            'filters' => $filters,
            'summary' => $summary,
            'details' => array_map(
                static fn (array $item): array => [
                    'familia_id' => (int) ($item['familia_id'] ?? 0),
                    'beneficiario_nome' => (string) ($item['beneficiario_nome'] ?? ''),
                    'beneficiario_cpf' => (string) ($item['beneficiario_cpf'] ?? ''),
                    'beneficiario_rg' => (string) ($item['beneficiario_rg'] ?? ''),
                    'beneficiario_orgao_expedidor' => (string) ($item['beneficiario_orgao_expedidor'] ?? ''),
                    'beneficiario_sexo' => (string) ($item['beneficiario_sexo'] ?? ''),
                    'beneficiario_data_nascimento' => (string) ($item['beneficiario_data_nascimento'] ?? ''),
                    'aptidao' => (string) ($item['aptidao'] ?? ''),
                    'status_entrega' => (string) ($item['status_entrega'] ?? ''),
                    'total_entregas' => (int) ($item['total_entregas'] ?? 0),
                ],
                $details
            ),
        ];
        $documentKey = hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return [
            'document_key' => $documentKey,
            'entity_id' => (hexdec(substr($documentKey, 0, 8)) & 0x7fffffff) ?: 1,
        ];
    }

    private function filterUrl(array $filters, bool $showSignature = false): string
    {
        $params = array_filter($filters, static fn (mixed $value): bool => (string) $value !== '');

        if ($showSignature) {
            $params['assinatura'] = '1';
        }

        return '/gestor/recomecar' . ($params !== [] ? '?' . http_build_query($params) : '');
    }

    private function latestSignature(array $identity): ?array
    {
        $log = (new LogRepository())->latestForEntityActions(
            ['assinou_recomecar', 'removeu_assinatura_recomecar'],
            self::DOCUMENT_TYPE,
            (int) $identity['entity_id']
        );

        if ($log === null || (string) ($log['acao'] ?? '') === 'removeu_assinatura_recomecar') {
            return null;
        }

        $decoded = json_decode((string) ($log['descricao'] ?? ''), true);

        if (!is_array($decoded) || !hash_equals((string) ($identity['document_key'] ?? ''), (string) ($decoded['document_key'] ?? ''))) {
            return null;
        }

        return $this->enrichSignatureWithCoSignatures($decoded + [
            'signed_at' => $log['criado_em'] ?? '',
            'usuario_id' => $log['usuario_id'] ?? null,
        ], (string) $identity['document_key']);
    }

    private function buildDocumentSignature(array $filters, array $identity, \DateTimeImmutable $generatedAt, array $currentUser, array $coSigners): array
    {
        $signers = [$this->signatureUserPayload($currentUser, 'assinante_principal')];
        $base = json_encode([
            'documento' => 'Programa Recomeçar',
            'document_key' => $identity['document_key'],
            'filters' => $filters,
            'generated_at' => $generatedAt->format('Y-m-d H:i:s'),
            'signers' => array_map(static fn (array $signer): int => (int) ($signer['usuario_id'] ?? 0), $signers),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [
            'nome' => (string) ($currentUser['nome'] ?? ''),
            'cpf' => (string) ($currentUser['cpf'] ?? ''),
            'graduacao' => (string) ($currentUser['graduacao'] ?? ''),
            'nome_guerra' => (string) ($currentUser['nome_guerra'] ?? ''),
            'matricula_funcional' => (string) ($currentUser['matricula_funcional'] ?? ''),
            'orgao' => (string) ($currentUser['orgao'] ?? ''),
            'unidade_setor' => (string) ($currentUser['unidade_setor'] ?? ''),
            'usuario_id' => (int) ($currentUser['id'] ?? 0),
            'signed_at' => $generatedAt->format('Y-m-d H:i:s'),
            'hash' => strtoupper(hash('sha256', (string) $base)),
            'documento' => 'Programa Recomeçar',
            'document_key' => $identity['document_key'],
            'assinantes' => $signers,
            'coassinantes_solicitados' => array_map(
                fn (array $coSigner): array => $this->signatureUserPayload($coSigner, 'responsavel_conferencia'),
                $coSigners
            ),
        ];
    }

    private function createCoSignatureRequests(array $filters, array $identity, array $signature, array $coSigners): void
    {
        (new CoassinaturaRepository())->replacePendingRequests([
            'documento_tipo' => self::DOCUMENT_TYPE,
            'documento_chave' => (string) $identity['document_key'],
            'entidade' => self::DOCUMENT_TYPE,
            'entidade_id' => (int) $identity['entity_id'],
            'titulo' => 'Programa Recomeçar',
            'descricao' => 'Documento de pagamento gerado por filtros operacionais em ' . date('d/m/Y H:i'),
            'url_documento' => $this->filterUrl($filters, true),
            'solicitante_usuario_id' => (int) (current_user()['id'] ?? 0),
            'assinante_principal' => array_merge(
                is_array($signature['assinantes'][0] ?? null) ? $signature['assinantes'][0] : [],
                [
                    'usuario_id' => (int) ($signature['usuario_id'] ?? current_user()['id'] ?? 0),
                    'signed_at' => (string) ($signature['signed_at'] ?? ''),
                    'hash' => (string) ($signature['hash'] ?? ''),
                ]
            ),
            'payload' => [
                'documento' => $signature['documento'] ?? 'Programa Recomeçar',
                'document_key' => $identity['document_key'],
                'hash' => $signature['hash'] ?? '',
                'filters' => $filters,
            ],
        ], $coSigners);
    }

    private function enrichSignatureWithCoSignatures(array $signature, string $documentKey): array
    {
        $repository = new CoassinaturaRepository();
        $status = $repository->statusSummary(self::DOCUMENT_TYPE, $documentKey);
        $primary = is_array($signature['assinantes'][0] ?? null) ? [$signature['assinantes'][0]] : [[
            'tipo' => 'assinante_principal',
            'usuario_id' => (int) ($signature['usuario_id'] ?? 0),
            'nome' => (string) ($signature['nome'] ?? ''),
            'cpf' => (string) ($signature['cpf'] ?? ''),
            'email' => (string) ($signature['email'] ?? ''),
            'telefone' => (string) ($signature['telefone'] ?? ''),
            'graduacao' => (string) ($signature['graduacao'] ?? ''),
            'nome_guerra' => (string) ($signature['nome_guerra'] ?? ''),
            'matricula_funcional' => (string) ($signature['matricula_funcional'] ?? ''),
            'orgao' => (string) ($signature['orgao'] ?? ''),
            'unidade_setor' => (string) ($signature['unidade_setor'] ?? ''),
        ]];
        $authorized = array_map(static function (array $payload): array {
            $payload['tipo'] = 'responsavel_conferencia';

            return $payload;
        }, $repository->authorizedSignerPayloads(self::DOCUMENT_TYPE, $documentKey));

        $signature['assinantes'] = array_merge($primary, $authorized);
        $signature['coassinatura_status'] = $status;
        $signature['impressao_liberada'] = (bool) ($status['impressao_liberada'] ?? true);

        return $signature;
    }

    private function selectedCoSigners(): array
    {
        $postedIds = is_array($_POST['assinantes_usuarios'] ?? null) ? $_POST['assinantes_usuarios'] : [];
        $currentUserId = (int) (current_user()['id'] ?? 0);
        $ids = array_values(array_filter(array_map(static fn (mixed $id): int => (int) $id, $postedIds), static fn (int $id): bool => $id > 0 && $id !== $currentUserId));

        return array_values(array_filter(
            $this->usuarios->activeByIds($ids),
            static fn (array $user): bool => in_array((string) ($user['perfil'] ?? ''), ['gestor', 'administrador'], true)
        ));
    }

    private function signatureUsers(int $currentUserId): array
    {
        return array_values(array_filter(
            $this->usuarios->activeExcept($currentUserId),
            static fn (array $user): bool => in_array((string) ($user['perfil'] ?? ''), ['gestor', 'administrador'], true)
        ));
    }

    private function currentUserRecord(): array
    {
        $userId = (int) (current_user()['id'] ?? 0);

        if ($userId <= 0) {
            return current_user() ?? [];
        }

        return $this->usuarios->find($userId) ?? (current_user() ?? []);
    }

    private function signatureUserPayload(array $user, string $tipo): array
    {
        return [
            'usuario_id' => (int) ($user['id'] ?? 0),
            'tipo' => $tipo,
            'nome' => (string) ($user['nome'] ?? ''),
            'cpf' => (string) ($user['cpf'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'telefone' => (string) ($user['telefone'] ?? ''),
            'graduacao' => (string) ($user['graduacao'] ?? ''),
            'nome_guerra' => (string) ($user['nome_guerra'] ?? ''),
            'matricula_funcional' => (string) ($user['matricula_funcional'] ?? ''),
            'orgao' => (string) ($user['orgao'] ?? ''),
            'unidade_setor' => (string) ($user['unidade_setor'] ?? ''),
        ];
    }

    private function guardPost(string $scope, string $failureRedirect): void
    {
        if (!Csrf::validate($_POST['_csrf_token'] ?? null)) {
            Session::flash('error', 'Sessão expirada ou formulário inválido.');
            $this->redirect($failureRedirect);
        }

        $idempotency = (new IdempotenciaService())->validateAndReserve($_POST['_idempotency_token'] ?? null, $scope);

        if (!$idempotency['ok']) {
            Session::flash('warning', $idempotency['message']);
            $this->redirect($failureRedirect);
        }
    }

    private function positiveInt(mixed $value): string
    {
        if (!is_string($value) && !is_int($value)) {
            return '';
        }

        $value = trim((string) $value);

        return filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value > 0 ? $value : '';
    }

    private function date(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $value = trim($value);
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : '';
    }
}
