import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../config/remote_config.dart';
import '../services/api_service.dart';
import '../services/offline_sync_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../widgets/page_scaffold.dart';
import '../widgets/state_views.dart';
import '../widgets/collection_payment_panel.dart';
import '../widgets/usage_area_chart.dart';
import 'staff_customer_edit_screen.dart';

class StaffCustomerDetailScreen extends StatefulWidget {
  const StaffCustomerDetailScreen({super.key, required this.api, required this.customerId});

  final ApiService api;
  final int customerId;

  @override
  State<StaffCustomerDetailScreen> createState() => _StaffCustomerDetailScreenState();
}

class _StaffCustomerDetailScreenState extends State<StaffCustomerDetailScreen> with SingleTickerProviderStateMixin {
  late final TabController _tabs = TabController(length: 2, vsync: this);
  Map<String, dynamic>? _customer;
  Map<String, dynamic>? _usage;
  bool _loading = true;
  Timer? _usageTimer;
  final _fmt = NumberFormat('#,##0.00');
  late final OfflineSyncService _offline = OfflineSyncService(widget.api);

  @override
  void initState() {
    super.initState();
    _load();
    _usageTimer = Timer.periodic(const Duration(seconds: 1), (_) => _pollUsage());
  }

  @override
  void dispose() {
    _usageTimer?.cancel();
    _tabs.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final detail = await widget.api.staffCustomerDetail(widget.customerId);
      if (mounted) setState(() => _customer = detail);
      await _pollUsage();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _pollUsage() async {
    try {
      final usage = await widget.api.staffCustomerUsageLive(widget.customerId);
      if (mounted) setState(() => _usage = usage);
    } catch (_) {}
  }

  Future<void> _suspend() async {
    try {
      final res = await widget.api.suspendCustomer(widget.customerId);
      if (mounted) showSnack(context, res['message']?.toString() ?? 'Suspended');
      _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    }
  }

  Future<void> _reconnect() async {
    try {
      final res = await widget.api.reconnectCustomer(widget.customerId);
      if (mounted) showSnack(context, res['message']?.toString() ?? 'Reconnected');
      _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    }
  }

  List<Map<String, dynamic>> get _invoices {
    final raw = _customer?['invoices'] as List<dynamic>?;
    if (raw == null) return [];
    return raw.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  @override
  Widget build(BuildContext context) {
    final name = _customer?['name']?.toString() ?? 'Customer';

    return PageScaffold(
      title: name,
      actions: [
        IconButton(
          icon: const Icon(Icons.edit_outlined),
          tooltip: 'Edit',
          onPressed: _customer == null
              ? null
              : () async {
                  final ok = await Navigator.push<bool>(
                    context,
                    MaterialPageRoute(
                      builder: (_) => StaffCustomerEditScreen(api: widget.api, customer: _customer!),
                    ),
                  );
                  if (ok == true) _load();
                },
        ),
        if (RemoteConfig.networkControl && _customer != null)
          PopupMenuButton<String>(
            onSelected: (v) {
              if (v == 'suspend') _suspend();
              if (v == 'reconnect') _reconnect();
            },
            itemBuilder: (_) => const [
              PopupMenuItem(value: 'suspend', child: Text('Suspend line')),
              PopupMenuItem(value: 'reconnect', child: Text('Reconnect line')),
            ],
          ),
        IconButton(icon: const Icon(Icons.refresh), onPressed: _load),
      ],
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _customer == null
              ? const EmptyState(icon: Icons.person_off, title: 'Customer not found', subtitle: '')
              : Column(
                  children: [
                    _compactHeader(),
                    Material(
                      color: Colors.white,
                      child: TabBar(
                        controller: _tabs,
                        labelColor: AppTheme.primary,
                        indicatorColor: AppTheme.accent,
                        tabs: const [
                          Tab(icon: Icon(Icons.home_outlined, size: 20), text: 'Home'),
                          Tab(icon: Icon(Icons.payments_outlined, size: 20), text: 'Bill & collect'),
                        ],
                      ),
                    ),
                    Expanded(
                      child: TabBarView(
                        controller: _tabs,
                        children: [
                          RefreshIndicator(onRefresh: _load, child: _homeTab()),
                          RefreshIndicator(onRefresh: _load, child: _billingTab()),
                        ],
                      ),
                    ),
                  ],
                ),
    );
  }

  Widget _compactHeader() {
    final c = _customer!;
    final due = (c['balance_due'] as num?)?.toDouble() ?? 0;
    final online = _usage?['online'] == true;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
      decoration: AppTheme.heroGradient,
      child: Row(
        children: [
          CircleAvatar(
            radius: 26,
            backgroundColor: Colors.white24,
            child: Text(
              (c['name']?.toString().isNotEmpty == true) ? c['name'].toString()[0].toUpperCase() : '?',
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 22),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${c['customer_code']} · ${c['phone'] ?? '—'}', style: const TextStyle(color: Colors.white70, fontSize: 12)),
                Text(c['package']?.toString() ?? '—', style: const TextStyle(color: Colors.white, fontSize: 13)),
                const SizedBox(height: 6),
                Wrap(
                  spacing: 8,
                  runSpacing: 4,
                  children: [
                    _chip(online ? 'Online' : 'Offline', online ? AppTheme.success : Colors.white38),
                    if (online && (_usage?['connection_duration'] != null))
                      _chip(_usage!['connection_duration'].toString(), AppTheme.primary),
                    if (!online && (_usage?['last_disconnect_human'] != null))
                      _chip('Off ${_usage!['last_disconnect_human']}', Colors.white38),
                    _chip('Due ${_fmt.format(due)}', due > 0 ? AppTheme.warning : AppTheme.success),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _connRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Row(
        children: [
          SizedBox(width: 120, child: Text(label, style: TextStyle(fontSize: 12, color: Colors.grey.shade600))),
          Expanded(child: Text(value, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600))),
        ],
      ),
    );
  }

  Widget _chip(String label, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.85), borderRadius: BorderRadius.circular(12)),
      child: Text(label, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600)),
    );
  }

  Widget _homeTab() {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(Icons.show_chart, color: AppTheme.primary, size: 22),
                    const SizedBox(width: 8),
                    const Text('Live usage (per second)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                    const Spacer(),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(color: AppTheme.success.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(8)),
                      child: const Text('LIVE', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppTheme.success)),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                UsageAreaChart(chart: _usage?['chart'] as Map<String, dynamic>?),
                const SizedBox(height: 10),
                Text(
                  '↓ ${_usage?['download_human'] ?? '—'} · ↑ ${_usage?['upload_human'] ?? '—'}',
                  style: const TextStyle(fontWeight: FontWeight.w600, color: AppTheme.primary),
                ),
                if (_usage?['framed_ip'] != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 6),
                    child: Text('IP ${_usage!['framed_ip']}', style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                  ),
              ],
            ),
          ),
        ),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Connection', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                const SizedBox(height: 8),
                _connRow('Status', (_usage?['online'] == true) ? 'Online' : 'Offline'),
                if (_usage?['session_started_formatted'] != null)
                  _connRow('Connected since', _usage!['session_started_formatted'].toString()),
                if (_usage?['connection_duration'] != null)
                  _connRow('Duration', _usage!['connection_duration'].toString()),
                if (_usage?['last_disconnect_formatted'] != null && _usage?['online'] != true)
                  _connRow('Last disconnect', _usage!['last_disconnect_formatted'].toString()),
                if (_customer?['connection']?['portal_last_logout_at'] != null &&
                    _customer!['connection']['portal_last_logout_at'].toString() != '—')
                  _connRow('App logout', _customer!['connection']['portal_last_logout_at'].toString()),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        OutlinedButton.icon(
          onPressed: () => _tabs.animateTo(1),
          icon: const Icon(Icons.payments),
          label: Text(
            ((_customer?['balance_due'] as num?)?.toDouble() ?? 0) > 0
                ? 'Collect due ${_fmt.format(_customer!['balance_due'])} BDT'
                : 'Bill & collection',
          ),
          style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48)),
        ),
      ],
    );
  }

  Widget _billingTab() {
    final c = _customer!;
    final due = (c['balance_due'] as num?)?.toDouble() ?? 0;
    return Column(
      children: [
        Expanded(
          child: CollectionPaymentPanel(
            api: widget.api,
            customerId: widget.customerId,
            balanceDue: due,
            onSuccess: _load,
          ),
        ),
        if (_invoices.isNotEmpty)
          Container(
            color: Colors.white,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              mainAxisSize: MainAxisSize.min,
              children: [
                const Padding(
                  padding: EdgeInsets.fromLTRB(16, 8, 16, 4),
                  child: Text('Open invoices — tap to collect', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                ),
                SizedBox(
                  height: 120,
                  child: ListView(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    children: _invoices.map((m) {
                      final id = (m['id'] as num?)?.toInt();
                      final invDue = (m['balance_due'] as num?)?.toDouble() ?? 0;
                      return Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: ActionChip(
                          avatar: const Icon(Icons.receipt, size: 18),
                          label: Text('${m['invoice_number']} · ${_fmt.format(invDue)}'),
                          onPressed: id == null
                              ? null
                              : () => Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                      builder: (_) => Scaffold(
                                        appBar: AppBar(title: Text(m['invoice_number']?.toString() ?? 'Collect')),
                                        body: CollectionPaymentPanel(
                                          api: widget.api,
                                          customerId: widget.customerId,
                                          balanceDue: invDue,
                                          invoiceId: id,
                                          invoiceNumber: m['invoice_number']?.toString(),
                                          onSuccess: () {
                                            Navigator.pop(context);
                                            _load();
                                          },
                                        ),
                                      ),
                                    ),
                                  ),
                        ),
                      );
                    }).toList(),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }

}
