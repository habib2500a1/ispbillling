import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/theme/design_tokens.dart';
import '../design_system/components/radiant_glass_card.dart';
import '../design_system/components/radiant_kpi_tile.dart';
import '../design_system/components/radiant_screen_header.dart';
import '../design_system/components/radiant_section.dart';
import '../design_system/radiant_tokens.dart';
import '../core/widgets/states.dart';
import '../features/staff_monitoring/data/monitoring_repository.dart';
import '../services/api_service.dart';
import '../utils/layout.dart';
import '../widgets/live_bandwidth_chart.dart';
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
    final brand = context.radiant;

    return Scaffold(
      backgroundColor: context.isDark ? RadiantTokens.darkBg : RadiantTokens.lightBg,
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: RadiantTokens.brand))
          : RefreshIndicator(
              onRefresh: _load,
              color: RadiantTokens.brand,
              child: ListView(
                padding: EdgeInsets.zero,
                children: [
                  RadiantScreenHeader(
                    title: 'Live monitoring',
                    subtitle: 'Updates every second',
                    trailing: [
                      RadiantHeaderIcon(icon: Icons.refresh_rounded, onPressed: _load, tooltip: 'Refresh'),
                    ],
                    child: Row(
                      children: [
                        Expanded(
                          child: RadiantKpiTile(
                            compact: true,
                            label: 'Online now',
                            value: '$_total',
                            icon: Icons.sensors_rounded,
                            color: RadiantTokens.success,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: RadiantKpiTile(
                            compact: true,
                            label: 'Per second',
                            value: _downloadLabel != null ? '↓ $_downloadLabel' : '—',
                            icon: Icons.speed_rounded,
                            color: RadiantTokens.brand,
                            trend: _uploadLabel != null ? '↑ $_uploadLabel' : null,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Padding(
                    padding: pagePadding(context, top: 8),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        RadiantGlassCard(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      'All users — Mbps per second',
                                      style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                                    ),
                                  ),
                                  const RadiantStatusChip(
                                    label: 'LIVE',
                                    color: RadiantTokens.success,
                                    icon: Icons.fiber_manual_record,
                                  ),
                                ],
                              ),
                              if (_bandwidthLabel != null && _downloadLabel == null)
                                Padding(
                                  padding: const EdgeInsets.only(top: 4),
                                  child: Text('Total $_bandwidthLabel', style: context.text.bodySmall?.copyWith(color: brand.muted)),
                                ),
                              const SizedBox(height: 12),
                              LiveBandwidthChart(chart: _chartData),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        RadiantSectionHeader(title: 'Online now ($_total)'),
                        if (_online.isEmpty)
                          const EmptyStateView(
                            icon: Icons.wifi_off_rounded,
                            title: 'No clients online',
                            message: 'Graph updates every second',
                          )
                        else
                          ..._online.map(_clientCard),
                      ],
                    ),
                  ),
                ],
              ),
            ),
    );
  }

  Widget _clientCard(OnlineClient c) {
    final brand = context.radiant;

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: RadiantGlassCard(
        onTap: () => Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => StaffCustomerDetailScreen(api: widget.api, customerId: c.id),
          ),
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: RadiantTokens.success.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
              ),
              child: const Icon(Icons.wifi_rounded, color: RadiantTokens.success, size: 20),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(c.name, style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
                  Text(
                    '${c.customerCode}${c.package.isNotEmpty ? ' · ${c.package}' : ''}',
                    style: context.text.bodySmall?.copyWith(color: brand.muted),
                  ),
                  if (c.sessionStarted.isNotEmpty)
                    Text(
                      'Since ${_formatSince(c.sessionStarted)}${c.onlineDuration.isNotEmpty ? ' · ${c.onlineDuration}' : ''}',
                      style: context.text.labelSmall?.copyWith(color: brand.muted),
                    ),
                  if (c.downloadHuman.isNotEmpty)
                    Text(
                      '↓ ${c.downloadHuman} ↑ ${c.uploadHuman.isNotEmpty ? c.uploadHuman : '—'}',
                      style: context.text.labelSmall?.copyWith(color: RadiantTokens.brand, fontWeight: FontWeight.w600),
                    ),
                ],
              ),
            ),
            Icon(Icons.chevron_right_rounded, color: brand.muted),
          ],
        ),
      ),
    );
  }
}
