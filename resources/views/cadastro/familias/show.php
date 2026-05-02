<?php
$vulnerabilidades = array_values(array_filter([
    (int) $familia['possui_criancas'] === 1 ? 'Criancas' : '',
    (int) $familia['possui_idosos'] === 1 ? 'Idosos' : '',
    (int) $familia['possui_pcd'] === 1 ? 'PCD' : '',
    (int) ($familia['possui_gestantes'] ?? 0) === 1 ? 'Gestantes' : '',
]));
$dataNascimento = !empty($familia['data_nascimento']) ? date('d/m/Y', strtotime((string) $familia['data_nascimento'])) : '-';
$representanteNascimento = !empty($familia['representante_data_nascimento']) ? date('d/m/Y', strtotime((string) $familia['representante_data_nascimento'])) : '-';
?>

<section class="family-detail-page">
    <header class="dashboard-header family-detail-header">
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
    </header>

    <section class="family-detail-summary">
        <article class="family-detail-profile">
            <span class="eyebrow">Responsavel familiar</span>
            <h2><?= h($familia['responsavel_nome']) ?></h2>
            <p><?= h($familia['responsavel_cpf']) ?><?= !empty($familia['responsavel_rg']) ? ' - RG ' . h($familia['responsavel_rg']) : '' ?></p>
            <div class="family-detail-tags">
                <span><?= h((int) $familia['quantidade_integrantes']) ?> integrante(s)</span>
                <span><?= h($vulnerabilidades !== [] ? implode(', ', $vulnerabilidades) : 'Sem vulnerabilidade marcada') ?></span>
            </div>
        </article>

        <article class="family-detail-status">
            <span>Status da acao</span>
            <strong><?= h(ucfirst((string) $residencia['acao_status'])) ?></strong>
            <em class="status-pill status-<?= h((string) $residencia['acao_status']) ?>"><?= h($residencia['localidade']) ?> - <?= h($residencia['tipo_evento']) ?></em>
        </article>
    </section>

    <section class="family-detail-grid">
        <article class="family-detail-card">
            <div class="family-detail-card-heading">
                <span>Dados pessoais</span>
                <strong>Responsavel</strong>
            </div>
            <dl class="family-detail-list">
                <div><dt>CPF</dt><dd><?= h($familia['responsavel_cpf']) ?></dd></div>
                <div><dt>RG</dt><dd><?= h($familia['responsavel_rg'] ?: '-') ?></dd></div>
                <div><dt>Orgao exp.</dt><dd><?= h($familia['responsavel_orgao_expedidor'] ?: '-') ?></dd></div>
                <div><dt>Sexo</dt><dd><?= h($familia['responsavel_sexo'] ?: '-') ?></dd></div>
                <div><dt>Nascimento</dt><dd><?= h($dataNascimento) ?></dd></div>
            </dl>
        </article>

        <article class="family-detail-card">
            <div class="family-detail-card-heading">
                <span>Contato</span>
                <strong>Canais informados</strong>
            </div>
            <dl class="family-detail-list">
                <div><dt>Telefone</dt><dd><?= h($familia['telefone'] ?: '-') ?></dd></div>
                <div><dt>E-mail</dt><dd><?= h($familia['email'] ?: '-') ?></dd></div>
            </dl>
        </article>

        <article class="family-detail-card">
            <div class="family-detail-card-heading">
                <span>Composicao</span>
                <strong>Familia</strong>
            </div>
            <dl class="family-detail-list">
                <div><dt>Integrantes</dt><dd><?= h((int) $familia['quantidade_integrantes']) ?></dd></div>
                <div><dt>Vulnerabilidades</dt><dd><?= h($vulnerabilidades !== [] ? implode(', ', $vulnerabilidades) : '-') ?></dd></div>
                <div><dt>Renda</dt><dd><?= h(familia_renda_label($familia['renda_familiar'] ?? null)) ?></dd></div>
                <div><dt>Situacao</dt><dd><?= h(familia_situacao_label($familia['situacao_familia'] ?? null)) ?></dd></div>
            </dl>
        </article>

        <article class="family-detail-card">
            <div class="family-detail-card-heading">
                <span>Representante</span>
                <strong>Preenchimento</strong>
            </div>
            <dl class="family-detail-list">
                <div><dt>Nome</dt><dd><?= h($familia['representante_nome'] ?: '-') ?></dd></div>
                <div><dt>CPF</dt><dd><?= h($familia['representante_cpf'] ?: '-') ?></dd></div>
                <div><dt>RG</dt><dd><?= h($familia['representante_rg'] ?: '-') ?></dd></div>
                <div><dt>Orgao exp.</dt><dd><?= h($familia['representante_orgao_expedidor'] ?: '-') ?></dd></div>
                <div><dt>Nascimento</dt><dd><?= h($representanteNascimento) ?></dd></div>
                <div><dt>Telefone</dt><dd><?= h($familia['representante_telefone'] ?: '-') ?></dd></div>
            </dl>
        </article>

        <article class="family-detail-card family-detail-card-wide">
            <div class="family-detail-card-heading">
                <span>Residencia</span>
                <strong><?= h($residencia['protocolo']) ?></strong>
            </div>
            <dl class="family-detail-list family-detail-list-wide">
                <div><dt>Endereco</dt><dd><?= h($residencia['endereco']) ?><?= !empty($residencia['complemento']) ? ' - ' . h($residencia['complemento']) : '' ?></dd></div>
                <div><dt>Bairro</dt><dd><?= h($residencia['bairro_comunidade']) ?></dd></div>
                <div><dt>Municipio</dt><dd><?= h($residencia['municipio_nome']) ?> / <?= h($residencia['uf']) ?></dd></div>
                <div><dt>Imovel</dt><dd><?= h(residencia_imovel_label($residencia['imovel'] ?? null)) ?></dd></div>
                <div><dt>Condicao</dt><dd><?= h(residencia_condicao_label($residencia['condicao_residencia'] ?? null)) ?></dd></div>
            </dl>
        </article>
    </section>
</section>
