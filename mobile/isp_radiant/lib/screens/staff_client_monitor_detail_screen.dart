import 'dart:async';

import 'package:flutter/material.dart';

import '../design_system/radiant_tokens.dart';
import '../widgets/legacy_softify_screen_header.dart';
import '../features/staff_monitoring/data/monitoring_repository.dart';
import '../services/api_service.dart';
import '../widgets/usage_area_chart.dart';

/// Legacy SOFTIFY per-subscriber "Monitoring" — ONU card, profile, live traffic graph.
class StaffClientMonitorDetailScreen extends StatefulWidget {
  const StaffClientMonitorDetailScreen({
    super.key,
    required this.api,
    required this.customerId,
    this.preview,
  });

  final ApiService api;
  final int customerId;
  final ClientMonitorRow? preview;

  @override
  State<StaffClientMonitorDetailScreen> createState() => _StaffClientMonitorDetailScreenState();
}

class _StaffClientMonitorDetailScreenState extends State<StaffClientMonitorDetailScreen> {
  Map<String, dynamic>? _customer;
  Map<String, dynamic>? _usage;
  bool _loading = true;
  Timer? _pollTimer;

  @override
  void initState() {
    super.initState();
    _load();
    _pollTimer = Timer.periodic(const Duration(seconds: 1), (_) => _pollUsage());
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final detail = await widget.api.staffCustomerDetail(widget.customerId);
      if (mounted) setState(() => _customer = detail);
      await _pollUsage();
    } catch (_) {
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _pollUsage() async {
    try {
      final body = await widget.api.staffCustomerUsageLive(widget.customerId);
      if (mounted) setState(() => _usage = body);
    } catch (_) {}
  }

  String _s(dynamic v, [String f = '—']) {
    final s = v?.toString();
    return (s == null || s.isEmpty) ? f : s;
  }

  @override
  Widget build(BuildContext context) {
    final preview = widget.preview;
    final customer = _customer;
    final usage = _usage?['usage'] as Map<String, dynamic>? ?? _usage;
    final name = _s(customer?['name'], preview?.name ?? '—');
    final username = _s(customer?['username'], preview?.username ?? '—');
    final code = _s(customer?['customer_code'], preview?.customerCode ?? '—');
    final phone = _s(customer?['phone'], preview?.phone ?? '—');
    final zone = _s(customer?['zone'], preview?.zone ?? '—');
    final monthlyNum = customer?['monthly_bill'] as num?;
    final monthlyText = monthlyNum != null ? monthlyNum.toStringAsFixed(2) : '—';
    final billingStatus = _s(customer?['status'] ?? preview?.connectionStatus, 'Active');
    final isOnline = usage?['online'] == true || preview?.isOnline == true;
    final activeStatus = isOnline ? 'Connected' : 'Offline';

    return LegacySoftifyPage(
      child: Scaffold(
        backgroundColor: RadiantTokens.legacyPageBg,
        appBar: AppBar(
          backgroundColor: RadiantTokens.legacyHeaderBlue,
          foregroundColor: Colors.white,
          elevation: 0,
          centerTitle: true,
          title: const Text('Monitoring'),
        ),
        body: _loading && customer == null
          ? const Center(child: CircularProgressIndicator(color: RadiantTokens.brand))
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(12),
                children: [
                  _sectionCard(
                    title: 'ONU Information',
                    trailing: TextButton(
                      onPressed: () {},
                      style: TextButton.styleFrom(
                        backgroundColor: RadiantTokens.brand,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                        minimumSize: Size.zero,
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      ),
                      child: const Text('View', style: TextStyle(fontSize: 12)),
                    ),
                    child: Column(
                      children: [
                        Row(
                          children: [
                            Expanded(child: _infoTile(Icons.public, 'Active Status', activeStatus, valueColor: const Color(0xFFFF7043))),
                            Expanded(child: _infoTile(Icons.receipt_long, 'Billing Status', billingStatus, valueColor: const Color(0xFF66BB6A))),
                          ],
                        ),
                        Row(
                          children: [
                            Expanded(child: _infoTile(Icons.payments, 'Monthly Bill', monthlyText)),
                            Expanded(child: _infoTile(Icons.location_on, 'Zone', zone)),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                  _sectionCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _detailLine('Name', name),
                        _detailLine('Username', username),
                        _detailLine('Client Code', code, valueColor: RadiantTokens.brand),
                        _detailLine('Mobile', phone),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                  _sectionCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Traffic Monitoring', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                        const SizedBox(height: 10),
                        UsageAreaChart(chart: usage?['chart'] as Map<String, dynamic>?),
                        const SizedBox(height: 10),
                        Row(
                          children: [
                            Expanded(
                              child: Text(
                                'Download : ${_s(usage?['download_human'], '0 Kb/s')}',
                                style: const TextStyle(color: Color(0xFF66BB6A), fontWeight: FontWeight.w600, fontSize: 12),
                              ),
                            ),
                            Expanded(
                              child: Text(
                                'Upload : ${_s(usage?['upload_human'], '0 Kb/s')}',
                                style: const TextStyle(color: Colors.red, fontWeight: FontWeight.w600, fontSize: 12),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            Expanded(child: _statFooter(Icons.arrow_upward, 'Upload', _humanBytes(usage?['total_upload']))),
                            Expanded(child: _statFooter(Icons.swap_vert, 'Uptime', _s(usage?['connection_duration']))),
                            Expanded(child: _statFooter(Icons.arrow_downward, 'Download', _humanBytes(usage?['total_download']))),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
      ),
    );
  }

  String _humanBytes(dynamic raw) {
    if (raw is num) {
      final v = raw.toDouble();
      if (v >= 1e9) return '${(v / 1e9).toStringAsFixed(1)} Gb';
      if (v >= 1e6) return '${(v / 1e6).toStringAsFixed(1)} Mb';
      if (v >= 1e3) return '${(v / 1e3).toStringAsFixed(1)} Kb';
      return '${v.toStringAsFixed(0)} b';
    }
    return _s(raw);
  }

  Widget _sectionCard({String? title, Widget? trailing, required Widget child}) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFE0E7EF)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (title != null)
            Row(
              children: [
                Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: RadiantTokens.brand)),
                const Spacer(),
                if (trailing != null) trailing,
              ],
            ),
          if (title != null) const SizedBox(height: 10),
          child,
        ],
      ),
    );
  }

  Widget _infoTile(IconData icon, String label, String value, {Color? valueColor}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10, right: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 16, color: RadiantTokens.brand),
              const SizedBox(width: 6),
              Text(label, style: TextStyle(fontSize: 11, color: Colors.grey.shade700)),
            ],
          ),
          const SizedBox(height: 4),
          Text(value, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: valueColor)),
        ],
      ),
    );
  }

  Widget _detailLine(String label, String value, {Color? valueColor}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        children: [
          SizedBox(width: 100, child: Text(label, style: const TextStyle(fontSize: 13, color: Color(0xFF607D8B)))),
          Expanded(
            child: Text(value, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: valueColor ?? const Color(0xFF212121))),
          ),
        ],
      ),
    );
  }

  Widget _statFooter(IconData icon, String label, String value) {
    return Column(
      children: [
        Icon(icon, size: 18, color: RadiantTokens.brand),
        const SizedBox(height: 4),
        Text(label, style: TextStyle(fontSize: 10, color: Colors.grey.shade600)),
        Text(value, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
      ],
    );
  }
}
