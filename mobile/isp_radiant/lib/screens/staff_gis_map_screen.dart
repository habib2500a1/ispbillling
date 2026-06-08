import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';

import '../design_system/components/radiant_glass_card.dart';
import '../design_system/radiant_tokens.dart';
import '../services/api_service.dart';

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
        final type = n['type']?.toString() ?? '';
        markers.add(
          Marker(
            point: LatLng(lat, lng),
            width: 40,
            height: 48,
            child: _GisMarkerPin(
              icon: _iconForType(type),
              color: _colorForType(type),
              label: n['label']?.toString(),
            ),
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
            color: RadiantTokens.accent.withValues(alpha: 0.85),
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
    return Scaffold(
      backgroundColor: context.isDark ? RadiantTokens.darkBg : RadiantTokens.lightBg,
      appBar: AppBar(
        title: const Text('Network map'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _load),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: RadiantTokens.brand))
          : _error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text(_error!, textAlign: TextAlign.center),
                  ),
                )
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
                      top: 12,
                      right: 12,
                      child: RadiantGlassCard(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                        child: Row(
                          children: [
                            const Icon(Icons.hub_outlined, size: 18, color: RadiantTokens.brand),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                '${_nodes.length} nodes · OpenStreetMap',
                                style: Theme.of(context).textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w600),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    Positioned(
                      left: 12,
                      bottom: 12,
                      child: RadiantGlassCard(
                        padding: const EdgeInsets.all(10),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            _legendRow(Icons.dns_rounded, 'OLT', RadiantTokens.brand),
                            _legendRow(Icons.router_rounded, 'ONU', RadiantTokens.accentCyan),
                            _legendRow(Icons.home_rounded, 'Customer', RadiantTokens.success),
                            _legendRow(Icons.call_split_rounded, 'Splitter', RadiantTokens.warning),
                          ],
                        ),
                      ),
                    ),
                    Positioned(
                      right: 12,
                      bottom: 12,
                      child: FloatingActionButton.small(
                        backgroundColor: RadiantTokens.brand,
                        foregroundColor: Colors.white,
                        onPressed: _load,
                        child: const Icon(Icons.refresh),
                      ),
                    ),
                  ],
                ),
    );
  }

  Widget _legendRow(IconData icon, String label, Color color) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: color),
          const SizedBox(width: 8),
          Text(label, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }

  IconData _iconForType(String type) {
    switch (type) {
      case 'olt':
        return Icons.dns_rounded;
      case 'onu':
        return Icons.router_rounded;
      case 'splitter':
        return Icons.call_split_rounded;
      case 'customer':
        return Icons.home_rounded;
      default:
        return Icons.place_rounded;
    }
  }

  Color _colorForType(String type) {
    switch (type) {
      case 'olt':
        return RadiantTokens.brand;
      case 'onu':
        return RadiantTokens.accentCyan;
      case 'customer':
        return RadiantTokens.success;
      default:
        return RadiantTokens.warning;
    }
  }
}

class _GisMarkerPin extends StatelessWidget {
  const _GisMarkerPin({required this.icon, required this.color, this.label});

  final IconData icon;
  final Color color;
  final String? label;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (label != null && label!.isNotEmpty)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
            margin: const EdgeInsets.only(bottom: 4),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.92),
              borderRadius: BorderRadius.circular(6),
              border: Border.all(color: color.withValues(alpha: 0.4)),
            ),
            child: Text(label!, style: TextStyle(fontSize: 9, fontWeight: FontWeight.w700, color: color)),
          ),
        Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(
            color: color,
            shape: BoxShape.circle,
            border: Border.all(color: Colors.white, width: 2),
            boxShadow: [
              BoxShadow(color: color.withValues(alpha: 0.45), blurRadius: 8, offset: const Offset(0, 3)),
            ],
          ),
          child: Icon(icon, color: Colors.white, size: 18),
        ),
        CustomPaint(size: const Size(12, 8), painter: _PinTailPainter(color)),
      ],
    );
  }
}

class _PinTailPainter extends CustomPainter {
  _PinTailPainter(this.color);

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..color = color;
    final path = ui.Path()
      ..moveTo(size.width / 2, size.height)
      ..lineTo(0, 0)
      ..lineTo(size.width, 0)
      ..close();
    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
