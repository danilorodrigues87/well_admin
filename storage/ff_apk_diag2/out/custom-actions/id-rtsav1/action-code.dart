import 'dart:convert';

import 'package:http/http.dart' as http;

Future<bool> salvarRotaOrdem(
  String baseUrl,
  String authToken,
  String data,
  int coletorId,
  List<RotaParadaItemStruct>? items,
) async {
  if (items == null || items.isEmpty) {
    return false;
  }
  try {
    final base = baseUrl.replaceAll(RegExp(r'/+$'), '');
    final uri = Uri.parse(
      '$base/rota-do-dia/salvar-ordem?data=${Uri.encodeQueryComponent(data)}&coletor_id=$coletorId',
    );
    final ordem = <Map<String, int>>[];
    for (var i = 0; i < items.length; i++) {
      ordem.add({'cliente_id': items[i].clienteId, 'ordem': i + 1});
    }
    final response = await http.post(
      uri,
      headers: {
        'Authorization': 'Bearer $authToken',
        'Content-Type': 'application/json',
      },
      body: jsonEncode({'ordem': ordem}),
    );
    return response.statusCode >= 200 && response.statusCode < 300;
  } catch (_) {
    return false;
  }
}