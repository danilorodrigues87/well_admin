<?php

namespace App\Service;

use App\Common\CompanyConfig;
use App\Common\Environment;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\InterCobranca as EntityInterCobranca;
use App\Utils\View;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

class MailService
{
    /** @return array{host:string,user:string,pass:string,port:int,from:string,from_name:string,encryption:string} */
    private static function smtpConfig(): array
    {
        $port = (int)Environment::get('MAIL_PORT', Environment::get('SMTP_PORT', '587'));
        $encryption = strtolower(trim((string)Environment::get(
            'MAIL_ENCRYPTION',
            Environment::get('SMTP_ENCRYPTION', $port === 465 ? 'ssl' : 'tls')
        )));

        // Porta 465 = SSL implícito (SMTPS). Porta 587 = STARTTLS.
        if ($port === 465 && $encryption === 'tls') {
            $encryption = 'ssl';
        }
        if ($port === 587 && $encryption === 'ssl') {
            $encryption = 'tls';
        }

        return [
            'host' => trim((string)Environment::get('MAIL_HOST', Environment::get('SMTP_HOST', ''))),
            'user' => trim((string)Environment::get('MAIL_USER', Environment::get('SMTP_USER', ''))),
            'pass' => (string)Environment::get('MAIL_PASS', Environment::get('SMTP_PASS', '')),
            'port' => $port,
            'from' => trim((string)Environment::get('MAIL_FROM', Environment::get('SMTP_FROM_EMAIL', ''))),
            'from_name' => CompanyConfig::mailFromName(),
            'encryption' => $encryption,
        ];
    }

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

        $cfg = self::smtpConfig();
        if ($cfg['host'] === '' || $cfg['user'] === '' || $cfg['from'] === '') {
            $cobranca->update(['email_erro' => 'SMTP incompleto no .env']);

            return ['ok' => false, 'error' => 'SMTP incompleto (MAIL_HOST, MAIL_USER, MAIL_FROM no .env)'];
        }

        $competencia = $cobranca->competencia ?? '—';
        [$ano, $mes] = array_pad(explode('-', $competencia), 2, '');
        $competenciaLabel = $mes !== '' ? $mes.'/'.$ano : $competencia;

        $linha = trim((string)$cobranca->linha_digitavel);
        $pix = trim((string)$cobranca->pix_copia_cola);

        $html = View::render('email/boleto', [
            'cliente_nome' => htmlspecialchars($cliente->nome_fantasia, ENT_QUOTES, 'UTF-8'),
            'competencia' => htmlspecialchars($competenciaLabel, ENT_QUOTES, 'UTF-8'),
            'empresa_nome' => htmlspecialchars(CompanyConfig::name(), ENT_QUOTES, 'UTF-8'),
            'empresa_sigla' => htmlspecialchars(CompanyConfig::shortName(), ENT_QUOTES, 'UTF-8'),
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

        $subject = 'Boleto '.CompanyConfig::shortName().' — competência '.$competenciaLabel;

        $attachments = [];
        $pdfPath = $cobranca->pdfAbsolutePath();
        if ($pdfPath && is_file($pdfPath)) {
            $attachments[] = ['path' => $pdfPath, 'name' => 'boleto-'.$cobranca->id.'.pdf'];
        }

        $sent = self::sendViaPhpMailer($cfg, trim($cliente->email), $subject, $html, $attachments);

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

    /** @param list<array{path:string,name:string}> $attachments */
    private static function resolveEncryption(string $encryption): string|bool
    {
        return match ($encryption) {
            'ssl', 'smtps' => PHPMailer::ENCRYPTION_SMTPS,
            'none' => false,
            default => PHPMailer::ENCRYPTION_STARTTLS,
        };
    }

    /**
     * @param array{host:string,user:string,pass:string,port:int,from:string,from_name:string,encryption:string} $cfg
     * @param list<array{path:string,name:string}> $attachments
     * @return array{ok:bool,error:?string}
     */
    private static function sendViaPhpMailer(
        array $cfg,
        string $to,
        string $subject,
        string $html,
        array $attachments = []
    ): array {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $cfg['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $cfg['user'];
            $mail->Password = $cfg['pass'];
            $mail->SMTPSecure = self::resolveEncryption($cfg['encryption']);
            $mail->Port = $cfg['port'];
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->Timeout = max(5, (int)Environment::get('MAIL_TIMEOUT', '25'));
            $mail->SMTPKeepAlive = false;

            if (filter_var(Environment::get('MAIL_SSL_VERIFY', 'true'), FILTER_VALIDATE_BOOLEAN) === false) {
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ];
            }

            $mail->setFrom($cfg['from'], $cfg['from_name']);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = strip_tags($html);

            foreach ($attachments as $att) {
                if (is_file($att['path'])) {
                    $mail->addAttachment($att['path'], $att['name']);
                }
            }

            $mail->send();

            return ['ok' => true, 'error' => null];
        } catch (MailException $e) {
            return ['ok' => false, 'error' => 'SMTP: '.$mail->ErrorInfo];
        }
    }
}
