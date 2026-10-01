# Custom Function `parseColetaItemsFromApi` — duplicata + código corrompido

## Situação

| Função | Key | Uso real |
|--------|-----|----------|
| **`parseColetaItemsFromApi`** | `colp01` | **Sim** — 8 actions em **CollectionsList** (On Load, busca, chips, paginação) |
| **`zzzDeleteMeColp99Stub`** (antes `colp99`) | `colp99` | **Não** — duplicata criada por engano via MCP; pode apagar depois de limpar |

As duas ficaram com **YAML dentro do corpo** (entre `MODIFY CODE ONLY BELOW/ABOVE`). Isso quebra o compilador.

**Não apague `colp01`** — o projeto depende dela. **Apague só `zzzDeleteMeColp99Stub`** quando o editor permitir.

---

## Passo 1 — Corrigir `parseColetaItemsFromApi` (`colp01`) (obrigatório)

1. **Custom Code → Custom Functions → `parseColetaItemsFromApi`**
2. Na área de código, apague **somente** o que está **entre**:
   - `/// MODIFY CODE ONLY BELOW THIS LINE`
   - `/// MODIFY CODE ONLY ABOVE THIS LINE`  
   **Não** apague imports nem a assinatura `List<ColetaListItemStruct>? parseColetaItemsFromApi(...)`.
3. Entre essas linhas deve ficar **só Dart** — sem `identifier:`, sem `code:`, sem aspas de YAML.
4. Cole o bloco abaixo (com a indentação de 2 espaços).
5. **Save**.

```dart
  if (apiJson == null) {
    return [];
  }
  if (apiJson is! Map) {
    return [];
  }
  final root = Map<String, dynamic>.from(apiJson);
  final data = root['data'];
  if (data is! Map) {
    return [];
  }
  final items = data['items'];
  if (items is! List) {
    return [];
  }
  final out = <ColetaListItemStruct>[];
  for (final raw in items) {
    if (raw is! Map) {
      continue;
    }
    final m = Map<String, dynamic>.from(raw);
    final status = m['status']?.toString() ?? '';
    String statusLabel = status;
    if (status == 'rascunho') {
      statusLabel = 'Rascunho';
    } else if (status == 'finalizada') {
      statusLabel = 'Finalizada';
    } else if (status == 'cancelada') {
      statusLabel = 'Cancelada';
    }
    final mtrRaw = m['numero_mtr']?.toString() ?? '';
    final displayMtr = mtrRaw.isNotEmpty ? '#$mtrRaw' : '-';
    final dc = m['data_coleta']?.toString() ?? '';
    final hr = m['hora']?.toString() ?? '';
    var dataHora = '';
    if (dc.isNotEmpty && hr.isNotEmpty) {
      dataHora = '$dc $hr';
    } else if (dc.isNotEmpty) {
      dataHora = dc;
    } else {
      dataHora = hr;
    }
    final idVal = m['id'];
    final id = idVal is int ? idVal : int.tryParse(idVal?.toString() ?? '') ?? 0;
    out.add(
      ColetaListItemStruct(
        id: id,
        displayMtr: displayMtr,
        clienteNome: m['cliente_nome']?.toString() ?? '',
        statusLabel: statusLabel,
        dataHora: dataHora,
      ),
    );
  }
  return out;
```

Se o **Save** reclamar dos nomes do struct (`displayMtr`, etc.), troque só o `out.add(...)` pela variante com underscore no final deste doc (seção “Struct com underscore”).

---

## Passo 2 — Remover duplicata `zzzDeleteMeColp99Stub` (`colp99`)

1. **Custom Functions → `zzzDeleteMeColp99Stub`**
2. Menu (⋮) → **Delete**  
   - Se disser “in use”, primeiro limpe o corpo (entre MODIFY) e deixe só:
     ```dart
       return [];
     ```
     Save, e tente Delete de novo.
3. Se ainda não deixar apagar, pode deixar o stub com `return [];` — **nenhuma action usa `colp99`**.

---

## Struct com underscore (se `displayMtr` falhar)

Substitua o `out.add(...)` por:

```dart
    out.add(
      ColetaListItemStruct(
        id: id,
        display_mtr: displayMtr,
        cliente_nome: m['cliente_nome']?.toString() ?? '',
        status_label: statusLabel,
        data_hora: dataHora,
      ),
    );
```

---

## `formataData` (`3prfl`) — erro depois de corrigir `colp01`

O código da data em si costuma estar certo. O FlutterFlow acusa *Code has errors* quando:

1. **Alterou fora da zona permitida** — linhas de `import`, assinatura `String? formataData()` ou chaves `{ }` fora do bloco `MODIFY CODE ONLY BELOW/ABOVE`.
2. **Return Type no painel ≠ código** — no painel esquerdo use **String** (não nullable); o corpo sempre retorna texto.

**Corpo recomendado** (cole só entre as linhas MODIFY; não usa `DateFormat`):

```dart
  final now = DateTime.now();
  final d = now.day.toString().padLeft(2, '0');
  final m = now.month.toString().padLeft(2, '0');
  return '$d/$m/${now.year}';
```

A função **não está ligada** a nenhum widget no projeto hoje — se não for usar no Dashboard, pode **apagar** `formataData` em Custom Functions para zerar o Issue.

---

## Por que não dá para “excluir as duas”

- **`colp01`** está ligada em **Update Page State** na página **CollectionsList** (parse do JSON da API → `coletaItems`).
- Excluir `colp01` exigiria reconfigurar todas essas actions no editor.

---

## Referência

[Listagem CollectionsList](FLUTTERFLOW_COLLECTIONS_LIST.md)
