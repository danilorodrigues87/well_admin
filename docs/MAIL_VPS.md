# E-mail SMTP — VPS e notificações ao gerador

O admin usa **PHPMailer** ([`app/Service/MailService.php`](../app/Service/MailService.php)) para:

- Boletos (ação manual em Pagamentos)
- **Notificações automáticas ao gerador** ([`GeradorNotificacaoService`](../app/Service/GeradorNotificacaoService.php)): aprovação/recusa de solicitação de coleta, MTR disponível após registro no SINIR

Enquanto o SMTP não estiver configurado, o sistema **continua funcionando**; os e-mails são ignorados (`MAIL_NOTIFICATIONS_ENABLED` + credenciais completas).

---

## 1. Criar caixa na VPS

Passos típicos (cPanel, Plesk ou painel do provedor):

1. Criar domínio/subdomínio de e-mail (ex.: `well.eco.br`).
2. Criar conta **noreply@well.eco.br** (ou `financeiro@…` para boletos).
3. Anotar **servidor SMTP**, **porta**, **usuário** e **senha**.
4. Configurar **SPF** e **DKIM** no DNS (reduz spam e rejeição).
5. Se o app roda em EasyPanel/Docker na mesma VPS, liberar saída SMTP na porta **587** (TLS) ou **465** (SSL).

---

## 2. Variáveis no `.env` (produção)

```env
URL=https://admin.well.eco.br

MAIL_HOST=mail.well.eco.br
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USER=noreply@well.eco.br
MAIL_PASS=********
MAIL_FROM=noreply@well.eco.br
MAIL_FROM_NAME=Well S.A.

# true = envia notificações ao gerador (quando SMTP ok)
MAIL_NOTIFICATIONS_ENABLED=true
```

| Porta | `MAIL_ENCRYPTION` |
|-------|-------------------|
| 587 | `tls` (STARTTLS) |
| 465 | `ssl` |

Teste local XAMPP: se STARTTLS falhar, use porta 465 + `ssl`. Em último caso (só dev): `MAIL_SSL_VERIFY=false`.

---

## 3. Smoke test (SSH ou local)

```bash
php database/scripts/mail_smoke_test.php seu-email@exemplo.com
```

Envia um e-mail de teste. Erros comuns:

| Sintoma | Ação |
|---------|------|
| `SMTP incompleto` | Preencher `MAIL_HOST`, `MAIL_USER`, `MAIL_FROM` |
| `Authentication failed` | Usuário/senha ou conta bloqueada no servidor |
| Timeout | Firewall da VPS bloqueando outbound 587/465 |
| Entrega na spam | SPF/DKIM, remetente alinhado ao domínio |

---

## 4. Destinatários das notificações gerador

Para cada cliente, o sistema envia para (sem duplicar endereço):

1. E-mail cadastral do **cliente** (`clientes.email`)
2. E-mail do **usuário portal** (`cliente_usuarios.email`)

Garanta que pelo menos um esteja válido em Clientes → cadastro / cadeado portal.

---

## 5. Boletos

O envio de boleto continua **manual** em Pagamentos (botão envelope). Usa o mesmo SMTP; falhas ficam em `inter_cobrancas.email_erro`.
