# Credenciais Banco Inter (fora do Git)

Coloque aqui os arquivos da integração Inter Empresas:

| Arquivo | Descrição |
|---------|-----------|
| `certificado.crt` | Certificado público (.crt) baixado no Internet Banking |
| `chave.key` | Chave privada (.key) |
| `oauth-token.json` | Cache automático do token OAuth (gerado pelo sistema) |

**Nunca commitar** estes arquivos. O `.gitignore` já os exclui.

Credenciais de texto (`INTER_CLIENT_ID`, `INTER_CLIENT_SECRET`, conta corrente) ficam no `.env` na raiz do projeto.
