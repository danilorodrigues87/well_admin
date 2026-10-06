
  final out = <int>[];
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
    final id = (m['id'] as num?)?.toInt();
    if (id != null && id > 0) {
      out.add(id);
    }
  }
  return out;
