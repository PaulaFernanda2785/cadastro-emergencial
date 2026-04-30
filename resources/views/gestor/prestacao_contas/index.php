<?php
$periodo = 'Todo o periodo';
if (($filters['data_inicio'] ?? '') !== '' || ($filters['data_fim'] ?? '') !== '') {
    $periodo = ($filters['data_inicio'] ?: 'inicio') . ' a ' . ($filters['data_fim'] ?: 'hoje');
}
?>

<section class="dashboard-header no-print">
    <div>
        <span class="eyebrow">Gestao operacional</span>
        <h1>Prestacao de contas</h1>
        <p>Totalizacao das entregas por tipo de ajuda, familia atendida e periodo.</p>
    </div>
    <button type="button" class="primary-button" onclick="window.print()">Imprimir</button>
</section>

<section class="table-panel report-filters no-print">
    <form method="get" action="<?= h(url('/gestor/prestacao-contas')) ?>" class="form-grid report-filter-grid">
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
            <span>Tipo de ajuda</span>
            <select name="tipo_ajuda_id">
                <option value="">Todos</option>
                <?php foreach ($tipos as $tipo): ?>
                    <option value="<?= h($tipo['id']) ?>" <?= (string) ($filters['tipo_ajuda_id'] ?? '') === (string) $tipo['id'] ? 'selected' : '' ?>>
                        <?= h($tipo['nome']) ?> (<?= h($tipo['unidade_medida']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
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
            <a class="secondary-link" href="<?= h(url('/gestor/prestacao-contas')) ?>">Limpar</a>
        </div>
    </form>
</section>

<section class="report-document">
    <div class="print-heading">
        <span class="eyebrow">Cadastro Emergencial</span>
        <h1>Prestacao de contas de ajuda humanitaria</h1>
        <p>Periodo: <?= h($periodo) ?> | Gerado em <?= h($generatedAt->format('d/m/Y H:i')) ?></p>
    </div>

    <section class="indicator-grid report-indicators" aria-label="Resumo da prestacao">
        <article class="indicator">
            <span>Entregas</span>
            <strong><?= h($indicators['total_entregas'] ?? 0) ?></strong>
            <small>Registros considerados.</small>
        </article>
        <article class="indicator">
            <span>Familias atendidas</span>
            <strong><?= h($indicators['familias_atendidas'] ?? 0) ?></strong>
            <small>Familias unicas com entrega.</small>
        </article>
        <article class="indicator">
            <span>Tipos distribuidos</span>
            <strong><?= h($indicators['tipos_distribuidos'] ?? 0) ?></strong>
            <small>Materiais distintos.</small>
        </article>
    </section>

    <section class="table-panel report-section">
        <div class="table-heading">
            <h2>Total por tipo de ajuda</h2>
            <span><?= h(count($totalsByType)) ?> tipo(s)</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tipo de ajuda</th>
                    <th>Unidade</th>
                    <th>Familias</th>
                    <th>Entregas</th>
                    <th>Quantidade total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($totalsByType as $total): ?>
                    <tr>
                        <td><?= h($total['nome']) ?></td>
                        <td><?= h($total['unidade_medida']) ?></td>
                        <td><?= h($total['familias_atendidas']) ?></td>
                        <td><?= h($total['total_entregas']) ?></td>
                        <td><?= h(number_format((float) $total['quantidade_total'], 2, ',', '.')) ?> <?= h($total['unidade_medida']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($totalsByType === []): ?>
                    <tr>
                        <td colspan="5" class="empty-state">Nenhuma entrega encontrada para os filtros informados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="table-panel report-section">
        <div class="table-heading">
            <h2>Listagem nominal</h2>
            <span><?= h(count($details)) ?> registro(s)</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Comprovante</th>
                    <th>Familia</th>
                    <th>Ajuda</th>
                    <th>Quantidade</th>
                    <th>Residencia</th>
                    <th>Data</th>
                    <th>Entregue por</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($details as $item): ?>
                    <tr>
                        <td><?= h($item['comprovante_codigo']) ?></td>
                        <td>
                            <?= h($item['responsavel_nome']) ?><br>
                            <small><?= h($item['responsavel_cpf']) ?></small>
                        </td>
                        <td><?= h($item['tipo_ajuda_nome']) ?></td>
                        <td><?= h(number_format((float) $item['quantidade'], 2, ',', '.')) ?> <?= h($item['unidade_medida']) ?></td>
                        <td>
                            <?= h($item['protocolo']) ?><br>
                            <small><?= h($item['bairro_comunidade']) ?> - <?= h($item['municipio_nome']) ?>/<?= h($item['uf']) ?></small>
                        </td>
                        <td><?= h(date('d/m/Y H:i', strtotime((string) $item['data_entrega']))) ?></td>
                        <td><?= h($item['entregue_por_nome']) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($details === []): ?>
                    <tr>
                        <td colspan="7" class="empty-state">Nenhum registro para detalhar.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="signature-grid">
        <div>
            <span></span>
            <p>Responsavel pela entrega</p>
        </div>
        <div>
            <span></span>
            <p>Responsavel pela conferencia</p>
        </div>
    </section>
</section>
