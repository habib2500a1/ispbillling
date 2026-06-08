import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../services/api_service.dart';
import '../utils/app_nav.dart';
import '../widgets/page_scaffold.dart';

/// In-app GIS map using OpenStreetMap tiles (Phase 2).
class StaffGisMapScreen extends StatefulWidget {
  const StaffGisMapScreen({super.key, required this.api, this.customerId, this.focusLat, this.focusLng});

  final ApiService api;
  final int? customerId;
  final double? focusLat;
  final double? focusLng;

  @override
  State<StaffGisMapScreen> createState() => _StaffGisMapScreenState();
}

class _StaffGisMapScreenState extends State<StaffGisMapScreen> {
  final _mapController = MapController();
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _nodes = [];
  List<Polyline> _polylines = [];
  LatLng _center = const LatLng(23.8103, 90.4125);

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final body = await widget.api.staffGisMap(customerId: widget.customerId);
      final payload = body['payload'] as Map<String, dynamic>? ?? {};
      final nodes = (payload['nodes'] as List<dynamic>? ?? [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
      final edges = (payload['edges'] as List<dynamic>? ?? [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
      final center = payload['center'] as Map?;

      final markers = <Marker>[];
      for (final n in nodes) {
        final lat = (n['lat'] as num?)?.toDouble() ?? (n['latitude'] as num?)?.toDouble();
        final lng = (n['lng'] as num?)?.toDouble() ?? (n['longitude'] as num?)?.toDouble();
        if (lat == null || lng == null) continue;
        markers.add(
          Marker(
            point: LatLng(lat, lng),
            width: 36,
            height: 36,
            child: Icon(_iconForType(n['type']?.toString() ?? ''), color: _colorForType(n['type']?.toString() ?? '')),
          ),
        );
      }

      final lines = <Polyline>[];
      for (final e in edges) {
        final from = e['from'] as List?;
        final to = e['to'] as List?;
        if (from == null || to == null || from.length < 2 || to.length < 2) continue;
        lines.add(
          Polyline(
            points: [
              LatLng((from[0] as num).toDouble(), (from[1] as num).toDouble()),
              LatLng((to[0] as num).toDouble(), (to[1] as num).toDouble()),
            ],
            color: Colors.deepOrange.withValues(alpha: 0.85),
            strokeWidth: 3,
          ),
        );
      }

      LatLng centerPoint = _center;
      if (widget.focusLat != null && widget.focusLng != null) {
        centerPoint = LatLng(widget.focusLat!, widget.focusLng!);
      } else if (center != null && center['lat'] != null && center['lng'] != null) {
        centerPoint = LatLng((center['lat'] as num).toDouble(), (center['lng'] as num).toDouble());
      } else if (markers.isNotEmpty) {
        centerPoint = markers.first.point;
      }

      if (mounted) {
        setState(() {
          _nodes = nodes;
          _markers = markers;
          _polylines = lines;
          _center = centerPoint;
          _loading = false;
        });
        _mapController.move(centerPoint, widget.customerId != null ? 15 : 12);
      }
    } on ApiException catch (e) {
      if (mounted) setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (_) {
      if (mounted) setState(() {
        _error = 'Could not load map';
        _loading = false;
      });
    }
  }

  List<Marker> _markers = [];

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'Network map',
      useGradientBody: true,
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : Stack(
                  children: [
                    FlutterMap(
                      mapController: _mapController,
                      options: MapOptions(initialCenter: _center, initialZoom: 12, minZoom: 5, maxZoom: 18),
                      children: [
                        TileLayer(
                          urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                          userAgentPackageName: 'com.isp.radiant',
                        ),
                        PolylineLayer(polylines: _polylines),
                        MarkerLayer(markers: _markers),
                      ],
                    ),
                    Positioned(
                      left: 12,
                      bottom: 12,
                      child: Card(
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                          child: Text('${_nodes.length} nodes · OSM', style: const TextStyle(fontSize: 12)),
                        ),
                      ),
                    ),
                    Positioned(
                      right: 12,
                      bottom: 12,
                      child: FloatingActionButton.small(
                        onPressed: _load,
                        child: const Icon(Icons.refresh),
                      ),
                    ),
                  ],
                ),
    );
  }

  IconData _iconForType(String type) {
    switch (type) {
      case 'olt':
        return Icons.dns;
      case 'onu':
        return Icons.router;
      case 'splitter':
        return Icons.call_split;
      case 'customer':
        return Icons.home;
      default:
        return Icons.place;
    }
  }

  Color _colorForType(String type) {
    switch (type) {
      case 'olt':
        return Colors.indigo;
      case 'onu':
        return Colors.teal;
      case 'customer':
        return Colors.green;
      default:
        return Colors.orange;
    }
  }
}
