import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';

import '../config/remote_config.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../widgets/customer_profile_hero.dart';
import '../widgets/staff_blue_app_bar.dart';
import '../widgets/state_views.dart';
import '../widgets/usage_area_chart.dart';
import 'staff_customer_edit_screen.dart';
import 'staff_receive_bill_screen.dart';

/// Customer profile + live usage. Bill collection only via [StaffReceiveBillScreen].
class StaffCustomerDetailScreen extends StatefulWidget {
  const StaffCustomerDetailScreen({
    super.key,
    required this.api,
    required this.customerId,
    this.openReceiveBill = false,
  });

  final ApiService api;
  final int customerId;
  final bool openReceiveBill;

  @override
  State<StaffCustomerDetailScreen> createState() => _StaffCustomerDetailScreenState();
}

class _StaffCustomerDetailScreenState extends State<StaffCustomerDetailScreen> {
  Map<String, dynamic>? _customer;
  Map<String, dynamic>? _usage;
  bool _loading = true;
  Timer? _usageTimer;
  final _fmt = NumberFormat('#,##0.00');

  @override
  void initState() {
    super.initState();
    _load();
    _usageTimer = Timer.periodic(const Duration(seconds: 1), (_) => _pollUsage());
  }

  @override
  void dispose() {
    _usageTimer?.cancel();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final detail = await widget.api.staffCustomerDetail(widget.customerId);
      if (mounted) setState(() => _customer = detail);
      await _pollUsage();
      if (mounted && widget.openReceiveBill && _customer != null) {
        WidgetsBinding.instance.addPostFrameCallback((_) => _openReceiveBill());
      }
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

  Future<void> _openReceiveBill({Map<String, dynamic>? invoice}) async {
    if (_customer == null) return;
    final ok = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => StaffReceiveBillScreen(api: widget.api, customer: _customer!, invoice: invoice),
      ),
    );
    if (ok == true) _load();
  }

  List<Map<String, dynamic>> get _invoices {
    final raw = _customer?['invoices'] as List<dynamic>?;
    if (raw == null) return [];
    return raw.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  @override
  Widget build(BuildContext context) {
    final name = _customer?['name']?.toString() ?? 'Customer';
    final due = (_customer?['balance_due'] as num?)?.toDouble() ?? 0;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: const Color(0xFFF0F4FF),
        appBar: StaffBlueAppBar(
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
                icon: const Icon(Icons.more_vert),
                onSelected: (v) {
                  if (v == 'receive') _openReceiveBill();
                  if (v == 'suspend') _suspend();
                  if (v == 'reconnect') _reconnect();
                },
                itemBuilder: (_) => [
                  const PopupMenuItem(value: 'receive', child: Text('Receive bill')),
                  const PopupMenuItem(value: 'suspend', child: Text('Suspend line')),
                  const PopupMenuItem(value: 'reconnect', child: Text('Reconnect line')),
                ],
              ),
            IconButton(icon: const Icon(Icons.refresh), onPressed: _load),
          ],
        ),
        body: _loading
            ? const Center(child: CircularProgressIndicator())
            : _customer == null
                ? const EmptyState(icon: Icons.person_off, title: 'Customer not found', subtitle: '')
                : Column(
                    children: [
                      CustomerProfileHero(
                        name: name,
                        customerCode: _customer!['customer_code']?.toString() ?? '',
                        phone: _customer!['phone']?.toString() ?? '—',
                        packageName: _customer!['package']?.toString() ?? '—',
                        balanceDue: due,
                        isOnline: _usage?['online'] == true,
                      ),
                      Expanded(
                        child: RefreshIndicator(
                          onRefresh: _load,
                          child: ListView(
                            padding: const EdgeInsets.all(16),
                            children: [
                              Card(
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                                child: Padding(
                                  padding: const EdgeInsets.all(14),
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Row(
                                        children: [
                                          Icon(Icons.show_chart, color: AppTheme.primary, size: 22),
                                          const SizedBox(width: 8),
                                          const Text('Live usage', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                                          const Spacer(),
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                            decoration: BoxDecoration(
                                              color: AppTheme.success.withValues(alpha: 0.15),
                                              borderRadius: BorderRadius.circular(8),
                                            ),
                                            child: const Text(
                                              'LIVE',
                                              style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppTheme.success),
                                            ),
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
                                    ],
                                  ),
                                ),
                              ),
                              const SizedBox(height: 16),
                              FilledButton.icon(
                                onPressed: () => _openReceiveBill(),
                                icon: const Icon(Icons.receipt_long),
                                label: Text(due > 0 ? 'Receive bill · Due ${_fmt.format(due)} BDT' : 'Receive bill'),
                                style: FilledButton.styleFrom(
                                  backgroundColor: AppTheme.primary,
                                  minimumSize: const Size.fromHeight(52),
                                ),
                              ),
                              if (_invoices.isNotEmpty) ...[
                                const SizedBox(height: 20),
                                const Text('Open invoices', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                                const SizedBox(height: 8),
                                ..._invoices.map((m) {
                                  final invDue = (m['balance_due'] as num?)?.toDouble() ?? 0;
                                  return Card(
                                    margin: const EdgeInsets.only(bottom: 8),
                                    child: ListTile(
                                      leading: const Icon(Icons.description_outlined, color: AppTheme.primary),
                                      title: Text(m['invoice_number']?.toString() ?? 'Invoice'),
                                      subtitle: Text('Due ${_fmt.format(invDue)} BDT'),
                                      trailing: FilledButton(
                                        onPressed: () => _openReceiveBill(invoice: m),
                                        style: FilledButton.styleFrom(
                                          backgroundColor: AppTheme.success,
                                          visualDensity: VisualDensity.compact,
                                        ),
                                        child: const Text('Receive'),
                                      ),
                                    ),
                                  );
                                }),
                              ],
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
      ),
    );
  }
}
