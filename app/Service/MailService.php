<?php

namespace App\Service;

use App\Common\Environment;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\InterCobranca as EntityInterCobranca;
use App\Utils\View;

class MailService
{
    /**
     * @return array{ok:bool,error:?string}
     */
    public static function enviarBoleto(EntityInterCobranca $cobranca, ?EntityCliente $cliente = null): array
    {
        $cliente ??= $cobranca->cliente_id ? EntityCliente::getById((int)$cobranca->cliente_id) : null;
        if (!$cliente || trim($cliente->email) === '') {
            $cobranca->update(['email_erro' => 'E-mail do cliente ausente']);

            return ['ok' => false, 'error' => 'E-mail do cliente ausente'];
        }

        $host = trim((string)Environment::get('MAIL_HOST', ''));
        if ($host === '') {
            $cobranca->update(['email_erro' => 'MAIL_HOST não configurado no .env']);

            return ['ok' => false, 'error' => 'Servidor SMTP não configurado'];
        }

        $competencia = $cobranca->competencia ?? '—';
        [$ano, $mes] = array_pad(explode('-', $competencia), 2, '');
        $competenciaLabel = $mes !== '' ? $mes.'/'.$ano : $competencia;

        $linha = trim((string)$cobranca->linha_digitavel);
        $pix = trim((string)$cobranca->pix_copia_cola);

        $html = View::render('email/boleto', [
            'cliente_nome' => htmlspecialchars($cliente->nome_fantasia, ENT_QUOTES, 'UTF-8'),
            'competencia' => htmlspecialchars($competenciaLabel, ENT_QUOTES, 'UTF-8'),
            'valor' => number_format($cobranca->valor_nominal, 2, ',', '.'),
            'vencimento' => date('d/m/Y', strtotime($cobranca->data_vencimento)),
            'bloco_linha' => $linha !== ''
                ? '<p><strong>Linha digitável:</strong><br>'.htmlspecialchars($linha, ENT_QUOTES, 'UTF-8').'</p>'
                : '',
            'bloco_pix' => $pix !== ''
                ? '<p><strong>PIX copia e cola:</strong><br><code style="word-break:break-all;">'
                    .htmlspecialchars($pix, ENT_QUOTES, 'UTF-8').'</code></p>'
                : '',
        ]);

        $subject = 'Boleto Well Eco — competência '.$competenciaLabel;
        $from = trim((string)Environment::get('MAIL_FROM', 'noreply@well.eco.br'));
        $fromName = trim((string)Environment::get('MAIL_FROM_NAME', 'Well Eco'));

        $attachments = [];
        $pdfPath = $cobranca->pdfAbsolutePath();
        if ($pdfPath && is_file($pdfPath)) {
            $attachments[] = ['path' => $pdfPath, 'name' => 'boleto-'.$cobranca->id.'.pdf'];
        }

        $sent = self::sendSmtp(
            $host,
            (int)Environment::get('MAIL_PORT', '587'),
            trim((string)Environment::get('MAIL_USER', '')),
            trim((string)Environment::get('MAIL_PASS', '')),
            $from,
            $fromName,
            $cliente->email,
            $subject,
            $html,
            $attachments
        );

        if ($sent['ok']) {
            $cobranca->update([
                'email_enviado_em' => date('Y-m-d H:i:s'),
                'email_erro' => null,
            ]);

            return ['ok' => true, 'error' => null];
        }

        $cobranca->update(['email_erro' => $sent['error']]);

        return $sent;
    }

    /**
     * @param list<array{path:string,name:string}> $attachments
     * @return array{ok:bool,error:?string}
     */
    private static function sendSmtp(
        string $host,
        int $port,
        string $user,
        string $pass,
        string $from,
        string $fromName,
        string $to,
        string $subject,
        string $html,
        array $attachments = []
    ): array {
        $socket = @stream_socket_client(
            'tcp://'.$host.':'.$port,
            $errno,
            $errstr,
            20
        );
        if (!$socket) {
            return ['ok' => false, 'error' => 'Conexão SMTP falhou: '.$errstr];
        }

        stream_set_timeout($socket, 20);
        $read = fn () => fgets($socket, 515) ?: '';
        $write = function (string $cmd) use ($socket): void {
            fwrite($socket, $cmd."\r\n");
        };

        $read();
        $write('EHLO well.eco');
        $read();

        if ($port === 587) {
            $write('STARTTLS');
            $read();
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);

                return ['ok' => false, 'error' => 'STARTTLS falhou'];
            }
            $write('EHLO well.eco');
            $read();
        }

        if ($user !== '') {
            $write('AUTH LOGIN');
            $read();
            $write(base64_encode($user));
            $read();
            $write(base64_encode($pass));
            $resp = $read();
            if (!str_starts_with($resp, '235')) {
                fclose($socket);

                return ['ok' => false, 'error' => 'Autenticação SMTP recusada'];
            }
        }

        $boundary = 'well-eco-'.md5((string)microtime(true));
        $write('MAIL FROM:<'.$from.'>');
        $read();
        $write('RCPT TO:<'.$to.'>');
        $read();
        $write('DATA');
        $read();

        $headers = [
            'From: '.$fromName.' <'.$from.'>',
            'To: '.$to,
            'Subject: '.$subject,
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="'.$boundary.'"',
        ];

        $body = '--'.$boundary."\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $body .= $html."\r\n";

        foreach ($attachments as $att) {
            if (!is_file($att['path'])) {
                continue;
            }
            $content = chunk_split(base64_encode((string)file_get_contents($att['path'])));
            $body .= '--'.$boundary."\r\n";
            $body .= 'Content-Type: application/pdf; name="'.$att['name']."\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= 'Content-Disposition: attachment; filename="'.$att['name']."\"\r\n\r\n";
            $body .= $content."\r\n";
        }

        $body .= '--'.$boundary."--\r\n";
        $write(implode("\r\n", $headers)."\r\n\r\n".$body."\r\n.");
        $resp = $read();
        $write('QUIT');
        fclose($socket);

        if (!str_starts_with($resp, '250')) {
            return ['ok' => false, 'error' => 'Envio SMTP rejeitado'];
        }

        return ['ok' => true, 'error' => null];
    }
}
