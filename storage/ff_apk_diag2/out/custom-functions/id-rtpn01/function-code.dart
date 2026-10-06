
  final out = <String>[];
  if (apiJson == null) {
    return out;
  }
  if (apiJson is! Map) {
    return out;
  }
  final root = Map<String, dynamic>.from(apiJson);
  final data = root['data'];
  if (data is! Map) {
    return out;
  }
  final items = data['rotas'];
  if (items is! List) {
    return out;
  }
  for (final raw in items) {
    if (raw is! Map) {
      continue;
    }
    final m = Map<String, dynamic>.from(raw);
    final nome = m['nome']?.toString() ?? '';
    final qtd = m['paradas'];
    if (nome.isEmpty) {
      continue;
    }
    if (qtd != null) {
      out.add('$nome ($qtd)');
    } else {
      out.add(nome);
    }
  }
  return out;
