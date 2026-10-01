  if (apiJson == null) {
    return [];
  }
  Map<String, dynamic> root;
  if (apiJson is String) {
    root = jsonDecode(apiJson) as Map<String, dynamic>;
  } else if (apiJson is Map) {
    root = Map<String, dynamic>.from(apiJson);
  } else {
    return [];
  }
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
    String statusLabel;
    switch (status) {
      case 'rascunho':
        statusLabel = 'Rascunho';
        break;
      case 'finalizada':
        statusLabel = 'Finalizada';
        break;
      case 'cancelada':
        statusLabel = 'Cancelada';
        break;
      default:
        statusLabel = status;
    }
    final mtr = m['numero_mtr'];
    final displayMtr =
        (mtr != null && mtr.toString().isNotEmpty) ? '#${mtr.toString()}' : '-';
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
    out.add(
      ColetaListItemStruct(
        id: m['id'] is int ? m['id'] as int : int.tryParse('${m['id']}') ?? 0,
        displayMtr: displayMtr,
        clienteNome: m['cliente_nome']?.toString() ?? '',
        statusLabel: statusLabel,
        dataHora: dataHora,
      ),
    );
  }
  return out;
