
  double _toD(dynamic v) {
    if (v == null) return 0.0;
    if (v is num) return v.toDouble();
    return double.tryParse(v.toString().replaceAll(',', '.')) ?? 0.0;
  }

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
  final items = data['paradas'];
  if (items is! List) {
    return [];
  }
  final out = <RotaParadaItemStruct>[];
  for (final raw in items) {
    if (raw is! Map) {
      continue;
    }
    final m = Map<String, dynamic>.from(raw);
    out.add(
      RotaParadaItemStruct(
        clienteId: (m['cliente_id'] as num?)?.toInt() ?? int.tryParse('${m['cliente_id']}') ?? 0,
        ordem: (m['ordem'] as num?)?.toInt() ?? int.tryParse('${m['ordem']}') ?? 0,
        nomeFantasia: m['nome_fantasia']?.toString() ?? m['nome']?.toString() ?? '',
        endereco: m['endereco']?.toString() ?? '',
        statusParada: m['status_parada']?.toString() ?? 'pendente',
        prioridade: m['prioridade']?.toString() ?? '',
        mapsUrl: m['maps_url']?.toString() ?? '',
        latitude: _toD(m['latitude'] ?? m['lat']),
        longitude: _toD(m['longitude'] ?? m['lng']),
      ),
    );
  }
  return out;