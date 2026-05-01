<?php
$query = array_filter($filters, static fn ($value): bool => $value !== '');
$exportUrl = '/gestor/relatorios/exportar' . ($query !== [] ? '?' . http_build_query($query) : '');
$periodo = 'Todo o periodo';
if (($filters['data_inicio'] ?? '') !== '' || ($filters['data_fim'] ?? '') !== '') {
    $periodo = ($filters['data_inicio'] ?: 'inicio') . ' a ' . ($filters['data_fim'] ?: 'hoje');
}
?>

<section class="dashboard-header no-print">
    <div>
        <span class="eyebrow">Gestao operacional</span>
        <h1>Relatorios operacionais</h1>
        <p>Visao consolidada de cadastros, entregas e pendencias por acao emergencial.</p>
    </div>
    <div class="form-actions">
        <a class="primary-link-button" href="<?= h(url($exportUrl)) ?>">Exportar CSV</a>
        <button type="button" class="secondary-button" onclick="window.print()">Imprimir</button>
    </div>
</section>

<section class="table-panel report-filters no-print">
    <form method="get" action="<?= h(url('/gestor/relatorios')) ?>" class="form-grid report-filter-grid">
        <label class="field">
            <span>Acao emergencial</span>
            <select name="acao_id">
                <option value="">Todas</option>
                <?php foreach ($acoes as $acao): ?>
                    <option value="<?= h($acao['id']) ?>" <?= (string) ($filters['acao_id'] ?? '') === (string) $acao['id'] ? 'selected' : '' ?>>
                        <?= h($acao['municipio_nome']) ?>/<?= h($acao['uf']) ?> - <?= h($acao['localidade']) ?> - <?= h($acao['tipo_evento']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Bairro/comunidade</span>
            <input type="text" name="bairro" value="<?= h($filters['bairro'] ?? '') ?>" maxlength="180">
        </label>

        <label class="field">
            <span>Data inicial</span>
            <input type="date" name="data_inicio" value="<?= h($filters['data_inicio'] ?? '') ?>">
        </label>

        <label class="field">
            <span>Data final</span>
            <input type="date" name="data_fim" value="<?= h($filters['data_fim'] ?? '') ?>">
        </label>

        <div class="form-actions report-filter-actions">
            <button type="submit" class="primary-button">Filtrar</button>
            <a class="secondary-link" href="<?= h(url('/gestor/relatorios')) ?>">Limpar</a>
        </div>
    </form>
</section>

<section class="report-document">
    <div class="print-heading">
        <span class="eyebrow">Cadastro Emergencial</span>
        <h1>Relatorio operacional</h1>
        <p>Periodo: <?= h($periodo) ?> | Gerado em <?= h($generatedAt->format('d/m/Y H:i')) ?></p>
    </div>

    <section class="indicator-grid report-indicators" aria-label="Indicadores do relatorio">
        <article class="indicator">
            <span>Residencias</span>
            <strong><?= h($indicators['residencias'] ?? 0) ?></strong>
            <small>Cadastros considerados.</small>
        </article>
        <article class="indicator">
            <span>Familias</span>
            <strong><?= h($indicators['familias'] ?? 0) ?></strong>
            <small>Familias vinculadas.</small>
        </article>
        <article class="indicator">
            <span>Pessoas</span>
            <strong><?= h($indicators['pessoas'] ?? 0) ?></strong>
            <small>Integrantes informados.</small>
        </article>
        <article class="indicator">
            <span>Entregas</span>
            <strong><?= h($indicators['entregas'] ?? 0) ?></strong>
            <small>Ajuda registrada.</small>
        </article>
        <article class="indicator">
            <span>Familias atendidas</span>
            <strong><?= h($indicators['familias_atendidas'] ?? 0) ?></strong>
            <small>Com pelo menos uma entrega.</small>
        </article>
        <article class="indicator">
            <span>Pendencias</span>
            <strong><?= h($indicators['familias_pendentes'] ?? 0) ?></strong>
            <small>Familias sem entrega.</small>
        </article>
    </section>

    <section class="table-panel report-section">
        <div class="table-heading">
            <h2>Resumo por acao emergencial</h2>
            <span><?= h(count($byAction)) ?> acao(oes)</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Municipio</th>
                    <th>Acao</th>
                    <th>Status</th>
                    <th>Residencias</th>
                    <th>Familias</th>
                    <th>Pessoas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($byAction as $item): ?>
                    <tr>
                        <td><?= h($item['municipio_nome']) ?>/<?= h($item['uf']) ?></td>
                        <td><?= h($item['localidade']) ?> - <?= h($item['tipo_evento']) ?></td>
                        <td><span class="status status-<?= h($item['status']) ?>"><?= h(ucfirst((string) $item['status'])) ?></span></td>
                        <td><?= h($item['residencias']) ?></td>
                        <td><?= h($item['familias']) ?></td>
                        <td><?= h($item['pessoas']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($byAction === []): ?>
                    <tr>
                        <td colspan="6" class="empty-state">Nenhum cadastro encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="detail-grid report-split">
        <article class="table-panel report-section">
            <div class="table-heading">
                <h2>Por bairro/comunidade</h2>
                <span><?= h(count($byNeighborhood)) ?> localidade(s)</span>
            </div>
            <table class="data-table compact-table">
                <thead>
                    <tr>
                        <th>Bairro</th>
                        <th>Familias</th>
                        <th>Pessoas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($byNeighborhood as $item): ?>
                        <tr>
                            <td><?= h($item['bairro_comunidade']) ?><br><small><?= h($item['municipio_nome']) ?>/<?= h($item['uf']) ?></small></td>
                            <td><?= h($item['familias']) ?></td>
                            <td><?= h($item['pessoas']) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($byNeighborhood === []): ?>
                        <tr>
                            <td colspan="3" class="empty-state">Sem dados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </article>

        <article class="table-panel report-section">
            <div class="table-heading">
                <h2>Por imovel</h2>
                <span><?= h(count($byHousingType)) ?> tipo(s)</span>
            </div>
            <table class="data-table compact-table">
                <thead>
                    <tr>
                        <th>Imovel</th>
                        <th>Residencias</th>
                        <th>Familias</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($byHousingType as $item): ?>
                        <tr>
                            <td><?= h(residencia_imovel_label($item['imovel'] ?? null)) ?></td>
                            <td><?= h($item['residencias']) ?></td>
                            <td><?= h($item['familias']) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($byHousingType === []): ?>
                        <tr>
                            <td colspan="3" class="empty-state">Sem dados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </article>
    </section>

    <section class="detail-grid report-split">
        <article class="table-panel report-section">
            <div class="table-heading">
                <h2>Por condicao</h2>
                <span><?= h(count($byResidenceCondition)) ?> situacao(oes)</span>
            </div>
            <table class="data-table compact-table">
                <thead>
                    <tr>
                        <th>Condicao</th>
                        <th>Residencias</th>
                        <th>Familias</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($byResidenceCondition as $item): ?>
                        <tr>
                            <td><?= h(residencia_condicao_label($item['condicao_residencia'] ?? null)) ?></td>
                            <td><?= h($item['residencias']) ?></td>
                            <td><?= h($item['familias']) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($byResidenceCondition === []): ?>
                        <tr>
                            <td colspan="3" class="empty-state">Sem dados.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </article>

        <article class="table-panel report-section">
            <div class="table-heading">
                <h2>Entregas por tipo</h2>
                <span><?= h(count($deliveriesByType)) ?> tipo(s)</span>
            </div>
            <table class="data-table compact-table">
                <thead>
                    <tr>
                        <th>Ajuda</th>
                        <th>Familias</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deliveriesByType as $item): ?>
                        <tr>
                            <td><?= h($item['nome']) ?></td>
                            <td><?= h($item['familias_atendidas']) ?></td>
                            <td><?= h(number_format((float) $item['quantidade_total'], 2, ',', '.')) ?> <?= h($item['unidade_medida']) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($deliveriesByType === []): ?>
                        <tr>
                            <td colspan="3" class="empty-state">Sem entregas.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </article>
    </section>

    <section class="table-panel report-section">
        <div class="table-heading">
            <h2>Pendencias de entrega</h2>
            <span><?= h(count($pendingFamilies)) ?> exibida(s)</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Familia</th>
                    <th>CPF</th>
                    <th>Telefone</th>
                    <th>Residencia</th>
                    <th>Acao</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingFamilies as $item): ?>
                    <tr>
                        <td><?= h($item['responsavel_nome']) ?></td>
                        <td><?= h($item['responsavel_cpf']) ?></td>
                        <td><?= h($item['telefone'] ?: '-') ?></td>
                        <td>
                            <a href="<?= h(url('/cadastros/residencias/' . $item['residencia_id'])) ?>"><?= h($item['protocolo']) ?></a><br>
                            <small><?= h($item['bairro_comunidade']) ?> - <?= h($item['municipio_nome']) ?>/<?= h($item['uf']) ?></small>
                        </td>
                        <td><?= h($item['localidade']) ?> - <?= h($item['tipo_evento']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($pendingFamilies === []): ?>
                    <tr>
                        <td colspan="5" class="empty-state">Nenhuma pendencia encontrada.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</section>
