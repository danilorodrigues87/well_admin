
  if (items == null || items.isEmpty) {
    return '';
  }
  if (apiKey.trim().isEmpty) {
    return '';
  }
  final markers = <String>[];
  var n = 1;
  for (final item in items) {
    final lat = item.latitude;
    final lng = item.longitude;
    if (lat == 0.0 && lng == 0.0) {
      continue;
    }
    final label = n <= 9 ? '$n' : '';
    markers.add(
      'markers=${Uri.encodeQueryComponent('color:0x7C3AED|label:$label|$lat,$lng')}',
    );
    n++;
  }
  if (markers.isEmpty) {
    return '';
  }
  final parts = <String>[
    'size=640x240',
    'scale=2',
    'maptype=roadmap',
    'language=pt-BR',
    ...markers,
  ];
  final poly = (encodedPolyline ?? '').trim();
  if (poly.isNotEmpty) {
    parts.add('path=${Uri.encodeQueryComponent('weight:4|color:0x198754ff|enc:$poly')}');
  }
  parts.add('key=${Uri.encodeQueryComponent(apiKey.trim())}');
  return 'https://maps.googleapis.com/maps/api/staticmap?${parts.join('&')}';