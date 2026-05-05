<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeInterface;

final class TicketEmailService
{
    public function __construct(
        private readonly EmailService $email = new EmailService(),
        private readonly QrCodeService $qrCode = new QrCodeService()
    ) {
    }

    /**
     * @return array{ok:bool,message:string,sent:int,total:int,recipients:array<int, string>}
     */
    public function sendFamilyReceipt(array $familia, string $receiptCode, string $validationUrl, DateTimeInterface $generatedAt): array
    {
        $subject = 'Comprovante de cadastro familiar - ' . $receiptCode;
        $lines = [
            'Comprovante de cadastro familiar',
            'Responsavel: ' . (string) ($familia['responsavel_nome'] ?? '-'),
            'CPF: ' . ((string) ($familia['responsavel_cpf'] ?? '') !== '' ? (string) $familia['responsavel_cpf'] : '-'),
            'Codigo: ' . $receiptCode,
            'Protocolo: ' . (string) ($familia['protocolo'] ?? '-'),
            'Municipio: ' . (string) ($familia['municipio_nome'] ?? '-') . '/' . (string) ($familia['uf'] ?? '-'),
            'Localidade: ' . (string) ($familia['localidade'] ?? '-'),
            'Emitido em: ' . $generatedAt->format('d/m/Y H:i'),
        ];

        if (familia_tem_representante($familia)) {
            $lines[] = 'Representante: ' . (string) ($familia['representante_nome'] ?? '-');
        }

        $qrCid = 'cadastro-familiar-qr-' . strtolower(substr(hash('sha256', $receiptCode . '|' . $validationUrl), 0, 16));
        $qrImage = $this->qrCode->png($validationUrl);
        $inlineImages = $qrImage !== null ? [[
            'cid' => $qrCid,
            'filename' => 'qr-validacao-cadastro-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $receiptCode) . '.png',
            'mime' => 'image/png',
            'content' => $qrImage,
        ]] : [];

        return $this->email->send(
            familia_email_destinos($familia),
            $subject,
            $this->htmlDocument(
                'Comprovante de Cadastro Familiar',
                $this->familyTicketHtml($familia, $receiptCode, $generatedAt, $qrImage !== null ? $qrCid : '')
            ),
            implode("\n", $lines),
            $inlineImages
        );
    }

    /**
     * @return array{ok:bool,message:string,sent:int,total:int,recipients:array<int, string>}
     */
    public function sendDeliveryReceipt(array $entrega, DateTimeInterface $generatedAt): array
    {
        $code = (string) ($entrega['comprovante_codigo'] ?? '');
        $items = $this->deliveryItemLines($entrega['itens'] ?? []);
        $subject = 'Comprovante de entrega - ' . $code;
        $lines = array_filter([
            'Comprovante de entrega',
            'Responsavel: ' . (string) ($entrega['responsavel_nome'] ?? '-'),
            'CPF: ' . ((string) ($entrega['responsavel_cpf'] ?? '') !== '' ? (string) $entrega['responsavel_cpf'] : '-'),
            'Codigo: ' . $code,
            'Protocolo: ' . (string) ($entrega['protocolo'] ?? '-'),
            'Municipio: ' . (string) ($entrega['municipio_nome'] ?? '-') . '/' . (string) ($entrega['uf'] ?? '-'),
            $items !== [] ? 'Itens:' . "\n" . implode("\n", $items) : '',
            'Emitido em: ' . $generatedAt->format('d/m/Y H:i'),
        ]);

        return $this->email->send(
            familia_email_destinos($entrega),
            $subject,
            $this->htmlDocument(
                'Comprovante de Entrega',
                $this->deliveryTicketHtml($entrega, $generatedAt)
            ),
            implode("\n", $lines)
        );
    }

