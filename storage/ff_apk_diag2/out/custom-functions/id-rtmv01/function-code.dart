
  if (items == null || items.isEmpty) {
    return items;
  }
  final list = List<RotaParadaItemStruct>.from(items);
  final idx = list.indexWhere((e) => e.clienteId == clienteId);
  if (idx < 0) {
    return list;
  }
  final next = idx + delta;
  if (next < 0 || next >= list.length) {
    return list;
  }
  final item = list.removeAt(idx);
  list.insert(next, item);
  final out = <RotaParadaItemStruct>[];
  for (var i = 0; i < list.length; i++) {
    final e = list[i];
    out.add(
      RotaParadaItemStruct(
        clienteId: e.clienteId,
        ordem: i + 1,
        nomeFantasia: e.nomeFantasia,
        endereco: e.endereco,
        statusParada: e.statusParada,
        prioridade: e.prioridade,
        mapsUrl: e.mapsUrl,
        latitude: e.latitude,
        longitude: e.longitude,
      ),
    );
  }
  return out;