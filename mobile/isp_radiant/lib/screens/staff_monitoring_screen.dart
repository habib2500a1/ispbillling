import 'dart:async';

import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/layout.dart';
import '../widgets/page_scaffold.dart';
import '../widgets/state_views.dart';
import 'staff_customer_detail_screen.dart';

class StaffMonitoringScreen extends StatefulWidget {
  const StaffMonitoringScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<StaffMonitoringScreen> createState() => _StaffMonitoringScreenState();
}

class _StaffMonitoringScreenState extends State<StaffMonitoringScreen> {
  List<Map<String, dynamic>> _online = [];
  int _total = 0;
  bool _loading = true;
  Timer? _liveTimer;
  final List<FlSpot> _chartSpots = [];
  int _spotIndex = 0;
  String? _bandwidthLabel;

  @override
  void initState() {
    super.initState();
    _load();
    _liveTimer = Timer.periodic(const Duration(seconds: 1), (_) => _pollLive());
  }

  @override
  void dispose() {
    _liveTimer?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final body = await widget.api.staffOnlineClients();
      if (mounted) {
        setState(() {
          _total = (body['total_online'] as num?)?.toInt() ?? 0;
          _online = (body['data'] as List<dynamic>?)
                  ?.map((e) => Map<String, dynamic>.from(e as Map))
                  .toList() ??
              [];
        });
      }
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _pollLive() async {
    try {
      final snap = await widget.api.staffMonitoringLive();
      if (!mounted) return;
      final count = (snap['online_count'] as num?)?.toDouble() ?? 0;
      setState(() {
        _bandwidthLabel = snap['bandwidth_human']?.toString();
        _chartSpots.add(FlSpot(_spotIndex.toDouble(), count));
        if (_chartSpots.length > 60) _chartSpots.removeAt(0);
        _spotIndex++;
      });
    } catch (_) {}
  }

  String _formatSince(String? iso) {
    if (iso == null || iso.isEmpty) return '—';
    try {
      final dt = DateTime.parse(iso).toLocal();
      return DateFormat('dd MMM, HH:mm').format(dt);
    } catch (_) {
      return iso;
    }
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'Live monitoring',
      actions: [IconButton(icon: const Icon(Icons.refresh), onPressed: _load)],
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: pagePadding(context, top: 8),
                children: [
                  _liveHeader(),
                  const SizedBox(height: 12),
                  _liveChart(),
                  const SizedBox(height: 16),
                  Text('Online now ($_total)', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  if (_online.isEmpty)
                    const EmptyState(icon: Icons.wifi_off, title: 'No clients online', subtitle: 'Graph updates every second'),
                  ..._online.map(_clientCard),
                ],
              ),
            ),
    );
  }

  Widget _liveHeader() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [AppTheme.primary, Color(0xFF5B7DB8)]),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        children: [
          const Icon(Icons.sensors, color: Colors.white, size: 36),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('$_total online', style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.bold)),
                Text(
                  _bandwidthLabel != null ? 'Total traffic $_bandwidthLabel' : 'Updating every 1 second…',
                  style: const TextStyle(color: Colors.white70, fontSize: 12),
                ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(color: AppTheme.success, borderRadius: BorderRadius.circular(20)),
            child: const Text('LIVE', style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }

  Widget _liveChart() {
    if (_chartSpots.isEmpty) {
      return const SizedBox(height: 120, child: Center(child: Text('Collecting live data…')));
    }
    final maxY = _chartSpots.map((s) => s.y).fold<double>(0, (a, b) => a > b ? a : b) + 2;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Online users (last 60s)', style: TextStyle(fontWeight: FontWeight.w600)),
            const SizedBox(height: 8),
            SizedBox(
              height: 140,
              child: LineChart(
                LineChartData(
                  minY: 0,
                  maxY: maxY,
                  gridData: const FlGridData(show: false),
                  titlesData: const FlTitlesData(show: false),
                  borderData: FlBorderData(show: false),
                  lineBarsData: [
                    LineChartBarData(
                      spots: _chartSpots,
                      isCurved: true,
                      color: AppTheme.accent,
                      barWidth: 3,
                      dotData: const FlDotData(show: false),
                      belowBarData: BarAreaData(show: true, color: AppTheme.accent.withValues(alpha: 0.15)),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _clientCard(Map<String, dynamic> c) {
    final started = c['session_started']?.toString();
    final duration = c['online_duration']?.toString();

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: CircleAvatar(
          backgroundColor: AppTheme.success.withValues(alpha: 0.15),
          child: const Icon(Icons.wifi, color: AppTheme.success, size: 20),
        ),
        title: Text(c['name']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('${c['customer_code']} · ${c['package'] ?? ''}'),
            if (started != null && started.isNotEmpty)
              Text('Since ${_formatSince(started)}${duration != null ? ' · $duration' : ''}', style: const TextStyle(fontSize: 11)),
            if (c['download_human'] != null)
              Text('↓ ${c['download_human']} ↑ ${c['upload_human'] ?? '—'}', style: const TextStyle(fontSize: 11, color: AppTheme.primary)),
          ],
        ),
        isThreeLine: true,
        trailing: const Icon(Icons.chevron_right),
        onTap: () => Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => StaffCustomerDetailScreen(api: widget.api, customerId: (c['id'] as num).toInt()),
          ),
        ),
      ),
    );
  }
}
