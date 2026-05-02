<?php
$statusLabel = static function (mixed $status): string {
    return [
        'pendente' => 'Pendente',
        'autorizado' => 'Autorizado',
        'negado' => 'Nao autorizado',
        'cancelado' => 'Cancelado',
    ][(string) $status] ?? '-';
};
$formatDateTime = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);

    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : '-';
};
?>

<section class="records-page signatures-page">
    <header class="dashboard-header signatures-header">
        <div>
            <span class="eyebrow">Assinaturas digitais</span>
            <h1>Assinaturas</h1>
            <p>Autorize ou nao autorize documentos nos quais voce foi selecionado como coautor.</p>
        </div>
    </header>

    <?php if (($pendentes ?? []) !== []): ?>
        <section class="signature-alert-panel" role="alert">
            <div>
                <span class="eyebrow">Pendencias</span>
                <h2>Voce possui <?= h(count($pendentes)) ?> documento(s) aguardando sua decisao</h2>
            </div>
            <a class="primary-button" href="#assinaturas-pendentes">Ver pendencias</a>
        </section>
    <?php endif; ?>

    <section class="table-panel" id="assinaturas-pendentes">
        <div class="table-heading">
            <h2>Para minha assinatura</h2>
            <span>Documentos selecionados para sua autorizacao como coautor.</span>
        </div>

        <div class="responsive-table">
            <table>
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Solicitante</th>
                        <th>Status</th>
                        <th>Solicitado em</th>
                        <th>Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($minhasAssinaturas ?? []) as $assinatura): ?>
                        <tr>
                            <td>
                                <strong><?= h($assinatura['titulo'] ?? '-') ?></strong><br>
                                <small><?= h($assinatura['descricao'] ?? '') ?></small>
                            </td>
                            <td><?= h($assinatura['solicitante_nome'] ?? '-') ?></td>
                            <td><span class="status-pill status-<?= h($assinatura['status'] ?? 'pendente') ?>"><?= h($statusLabel($assinatura['status'] ?? '')) ?></span></td>
                            <td><?= h($formatDateTime($assinatura['solicitado_em'] ?? '')) ?></td>
                            <td><a class="secondary-button" href="<?= h(url('/assinaturas/' . (int) $assinatura['id'])) ?>">Abrir</a></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (($minhasAssinaturas ?? []) === []): ?>
                        <tr>
                            <td colspan="5" class="empty-state">Nenhuma assinatura atribuida ao seu usuario.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="table-panel">
        <div class="table-heading">
            <h2>Solicitadas por mim</h2>
            <span>Acompanhe as decisoes dos coautores selecionados por voce.</span>
        </div>

        <div class="responsive-table">
            <table>
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Coautor</th>
                        <th>Status</th>
                        <th>Atualizacao</th>
                        <th>Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($solicitadasPorMim ?? []) as $assinatura): ?>
                        <tr>
                            <td>
                                <strong><?= h($assinatura['titulo'] ?? '-') ?></strong><br>
                                <small><?= h($assinatura['descricao'] ?? '') ?></small>
                            </td>
                            <td><?= h($assinatura['coautor_nome'] ?? '-') ?></td>
                            <td><span class="status-pill status-<?= h($assinatura['status'] ?? 'pendente') ?>"><?= h($statusLabel($assinatura['status'] ?? '')) ?></span></td>
                            <td><?= h($formatDateTime($assinatura['atualizado_em'] ?? $assinatura['solicitado_em'] ?? '')) ?></td>
                            <td><a class="secondary-button" href="<?= h(url('/assinaturas/' . (int) $assinatura['id'])) ?>">Detalhes</a></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (($solicitadasPorMim ?? []) === []): ?>
                        <tr>
                            <td colspan="5" class="empty-state">Nenhuma coassinatura solicitada por voce.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>
