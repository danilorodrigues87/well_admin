# Contratos comerciais (gerador dinâmico)

## Fluxo (operador)

1. Cadastre o **plano** com **Modelo de contrato** e **itens do plano** (franquia / excedente / crédito — tarifas de referência).
2. No **cliente**, preencha responsável, CPF, RG e cargo (qualificação no contrato).
3. **Novo contrato** (wizard): plano → **marque os resíduos** que aquele gerador usará → **valor mensal negociado** → revisão → enviar assinatura.
4. Gerador assina no portal; contrato fica **ativo** com HTML e valores congelados.

## Regra comercial × faturamento

| Campo | Origem após assinatura |
|-------|-------------------------|
| **Mensalidade** | `clientes_contratos.valor_mensal` (informada no contrato) |
| **Franquia / excedente / crédito** | `comercial_snapshot_json.itens` — cópia do plano **só dos tipos marcados** na criação |
| **Cálculo mensal** | `PlanoCobrancaService::calcularMes` + emissão em Pagamentos |

Sem contrato ativo assinado, o faturamento usa o plano vigente do cliente (`plano_itens` ao vivo).

**Pagamentos:** clientes com contrato ativo só entram na competência a partir de `primeira_competencia`. Badge **Só plano** = sem contrato assinado (cobrança pelo plano vigente). Filtro «Base de cobrança» no relatório de faturamento.

**Lacuna:** modelos RCC/obra na cláusula PDF podem diferir do motor kg mensal em `PlanoCobrancaService`.

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

## Snapshot comercial (`comercial_snapshot_json`)

Congela **mensalidade do contrato** + **subconjunto de `plano_itens`** escolhido na UI. Serviço: `ContratoComercialSnapshot::fromPlanoItensSelecionados`. API de itens do plano: `GET /painel/contratos/plano-itens?plano_id=`.

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
