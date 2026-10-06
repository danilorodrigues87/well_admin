import 'dart:math' as math;

import 'package:google_maps_flutter/google_maps_flutter.dart' as gmaps;

/// Mapa da rota com pinos, polyline e caminhao do coletor
class RotaMapPolyline extends StatefulWidget {
  const RotaMapPolyline({
    super.key,
    this.width,
    this.height,
    this.markers,
    this.encodedPolyline,
    this.collectorLat,
    this.collectorLng,
  });

  final double? width;
  final double? height;
  final List<LatLng>? markers;
  final String? encodedPolyline;
  final double? collectorLat;
  final double? collectorLng;

  @override
  State<RotaMapPolyline> createState() => _RotaMapPolylineState();
}

class _RotaMapPolylineState extends State<RotaMapPolyline> {
  gmaps.GoogleMapController? _controller;
  Set<gmaps.Marker> _markers = {};
  Set<gmaps.Polyline> _polylines = {};
  gmaps.LatLng _center = const gmaps.LatLng(-24.9555, -53.4552);
  String _lastPolyline = '';

  @override
  void initState() {
    super.initState();
    _rebuildOverlays(fit: true);
  }

  @override
  void didUpdateWidget(covariant RotaMapPolyline oldWidget) {
    super.didUpdateWidget(oldWidget);
    final markersChanged = oldWidget.markers != widget.markers;
    final polyChanged = oldWidget.encodedPolyline != widget.encodedPolyline;
    final truckChanged = oldWidget.collectorLat != widget.collectorLat ||
        oldWidget.collectorLng != widget.collectorLng;
    if (markersChanged || polyChanged || truckChanged) {
      _rebuildOverlays(fit: markersChanged || polyChanged);
    }
  }

  List<gmaps.LatLng> _decodePolyline(String encoded) {
    final List<gmaps.LatLng> points = [];
    if (encoded.isEmpty) {
      return points;
    }
    var index = 0;
    var lat = 0;
    var lng = 0;
    while (index < encoded.length) {
      var shift = 0;
      var result = 0;
      int b;
      do {
        b = encoded.codeUnitAt(index++) - 63;
        result |= (b & 0x1f) << shift;
        shift += 5;
      } while (b >= 0x20);
      final dlat = ((result & 1) != 0) ? ~(result >> 1) : (result >> 1);
      lat += dlat;
      shift = 0;
      result = 0;
      do {
        b = encoded.codeUnitAt(index++) - 63;
        result |= (b & 0x1f) << shift;
        shift += 5;
      } while (b >= 0x20);
      final dlng = ((result & 1) != 0) ? ~(result >> 1) : (result >> 1);
      lng += dlng;
      points.add(gmaps.LatLng(lat / 1e5, lng / 1e5));
    }
    return points;
  }

  Future<void> _rebuildOverlays({required bool fit}) async {
    final pts = widget.markers ?? <LatLng>[];
    final markers = <gmaps.Marker>{};
    for (var i = 0; i < pts.length; i++) {
      final p = pts[i];
      final hue = (i == 0)
          ? gmaps.BitmapDescriptor.hueGreen
          : (i == pts.length - 1)
              ? gmaps.BitmapDescriptor.hueOrange
              : gmaps.BitmapDescriptor.hueViolet;
      markers.add(
        gmaps.Marker(
          markerId: gmaps.MarkerId('stop_$i'),
          position: gmaps.LatLng(p.latitude, p.longitude),
          infoWindow: gmaps.InfoWindow(title: '${i + 1}'),
          icon: gmaps.BitmapDescriptor.defaultMarkerWithHue(hue),
          zIndexInt: i,
        ),
      );
    }

    final clat = widget.collectorLat;
    final clng = widget.collectorLng;
    if (clat != null && clng != null && !(clat == 0.0 && clng == 0.0)) {
      markers.add(
        gmaps.Marker(
          markerId: const gmaps.MarkerId('truck'),
          position: gmaps.LatLng(clat, clng),
          infoWindow: const gmaps.InfoWindow(title: 'Coletor'),
          icon: gmaps.BitmapDescriptor.defaultMarkerWithHue(
            gmaps.BitmapDescriptor.hueAzure,
          ),
          zIndexInt: 999,
          flat: true,
          anchor: const Offset(0.5, 0.5),
        ),
      );
    }

    final polyRaw = (widget.encodedPolyline ?? '').trim();
    List<gmaps.LatLng> path;
    if (polyRaw == _lastPolyline && _polylines.isNotEmpty) {
      path = _polylines.first.points;
    } else {
      path = _decodePolyline(polyRaw);
      _lastPolyline = polyRaw;
    }
    final polylines = <gmaps.Polyline>{};
    if (path.length >= 2) {
      polylines.add(
        gmaps.Polyline(
          polylineId: const gmaps.PolylineId('route'),
          points: path,
          color: const Color(0xFF198754),
          width: 5,
        ),
      );
    }
    if (pts.isNotEmpty) {
      _center = gmaps.LatLng(pts.first.latitude, pts.first.longitude);
    } else if (clat != null && clng != null) {
      _center = gmaps.LatLng(clat, clng);
    } else if (path.isNotEmpty) {
      _center = path.first;
    }
    if (!mounted) {
      return;
    }
    setState(() {
      _markers = markers;
      _polylines = polylines;
    });
    if (fit) {
      await _fitBounds();
    }
  }

  Future<void> _fitBounds() async {
    final c = _controller;
    if (c == null) {
      return;
    }
    final pts = <gmaps.LatLng>[];
    for (final m in _markers) {
      pts.add(m.position);
    }
    if (_polylines.isNotEmpty) {
      pts.addAll(_polylines.first.points);
    }
    if (pts.isEmpty) {
      return;
    }
    if (pts.length == 1) {
      await c.animateCamera(gmaps.CameraUpdate.newLatLngZoom(pts.first, 14));
      return;
    }
    var swLat = pts.first.latitude;
    var swLng = pts.first.longitude;
    var neLat = pts.first.latitude;
    var neLng = pts.first.longitude;
    for (final p in pts) {
      swLat = math.min(swLat, p.latitude);
      swLng = math.min(swLng, p.longitude);
      neLat = math.max(neLat, p.latitude);
      neLng = math.max(neLng, p.longitude);
    }
    await c.animateCamera(
      gmaps.CameraUpdate.newLatLngBounds(
        gmaps.LatLngBounds(
          southwest: gmaps.LatLng(swLat, swLng),
          northeast: gmaps.LatLng(neLat, neLng),
        ),
        56,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: widget.width,
      height: widget.height ?? 240,
      child: gmaps.GoogleMap(
        initialCameraPosition: gmaps.CameraPosition(target: _center, zoom: 12),
        markers: _markers,
        polylines: _polylines,
        myLocationEnabled: false,
        myLocationButtonEnabled: false,
        zoomControlsEnabled: false,
        mapToolbarEnabled: false,
        compassEnabled: false,
        onMapCreated: (controller) async {
          _controller = controller;
          await Future<void>.delayed(const Duration(milliseconds: 280));
          await _fitBounds();
        },
      ),
    );
  }
}