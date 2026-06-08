import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';

import '../config/remote_config.dart';
import '../core/theme/design_tokens.dart';
import '../design_system/components/radiant_glass_card.dart';
import '../design_system/components/radiant_section.dart';
import '../design_system/radiant_tokens.dart';
import '../core/widgets/skeleton.dart';
import '../core/widgets/states.dart';
import '../services/api_service.dart';
import '../utils/app_nav.dart';
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
    final brand = context.radiant;
    final isOnline = _usage?['online'] == true;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: context.isDark ? SystemUiOverlayStyle.light : SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: context.isDark ? RadiantTokens.darkBg : RadiantTokens.lightBg,
        appBar: AppBar(
          title: Text(name, maxLines: 1, overflow: TextOverflow.ellipsis),
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
                : RefreshIndicator(
                    onRefresh: _load,
                    color: RadiantTokens.brand,
                    child: ListView(
                      padding: EdgeInsets.zero,
                      children: [
                        _CrmHero(
                          name: name,
                          customerCode: _customer!['customer_code']?.toString() ?? '',
                          phone: _customer!['phone']?.toString() ?? '—',
                          packageName: _customer!['package']?.toString() ?? '—',
                          balanceDue: due,
                          isOnline: isOnline,
                          fmt: _fmt,
                        ),
                        Padding(
                          padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              RadiantGlassCard(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      children: [
                                        Container(
                                          padding: const EdgeInsets.all(8),
                                          decoration: BoxDecoration(
                                            color: RadiantTokens.brand.withValues(alpha: 0.12),
                                            borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
                                          ),
                                          child: const Icon(Icons.show_chart_rounded, color: RadiantTokens.brand, size: 20),
                                        ),
                                        const SizedBox(width: 10),
                                        Expanded(
                                          child: Text(
                                            'Live usage',
                                            style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                                          ),
                                        ),
                                        const RadiantStatusChip(
                                          label: 'LIVE',
                                          color: RadiantTokens.success,
                                          icon: Icons.sensors_rounded,
                                        ),
                                      ],
                                    ),
                                    const SizedBox(height: 14),
                                    UsageAreaChart(chart: _usage?['chart'] as Map<String, dynamic>?),
                                    const SizedBox(height: 12),
                                    Text(
                                      '↓ ${_usage?['download_human'] ?? '—'} · ↑ ${_usage?['upload_human'] ?? '—'}',
                                      style: context.text.bodyMedium?.copyWith(
                                        fontWeight: FontWeight.w600,
                                        color: RadiantTokens.brand,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 14),
                              FilledButton.icon(
                                onPressed: () => _openReceiveBill(),
                                icon: const Icon(Icons.receipt_long_rounded),
                                label: Text(due > 0 ? 'Receive bill · Due ${_fmt.format(due)} BDT' : 'Receive bill'),
                                style: FilledButton.styleFrom(
                                  minimumSize: const Size.fromHeight(52),
                                  backgroundColor: RadiantTokens.brand,
                                ),
                              ),
                              if (_invoices.isNotEmpty) ...[
                                const SizedBox(height: 20),
                                const RadiantSectionHeader(title: 'Open invoices'),
                                ..._invoices.map((m) {
                                  final invDue = (m['balance_due'] as num?)?.toDouble() ?? 0;
                                  return Padding(
                                    padding: const EdgeInsets.only(bottom: 10),
                                    child: RadiantGlassCard(
                                      onTap: () => _openReceiveBill(invoice: m),
                                      child: Row(
                                        children: [
                                          Container(
                                            padding: const EdgeInsets.all(10),
                                            decoration: BoxDecoration(
                                              color: RadiantTokens.brand.withValues(alpha: 0.12),
                                              borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
                                            ),
                                            child: const Icon(Icons.description_rounded, color: RadiantTokens.brand, size: 20),
                                          ),
                                          const SizedBox(width: 12),
                                          Expanded(
                                            child: Column(
                                              crossAxisAlignment: CrossAxisAlignment.start,
                                              children: [
                                                Text(
                                                  m['invoice_number']?.toString() ?? 'Invoice',
                                                  style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                                                ),
                                                Text(
                                                  'Due ৳${_fmt.format(invDue)}',
                                                  style: context.text.bodySmall?.copyWith(color: brand.muted),
                                                ),
                                              ],
                                            ),
                                          ),
                                          FilledButton(
                                            onPressed: () => _openReceiveBill(invoice: m),
                                            style: FilledButton.styleFrom(
                                              backgroundColor: RadiantTokens.success,
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
                      ],
                    ),
                  ),
      ),
    );
  }
}

class _CrmHero extends StatelessWidget {
  const _CrmHero({
    required this.name,
    required this.customerCode,
    required this.phone,
    required this.packageName,
    required this.balanceDue,
    required this.isOnline,
    required this.fmt,
  });

  final String name;
  final String customerCode;
  final String phone;
  final String packageName;
  final double balanceDue;
  final bool isOnline;
  final NumberFormat fmt;

  @override
  Widget build(BuildContext context) {
    final initial = name.isNotEmpty ? name[0].toUpperCase() : '?';
    final hasDue = balanceDue > 0.009;
    final text = Theme.of(context).textTheme;

    return RadiantMeshBackground(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 20),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: LinearGradient(
                  colors: [RadiantTokens.brand.withValues(alpha: 0.35), RadiantTokens.accent.withValues(alpha: 0.25)],
                ),
                border: Border.all(color: Colors.white.withValues(alpha: 0.35)),
              ),
              alignment: Alignment.center,
              child: Text(initial, style: text.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(name, style: text.titleMedium?.copyWith(fontWeight: FontWeight.w800, letterSpacing: -0.3)),
                  const SizedBox(height: 4),
                  Text(
                    '$customerCode · $phone',
                    style: text.bodySmall?.copyWith(color: context.radiant.muted),
                  ),
                  const SizedBox(height: 6),
                  Text(packageName, style: text.bodyMedium?.copyWith(fontWeight: FontWeight.w600)),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 6,
                    children: [
                      RadiantStatusChip(
                        label: isOnline ? 'Online' : 'Offline',
                        color: isOnline ? RadiantTokens.success : context.radiant.muted,
                        icon: isOnline ? Icons.wifi_rounded : Icons.wifi_off_rounded,
                      ),
                      RadiantStatusChip(
                        label: 'Due ${fmt.format(balanceDue)} BDT',
                        color: hasDue ? RadiantTokens.warning : RadiantTokens.success,
                        icon: Icons.account_balance_wallet_outlined,
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
