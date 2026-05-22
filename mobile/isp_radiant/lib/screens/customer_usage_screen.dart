import 'dart:async';

import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/layout.dart';
import '../widgets/isp_tab_screen.dart';
import '../widgets/isp_ui_kit.dart';
import '../widgets/usage_area_chart.dart';

class CustomerUsageScreen extends StatefulWidget {
  const CustomerUsageScreen({
    super.key,
    required this.api,
    this.active = false,
  });

  final ApiService api;
  final bool active;

  @override
  State<CustomerUsageScreen> createState() => _CustomerUsageScreenState();
}

class _CustomerUsageScreenState extends State<CustomerUsageScreen> {
  Map<String, dynamic>? _usage;
  bool _loading = true;
  String? _error;
  Timer? _liveTimer;

  @override
  void initState() {
    super.initState();
    _load();
    _liveTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (widget.active) _pollLive();
    });
  }

  @override
  void dispose() {
    _liveTimer?.cancel();
    super.dispose();
  }

  @override
  void didUpdateWidget(CustomerUsageScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active && !oldWidget.active) {
      _load();
      _pollLive();
    }
  }

  Future<void> _pollLive() async {
    try {
      final body = await widget.api.customerUsageLive();
      if (mounted) setState(() => _usage = body['usage'] as Map<String, dynamic>? ?? body);
    } catch (_) {}
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final body = await widget.api.customerUsageLive();
      if (mounted) {
        setState(() => _usage = body['usage'] as Map<String, dynamic>? ?? body);
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not load live usage');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final u = _usage ?? {};
    final online = u['online'] == true;

    return IspTabScreen(
      title: 'Live usage',
      subtitle: online ? 'Connected' : 'Offline',
      loading: _loading,
      error: _error,
      onRetry: _load,
      onRefresh: _load,
      child: ListView(
        padding: pagePadding(context, top: 10),
        children: [
          Container(
            decoration: IspUiKit.cardDecoration(
              tint: online ? const Color(0xFFECFDF5) : const Color(0xFFF1F5F9),
            ),
            padding: const EdgeInsets.all(20),
            child: Column(
              children: [
                Icon(
                  online ? Icons.wifi_tethering : Icons.wifi_off,
                  size: 56,
                  color: online ? AppTheme.success : const Color(0xFF94A3B8),
                ),
                const SizedBox(height: 10),
                Text(
                  online ? 'Online' : 'Offline',
                  style: TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.bold,
                    color: online ? AppTheme.success : const Color(0xFF64748B),
                  ),
                ),
                if (u['framed_ip'] != null)
                  Text('IP ${u['framed_ip']}', style: const TextStyle(color: Color(0xFF64748B), fontSize: 12)),
                if (online && u['connection_duration'] != null)
                  Text('Uptime: ${u['connection_duration']}', style: const TextStyle(color: Color(0xFF64748B), fontSize: 12)),
              ],
            ),
          ),
          IspUiKit.sectionTitle('Live graph'),
          Container(
            decoration: IspUiKit.cardDecoration(),
            padding: const EdgeInsets.all(12),
            child: UsageAreaChart(chart: u['chart'] as Map<String, dynamic>?),
          ),
          IspUiKit.sectionTitle('Speed'),
          _metricCard('Download', u['download_human']?.toString() ?? '—', Icons.download_rounded, AppTheme.primary),
          _metricCard('Upload', u['upload_human']?.toString() ?? '—', Icons.upload_rounded, AppTheme.teal),
          IspUiKit.sectionTitle('Today'),
          _metricCard('Download today', '${u['today_download'] ?? '—'}', Icons.arrow_downward),
          _metricCard('Upload today', '${u['today_upload'] ?? '—'}', Icons.arrow_upward),
          const SizedBox(height: 8),
          const Text(
            'Updates every second while online',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8)),
          ),
        ],
      ),
    );
  }

  Widget _metricCard(String label, String value, IconData icon, [Color? color]) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Material(
        color: AppTheme.card,
        borderRadius: BorderRadius.circular(14),
        child: ListTile(
          leading: Icon(icon, color: color ?? AppTheme.primary),
          title: Text(label, style: const TextStyle(fontSize: 13, color: Color(0xFF64748B))),
          trailing: Text(value, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
        ),
      ),
    );
  }
}
