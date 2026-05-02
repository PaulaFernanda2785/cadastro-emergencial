<?php

declare(strict_types=1);

namespace App\Controllers\Gestor;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Repositories\AcaoEmergencialRepository;
use App\Repositories\LogRepository;
use App\Repositories\PrestacaoContasRepository;
use App\Repositories\TipoAjudaRepository;
use App\Repositories\UsuarioRepository;
use App\Services\AuditLogService;
use App\Services\IdempotenciaService;

final class PrestacaoContasController extends Controller
{
    private const PER_PAGE = 10;

    public function __construct(
        private readonly PrestacaoContasRepository $prestacao = new PrestacaoContasRepository(),
        private readonly AcaoEmergencialRepository $acoes = new AcaoEmergencialRepository(),
        private readonly TipoAjudaRepository $tipos = new TipoAjudaRepository(),
        private readonly UsuarioRepository $usuarios = new UsuarioRepository()
    ) {
    }

    public function index(): void
    {
        $filters = $this->filters();
        $hasAppliedFilters = $this->hasAppliedFilters($filters);
        $page = max(1, (int) ($_GET['pagina'] ?? 1));
        $total = $hasAppliedFilters ? $this->prestacao->countDetails($filters) : 0;
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min($page, $totalPages);
        $generatedAt = new \DateTimeImmutable();
        $currentUser = $this->currentUserRecord();
        $documentIdentity = $hasAppliedFilters ? $this->documentIdentity($filters) : null;
        $showSignature = $hasAppliedFilters && (string) ($_GET['assinatura'] ?? '') === '1';
        $signature = ($documentIdentity !== null && $showSignature) ? $this->latestSignature($documentIdentity) : null;

        $this->view('gestor.prestacao_contas.index', [
            'title' => 'Prestacao de contas',
            'filters' => $filters,
            'acoes' => $this->acoes->all(),
            'tipos' => $this->tipos->all(),
            'signatureUsers' => $this->usuarios->activeExcept((int) ($currentUser['id'] ?? 0)),
            'hasAppliedFilters' => $hasAppliedFilters,
            'indicators' => $hasAppliedFilters ? $this->prestacao->indicators($filters) : $this->emptyIndicators(),
            'totalsByType' => $hasAppliedFilters ? $this->prestacao->totalsByType($filters) : [],
            'details' => $hasAppliedFilters ? $this->prestacao->details($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE) : [],
            'documentContext' => $hasAppliedFilters ? $this->prestacao->documentContext($filters) : [],
            'currentUser' => $currentUser,
            'signature' => $signature,
            'documentIdentity' => $documentIdentity,
            'pagination' => [
                'page' => $page,
                'per_page' => self::PER_PAGE,
                'total' => $total,
                'pages' => $totalPages,
            ],
            'generatedAt' => $generatedAt,
        ]);
    }

