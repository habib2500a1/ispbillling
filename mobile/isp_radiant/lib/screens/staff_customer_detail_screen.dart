import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';

import '../config/remote_config.dart';
import '../core/theme/design_tokens.dart';
import '../core/widgets/cards.dart';
import '../core/widgets/skeleton.dart';
import '../core/widgets/states.dart';
import '../services/api_service.dart';
import '../utils/app_nav.dart';
import '../widgets/customer_profile_hero.dart';
import '../widgets/staff_blue_app_bar.dart';
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
            ? const SkeletonList(count: 4, rowHeight: 120)
            : _customer == null
                ? const EmptyStateView(icon: Icons.person_off_rounded, title: 'Customer not found')
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
                              AppCard(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      children: [
                                        const Icon(Icons.show_chart_rounded, color: DesignTokens.primary, size: 22),
                                        const SizedBox(width: 8),
                                        Text('Live usage',
                                            style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
                                        const Spacer(),
                                        Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                          decoration: BoxDecoration(
                                            color: DesignTokens.success.withValues(alpha: 0.15),
                                            borderRadius: BorderRadius.circular(8),
                                          ),
                                          child: const Text('LIVE',
                                              style: TextStyle(
                                                  fontSize: 10, fontWeight: FontWeight.bold, color: DesignTokens.success)),
                                        ),
                                      ],
                                    ),
                                    const SizedBox(height: 12),
                                    UsageAreaChart(chart: _usage?['chart'] as Map<String, dynamic>?),
                                    const SizedBox(height: 10),
                                    Text(
                                      '↓ ${_usage?['download_human'] ?? '—'} · ↑ ${_usage?['upload_human'] ?? '—'}',
                                      style: const TextStyle(fontWeight: FontWeight.w600, color: DesignTokens.primary),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 16),
                              FilledButton.icon(
                                onPressed: () => _openReceiveBill(),
                                icon: const Icon(Icons.receipt_long_rounded),
                                label: Text(due > 0 ? 'Receive bill · Due ${_fmt.format(due)} BDT' : 'Receive bill'),
                                style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(52)),
                              ),
                              if (_invoices.isNotEmpty) ...[
                                const SizedBox(height: 20),
                                const SectionHeader(title: 'Open invoices'),
                                ..._invoices.map((m) {
                                  final invDue = (m['balance_due'] as num?)?.toDouble() ?? 0;
                                  return Padding(
                                    padding: const EdgeInsets.only(bottom: 10),
                                    child: AppCard(
                                      onTap: () => _openReceiveBill(invoice: m),
                                      child: Row(
                                        children: [
                                          Container(
                                            padding: const EdgeInsets.all(9),
                                            decoration: BoxDecoration(
                                                color: DesignTokens.primary.withValues(alpha: 0.14),
                                                borderRadius: BorderRadius.circular(DesignTokens.radiusSm)),
                                            child: const Icon(Icons.description_rounded, color: DesignTokens.primary, size: 20),
                                          ),
                                          const SizedBox(width: 12),
                                          Expanded(
                                            child: Column(
                                              crossAxisAlignment: CrossAxisAlignment.start,
                                              children: [
                                                Text(m['invoice_number']?.toString() ?? 'Invoice',
                                                    style: const TextStyle(fontWeight: FontWeight.w700)),
                                                Text('Due ৳${_fmt.format(invDue)}',
                                                    style: TextStyle(fontSize: 12, color: context.brand.textMuted)),
                                              ],
                                            ),
                                          ),
                                          FilledButton(
                                            onPressed: () => _openReceiveBill(invoice: m),
                                            style: FilledButton.styleFrom(
                                              backgroundColor: DesignTokens.success,
                                              visualDensity: VisualDensity.compact,
                                            ),
                                            child: const Text('Receive'),
                                          ),
                                        ],
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
