<?php
$summary = $summary ?? [];
$filters = $filters ?? [];
$pagination = $pagination ?? ['page' => 1, 'pages' => 1, 'total' => 0];
$pageUrl = static function (int $page) use ($filters): string {
    $params = array_filter($filters, static fn (mixed $value): bool => (string) $value !== '');
    $params['pagina'] = $page;

    return url('/gestor/entregas') . '?' . http_build_query($params);
};
require BASE_PATH . '/resources/views/gestor/entregas/_nav.php';
?>

<section class="dashboard-header deliveries-header">
    <div>
        <span class="eyebrow">Gestao operacional</span>
        <h1>Historico de entregas</h1>
        <p>Consulte entregas registradas por familia, acao, residencia, tipo de ajuda e periodo.</p>
    </div>
</section>

<section class="records-summary-grid delivery-summary-grid">
    <article class="records-summary-card">
        <span>Entregas filtradas</span>
        <strong><?= h($summary['total_entregas'] ?? 0) ?></strong>
        <small>Registros encontrados.</small>
    </article>
    <article class="records-summary-card">
        <span>Familias atendidas</span>
        <strong><?= h($summary['familias_atendidas'] ?? 0) ?></strong>
        <small>Familias unicas no filtro.</small>
    </article>
    <article class="records-summary-card">
        <span>Quantidade total</span>
        <strong><?= h(number_format((float) ($summary['total_quantidade'] ?? 0), 2, ',', '.')) ?></strong>
        <small>Soma das quantidades.</small>
    </article>
    <article class="records-summary-card">
        <span>Ultima entrega</span>
        <strong><?= !empty($summary['ultima_entrega']) ? h(date('d/m/Y', strtotime((string) $summary['ultima_entrega']))) : '-' ?></strong>
        <small><?= !empty($summary['ultima_entrega']) ? h(date('H:i', strtotime((string) $summary['ultima_entrega']))) : 'Sem registro' ?></small>
    </article>
</section>

<section class="records-filter-panel delivery-filter-panel">
    <div class="table-heading">
        <h2>Filtros inteligentes</h2>
        <span>Combine texto, acao, residencia, periodo e tipo de ajuda.</span>
    </div>
    <form method="get" action="<?= h(url('/gestor/entregas')) ?>" class="delivery-filter-form">
        <label class="field">
            <span>Buscar</span>
            <input type="search" name="q" value="<?= h($filters['q'] ?? '') ?>" placeholder="Nome, CPF, comprovante, protocolo">
        </label>
        <label class="field">
            <span>Acao</span>
            <select name="acao_id">
                <option value="">Todas</option>
                <?php foreach ($acoes ?? [] as $acao): ?>
                    <option value="<?= h($acao['id']) ?>" <?= (string) ($filters['acao_id'] ?? '') === (string) $acao['id'] ? 'selected' : '' ?>>
                        <?= h($acao['localidade']) ?> - <?= h($acao['tipo_evento']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Residencia</span>
            <select name="residencia_id">
                <option value="">Todas</option>
                <?php foreach ($residencias ?? [] as $residencia): ?>
                    <option value="<?= h($residencia['id']) ?>" <?= (string) ($filters['residencia_id'] ?? '') === (string) $residencia['id'] ? 'selected' : '' ?>>
                        <?= h($residencia['protocolo']) ?> - <?= h($residencia['bairro_comunidade']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Tipo de ajuda</span>
            <select name="tipo_ajuda_id">
                <option value="">Todos</option>
                <?php foreach ($tipos ?? [] as $tipo): ?>
                    <option value="<?= h($tipo['id']) ?>" <?= (string) ($filters['tipo_ajuda_id'] ?? '') === (string) $tipo['id'] ? 'selected' : '' ?>>
                        <?= h($tipo['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span>Inicio</span>
            <input type="date" name="data_inicio" value="<?= h($filters['data_inicio'] ?? '') ?>">
        </label>
        <label class="field">
            <span>Fim</span>
            <input type="date" name="data_fim" value="<?= h($filters['data_fim'] ?? '') ?>">
        </label>
        <div class="records-filter-actions">
            <button type="submit" class="primary-button">Filtrar</button>
            <a class="secondary-button" href="<?= h(url('/gestor/entregas')) ?>">Limpar</a>
        </div>
    </form>
</section>

<section class="table-panel delivery-table-panel">
    <div class="table-heading">
        <h2>Registros de entrega</h2>
        <span><?= h($pagination['total'] ?? count($entregas)) ?> comprovante(s)</span>
    </div>
    <div class="table-scroll">
        <table class="data-table delivery-data-table">
            <thead>
                <tr>
                    <th>Comprovante</th>
                    <th>Familia</th>
                    <th>Ajuda</th>
                    <th>Quantidade</th>
                    <th>Residencia</th>
                    <th>Entrega</th>
                    <th class="actions-column">Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entregas as $entrega): ?>
                    <tr>
                        <td data-label="Comprovante"><?= h($entrega['comprovante_codigo']) ?></td>
                        <td data-label="Familia">
                            <?= h($entrega['responsavel_nome']) ?><br>
                            <small><?= h($entrega['responsavel_cpf']) ?></small>
                        </td>
                        <td data-label="Ajuda"><?= h($entrega['itens_resumo'] ?? $entrega['tipo_ajuda_nome'] ?? '-') ?></td>
                        <td data-label="Quantidade"><?= h(number_format((float) ($entrega['quantidade_total'] ?? $entrega['quantidade'] ?? 0), 2, ',', '.')) ?></td>
                        <td data-label="Residencia">
                            <a href="<?= h(url('/cadastros/residencias/' . $entrega['residencia_id'])) ?>"><?= h($entrega['protocolo']) ?></a><br>
                            <small><?= h($entrega['bairro_comunidade']) ?> - <?= h($entrega['municipio_nome']) ?>/<?= h($entrega['uf']) ?></small>
                        </td>
                        <td data-label="Entrega">
                            <?= h(date('d/m/Y H:i', strtotime((string) $entrega['data_entrega']))) ?><br>
                            <small><?= h($entrega['entregue_por_nome']) ?></small>
                        </td>
                        <td class="actions-column" data-label="Acoes">
                            <a href="<?= h(url('/gestor/entregas/' . $entrega['id'] . '/comprovante')) ?>">Comprovante</a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($entregas === []): ?>
                    <tr>
                        <td colspan="7" class="empty-state">Nenhuma entrega encontrada para os filtros informados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (($pagination['pages'] ?? 1) > 1): ?>
        <nav class="records-pagination delivery-pagination" aria-label="Paginacao do historico de entregas">
            <a class="secondary-button <?= (int) $pagination['page'] <= 1 ? 'is-disabled' : '' ?>" href="<?= h($pageUrl(max(1, (int) $pagination['page'] - 1))) ?>">Anterior</a>
            <div class="pagination-pages">
                <?php for ($page = 1; $page <= (int) $pagination['pages']; $page++): ?>
                    <a class="<?= $page === (int) $pagination['page'] ? 'is-active' : '' ?>" href="<?= h($pageUrl($page)) ?>"><?= h($page) ?></a>
                <?php endfor; ?>
            </div>
            <a class="secondary-button <?= (int) $pagination['page'] >= (int) $pagination['pages'] ? 'is-disabled' : '' ?>" href="<?= h($pageUrl(min((int) $pagination['pages'], (int) $pagination['page'] + 1))) ?>">Proxima</a>
        </nav>
    <?php endif; ?>
</section>
