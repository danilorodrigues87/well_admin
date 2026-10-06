
  if (items == null || items.isEmpty) {
    return '';
  }
  final pts = <String>[];
  for (final item in items) {
    final lat = item.latitude;
    final lng = item.longitude;
    if (lat == 0.0 && lng == 0.0) {
      continue;
    }
    pts.add('$lat,$lng');
  }
  if (pts.isEmpty) {
    return '';
  }
  if (pts.length == 1) {
    return 'https://www.google.com/maps/dir/?api=1&destination=${pts.first}&travelmode=driving';
  }
  final origin = pts.first;
  final dest = pts.last;
  final mid = pts.length > 2 ? pts.sublist(1, pts.length - 1).join('|') : '';
  var url =
      'https://www.google.com/maps/dir/?api=1&origin=$origin&destination=$dest&travelmode=driving';
  if (mid.isNotEmpty) {
    url += '&waypoints=$mid';
  }
  return url;
