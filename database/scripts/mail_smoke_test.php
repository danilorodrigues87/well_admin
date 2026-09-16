<?php
/**
 * Smoke test SMTP — envio de boleto.
 * Uso:
 *   php database/scripts/mail_smoke_test.php
 *   php database/scripts/mail_smoke_test.php --send=seu@email.com
 */
declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/includes/app.php';

use App\Common\Environment;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

$sendTo = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--send=')) {
        $sendTo = trim(substr($arg, 7));
    }
}

$host = trim((string)Environment::get('MAIL_HOST', Environment::get('SMTP_HOST', '')));
$port = (int)Environment::get('MAIL_PORT', Environment::get('SMTP_PORT', '587'));
$user = trim((string)Environment::get('MAIL_USER', Environment::get('SMTP_USER', '')));
$pass = (string)Environment::get('MAIL_PASS', Environment::get('SMTP_PASS', ''));
$from = trim((string)Environment::get('MAIL_FROM', Environment::get('SMTP_FROM_EMAIL', '')));
$encryption = strtolower(trim((string)Environment::get(
    'MAIL_ENCRYPTION',
    Environment::get('SMTP_ENCRYPTION', $port === 465 ? 'ssl' : 'tls')
)));

if ($port === 465 && $encryption === 'tls') {
    $encryption = 'ssl';
}

echo "=== Well — SMTP smoke test ===\n\n";

if (!extension_loaded('openssl')) {
    fwrite(STDERR, "[FALHA] Extensão PHP openssl não está habilitada.\n");
    exit(1);
}

echo "Configuração (.env):\n";
echo '  MAIL_HOST='.($host !== '' ? $host : '(vazio)')."\n";
echo '  MAIL_PORT='.$port."\n";
echo '  MAIL_ENCRYPTION='.$encryption."\n";
echo '  MAIL_USER='.($user !== '' ? $user : '(vazio)')."\n";
echo '  MAIL_PASS='.($pass !== '' ? '*** ('.strlen($pass).' chars)' : '(vazio)')."\n";
echo '  MAIL_FROM='.($from !== '' ? $from : '(vazio)')."\n\n";

$missing = [];
if ($host === '') {
    $missing[] = 'MAIL_HOST';
}
if ($user === '') {
    $missing[] = 'MAIL_USER';
}
if ($pass === '') {
    $missing[] = 'MAIL_PASS';
}
if ($from === '') {
    $missing[] = 'MAIL_FROM';
}

if ($missing !== []) {
    fwrite(STDERR, '[FALHA] Variáveis ausentes no .env: '.implode(', ', $missing)."\n");
    exit(1);
}

if (!is_file(dirname(__DIR__, 2).'/vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
    fwrite(STDERR, "[FALHA] PHPMailer não instalado. Rode: composer install\n");
    exit(1);
}

$smtpSecure = match ($encryption) {
    'ssl', 'smtps' => PHPMailer::ENCRYPTION_SMTPS,
    'none' => false,
    default => PHPMailer::ENCRYPTION_STARTTLS,
};

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $user;
    $mail->Password = $pass;
    $mail->SMTPSecure = $smtpSecure;
    $mail->Port = $port;
    $mail->Timeout = 20;
    $mail->SMTPDebug = 0;

    if (filter_var(Environment::get('MAIL_SSL_VERIFY', 'true'), FILTER_VALIDATE_BOOLEAN) === false) {
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
    }

    echo "Conectando em {$host}:{$port} ({$encryption})...\n";
    if (!$mail->smtpConnect()) {
        fwrite(STDERR, '[FALHA] smtpConnect retornou false: '.$mail->ErrorInfo."\n");
        exit(1);
    }
    echo "[OK] Conexão SMTP estabelecida.\n";
    $mail->smtpClose();

    if ($sendTo === null || $sendTo === '') {
        echo "\nConexão OK. Para enviar e-mail de teste:\n";
        echo "  php database/scripts/mail_smoke_test.php --send=seu@email.com\n";
        exit(0);
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $user;
    $mail->Password = $pass;
    $mail->SMTPSecure = $smtpSecure;
    $mail->Port = $port;
    $mail->CharSet = 'UTF-8';
    $mail->Timeout = 25;
    if (filter_var(Environment::get('MAIL_SSL_VERIFY', 'true'), FILTER_VALIDATE_BOOLEAN) === false) {
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
    }
    $mail->setFrom($from, 'Well SMTP Test');
    $mail->addAddress($sendTo);
    $mail->Subject = 'Teste SMTP Well Admin';
    $mail->Body = '<p>E-mail de teste enviado em '.date('d/m/Y H:i:s').'</p>';
    $mail->isHTML(true);
    $mail->send();

    echo "[OK] E-mail de teste enviado para {$sendTo}\n";
    exit(0);
} catch (MailException $e) {
    fwrite(STDERR, '[FALHA] '.$mail->ErrorInfo."\n\n");
    fwrite(STDERR, "Dicas (hospedagem compartilhada / cPanel):\n");
    fwrite(STDERR, "  - Porta 465 → MAIL_ENCRYPTION=ssl\n");
    fwrite(STDERR, "  - Porta 587 → MAIL_ENCRYPTION=tls\n");
    fwrite(STDERR, "  - Se mail.dominio falhar, tente MAIL_HOST=localhost\n");
    fwrite(STDERR, "  - Confirme que vendor/ foi enviado (composer install no servidor)\n");
    exit(1);
}
