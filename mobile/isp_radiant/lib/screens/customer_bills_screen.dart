import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';
import '../utils/layout.dart';
import '../widgets/state_views.dart';
import 'customer_pay_screen.dart';

class CustomerBillsScreen extends StatefulWidget {
  const CustomerBillsScreen({
    super.key,
    required this.api,
    this.active = false,
    this.onPay,
  });

  final ApiService api;
  final bool active;
  final VoidCallback? onPay;

  @override
  State<CustomerBillsScreen> createState() => _CustomerBillsScreenState();
}

class _CustomerBillsScreenState extends State<CustomerBillsScreen> {
  List<Map<String, dynamic>> _bills = [];
  bool _loading = true;
  String? _error;
  final _fmt = NumberFormat('#,##0.00');

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void didUpdateWidget(CustomerBillsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active && !oldWidget.active) _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final list = await widget.api.customerBills();
      if (mounted) setState(() => _bills = list);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not load bills');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _pay(int invoiceId) {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => CustomerPayScreen(api: widget.api, invoiceId: invoiceId)),
    ).then((_) => _load());
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return Center(child: Padding(padding: const EdgeInsets.all(24), child: ErrorBanner(message: _error!, onRetry: _load)));
    }
    if (_bills.isEmpty) {
      return const EmptyState(icon: Icons.receipt_long, title: 'No bills found');
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: pagePadding(context, top: 8),
        itemCount: _bills.length + 1,
        separatorBuilder: (_, _) => const SizedBox(height: 8),
        itemBuilder: (context, i) {
          if (i == 0) {
            return FilledButton.icon(
              onPressed: () {
                if (widget.onPay != null) {
                  widget.onPay!();
                  return;
                }
                for (final b in _bills) {
                  if ((b['balance_due'] as num? ?? 0) > 0) {
                    _pay((b['id'] as num).toInt());
                    return;
                  }
                }
              },
              icon: const Icon(Icons.payment),
              label: const Text('Pay outstanding bill'),
            );
          }
          final b = _bills[i - 1];
          final due = (b['balance_due'] as num?)?.toDouble() ?? 0;
          final canPay = b['can_pay'] == true && due > 0;
          final id = (b['id'] as num).toInt();
          return Card(
            child: ListTile(
              title: Text(b['invoice_number']?.toString() ?? 'Invoice'),
              subtitle: Text('Due ${_fmt.format(due)} BDT · ${b['status']}'),
              trailing: canPay ? FilledButton(onPressed: () => _pay(id), child: const Text('Pay')) : Chip(label: Text('${b['status']}')),
            ),
          );
        },
      ),
    );
  }
}
