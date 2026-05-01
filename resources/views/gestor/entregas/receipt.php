<section class="receipt-actions no-print">
    <a class="secondary-link" href="<?= h(url('/gestor/entregas')) ?>">Voltar para entregas</a>
    <a class="secondary-link" href="<?= h(url('/cadastros/residencias/' . $entrega['residencia_id'])) ?>">Ver residencia</a>
    <button type="button" class="primary-button" onclick="window.print()">Imprimir comprovante</button>
</section>

<article class="receipt-ticket">
    <header class="receipt-header">
        <strong>Cadastro Emergencial</strong>
        <span>CEDEC-PA</span>
        <span>Comprovante de Entrega</span>
    </header>

    <div class="receipt-separator"></div>

    <dl class="receipt-lines">
        <div>
            <dt>Codigo</dt>
            <dd><?= h($entrega['comprovante_codigo']) ?></dd>
        </div>
        <div>
            <dt>Data</dt>
            <dd><?= h(date('d/m/Y H:i', strtotime((string) $entrega['data_entrega']))) ?></dd>
        </div>
        <div>
            <dt>Protocolo</dt>
            <dd><?= h($entrega['protocolo']) ?></dd>
        </div>
        <div>
            <dt>Municipio</dt>
            <dd><?= h($entrega['municipio_nome']) ?>/<?= h($entrega['uf']) ?></dd>
        </div>
        <div>
            <dt>Localidade</dt>
            <dd><?= h($entrega['localidade']) ?></dd>
        </div>
        <div>
            <dt>Evento</dt>
            <dd><?= h($entrega['tipo_evento']) ?></dd>
        </div>
    </dl>

    <div class="receipt-separator"></div>

    <dl class="receipt-lines">
        <div>
            <dt>Responsavel</dt>
            <dd><?= h($entrega['responsavel_nome']) ?></dd>
        </div>
        <div>
            <dt>CPF</dt>
            <dd><?= h($entrega['responsavel_cpf']) ?></dd>
        </div>
        <div>
            <dt>Integrantes</dt>
            <dd><?= h($entrega['quantidade_integrantes']) ?></dd>
        </div>
        <div>
            <dt>Endereco</dt>
            <dd>
                <?= h($entrega['endereco']) ?>
                <?php if (!empty($entrega['complemento'])): ?>
                    - <?= h($entrega['complemento']) ?>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt>Bairro</dt>
            <dd><?= h($entrega['bairro_comunidade']) ?></dd>
        </div>
        <div>
            <dt>Imovel</dt>
            <dd><?= h(residencia_imovel_label($entrega['imovel'] ?? null)) ?></dd>
        </div>
        <div>
            <dt>Condicao</dt>
            <dd><?= h(residencia_condicao_label($entrega['condicao_residencia'] ?? null)) ?></dd>
        </div>
    </dl>

    <div class="receipt-separator"></div>

    <table class="receipt-items">
        <thead>
            <tr>
                <th>Item</th>
                <th>Qtd.</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?= h($entrega['tipo_ajuda_nome']) ?></td>
                <td><?= h(number_format((float) $entrega['quantidade'], 2, ',', '.')) ?> <?= h($entrega['unidade_medida']) ?></td>
            </tr>
        </tbody>
    </table>

    <?php if (!empty($entrega['observacao'])): ?>
        <div class="receipt-separator"></div>
        <p class="receipt-note"><?= h($entrega['observacao']) ?></p>
    <?php endif; ?>

    <div class="receipt-separator"></div>

    <dl class="receipt-lines">
        <div>
            <dt>Entregue por</dt>
            <dd><?= h($entrega['entregue_por_nome']) ?></dd>
        </div>
        <div>
            <dt>Emitido em</dt>
            <dd><?= h($generatedAt->format('d/m/Y H:i')) ?></dd>
        </div>
    </dl>

    <div class="receipt-signature">
        <span></span>
        <p>Assinatura do responsavel familiar</p>
    </div>

    <footer class="receipt-footer">
        <span>Documento gerado pelo sistema Cadastro Emergencial.</span>
        <span>Guarde este comprovante para conferencia.</span>
    </footer>
</article>
