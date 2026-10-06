
  if (apiJson == null) {
    return 0;
  }
  if (apiJson is! Map) {
    return 0;
  }
  final root = Map<String, dynamic>.from(apiJson);
  final data = root['data'];
  if (data is! Map) {
    return 0;
  }
  final items = data['rotas'];
  if (items is! List || items.isEmpty) {
    return 0;
  }
  for (final raw in items) {
    if (raw is! Map) {
      continue;
    }
    final m = Map<String, dynamic>.from(raw);
    final id = (m['id'] as num?)?.toInt();
    if (id != null && id > 0) {
      return id;
    }
  }
  return 0;
