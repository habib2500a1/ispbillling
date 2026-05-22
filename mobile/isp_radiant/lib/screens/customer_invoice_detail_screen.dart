import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import 'customer_pay_screen.dart';

class CustomerInvoiceDetailScreen extends StatefulWidget {
  const CustomerInvoiceDetailScreen({super.key, required this.api, required this.invoiceId});

  final ApiService api;
  final int invoiceId;

  @override
  State<CustomerInvoiceDetailScreen> createState() => _CustomerInvoiceDetailScreenState();
}

class _CustomerInvoiceDetailScreenState extends State<CustomerInvoiceDetailScreen> {
  Map<String, dynamic>? _data;
  bool _loading = true;
  final _fmt = NumberFormat('#,##0.00');

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final data = await widget.api.customerBillDetail(widget.invoiceId);
      if (mounted) setState(() => _data = data);
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  void _recharge() {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => CustomerPayScreen(api: widget.api, invoiceId: widget.invoiceId)),
    ).then((_) => _load());
  }

  @override
  Widget build(BuildContext context) {
    final inv = _data?['invoice'] as Map<String, dynamic>? ?? {};
    final cust = _data?['customer'] as Map<String, dynamic>? ?? {};
    final period = inv['period_label']?.toString() ?? 'Invoice';
    final balance = (inv['balance_due'] as num?)?.toDouble() ?? 0;
    final canPay = inv['can_pay'] == true;

    return Scaffold(
      backgroundColor: const Color(0xFFF1F5F9),
      appBar: AppBar(
        title: Text('Invoice of $period'),
        centerTitle: true,
      ),
      floatingActionButton: canPay
          ? FloatingActionButton.extended(
              onPressed: _recharge,
              backgroundColor: AppTheme.success,
              icon: const Icon(Icons.account_balance_wallet_outlined),
              label: const Text('Recharge/Pay'),
            )
          : null,
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(14, 8, 14, 88),
                children: [
                  _summaryCard(inv, balance, canPay),
                  const SizedBox(height: 12),
                  _clientCard(cust),
                  const SizedBox(height: 16),
                  _sectionTitle('Payments'),
                  ..._payments(inv),
                  const SizedBox(height: 12),
                  _sectionTitle('Items'),
                  ..._items(inv),
                  if (inv['note'] != null) ...[
                    const SizedBox(height: 12),
                    _sectionTitle('Note'),
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(14),
                        child: Text(inv['note'].toString(), style: const TextStyle(fontSize: 13)),
                      ),
                    ),
                  ],
                ],
              ),
            ),
    );
  }

  Widget _summaryCard(Map<String, dynamic> inv, double balance, bool canPay) {
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Text(
                  _fmt.format(inv['total'] ?? 0),
                  style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w300, color: AppTheme.info),
                ),
                const Spacer(),
                if (canPay)
                  FilledButton(
                    onPressed: _recharge,
                    style: FilledButton.styleFrom(backgroundColor: AppTheme.primaryDark),
                    child: const Text('Recharge'),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            Text('Generation Date: ${inv['generation_date'] ?? '—'}', style: _muted),
            Text('Expire Date: ${inv['expire_date'] ?? '—'}', style: _muted),
            const Padding(padding: EdgeInsets.symmetric(vertical: 10), child: Divider()),
            _line('Sub-Total', inv['subtotal']),
            _line('Previous Due', inv['previous_due'] ?? 0),
            _line('Total Amount', inv['total']),
            _line('Paid Amount', inv['amount_paid']),
            const Padding(padding: EdgeInsets.symmetric(vertical: 8), child: Divider()),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Balance Due', style: TextStyle(fontWeight: FontWeight.bold)),
                Text(
                  _fmt.format(balance),
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: AppTheme.accent),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _clientCard(Map<String, dynamic> cust) {
    return Card(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          children: [
            Row(
              children: [
                Expanded(child: _info(Icons.person_outline, 'Name', cust['name']?.toString() ?? '—')),
                Expanded(child: _info(Icons.dns_outlined, 'Server', cust['server']?.toString() ?? '—')),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(child: _info(Icons.badge_outlined, 'Client Code', cust['customer_code']?.toString() ?? '—')),
                Expanded(child: _info(Icons.phone_outlined, 'Mobile', cust['phone']?.toString() ?? '—')),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _info(IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Icon(icon, size: 18, color: Colors.grey),
          const SizedBox(width: 6),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: const TextStyle(fontSize: 10, color: Colors.grey)),
                Text(value, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _sectionTitle(String t) => Padding(
        padding: const EdgeInsets.only(left: 4, bottom: 6),
        child: Text(t, style: const TextStyle(fontSize: 12, color: Colors.grey)),
      );

  List<Widget> _payments(Map<String, dynamic> inv) {
    final list = inv['payments'] as List<dynamic>? ?? [];
    if (list.isEmpty) {
      return [const Card(child: ListTile(title: Text('No payments yet')))];
    }
    return list.map((p) {
      final m = p as Map<String, dynamic>;
      return Card(
        margin: const EdgeInsets.only(bottom: 8),
        child: ListTile(
          leading: CircleAvatar(
            backgroundColor: Colors.orange.shade50,
            child: Text(m['method']?.toString().substring(0, 1).toUpperCase() ?? 'P', style: const TextStyle(fontWeight: FontWeight.bold)),
          ),
          title: Text(m['method']?.toString() ?? 'Payment'),
          subtitle: Text(m['paid_at']?.toString() ?? ''),
          trailing: Text(
            _fmt.format(m['amount'] ?? 0),
            style: const TextStyle(color: AppTheme.success, fontWeight: FontWeight.bold),
          ),
        ),
      );
    }).toList();
  }

  List<Widget> _items(Map<String, dynamic> inv) {
    final list = inv['items'] as List<dynamic>? ?? [];
    return list.map((it) {
      final m = it as Map<String, dynamic>;
      return Card(
        margin: const EdgeInsets.only(bottom: 8),
        child: ListTile(
          leading: const CircleAvatar(child: Icon(Icons.public, color: AppTheme.primary)),
          title: Text(m['description']?.toString() ?? 'Item'),
          subtitle: m['subtitle'] != null ? Text(m['subtitle'].toString()) : null,
          trailing: Text(
            _fmt.format(m['line_total'] ?? 0),
            style: const TextStyle(color: AppTheme.success, fontWeight: FontWeight.bold),
          ),
        ),
      );
    }).toList();
  }

  Widget _line(String k, dynamic v) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(k, style: _muted),
          Text(_fmt.format(v ?? 0), style: const TextStyle(fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }

  static const _muted = TextStyle(fontSize: 12, color: Colors.grey);
}
