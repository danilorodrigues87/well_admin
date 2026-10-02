# FlutterFlow via MCP — guia operacional e armadilhas

Projeto: **well-coletas-by2777**. Este documento registra o que já custou tempo
em sessões anteriores. Leia antes de mexer no app pelo MCP.

Contexto geral do app: [FLUTTERFLOW.md](FLUTTERFLOW.md) ·
matriz página → API → RBAC: [FLUTTERFLOW_APP_MATRIX.md](FLUTTERFLOW_APP_MATRIX.md).

---

## 1. Regra de ouro: gravação "com sucesso" não significa gravado

`update_project_yaml` responde `{"success": true}` em três situações distintas:
a alteração foi aplicada, a chave não existe e foi ignorada, ou o servidor
aceitou e descartou. **Sempre confirme** com `sync_project(force: true)` seguido
de `get_project_yaml` do mesmo arquivo. Em algo crítico, confirme pela API
oficial (§5), que lê do servidor sem passar pelo cache do MCP.

Isso vale também para o editor: a FlutterFlow tem issues abertas de
`updateProjectByYaml` retornando 200 sem refletir mudança
([#6786](https://github.com/FlutterFlow/flutterflow-issues/issues/6786)) e de 500
em arquivos de widget aninhados
([#6579](https://github.com/FlutterFlow/flutterflow-issues/issues/6579)).

---

## 2. Custom Functions — NUNCA editar pelo MCP

O MCP expõe o código como `custom-functions/id-<key>/function-code.dart`, mas a
chave real do projeto é `custom-functions/id-<key>/function-code`, **sem o
sufixo `.dart`**. Como o caminho não existe, o conteúdo enviado é descartado e o
MCP regrava o arquivo *renderizado* (metadados + campo `code:`) dentro do próprio
`code:`. Cada tentativa aninha mais um nível de YAML escapado, em crescimento
exponencial.

**O mesmo vale para Custom Actions:** a chave real é
`custom-actions/id-<key>/action-code` (sem `.dart`). Em 2026-10-02 o
`openRotaDirections` foi corrompido ao gravar via
`.../action-code.dart` — o `canLaunchUrl` voltou a bloquear a abertura do Maps.

Sintomas: o FlutterFlow acusa *"Code has errors or is improperly formatted"*, e
com o arquivo grande o editor trava — não aceita edição **nem exclusão** da
função. Em 2026-10-01 a `parseColetaDetailItemsFromApi` chegou a 23 KB de YAML
escapado no lugar de quatro linhas de Dart.

### Como identificar corrupção

Compare com uma função sã. `get_project_yaml` de uma função correta devolve
**só o corpo Dart**:

```
# custom-functions/id-3prfl/function-code.dart

  final now = DateTime.now();
  return '$d/$m/${now.year}';
```

Se a leitura devolver `identifier:` / `arguments:` / `returnParameter:` /
`code: "..."`, o corpo Dart foi substituído por texto YAML — está corrompida.

### Layout correto dos arquivos

| Arquivo | Conteúdo |
|---------|----------|
| `custom-functions/id-<key>` | `identifier`, `arguments`, `returnParameter`, `tests` — **sem** `code` |
| `custom-functions/id-<key>/function-code` | **somente o corpo Dart**, sem assinatura e sem wrapper YAML |

A assinatura é gerada pelo FlutterFlow a partir dos metadados.

### Procedimento correto (API oficial)

```powershell
$env:FF_TOKEN = '<token de Account Settings -> API Tokens>'
$dart = "`n  if (apiJson == null) {`n    return '';`n  }`n  return apiJson.toString();"

$body = @{
  projectId        = 'well-coletas-by2777'
  fileKeyToContent = @{ 'custom-functions/id-cddp01/function-code' = $dart }
} | ConvertTo-Json -Depth 4 -Compress

Invoke-RestMethod -Uri 'https://api.flutterflow.io/v2/updateProjectByYaml' `
  -Headers @{ Authorization = "Bearer $env:FF_TOKEN" } `
  -Method Post -ContentType 'application/json' `
  -Body ([Text.Encoding]::UTF8.GetBytes($body))
```

Valide antes em `/v2/validateProjectYaml` (campos **singulares** `fileKey` +
`fileContent`) e confira depois com `/v2/projectYamls` (§5).

---

## 3. Nomes de parâmetros das ferramentas MCP

Errar aqui faz a ferramenta devolver a listagem inteira de 530+ arquivos em vez
de um erro, o que consome contexto à toa.

| Ferramenta | Parâmetro correto | Erro comum |
|------------|-------------------|------------|
| `get_project_yaml` | `fileName` | `fileKey` |
| `validate_yaml` | `fileKey` + `fileContent` (strings) | `fileKeys` em array |
| `update_project_yaml` | `fileKeyToContent` (mapa) | — |
| `get_editing_guide` | `task` é **obrigatório** | chamar com `{}` |
| `get_yaml_docs` | `topic` | — |

Na dúvida, chame `GetDynamicTools(namespace: "user-flutterflow", toolName: "...")`
antes de usar. **O MCP não tem ferramenta de exclusão** — remover página,
componente ou função só pela UI.

---

## 4. Estrutura de arquivos de página e widget

### Chaves de nó são planas

A hierarquia vive no outline, não no caminho. O caminho é sempre:

```
page/id-<Scaffold>/page-widget-tree-outline/node/id-<NodeKey>
```

Não existe `.../node/id-A/node/id-B/node/id-C`. Caminhos inventados assim
retornam sucesso e não gravam nada. Leia
`page/id-<Scaffold>/page-widget-tree-outline` para descobrir as chaves reais
antes de editar. Componentes usam `component-widget-tree-outline`.

### Formato de um nó é plano

```yaml
key: Text_6wdumo9w
type: Text
props:
  text:
    themeStyle: BODY_LARGE
    textValue:
      variable: { ... }
name: NomeResiduo2
valueKey: {}
```

Pôr `column:` ou `container:` no topo devolve `Unknown field name`. O tipo vai em
`type:` e as propriedades dentro de `props:`.

### Sem âncoras YAML

`&ancora` e `<<: *ancora` fazem a API responder **400** (`While parsing a flow
mapping, expected ',' or '}'`). Escreva o YAML expandido, mesmo que fique longo.

### Dica de validação

`validate_yaml` no outline devolve *"File is referenced but is empty"* para cada
nó referenciado sem arquivo — é a forma rápida de descobrir o que falta criar.

---

## 5. API oficial do FlutterFlow

Base: `https://api.flutterflow.io/v2/` · header `Authorization: Bearer <token>`.
Documentação: [Project APIs](https://docs.flutterflow.io/resources/projects/settings/project-apis/).

| Endpoint | Método | Uso |
|----------|--------|-----|
| `/listPartitionedFileNames` | GET | Lista as **chaves reais** do projeto — use para conferir caminhos |
| `/projectYamls` | GET | Export; devolve zip em base64 em `value.project_yaml_bytes` |
| `/validateProjectYaml` | POST | `projectId`, `fileKey`, `fileContent` |
| `/updateProjectByYaml` | POST | `projectId`, `fileKeyToContent` |

Ler o estado real do servidor:

```powershell
$r = Invoke-RestMethod -Method Get `
  -Uri 'https://api.flutterflow.io/v2/projectYamls?projectId=well-coletas-by2777&fileNames=custom-functions/id-colp01/function-code' `
  -Headers @{ Authorization = "Bearer $env:FF_TOKEN" }
[IO.File]::WriteAllBytes("$dir\p.zip", [Convert]::FromBase64String($r.value.project_yaml_bytes))
Expand-Archive "$dir\p.zip" "$dir\out" -Force
```

`updateProjectByYaml` **não suporta** arquivos de widget aninhados de página
(`page/.../node/...`) — resposta oficial da FlutterFlow na issue #6579. Para
esses, use o MCP; para custom functions e arquivos de configuração, use a API.

### Token

Gerado em **Account Settings → API Tokens**. Nunca vai para o repositório nem
para o `.env` versionado: use só em variável de ambiente da sessão
(`$env:FF_TOKEN`) e limpe ao terminar (`Remove-Item Env:\FF_TOKEN`). Se o token
for exposto em chat ou log, revogue e gere outro.

---

## 6. Fontes de variável (referência rápida)

| `source` | Bloco obrigatório |
|----------|-------------------|
| `LOCAL_STATE` | `localState.fieldIdentifier` + `stateVariableType: WIDGET_CLASS_STATE` + `nodeKeyRef` |
| `ACTION_OUTPUTS` | `actionOutput.outputVariableIdentifier` + `actionKeyRef`; operações `apiResponseField: JSON_BODY` e `jsonPathOperation` |
| `WIDGET_CLASS_PARAMETER` | parâmetro do componente/página |
| `FUNCTION_CALL` | `stringInterpolation`, `conditionalValue`, `codeExpression`, `customFunction` |
| `GENERATOR_VARIABLE` | item do ListView dinâmico |

Relações de condição: `EXISTS_AND_NON_EMPTY`, `DOES_NOT_EXIST_OR_IS_EMPTY`,
`EQUAL_TO`.

Mantenha `inputValue` e `mostRecentInputValue` sincronizados — exceto
`fontWeightValue` e `fontSizeValue`, que só aceitam `inputValue`.

---

## 7. Decisões de arquitetura do app

**Formatação no PHP, não no FlutterFlow.** Interpolação de string no FF se
mostrou instável via MCP (um binding somiu do cache ao aplicar
`stringInterpolation` com vários valores). A API entrega campos prontos para a
UI — `resumo.titulo`, `status_label`, `peso_total_label`,
`itens[].quantidade_label` — e o widget faz binding direto. Ver
`app/Service/ColetaApiPresenter.php` e [API.md](API.md).

**Listas dinâmicas usam Data Struct + custom function, com o código Dart gravado
pela API oficial** (`custom-functions/id-<key>/function-code`, sem `.dart`). A
`CollectionDetail` lista resíduos num ListView ligado a `residuos`
(`List<ColetaResiduoItem>`), preenchido por `parseColetaDetailItemsFromApi`.
Cards fixos (item1/item2/item3) estouravam o layout e omitiam o 4º resíduo;
JSON nulo virava o texto `"null"`.

**Navegação a partir de bottom sheet:** não feche a sheet antes de navegar. O
dismiss descarta o contexto que carrega os parâmetros do componente. O botão
"Ver detalhes" do `CardCom4` faz **Navigate direto** para `CollectionDetail`
passando `coletaId`.

---

## 8. Fluxo de trabalho recomendado

1. `sync_project(force: true)` — nunca confie em cache antigo.
2. Descobrir chaves reais: `page-widget-tree-outline` ou
   `/listPartitionedFileNames`.
3. Ler o arquivo existente e **copiar o formato dele** em vez de inventar
   schema; `get_yaml_docs(topic:)` para campos desconhecidos.
4. `validate_yaml` em cada arquivo.
5. `update_project_yaml` com todos os arquivos relacionados numa só chamada
   (outline + nós).
6. `sync_project(force: true)` + reler para **confirmar** que gravou.
7. Atualizar a documentação da feature.
