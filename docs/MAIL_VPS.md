# E-mail — Brevo (envio) + ImprovMX (entrada)

O admin usa **PHPMailer** ([`app/Service/MailService.php`](../app/Service/MailService.php)) para:

- **Boletos** (manual em Pagamentos) — usa SMTP direto; **não** depende de `MAIL_NOTIFICATIONS_ENABLED`
- **Notificações ao gerador** ([`GeradorNotificacaoService`](../app/Service/GeradorNotificacaoService.php)) — exige SMTP ok **e** `MAIL_NOTIFICATIONS_ENABLED=true`

| Papel | Serviço | DNS |
|-------|---------|-----|
| **Saída** (app PHP) | Brevo SMTP `smtp-relay.brevo.com` | SPF + DKIM (CNAME Brevo) + DMARC |
| **Entrada** (aliases) | ImprovMX | MX `mx1/mx2.improvmx.com` + SPF `include:spf.improvmx.com` |

O **ImprovMX não envia** e-mails da aplicação; só redireciona o que **chega** no domínio. O remetente `MAIL_FROM=noreply@well.eco.br` deve estar **autenticado no Brevo** (domínio verificado).

### Erro no log Brevo (rejeição)

> *Sending has been rejected because the sender you used noreply@well.eco.br is not valid. Validate your sender or authenticate your domain*

O SMTP **conecta**, mas o Brevo **não entrega** enquanto o domínio/remetente não estiver validado. Corrija no painel Brevo (abaixo), não no `.env`.

**Passos no Brevo:**

1. **Senders, Domains & Dedicated IPs** → **Domains** → adicionar **`well.eco.br`** (se ainda não estiver).
2. Copiar os registros DNS que o Brevo pede (CNAME DKIM `brevo1` / `brevo2`, etc.) e conferir no **Registro.br** — aguardar propagação (até algumas horas).
3. Clicar **Authenticate domain** / **Verify** até o domínio ficar **Verified** (verde).
4. **Senders** → adicionar **`noreply@well.eco.br`** (nome ex.: Well S.A.) e concluir verificação se o painel pedir (e-mail de confirmação ou validação automática com domínio autenticado).
5. Só então repetir: `php database/scripts/mail_smoke_test.php ...`

Enquanto o log mostrar *sender is not valid*, nenhum destinatário (Gmail, etc.) receberá — mesmo com smoke test “OK” no PHP.

---

## 1. Brevo — credenciais SMTP (`.env`)

No Brevo: **Settings → SMTP & API → SMTP** (aba SMTP, não API).

| Variável | Valor |
|----------|--------|
| `MAIL_HOST` | `smtp-relay.brevo.com` |
| `MAIL_PORT` | `587` |
| `MAIL_ENCRYPTION` | `tls` |
| `MAIL_USER` | Login SMTP (formato `xxxx@smtp-brevo.com`) — **não** use o host como usuário |
| `MAIL_PASS` | **Chave SMTP** gerada no Brevo — **não** use chave de API (`xkeysib-…`) |
| `MAIL_FROM` | Remetente verificado, ex.: `noreply@well.eco.br` |
| `MAIL_FROM_NAME` | `Well S.A.` (marca — ver `CompanyConfig`) |

```env
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USER=seu-login@smtp-brevo.com
MAIL_PASS=xsmtpsib-...   # chave SMTP (não API)
MAIL_FROM=noreply@well.eco.br
MAIL_FROM_NAME=Well S.A.
MAIL_NOTIFICATIONS_ENABLED=true
```

Erro **`Could not authenticate` / 535**: quase sempre `MAIL_PASS` é API key em vez de **SMTP key**, ou login errado. Gere nova chave SMTP se perdeu a senha (só aparece uma vez).

Se no Brevo estiver ativo **bloqueio de IP para SMTP**, inclua o IP público da VPS na lista autorizada.

---

## 2. DNS (Registro.br) — well.eco.br

**Envio (Brevo):**

- CNAME DKIM: `brevo1._domainkey`, `brevo2._domainkey` (conforme painel Brevo)
- TXT DMARC: `_dmarc.well.eco.br`
- TXT SPF (recomendado **ImprovMX + Brevo** na mesma linha):

```text
v=spf1 include:spf.improvmx.com include:spf.brevo.com ~all
```

Se o SPF tiver só ImprovMX, e-mails **enviados pelo Brevo** podem falhar SPF no destino (spam/rejeição).

**Entrada (ImprovMX):**

- MX prioridade 10 → `mx1.improvmx.com`
- MX prioridade 20 → `mx2.improvmx.com`

Aliases no ImprovMX: grafia exata (`noreply@`, não `noreplay@`) só afeta **recebimento** nesse endereço; não bloqueia envio via Brevo.

---

## 3. Diagnóstico e smoke test

Local ou container:

```bash
php database/scripts/mail_diagnose.php
php database/scripts/mail_smoke_test.php destino@exemplo.com
```

`mail_diagnose.php` mostra host/user/from, **tipo** da senha (SMTP vs API), origem da variável (Easypanel vs `.env`) — sem expor a chave inteira.

| Sintoma | Ação |
|---------|------|
| `SMTP incompleto` | `MAIL_HOST`, `MAIL_USER`, `MAIL_FROM` |
| `Could not authenticate` | Chave **SMTP** (`xsmtpsib-`), login `@smtp-brevo.com` |
| `Unauthorized IP` | Liberar IP da VPS no Brevo (SMTP keys) |
| Timeout | Saída TCP 587/465 da VPS / Docker |
| Boleto “Falhou” no admin | Hover no badge — grava `inter_cobrancas.email_erro`; conferir e-mail do **cliente** |
| Smoke **OK** mas inbox vazio | Ver **Logs transacionais** no Brevo (blocked/deferred); domínio autenticado; Gmail spam |

Debug verboso (só diagnóstico): `MAIL_SMTP_DEBUG=2` no ambiente ao rodar o smoke test.

---

## 4. Boletos vs notificações gerador

- **Boletos:** [`MailService::enviarBoleto`](../app/Service/MailService.php) — SMTP completo + e-mail em `clientes.email`.
- **Gerador:** [`MailService::enviarHtml`](../app/Service/MailService.php) — exige `MAIL_NOTIFICATIONS_ENABLED=true` (recomendado também na VPS/Easypanel).

Destinatários gerador: `clientes.email` e/ou `cliente_usuarios.email`.

---

## 5. Legado (caixa na hospedagem)

Se no futuro usar `mail.well.eco.br` em vez de Brevo: porta **465** → `MAIL_ENCRYPTION=ssl`; **587** → `tls`. Ver comentários no `.env.example`.
