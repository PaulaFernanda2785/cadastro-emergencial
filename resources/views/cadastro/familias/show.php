<section class="dashboard-header">
    <div>
        <span class="eyebrow">Familia vinculada</span>
        <h1><?= h($familia['responsavel_nome']) ?></h1>
        <p>Residencia <?= h($residencia['protocolo']) ?> - <?= h($residencia['bairro_comunidade']) ?></p>
    </div>
    <div class="header-actions">
        <a class="secondary-button residence-action-button" href="<?= h(url('/cadastros/residencias/' . $residencia['id'])) ?>">Voltar para residencia</a>
        <a class="secondary-button residence-action-button" href="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/familias/' . $familia['id'] . '/comprovante')) ?>">Comprovante</a>
        <?php if (($residencia['acao_status'] ?? null) === 'aberta'): ?>
            <a class="primary-link-button" href="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/familias/' . $familia['id'] . '/editar')) ?>">Editar familia</a>
        <?php endif; ?>
    </div>
</section>

<section class="detail-grid residence-detail-grid">
    <article class="detail-panel">
        <h2>Responsavel</h2>
        <p><?= h($familia['responsavel_nome']) ?></p>
        <p>CPF: <?= h($familia['responsavel_cpf']) ?></p>
        <p>RG: <?= h($familia['responsavel_rg'] ?: '-') ?></p>
    </article>
    <article class="detail-panel">
        <h2>Contato</h2>
        <p>Telefone: <?= h($familia['telefone'] ?: '-') ?></p>
        <p>E-mail: <?= h($familia['email'] ?: '-') ?></p>
        <p>Nascimento: <?= !empty($familia['data_nascimento']) ? h(date('d/m/Y', strtotime((string) $familia['data_nascimento']))) : '-' ?></p>
    </article>
    <article class="detail-panel">
        <h2>Composicao familiar</h2>
        <p>Integrantes: <?= h($familia['quantidade_integrantes']) ?></p>
        <p>
            <?= (int) $familia['possui_criancas'] === 1 ? 'Criancas ' : '' ?>
            <?= (int) $familia['possui_idosos'] === 1 ? 'Idosos ' : '' ?>
            <?= (int) $familia['possui_pcd'] === 1 ? 'PCD' : '' ?>
            <?= (int) ($familia['possui_gestantes'] ?? 0) === 1 ? 'Gestantes' : '' ?>
            <?= (int) $familia['possui_criancas'] !== 1 && (int) $familia['possui_idosos'] !== 1 && (int) $familia['possui_pcd'] !== 1 && (int) ($familia['possui_gestantes'] ?? 0) !== 1 ? 'Sem vulnerabilidade marcada' : '' ?>
        </p>
    </article>
</section>

<section class="detail-grid residence-detail-grid">
    <article class="detail-panel">
        <h2>Representante</h2>
        <p><?= h($familia['representante_nome'] ?: '-') ?></p>
        <p>CPF: <?= h($familia['representante_cpf'] ?: '-') ?></p>
        <p>RG: <?= h($familia['representante_rg'] ?: '-') ?></p>
        <p>Telefone: <?= h($familia['representante_telefone'] ?: '-') ?></p>
    </article>
    <article class="detail-panel">
        <h2>Residencia</h2>
        <p><?= h($residencia['endereco']) ?></p>
        <p><?= h($residencia['municipio_nome']) ?> / <?= h($residencia['uf']) ?></p>
        <p>Imovel: <?= h(residencia_imovel_label($residencia['imovel'] ?? null)) ?></p>
        <p>Condicao: <?= h(residencia_condicao_label($residencia['condicao_residencia'] ?? null)) ?></p>
    </article>
    <article class="detail-panel">
        <h2>Acao</h2>
        <p><?= h($residencia['localidade']) ?> - <?= h($residencia['tipo_evento']) ?></p>
        <span class="status-pill status-<?= h((string) $residencia['acao_status']) ?>"><?= h(ucfirst((string) $residencia['acao_status'])) ?></span>
    </article>
</section>
