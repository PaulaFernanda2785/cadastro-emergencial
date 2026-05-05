<?php

declare(strict_types=1);

namespace App\Services;

final class EmailService
{
    private const SMTP_TIMEOUT_SECONDS = 20;

    /**
     * @param array<int, array{email:string, nome?:string, tipo?:string}> $recipients
     * @return array{ok:bool,message:string,sent:int,total:int,recipients:array<int, string>}
     */
    public function send(array $recipients, string $subject, string $htmlBody, string $textBody = '', array $inlineImages = []): array
    {
        $config = require BASE_PATH . '/config/mail.php';

        if (empty($config['enabled'])) {
            return $this->result(false, 'Envio de e-mail desativado na configuracao do sistema.', 0, 0, []);
        }

        $cleanRecipients = $this->normalizeRecipients($recipients);

        if ($cleanRecipients === []) {
            return $this->result(false, 'Nenhum e-mail valido cadastrado para envio.', 0, 0, []);
        }

        $fromEmail = trim((string) ($config['from_email'] ?? ''));
        $fromName = trim((string) ($config['from_name'] ?? 'Cadastro Emergencial'));

        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->result(false, 'Configure SMTP_FROM_EMAIL com um e-mail valido do dominio.', 0, count($cleanRecipients), array_column($cleanRecipients, 'email'));
        }