    private function familyTicketHtml(array $familia, string $receiptCode, DateTimeInterface $generatedAt, string $qrCid): string
    {
        $rows = [
            ['label' => 'Codigo', 'value' => $receiptCode],
            ['label' => 'Cadastro', 'value' => $this->dateTimeValue($familia['criado_em'] ?? 'now')],
            ['label' => 'Protocolo', 'value' => (string) ($familia['protocolo'] ?? '-')],
            ['label' => 'Municipio', 'value' => (string) ($familia['municipio_nome'] ?? '-') . '/' . (string) ($familia['uf'] ?? '-')],
            ['label' => 'Localidade', 'value' => (string) ($familia['localidade'] ?? '-')],
            ['label' => 'Evento', 'value' => (string) ($familia['tipo_evento'] ?? '-')],
            ['label' => 'Responsavel', 'value' => (string) ($familia['responsavel_nome'] ?? '-')],
            ['label' => 'CPF', 'value' => (string) (($familia['responsavel_cpf'] ?? '') ?: '-')],
            ['label' => 'RG', 'value' => (string) (($familia['responsavel_rg'] ?? '') ?: '-')],
            ['label' => 'Integrantes', 'value' => (string) ($familia['quantidade_integrantes'] ?? '-')],
            ['label' => 'Situacao', 'value' => familia_situacao_label($familia['situacao_familia'] ?? null)],
            ['label' => 'Renda', 'value' => familia_renda_label($familia['renda_familiar'] ?? null)],
        ];

        if (familia_tem_representante($familia)) {
            $rows[] = ['label' => 'Represent.', 'value' => (string) (($familia['representante_nome'] ?? '') ?: '-')];
            $rows[] = ['label' => 'CPF rep.', 'value' => (string) (($familia['representante_cpf'] ?? '') ?: '-')];
        }

        $rows[] = ['label' => 'Endereco', 'value' => $this->addressValue($familia)];
        $rows[] = ['label' => 'Bairro', 'value' => (string) (($familia['bairro_comunidade'] ?? '') ?: '-')];
        $rows[] = ['label' => 'Imovel', 'value' => residencia_imovel_label($familia['imovel'] ?? null)];
        $rows[] = ['label' => 'Condicao', 'value' => residencia_condicao_label($familia['condicao_residencia'] ?? null)];
        $rows[] = ['label' => 'Pendentes', 'value' => familia_campos_pendentes_resumo($familia, 6)];
        $rows[] = ['label' => 'Emitido em', 'value' => $generatedAt->format('d/m/Y H:i')];

        return $this->ticketHtml('Cadastro Emergencial', 'Comprovante de Cadastro Familiar', $rows, $receiptCode, $qrCid);
    }

    private function deliveryTicketHtml(array $entrega, DateTimeInterface $generatedAt): string
    {
        $rows = [
            ['label' => 'Codigo', 'value' => (string) ($entrega['comprovante_codigo'] ?? '-')],
            ['label' => 'Data', 'value' => $this->dateTimeValue($entrega['data_entrega'] ?? 'now')],
            ['label' => 'Protocolo', 'value' => (string) ($entrega['protocolo'] ?? '-')],
            ['label' => 'Municipio', 'value' => (string) ($entrega['municipio_nome'] ?? '-') . '/' . (string) ($entrega['uf'] ?? '-')],
            ['label' => 'Localidade', 'value' => (string) (($entrega['localidade'] ?? '') ?: '-')],
            ['label' => 'Evento', 'value' => (string) (($entrega['tipo_evento'] ?? '') ?: '-')],
            ['label' => 'Responsavel', 'value' => (string) ($entrega['responsavel_nome'] ?? '-')],
            ['label' => 'CPF', 'value' => (string) (($entrega['responsavel_cpf'] ?? '') ?: '-')],
            ['label' => 'Integrantes', 'value' => (string) ($entrega['quantidade_integrantes'] ?? '-')],
            ['label' => 'Endereco', 'value' => $this->addressValue($entrega)],
            ['label' => 'Bairro', 'value' => (string) (($entrega['bairro_comunidade'] ?? '') ?: '-')],
            ['label' => 'Itens', 'value' => implode("\n", $this->deliveryItemLines($entrega['itens'] ?? [])) ?: '-'],
            ['label' => 'Entregue por', 'value' => (string) ($entrega['entregue_por_nome'] ?? '-')],
            ['label' => 'Emitido em', 'value' => $generatedAt->format('d/m/Y H:i')],
        ];

        return $this->ticketHtml('Cadastro Emergencial', 'Comprovante de Entrega', $rows, (string) ($entrega['comprovante_codigo'] ?? ''), '', true);
    }

