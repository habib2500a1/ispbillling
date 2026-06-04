import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/theme/design_tokens.dart';
import '../core/widgets/cards.dart';
import '../core/widgets/states.dart';
import '../features/staff_monitoring/data/monitoring_repository.dart';
import '../services/api_service.dart';
import '../utils/layout.dart';
import '../widgets/live_bandwidth_chart.dart';
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
  late final MonitoringRepository _repo = MonitoringRepository(widget.api);
  List<OnlineClient> _online = [];
  int _total = 0;
  bool _loading = true;
  Timer? _liveTimer;
  Map<String, dynamic>? _chartData;
  String? _bandwidthLabel;
  String? _downloadLabel;
  String? _uploadLabel;

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
    final res = await _repo.onlineClients();
    if (!mounted) return;
    res.when(
      ok: (page) => setState(() {
        _total = page.totalOnline;
        _online = page.clients;
        _loading = false;
      }),
      err: (_) => setState(() => _loading = false),
    );
  }

  Future<void> _pollLive() async {
    final snap = await _repo.live();
    if (snap == null || !mounted) return;
    setState(() {
      _total = snap.onlineCount ?? _total;
      _bandwidthLabel = snap.bandwidthHuman;
      _downloadLabel = snap.downloadHuman;
      _uploadLabel = snap.uploadHuman;
      _chartData = snap.chart;
    });
  }

  String _formatSince(String iso) {
    if (iso.isEmpty) return '—';
    try {
      return DateFormat('dd MMM, HH:mm').format(DateTime.parse(iso).toLocal());
    } catch (_) {
      return iso;
    }
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'Live monitoring',
      useGradientBody: true,
      actions: [IconButton(icon: const Icon(Icons.refresh), onPressed: _load)],
      body: _loading
          ? const ListLoading()
          : RefreshIndicator(
              onRefresh: _load,
              color: DesignTokens.primary,
              child: ListView(
                padding: pagePadding(context, top: 8),
                children: [
                  _liveHeader(),
                  const SizedBox(height: 12),
                  AppCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('All users — Mbps per second',
                            style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
                        const SizedBox(height: 8),
                        LiveBandwidthChart(chart: _chartData),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                  SectionHeader(title: 'Online now ($_total)'),
                  if (_online.isEmpty)
                    const EmptyStateView(
                        icon: Icons.wifi_off_rounded,
                        title: 'No clients online',
                        message: 'Graph updates every second'),
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
        gradient: LinearGradient(
          colors: context.brand.heroGradient,
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(DesignTokens.radius),
      ),
      child: Row(
        children: [
          const Icon(Icons.sensors_rounded, color: Colors.white, size: 36),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('$_total online',
                    style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.bold)),
                Text(
                  _downloadLabel != null
                      ? '↓ $_downloadLabel · ↑ ${_uploadLabel ?? '—'} (per sec)'
                      : (_bandwidthLabel != null ? 'Total $_bandwidthLabel' : 'Updating every 1 second…'),
                  style: const TextStyle(color: Colors.white70, fontSize: 12),
                ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(color: DesignTokens.success, borderRadius: BorderRadius.circular(20)),
            child: const Text('LIVE',
                style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }

  Widget _clientCard(OnlineClient c) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: AppCard(
        onTap: () => Navigator.push(
          context,
          MaterialPageRoute(
              builder: (_) => StaffCustomerDetailScreen(api: widget.api, customerId: c.id)),
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(9),
              decoration: BoxDecoration(
                  color: DesignTokens.success.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(DesignTokens.radiusSm)),
              child: const Icon(Icons.wifi_rounded, color: DesignTokens.success, size: 20),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(c.name, style: const TextStyle(fontWeight: FontWeight.w700)),
                  Text('${c.customerCode}${c.package.isNotEmpty ? ' · ${c.package}' : ''}',
                      style: TextStyle(fontSize: 12, color: context.brand.textMuted)),
                  if (c.sessionStarted.isNotEmpty)
                    Text(
                      'Since ${_formatSince(c.sessionStarted)}${c.onlineDuration.isNotEmpty ? ' · ${c.onlineDuration}' : ''}',
                      style: TextStyle(fontSize: 11, color: context.brand.textMuted),
                    ),
                  if (c.downloadHuman.isNotEmpty)
                    Text('↓ ${c.downloadHuman} ↑ ${c.uploadHuman.isNotEmpty ? c.uploadHuman : '—'}',
                        style: const TextStyle(fontSize: 11, color: DesignTokens.primary)),
                ],
              ),
            ),
            Icon(Icons.chevron_right_rounded, color: context.brand.textMuted),
          ],
        ),
      ),
    );
  }
}
