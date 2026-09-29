# Contratos comerciais (gerador dinâmico)

## Fluxo

1. Cadastre o **plano** com **Modelo de contrato** (Cadastros → Planos).
2. Preencha **itens do plano** (franquia / excedente) — entram na cláusula de preço.
3. Cliente: responsável, CPF, RG e cargo (qualificação do contratante).
4. Contratos → Novo: plano, vigência, taxa de adesão, foro (opcional).
5. Imprimir / portal gerador: HTML com cláusulas do modelo.

## Modelos (`planos.contrato_modelo_tipo`)

| Valor | Uso |
|-------|-----|
| `GENERICO` | Layout simplificado (`modelo_padrao.html` + cláusulas 1–3 do plano) — sem editor HTML separado |
| `RSS_CLINICA` | RSS clínica (Grupos A, B, D, E) |
| `RSS_HOSPITAL` | RSS hospital (+ Grupo C, RT) |
| `CLASSE_I_II` | Perigosos + recicláveis |
| `CLASSE_I` | Só Classe I |
| `RECICLAVEIS` | Classe II-B recicláveis |
| `PNEUS` | Classe II-A pneus |
| `RCC_PADRAO` | RCC — medição / caçamba |
| `OBRA_GRANDE_PORTE` | Obra — valor global, retenção, LGPD/anticorrupção |

Textos jurídicos completos ficam em **`contrato_modelos`** (por `operadora_id`), seed em `database/seeds/contrato_modelos/` + `php database/scripts/seed_contrato_modelos.php`.

Defaults comerciais (taxa adesão, índice): [`ContratoModeloCatalog`](../app/Common/Contrato/ContratoModeloCatalog.php).

## Snapshot comercial

Na criação do contrato grava-se `comercial_snapshot_json` (mensalidade + itens/franquia/excedente). **Faturamento** (`PlanoCobrancaService`) usa o contrato ativo, não o plano vigente.

## Rescisão

- **Cancelar:** rascunho / aguardando assinatura → status `cancelado` (botão na tela do contrato)
- **Rescindir:** contrato `ativo` → `encerrado`, limpa `clientes.contrato_ativo_id`

## Logo no cabeçalho

Por padrão usa `resources/assets/imgs/logo.png` (URL absoluta do admin). Por operadora, opcional em `operadora_config`:

- `contrato_logo_path` — caminho relativo (ex.: `/resources/assets/imgs/logo.png`)
- `contrato_logo_url` — URL completa ou caminho relativo

Helper: `ContratoBrandingHelper::logoHtml($operadoraId)`.

Contratos já assinados (`html_snapshot`) mantêm o HTML antigo até novo contrato ou limpeza do snapshot em teste.

## Personalização

- **Por contrato:** `/painel/contratos/{id}/editar` — valor mensal, frequência, promo HTML, extras, snapshot de itens.
- **Modelos jurídicos (admin):** `/painel/contratos/modelos` — editar `body_html`, nova versão, reimportar seed.

## Código

- Template DB: `ContratoTemplateService`, `ContratoVariableResolver`
- Fallback legado: `ContratoDocumentFactory` + `ContratoRenderService`
- Shell impressão: `resources/view/contratos/shell.html`
- Entrada: `ContratoClienteService::renderHtml()` (HTML congelado após assinatura)

## Migrations

- `049_contratos_dinamicos.sql`
- `050_contrato_modelos.sql`