        $replyTo = trim((string) ($config['reply_to'] ?? ''));
        $boundary = 'cadastro-emergencial-' . bin2hex(random_bytes(12));
        $relatedBoundary = 'cadastro-emergencial-related-' . bin2hex(random_bytes(12));
        $hasInlineImages = $this->hasInlineImages($inlineImages);
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: ' . ($hasInlineImages
                ? 'multipart/related; type="multipart/alternative"; boundary="' . $relatedBoundary . '"'
                : 'multipart/alternative; boundary="' . $boundary . '"'),
            'From: ' . $this->formatAddress($fromEmail, $fromName),
            'X-Mailer: PHP/' . PHP_VERSION,
        ];

        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $this->formatAddress($replyTo, $fromName);
        }

        $textBody = $textBody !== '' ? $textBody : trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));
        $message = $hasInlineImages
            ? $this->multipartRelatedBody($relatedBoundary, $boundary, $textBody, $htmlBody, $inlineImages)
            : $this->multipartBody($boundary, $textBody, $htmlBody);
        $encodedSubject = $this->encodeHeader($subject);

        if ($this->shouldUseSmtp($config) && !$this->hasSmtpCredentials($config)) {
            return $this->result(false, 'Configure SMTP_USER e SMTP_PASS no .env para enviar pela Hostinger.', 0, count($cleanRecipients), array_column($cleanRecipients, 'email'));
        }

        $sent = $this->shouldUseSmtp($config)
            ? $this->sendWithSmtp($config, $cleanRecipients, $fromEmail, $encodedSubject, $headers, $message)
            : $this->sendWithMailFunction($cleanRecipients, $fromEmail, $encodedSubject, $headers, $message);


        if ($sent === count($cleanRecipients)) {
            return $this->result(true, 'E-mail enviado para ' . $sent . ' destinatario(s).', $sent, count($cleanRecipients), array_column($cleanRecipients, 'email'));
        }

        if ($sent > 0) {
            return $this->result(false, 'E-mail enviado parcialmente. Verifique os destinatarios sem recebimento.', $sent, count($cleanRecipients), array_column($cleanRecipients, 'email'));
        }

        return $this->result(false, 'Nao foi possivel enviar o e-mail. Verifique a configuracao de e-mail da hospedagem.', 0, count($cleanRecipients), array_column($cleanRecipients, 'email'));
    }

    private function hasInlineImages(array $inlineImages): bool
    {
        foreach ($inlineImages as $image) {
            if (is_array($image) && !empty($image['cid']) && !empty($image['content'])) {
                return true;
            }
        }

        return false;
    }

    private function shouldUseSmtp(array $config): bool
    {
        $smtp = is_array($config['smtp'] ?? null) ? $config['smtp'] : [];

        return trim((string) ($smtp['host'] ?? '')) !== '';
    }

    private function hasSmtpCredentials(array $config): bool
    {
        $smtp = is_array($config['smtp'] ?? null) ? $config['smtp'] : [];

        return trim((string) ($smtp['user'] ?? '')) !== '' && (string) ($smtp['pass'] ?? '') !== '';
    }

    /**
     * @param array<int, array{email:string, nome:string, tipo:string}> $recipients
     * @param array<int, string> $headers
     */
    private function sendWithMailFunction(array $recipients, string $fromEmail, string $encodedSubject, array $headers, string $message): int
    {
        $sent = 0;

        foreach ($recipients as $recipient) {
            $sent += @mail(
                $recipient['email'],
                $encodedSubject,
                $message,
                implode("\r\n", $headers),
                $this->envelopeSender($fromEmail)
            ) ? 1 : 0;
        }

        return $sent;
    }

    /**
     * @param array<int, array{email:string, nome:string, tipo:string}> $recipients
     * @param array<int, string> $headers
     */
    private function sendWithSmtp(array $config, array $recipients, string $fromEmail, string $encodedSubject, array $headers, string $message): int
    {
        $smtp = is_array($config['smtp'] ?? null) ? $config['smtp'] : [];
        $host = trim((string) ($smtp['host'] ?? ''));
        $user = trim((string) ($smtp['user'] ?? ''));
        $pass = (string) ($smtp['pass'] ?? '');

        if ($host === '' || $user === '' || $pass === '') {
            return 0;
        }

        $sent = 0;

        foreach ($recipients as $recipient) {
            try {
                $this->sendSmtpMessage($smtp, $fromEmail, $recipient, $encodedSubject, $headers, $message);
                $sent++;
            } catch (\Throwable $exception) {
                error_log('Falha SMTP ao enviar e-mail: ' . $exception->getMessage());
            }
        }

        return $sent;
    }

    /**
     * @param array{host?:mixed,port?:mixed,user?:mixed,pass?:mixed,secure?:mixed} $smtp
     * @param array{email:string,nome:string,tipo:string} $recipient
     * @param array<int, string> $headers
     */
    private function sendSmtpMessage(array $smtp, string $fromEmail, array $recipient, string $encodedSubject, array $headers, string $message): void
    {
        $host = trim((string) ($smtp['host'] ?? ''));
        $port = (int) ($smtp['port'] ?? 465);
        $secure = strtolower(trim((string) ($smtp['secure'] ?? 'ssl')));
        $target = ($secure === 'ssl' ? 'ssl://' : '') . $host;
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($target, $port, $errno, $errstr, self::SMTP_TIMEOUT_SECONDS);

        if (!is_resource($socket)) {
            throw new \RuntimeException('Nao conectou ao SMTP: ' . $errstr);
        }

        stream_set_timeout($socket, self::SMTP_TIMEOUT_SECONDS);

        try {
            $this->smtpExpect($socket, [220]);
            $hostname = preg_replace('/[^A-Za-z0-9.-]/', '', (string) ($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
            $this->smtpCommand($socket, 'EHLO ' . $hostname, [250]);

            if ($secure === 'tls') {
                $this->smtpCommand($socket, 'STARTTLS', [220]);

                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('Nao foi possivel iniciar TLS no SMTP.');
                }

                $this->smtpCommand($socket, 'EHLO ' . $hostname, [250]);
            }

            $this->smtpCommand($socket, 'AUTH LOGIN', [334]);
            $this->smtpCommand($socket, base64_encode((string) ($smtp['user'] ?? '')), [334]);
            $this->smtpCommand($socket, base64_encode((string) ($smtp['pass'] ?? '')), [235]);
            $this->smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->smtpCommand($socket, 'RCPT TO:<' . $recipient['email'] . '>', [250, 251]);
            $this->smtpCommand($socket, 'DATA', [354]);

            fwrite($socket, $this->smtpData($recipient, $encodedSubject, $headers, $message));
            $this->smtpExpect($socket, [250]);
            $this->smtpCommand($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    /**
     * @param array{email:string,nome:string,tipo:string} $recipient
     * @param array<int, string> $headers
     */
    private function smtpData(array $recipient, string $encodedSubject, array $headers, string $message): string
    {
        $dataHeaders = array_merge($headers, [
            'To: ' . $this->formatAddress($recipient['email'], $recipient['nome']),
            'Subject: ' . $encodedSubject,
            'Date: ' . date(DATE_RFC2822),
        ]);
        $data = implode("\r\n", $dataHeaders) . "\r\n\r\n" . $message;
        $lines = preg_split('/\r\n|\r|\n/', $data) ?: [];
        $stuffed = array_map(static function (string $line): string {
            return str_starts_with($line, '.') ? '.' . $line : $line;
        }, $lines);

        return implode("\r\n", $stuffed) . "\r\n.\r\n";
    }

    /**
     * @param array<int, int> $expectedCodes
     */
    private function smtpCommand(mixed $socket, string $command, array $expectedCodes): string
    {
        fwrite($socket, $command . "\r\n");

        return $this->smtpExpect($socket, $expectedCodes);
    }

    /**
     * @param array<int, int> $expectedCodes
     */
    private function smtpExpect(mixed $socket, array $expectedCodes): string
    {
        $response = $this->smtpRead($socket);
        $code = (int) substr($response, 0, 3);

        if (!in_array($code, $expectedCodes, true)) {
            throw new \RuntimeException('Resposta SMTP inesperada: ' . trim($response));
        }

        return $response;
    }

    private function smtpRead(mixed $socket): string
    {
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;

            if (preg_match('/^\d{3}\s/', $line) === 1) {
                break;
            }
        }

        if ($response === '') {
            throw new \RuntimeException('SMTP nao retornou resposta.');
        }

        return $response;
    }

    /**
     * @param array<int, array{email:string, nome?:string, tipo?:string}> $recipients
     * @return array<int, array{email:string, nome:string, tipo:string}>
     */
    private function normalizeRecipients(array $recipients): array
    {
        $clean = [];
        $seen = [];

        foreach ($recipients as $recipient) {
            $email = strtolower(trim((string) ($recipient['email'] ?? '')));

            if ($email === '' || isset($seen[$email]) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $seen[$email] = true;
            $clean[] = [
                'email' => $email,
                'nome' => $this->sanitizeHeader((string) ($recipient['nome'] ?? '')),
                'tipo' => $this->sanitizeHeader((string) ($recipient['tipo'] ?? '')),
            ];
        }

        return $clean;
    }

    private function multipartBody(string $boundary, string $textBody, string $htmlBody): string
    {
        return implode("\r\n", [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            $this->base64MimeBody($textBody),
            '--' . $boundary,
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            $this->base64MimeBody($htmlBody),
            '--' . $boundary . '--',
            '',
        ]);
    }

    private function multipartRelatedBody(string $relatedBoundary, string $alternativeBoundary, string $textBody, string $htmlBody, array $inlineImages): string
    {
        $parts = [
            '--' . $relatedBoundary,
            'Content-Type: multipart/alternative; boundary="' . $alternativeBoundary . '"',
            '',
            $this->multipartBody($alternativeBoundary, $textBody, $htmlBody),
        ];

        foreach ($inlineImages as $image) {
            if (!is_array($image) || empty($image['cid']) || empty($image['content'])) {
                continue;
            }

            $filename = $this->sanitizeHeader((string) ($image['filename'] ?? 'inline.png'));
            $mimeType = $this->sanitizeHeader((string) ($image['mime'] ?? 'image/png'));
            $cid = preg_replace('/[^A-Za-z0-9._-]/', '', (string) $image['cid']) ?: 'inline-image';

            $parts = array_merge($parts, [
                '--' . $relatedBoundary,
                'Content-Type: ' . $mimeType . '; name="' . $filename . '"',
                'Content-Transfer-Encoding: base64',
                'Content-ID: <' . $cid . '>',
                'Content-Disposition: inline; filename="' . $filename . '"',
                '',
                $this->base64MimeBody((string) $image['content']),
            ]);
        }

        $parts[] = '--' . $relatedBoundary . '--';
        $parts[] = '';

        return implode("\r\n", $parts);
    }

    private function base64MimeBody(string $body): string
    {
        return rtrim(chunk_split(base64_encode($body), 76, "\r\n"));
    }

    private function formatAddress(string $email, string $name): string
    {
        $name = $this->sanitizeHeader($name);

        if ($name === '') {
            return $email;
        }

        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function encodeHeader(string $value): string
    {
        $value = $this->sanitizeHeader($value);

        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function sanitizeHeader(string $value): string
    {
        return trim(preg_replace('/[\r\n]+/', ' ', $value) ?? '');
    }

    private function envelopeSender(string $fromEmail): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return '';
        }

        return '-f' . escapeshellarg($fromEmail);
    }

    /**
     * @param array<int, string> $recipients
     * @return array{ok:bool,message:string,sent:int,total:int,recipients:array<int, string>}
     */
    private function result(bool $ok, string $message, int $sent, int $total, array $recipients): array
    {
        return [
            'ok' => $ok,
            'message' => $message,
            'sent' => $sent,
            'total' => $total,
            'recipients' => $recipients,
        ];
    }
}
