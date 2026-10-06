
  if (items == null) {
    return <LatLng>[];
  }
  final out = <LatLng>[];
  for (final item in items) {
    final lat = item.latitude;
    final lng = item.longitude;
    if (lat == 0.0 && lng == 0.0) {
      continue;
    }
    out.add(LatLng(lat, lng));
  }
  return out;