    public function sign(): void
    {
        $filters = $this->filtersFromArray($_POST);

        if (!$this->hasAppliedFilters($filters)) {
            Session::flash('warning', 'Aplique pelo menos um filtro antes de assinar a prestacao de contas.');
            $this->redirect('/gestor/prestacao-contas');
        }

        $identity = $this->documentIdentity($filters);
        $this->guardPost('gestor.prestacao_contas.sign.' . $identity['entity_id'], $this->filterUrl($filters));

        $signature = $this->buildDocumentSignature(
            $filters,
            $identity,
            new \DateTimeImmutable(),
            $this->currentUserRecord(),
            $this->selectedCoSigners()
        );

        (new AuditLogService())->record(
            'assinou_prestacao_contas',
            'prestacao_contas',
            (int) $identity['entity_id'],
            json_encode($signature, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        Session::flash('success', 'Prestacao de contas assinada digitalmente com os usuarios selecionados.');
        $this->redirect($this->filterUrl($filters, true));
    }

    public function removeSignature(): void
    {
        $filters = $this->filtersFromArray($_POST);

        if (!$this->hasAppliedFilters($filters)) {
            Session::flash('warning', 'Aplique pelo menos um filtro antes de remover assinatura.');
            $this->redirect('/gestor/prestacao-contas');
        }

        $identity = $this->documentIdentity($filters);
        $this->guardPost('gestor.prestacao_contas.remove_signature.' . $identity['entity_id'], $this->filterUrl($filters));

        if ($this->latestSignature($identity) === null) {
            Session::flash('warning', 'Esta prestacao de contas nao possui assinatura ativa para remover.');
            $this->redirect($this->filterUrl($filters));
        }

        (new AuditLogService())->record(
            'removeu_assinatura_prestacao_contas',
            'prestacao_contas',
            (int) $identity['entity_id'],
            json_encode([
                'document_key' => $identity['document_key'],
                'removido_por' => (int) (current_user()['id'] ?? 0),
                'removed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        Session::flash('success', 'Assinatura da prestacao de contas removida. O documento pode ser assinado novamente.');
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

        if ($acaoId === '' && preg_match('/Acao\s+#(\d+)/i', $acaoBusca, $matches) === 1) {
            $acaoId = $this->positiveInt($matches[1]);
        }

        return [
            'q' => mb_substr(trim((string) ($source['q'] ?? '')), 0, 120),
            'acao_id' => $acaoId,
            'acao_busca' => $acaoBusca,
            'tipo_ajuda_id' => $this->positiveInt($source['tipo_ajuda_id'] ?? null),
            'tipo_ajuda_busca' => mb_substr(trim((string) ($source['tipo_ajuda_busca'] ?? '')), 0, 120),
            'localidade_busca' => mb_substr(trim((string) ($source['localidade_busca'] ?? '')), 0, 120),
            'data_inicio' => $this->date($source['data_inicio'] ?? null),
            'data_fim' => $this->date($source['data_fim'] ?? null),
        ];
    }

    private function currentUserRecord(): array
    {
        $userId = (int) (current_user()['id'] ?? 0);

        if ($userId <= 0) {
            return current_user() ?? [];
        }

        return $this->usuarios->find($userId) ?? (current_user() ?? []);
    }

    private function hasAppliedFilters(array $filters): bool
    {
        foreach (['q', 'acao_id', 'acao_busca', 'tipo_ajuda_id', 'tipo_ajuda_busca', 'localidade_busca', 'data_inicio', 'data_fim'] as $key) {
            if ((string) ($filters[$key] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    private function emptyIndicators(): array
    {
        return [
            'total_entregas' => 0,
            'familias_atendidas' => 0,
            'tipos_distribuidos' => 0,
            'quantidade_total' => 0,
        ];
    }

    private function selectedCoSigners(): array
    {
        $postedIds = is_array($_POST['assinantes_usuarios'] ?? null) ? $_POST['assinantes_usuarios'] : [];
        $currentUserId = (int) (current_user()['id'] ?? 0);
        $ids = array_values(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $postedIds
        ), static fn (int $id): bool => $id > 0 && $id !== $currentUserId));

        return $this->usuarios->activeByIds($ids);
    }

    private function latestSignature(array $identity): ?array
    {
        $log = (new LogRepository())->latestForEntityActions(
            ['assinou_prestacao_contas', 'removeu_assinatura_prestacao_contas'],
            'prestacao_contas',
            (int) $identity['entity_id']
        );

        if ($log === null || (string) ($log['acao'] ?? '') === 'removeu_assinatura_prestacao_contas') {
            return null;
        }

        $decoded = json_decode((string) ($log['descricao'] ?? ''), true);

        if (!is_array($decoded) || !hash_equals((string) ($identity['document_key'] ?? ''), (string) ($decoded['document_key'] ?? ''))) {
            return null;
        }

        return $decoded + [
            'signed_at' => $log['criado_em'] ?? '',
            'usuario_id' => $log['usuario_id'] ?? null,
        ];
    }

    private function documentIdentity(array $filters): array
    {
        $details = $this->prestacao->details($filters);
        $summary = $this->prestacao->indicators($filters);
        $payload = [
            'filters' => $filters,
            'summary' => $summary,
            'details' => array_map(
                static fn (array $item): array => [
                    'familia' => (string) ($item['beneficiario_cpf'] ?? ''),
                    'tipo_ajuda_id' => (int) ($item['tipo_ajuda_id'] ?? 0),
                    'quantidade_total' => (string) ($item['quantidade_total'] ?? '0'),
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

        return '/gestor/prestacao-contas' . ($params !== [] ? '?' . http_build_query($params) : '');
    }

    private function buildDocumentSignature(
        array $filters,
        array $identity,
        \DateTimeImmutable $generatedAt,
        array $currentUser,
        array $coSigners
    ): array {
        $signers = array_merge(
            [$this->signatureUserPayload($currentUser, 'assinante_principal')],
            array_map(
                fn (array $coSigner): array => $this->signatureUserPayload($coSigner, 'responsavel_conferencia'),
                $coSigners
            )
        );

        $base = json_encode([
            'documento' => 'Prestacao de contas de ajuda humanitaria',
            'document_key' => $identity['document_key'],
            'filters' => $filters,
            'generated_at' => $generatedAt->format('Y-m-d H:i:s'),
            'signers' => array_map(
                static fn (array $signer): int => (int) ($signer['usuario_id'] ?? 0),
                $signers
            ),
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
            'documento' => 'Prestacao de contas de ajuda humanitaria',
            'document_key' => $identity['document_key'],
            'assinantes' => $signers,
        ];
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
            Session::flash('error', 'Sessao expirada ou formulario invalido.');
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
        $errors = \DateTimeImmutable::getLastErrors();

        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return '';
        }

        return $value;
    }
}
