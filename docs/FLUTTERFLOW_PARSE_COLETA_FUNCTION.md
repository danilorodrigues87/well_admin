# Custom functions corrompidas pelo MCP — resolvido em 2026-10-01

## O que aconteceu

As custom functions do app FlutterFlow ficaram com **YAML gravado dentro do
corpo Dart** (`identifier:` / `arguments:` / `code: "..."` no lugar do código),
o que faz o FlutterFlow acusar *"Code has errors or is improperly formatted"* e,
com o arquivo grande, trava o editor a ponto de não aceitar edição nem exclusão.

| Função | Key | Uso real | Estado |
|--------|-----|----------|--------|
| `parseColetaItemsFromApi` | `colp01` | **Sim** — 8 actions em CollectionsList | Corrigida |
| `parseColetaDetailItemsFromApi` | `cddp01` | Não — órfã | Corrigida; pode ser excluída pela UI |
| `zzzDeleteMeColp99Stub` | `colp99` | Não — duplicata criada por engano | Excluir quando o editor permitir |

## Causa raiz

O MCP expõe o código como `custom-functions/id-<key>/function-code.dart`, mas a
chave real do projeto é `custom-functions/id-<key>/function-code`, **sem o
`.dart`**. A gravação não atinge o arquivo real e o MCP regrava o arquivo
renderizado dentro do próprio campo `code:`, aninhando mais um nível a cada
tentativa.

## Correção aplicada

Gravação direta por `POST https://api.flutterflow.io/v2/updateProjectByYaml`
com a chave correta e **somente o corpo Dart** como conteúdo. Resultado
conferido por `GET /v2/projectYamls`, que lê do servidor sem o cache do MCP.

Corpos Dart de referência: `storage/ff-yaml/colp01_function-body.dart` e
`storage/ff-yaml/cddp01_function-body.dart`.

## Como não repetir

Procedimento completo, comandos e demais armadilhas do MCP:
[FLUTTERFLOW_MCP_GUIA.md](FLUTTERFLOW_MCP_GUIA.md) §2.
