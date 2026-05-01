<section class="dashboard-header no-print receipt-preview-header">
    <div>
        <span class="eyebrow">Comprovante de cadastro familiar</span>
        <h1>Pre-visualizacao do ticket</h1>
        <p><?= h($familia['responsavel_nome']) ?> - residencia <?= h($familia['protocolo']) ?></p>
    </div>
</section>

<section class="receipt-preview-shell">
<section class="receipt-actions no-print">
    <a class="secondary-button" href="<?= h(url('/cadastros/residencias/' . $residencia['id'])) ?>">Voltar para residencia</a>
    <a class="secondary-button" href="<?= h(url('/cadastros/residencias/' . $residencia['id'] . '/familias/' . $familia['id'])) ?>">Ver familia</a>
    <button
        type="button"
        class="secondary-button"
        data-family-receipt-share
    >Enviar para WhatsApp</button>
    <button type="button" class="primary-button" onclick="window.print()">Imprimir</button>
    <span class="receipt-share-status" data-family-receipt-share-status></span>
</section>

<article class="receipt-ticket family-receipt-ticket" data-family-receipt-ticket data-receipt-code="<?= h($receiptCode) ?>">
    <div class="receipt-paper-edge" aria-hidden="true"></div>
    <header class="receipt-header">
        <strong>Cadastro Emergencial</strong>
        <span>CEDEC-PA</span>
        <span>Comprovante de Cadastro Familiar</span>
    </header>

    <div class="receipt-separator"></div>

    <dl class="receipt-lines">
        <div>
            <dt>Codigo</dt>
            <dd><?= h($receiptCode) ?></dd>
        </div>
        <div>
            <dt>Cadastro</dt>
            <dd><?= h(date('d/m/Y H:i', strtotime((string) ($familia['criado_em'] ?? 'now')))) ?></dd>
        </div>
        <div>
            <dt>Protocolo</dt>
            <dd><?= h($familia['protocolo']) ?></dd>
        </div>
        <div>
            <dt>Municipio</dt>
            <dd><?= h($familia['municipio_nome']) ?>/<?= h($familia['uf']) ?></dd>
        </div>
        <div>
            <dt>Localidade</dt>
            <dd><?= h($familia['localidade']) ?></dd>
        </div>
        <div>
            <dt>Evento</dt>
            <dd><?= h($familia['tipo_evento']) ?></dd>
        </div>
    </dl>

    <div class="receipt-separator"></div>

    <dl class="receipt-lines">
        <div>
            <dt>Responsavel</dt>
            <dd><?= h($familia['responsavel_nome']) ?></dd>
        </div>
        <div>
            <dt>CPF</dt>
            <dd><?= h($familia['responsavel_cpf']) ?></dd>
        </div>
        <div>
            <dt>RG</dt>
            <dd><?= h($familia['responsavel_rg'] ?: '-') ?></dd>
        </div>
        <div>
            <dt>Integrantes</dt>
            <dd><?= h($familia['quantidade_integrantes']) ?></dd>
        </div>
        <div>
            <dt>Situacao</dt>
            <dd><?= h(familia_situacao_label($familia['situacao_familia'] ?? null)) ?></dd>
        </div>
        <div>
            <dt>Renda</dt>
            <dd><?= h(familia_renda_label($familia['renda_familiar'] ?? null)) ?></dd>
        </div>
    </dl>

    <?php if (!empty($familia['representante_nome'])): ?>
        <div class="receipt-separator"></div>
        <dl class="receipt-lines">
            <div>
                <dt>Represent.</dt>
                <dd><?= h($familia['representante_nome']) ?></dd>
            </div>
            <div>
                <dt>CPF rep.</dt>
                <dd><?= h($familia['representante_cpf'] ?: '-') ?></dd>
            </div>
        </dl>
    <?php endif; ?>

    <div class="receipt-separator"></div>

    <dl class="receipt-lines">
        <div>
            <dt>Endereco</dt>
            <dd>
                <?= h($familia['endereco']) ?>
                <?php if (!empty($familia['complemento'])): ?>
                    - <?= h($familia['complemento']) ?>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt>Bairro</dt>
            <dd><?= h($familia['bairro_comunidade']) ?></dd>
        </div>
        <div>
            <dt>Imovel</dt>
            <dd><?= h(residencia_imovel_label($familia['imovel'] ?? null)) ?></dd>
        </div>
        <div>
            <dt>Condicao</dt>
            <dd><?= h(residencia_condicao_label($familia['condicao_residencia'] ?? null)) ?></dd>
        </div>
    </dl>

    <div class="receipt-separator"></div>

    <div class="receipt-qr">
        <canvas data-family-receipt-qr data-qr-value="<?= h($validationUrl) ?>" aria-label="QR Code de validacao do cadastro familiar"></canvas>
        <strong><?= h($receiptCode) ?></strong>
        <span>Leia este QR na pagina de entregas para validar o cadastro e registrar a baixa.</span>
    </div>

    <div class="receipt-separator"></div>

    <dl class="receipt-lines">
        <div>
            <dt>Emitido</dt>
            <dd><?= h($generatedAt->format('d/m/Y H:i')) ?></dd>
        </div>
    </dl>

    <footer class="receipt-footer">
        <span>Documento gerado pelo sistema Cadastro Emergencial.</span>
        <span>Apresente este comprovante na retirada da ajuda humanitaria.</span>
    </footer>
</article>
</section>
