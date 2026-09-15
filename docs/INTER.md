# Integração Banco Inter — Cobrança BolePix

> API Cobrança v3 (boleto com QR Code PIX). Autenticação OAuth2 + mTLS (certificado + chave).

## Arquivos de certificado

Copiados para (fora do Git):

```
storage/inter/certificado.crt
storage/inter/chave.key
```

O cache do token OAuth fica em `storage/inter/oauth-token.json` (gerado automaticamente).

## Configuração `.env`

Abra o `.env` na raiz do projeto e adicione:

```env
INTER_ENABLED=true
INTER_ENV=production
INTER_CLIENT_ID=seu_client_id_aqui
INTER_CLIENT_SECRET=seu_client_secret_aqui
INTER_CONTA_CORRENTE=12345678
INTER_CNPJ=18675233000150
```

Opcional (defaults já apontam para `storage/inter/`):

```env
INTER_CERT_PATH=
INTER_KEY_PATH=
INTER_KEY_PASSWORD=
INTER_SCOPE="boleto-cobranca.read boleto-cobranca.write webhook.read webhook.write"
INTER_WEBHOOK_URL=https://admin.well.eco.br/api/v1/webhooks/inter/cobranca
INTER_WEBHOOK_SECRET=um_segredo_forte_aqui
```

| Variável | Onde obter |
|----------|------------|
| `INTER_CLIENT_ID` | Internet Banking Inter → Soluções para sua empresa → sua integração |
| `INTER_CLIENT_SECRET` | Mesma tela (só exibido na criação — guarde em local seguro) |
| `INTER_CONTA_CORRENTE` | Número da conta PJ no Inter (somente dígitos) |
| Certificado + chave | Download na integração → ativar certificado |

**Nunca commitar** client secret nem certificados.

## Migration + smoke test (Windows / XAMPP)

No PowerShell, na raiz do projeto. O `mysql` do XAMPP **não** fica no PATH — use o caminho completo:

```powershell
cd C:\xampp\htdocs\pjt\admin.well.eco

Get-Content database\migrations\011_inter_cobrancas.sql | C:\xampp\mysql\bin\mysql.exe -u root well_admin

php database\scripts\inter_smoke_token.php
```

> **Nota:** o operador `<` do bash (`mysql ... < arquivo.sql`) costuma falhar no PowerShell. Use `Get-Content ... | mysql` como acima.

## Smoke test

Após preencher o `.env`:

```powershell
php database/scripts/inter_smoke_token.php
```

Sucesso esperado: `[OK] Token OAuth obtido com sucesso na API Inter.`

## Arquitetura no código

```
app/Common/InterConfig.php
app/Service/Banco/BancoGatewayInterface.php
app/Service/Inter/
  InterGateway.php          — HTTP + mTLS
  InterAuthService.php      — OAuth2 client_credentials + cache token
  InterCobrancaService.php  — emitir/consultar/cancelar cobrança
  InterWebhookService.php   — baixa automática (PAGO/VENCIDO/CANCELADO)
  InterService.php          — smoke test, isReady()
app/Controller/Api/Webhooks/InterCobranca.php
app/Model/Entity/InterCobranca.php
database/migrations/011_inter_cobrancas.sql
database/scripts/inter_register_webhook.php
```

## Endpoints Inter utilizados

| Operação | Método | Path |
|----------|--------|------|
| Token OAuth | POST | `/oauth/v2/token` |
| Emitir cobrança | POST | `/cobranca/v3/cobrancas` |
| Consultar | GET | `/cobranca/v3/cobrancas/{codigoSolicitacao}` |
| PDF base64 | GET | `/cobranca/v3/cobrancas/{codigoSolicitacao}/pdf` |
| Cancelar | POST | `/cobranca/v3/cobrancas/{codigoSolicitacao}/cancelar` |
| Webhook | PUT | `/cobranca/v3/cobrancas/webhook` |

Base URL produção: `https://cdpj.partners.bancointer.com.br`  
Sandbox: `INTER_ENV=sandbox` → `https://cdpj-sandbox.partners.uatinter.co`

## Faturamento no painel (`/painel/pagamentos`)

1. Selecionar competência (mês) — carrega clientes ativos com plano
2. Conferir valor plano + excedentes (`PlanoCobrancaService::calcularMes`)
3. Ajustar valor final por cliente (opcional)
4. Configurar multa/juros (padrão global ou override no lote)
5. **Gerar boletos** — emissão Inter em lote + PDF/linha/PIX

**Valor mínimo:** a API Cobrança v3 do Inter exige `valorNominal >= 2.50`. Valores menores (ex.: R$ 1,00 para teste) são rejeitados pela API.
6. Aba **Cobranças emitidas** — download PDF, copiar linha/PIX, reenviar e-mail

**E-mail:** configure `MAIL_HOST`, `MAIL_USER`, `MAIL_PASS` no `.env`.

**Multa/juros padrão:** `.env` (`COBRANCA_MULTA_*`, `COBRANCA_MORA_*`) ou aba Configurações (persiste em `config_sistema`).

## Webhook (baixa automática)

Endpoint local (sem JWT — validação opcional por segredo):

```
POST /api/v1/webhooks/inter/cobranca
Header: X-Webhook-Secret: {INTER_WEBHOOK_SECRET}   (se definido no .env)
```

O Inter envia `codigoSolicitacao` + `situacao`; o serviço atualiza `inter_cobrancas.status` para `PAGO`, `VENCIDO` ou `CANCELADO`.

**Registrar URL no Inter** (produção, com certificados no `.env`):

```powershell
php database/scripts/inter_register_webhook.php
```

Documentação oficial: https://developers.inter.co/
