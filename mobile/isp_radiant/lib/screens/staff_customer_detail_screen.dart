import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../config/remote_config.dart';
import '../services/api_service.dart';
import '../services/offline_sync_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../widgets/page_scaffold.dart';
import '../widgets/state_views.dart';
import 'staff_customer_edit_screen.dart';

class StaffCustomerDetailScreen extends StatefulWidget {
  const StaffCustomerDetailScreen({super.key, required this.api, required this.customerId});

  final ApiService api;
  final int customerId;

  @override
  State<StaffCustomerDetailScreen> createState() => _StaffCustomerDetailScreenState();
}

class _StaffCustomerDetailScreenState extends State<StaffCustomerDetailScreen> {
  Map<String, dynamic>? _customer;
  bool _loading = true;
  final _amountCtrl = TextEditingController();
  final _fmt = NumberFormat('#,##0.00');
  late final OfflineSyncService _offline = OfflineSyncService(widget.api);

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _amountCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final body = await widget.api.staffCustomerDetail(widget.customerId);
      if (mounted) setState(() => _customer = body);
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
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

  Future<void> _collect({int? invoiceId}) async {
    final amount = double.tryParse(_amountCtrl.text.trim());
    if (amount == null || amount <= 0) {
      showSnack(context, 'Enter valid amount', isError: true);
      return;
    }
    try {
      final res = await widget.api.recordCollection(
        customerId: widget.customerId,
        amount: amount,
        invoiceId: invoiceId,
      );
      if (mounted) {
        showSnack(context, 'Collected — receipt ${res['payment']?['receipt_number'] ?? ''}');
        _amountCtrl.clear();
        _load();
      }
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } catch (_) {
      if (RemoteConfig.offlineSync) {
        await _offline.enqueueCollection(
          customerId: widget.customerId,
          amount: amount,
          invoiceId: invoiceId,
        );
        if (mounted) showSnack(context, 'Saved offline — will sync when online');
      } else if (mounted) {
        showSnack(context, 'Connection failed', isError: true);
      }
    }
  }

  List<Map<String, dynamic>> get _invoices {
    final raw = _customer?['invoices'] as List<dynamic>?;
    if (raw == null) return [];
    return raw.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'Bill collection',
      actions: [
        IconButton(
          icon: const Icon(Icons.edit),
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
        IconButton(icon: const Icon(Icons.refresh), onPressed: _load),
      ],
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _customer == null
              ? const EmptyState(icon: Icons.person_off, title: 'Customer not found', subtitle: 'Pull back and try again')
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
                    children: [
                      _profileHeader(),
                      const SizedBox(height: 16),
                      _collectionCard(),
                      if (RemoteConfig.networkControl) ...[
                        const SizedBox(height: 12),
                        _networkActions(),
                      ],
                      const SizedBox(height: 20),
                      const SectionTitle('Open invoices'),
                      const SizedBox(height: 8),
                      ..._buildInvoices(),
                    ],
                  ),
                ),
    );
  }

  Widget _profileHeader() {
    final c = _customer!;
    final due = (c['balance_due'] as num?)?.toDouble() ?? 0;
    final code = c['customer_code']?.toString() ?? '';
    final name = c['name']?.toString() ?? 'Customer';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: AppTheme.heroGradient,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CircleAvatar(
            radius: 32,
            backgroundColor: Colors.white24,
            child: Text(
              name.isNotEmpty ? name[0].toUpperCase() : '?',
              style: const TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.bold),
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(name, style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold)),
                const SizedBox(height: 4),
                Text('$code · ${c['phone'] ?? '—'}', style: const TextStyle(color: Colors.white70, fontSize: 13)),
                const SizedBox(height: 10),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: due > 0 ? AppTheme.warning.withValues(alpha: 0.9) : AppTheme.success.withValues(alpha: 0.9),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    'Due ${_fmt.format(due)} BDT',
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _collectionCard() {
    final c = _customer!;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: AppTheme.tinted(AppTheme.success),
                  child: const Icon(Icons.payments, color: AppTheme.success),
                ),
                const SizedBox(width: 12),
                const Expanded(
                  child: Text('Record collection', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                ),
              ],
            ),
            const SizedBox(height: 14),
            TextField(
              controller: _amountCtrl,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600),
              decoration: InputDecoration(
                labelText: 'Amount (BDT)',
                hintText: _fmt.format(c['balance_due'] ?? 0),
                prefixIcon: const Icon(Icons.attach_money, color: AppTheme.accent),
              ),
            ),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: () => _collect(),
              icon: const Icon(Icons.savings),
              label: const Text('Record cash collection'),
              style: FilledButton.styleFrom(
                backgroundColor: AppTheme.success,
                minimumSize: const Size.fromHeight(48),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _networkActions() {
    return Row(
      children: [
        Expanded(
          child: OutlinedButton.icon(
            onPressed: _suspend,
            icon: const Icon(Icons.pause_circle_outline, color: AppTheme.danger),
            label: const Text('Suspend', style: TextStyle(color: AppTheme.danger, fontWeight: FontWeight.w600)),
            style: OutlinedButton.styleFrom(
              side: const BorderSide(color: AppTheme.danger),
              backgroundColor: AppTheme.danger.withValues(alpha: 0.06),
              minimumSize: const Size.fromHeight(44),
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: FilledButton.icon(
            onPressed: _reconnect,
            icon: const Icon(Icons.play_circle_outline),
            label: const Text('Reconnect'),
            style: FilledButton.styleFrom(
              backgroundColor: AppTheme.info,
              minimumSize: const Size.fromHeight(44),
            ),
          ),
        ),
      ],
    );
  }

  List<Widget> _buildInvoices() {
    if (_invoices.isEmpty) {
      return [
        Card(
          color: AppTheme.accentSoft,
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Row(
              children: [
                Icon(Icons.receipt_long, color: AppTheme.accent, size: 40),
                const SizedBox(width: 14),
                Expanded(
                  child: Text(
                    'No open invoices — customer is up to date.',
                    style: TextStyle(color: Colors.grey.shade800, fontWeight: FontWeight.w500),
                  ),
                ),
              ],
            ),
          ),
        ),
      ];
    }

    return _invoices.map((m) {
      final id = (m['id'] as num?)?.toInt();
      final due = (m['balance_due'] as num?)?.toDouble() ?? 0;
      final overdue = m['is_overdue'] == true;
      return Card(
        margin: const EdgeInsets.only(bottom: 10),
        child: ListTile(
          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          leading: CircleAvatar(
            backgroundColor: (overdue ? AppTheme.danger : AppTheme.primary).withValues(alpha: 0.12),
            child: Icon(Icons.receipt, color: overdue ? AppTheme.danger : AppTheme.primary),
          ),
          title: Text(
            m['invoice_number']?.toString() ?? 'Invoice',
            style: const TextStyle(fontWeight: FontWeight.bold),
          ),
          subtitle: Text(
            'Due ${_fmt.format(due)} BDT · ${m['status'] ?? ''}${overdue ? ' · Overdue' : ''}',
            style: TextStyle(color: overdue ? AppTheme.danger : const Color(0xFF64748B)),
          ),
          trailing: id != null
              ? FilledButton(
                  onPressed: () {
                    _amountCtrl.text = due.toStringAsFixed(2);
                    _collect(invoiceId: id);
                  },
                  style: FilledButton.styleFrom(
                    backgroundColor: AppTheme.accent,
                    foregroundColor: const Color(0xFF1F2937),
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                  ),
                  child: const Text('Collect'),
                )
              : null,
        ),
      );
    }).toList();
  }
}