    /**
     * @param mixed $items
     * @return array<int, string>
     */
    private function deliveryItemLines(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        return array_map(static function (array $item): string {
            $line = (string) ($item['tipo_ajuda_nome'] ?? '-') . ': '
                . number_format((float) ($item['quantidade'] ?? 0), 2, ',', '.')
                . ' ' . (string) ($item['unidade_medida'] ?? '');
            $observacao = trim((string) ($item['observacao'] ?? ''));

            return $observacao !== '' ? $line . ' | Obs.: ' . $observacao : $line;
        }, $items);
    }

    private function addressValue(array $record): string
    {
        $address = trim((string) ($record['endereco'] ?? ''));
        $complement = trim((string) ($record['complemento'] ?? ''));

        if ($address === '') {
            return '-';
        }

        return $complement !== '' ? $address . ' - ' . $complement : $address;
    }

    private function dateTimeValue(mixed $value): string
    {
        $timestamp = strtotime((string) $value);

        return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : '-';
    }

    /**
     * @param array<int, array{label:string,value:string}> $rows
     */
    private function ticketHtml(string $brand, string $title, array $rows, string $code, string $qrCid = '', bool $signature = false): string
    {
        $contentRows = '';

        foreach ($rows as $row) {
            $contentRows .= '<tr>'
                . '<th style="width:96px;text-align:left;vertical-align:top;border-bottom:1px dashed #d1d5db;padding:8px 8px 8px 0;font-size:12px;line-height:1.35;color:#374151;font-weight:bold;white-space:nowrap;">' . h($row['label']) . '</th>'
                . '<td style="text-align:left;vertical-align:top;border-bottom:1px dashed #d1d5db;padding:8px 0 8px 8px;font-size:12px;line-height:1.35;color:#111827;word-break:break-word;overflow-wrap:anywhere;">' . nl2br(h($row['value'])) . '</td>'
                . '</tr>';
        }

        $qrHtml = $qrCid !== ''
            ? '<div style="text-align:center;border-top:1px dashed #9ca3af;margin-top:12px;padding-top:12px;">'
                . '<img src="cid:' . h($qrCid) . '" alt="QR Code de validacao do cadastro familiar" width="180" height="180" style="display:block;width:180px;height:180px;margin:0 auto;">'
                . '<span style="display:block;font-size:11px;color:#374151;margin-top:6px;">Leia este QR Code para validar o cadastro familiar.</span>'
                . '</div>'
            : '';
        $signatureHtml = $signature
            ? '<div style="margin:26px 14px 0;border-top:1px solid #111827;text-align:center;padding-top:6px;font-size:11px;color:#374151;">Assinatura do responsavel familiar</div>'
            : '';

        return '<section style="width:320px;max-width:100%;margin:0 auto;background:#ffffff;border:1px solid #d1d5db;border-radius:6px;padding:18px 16px;color:#111827;">'
            . '<header style="text-align:center;border-bottom:1px dashed #9ca3af;padding-bottom:12px;margin-bottom:10px;">'
            . '<strong style="display:block;font-size:17px;line-height:1.2;">' . h($brand) . '</strong>'
            . '<span style="display:block;font-size:12px;margin-top:3px;">CEDEC-PA</span>'
            . '<span style="display:block;font-size:12px;margin-top:3px;">' . h($title) . '</span>'
            . '</header>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;table-layout:fixed;">'
            . '<tbody>' . $contentRows . '</tbody>'
            . '</table>'
            . $qrHtml
            . $signatureHtml
            . '<footer style="text-align:center;border-top:1px dashed #9ca3af;margin-top:12px;padding-top:12px;">'
            . '<strong style="display:block;font-size:13px;letter-spacing:.04em;">' . h($code) . '</strong>'
            . '<span style="display:block;font-size:11px;color:#6b7280;margin-top:6px;">Documento gerado pelo sistema Cadastro Emergencial.</span>'
            . '</footer>'
            . '</section>';
    }

    private function htmlDocument(string $title, string $ticketHtml): string
    {
        return '<!doctype html><html><body style="font-family:Arial,sans-serif;background:#f8fafc;margin:0;padding:24px;color:#111827;">'
            . '<main style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">'
            . '<h1 style="font-size:20px;text-align:center;margin:0 0 18px;">' . h($title) . '</h1>'
            . $ticketHtml
            . '<p style="font-size:12px;color:#6b7280;margin:20px 0 0;">Documento gerado pelo sistema Cadastro Emergencial.</p>'
            . '</main></body></html>';
    }
}
