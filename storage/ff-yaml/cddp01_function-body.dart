
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
  final items = data['itens'];
  if (items is! List) {
    return [];
  }
  final out = <ColetaResiduoItemStruct>[];
  for (final raw in items) {
    if (raw is! Map) {
      continue;
    }
    final m = Map<String, dynamic>.from(raw);
    final nome = m['nome_label']?.toString() ?? m['nome']?.toString() ?? '';
    final detalhe = m['classe_nome']?.toString() ?? '';
    final quantidade = m['quantidade_label']?.toString() ?? '';
    out.add(
      ColetaResiduoItemStruct(
        nome: nome,
        detalhe: detalhe,
        quantidade: quantidade,
      ),
    );
  }
  return out;
