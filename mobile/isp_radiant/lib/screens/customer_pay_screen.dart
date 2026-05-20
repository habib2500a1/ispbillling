import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';
import '../utils/app_nav.dart';
import 'payment_checkout_screen.dart';

class CustomerPayScreen extends StatefulWidget {
  const CustomerPayScreen({super.key, required this.api, this.invoiceId});

  final ApiService api;
  final int? invoiceId;

  @override
  State<CustomerPayScreen> createState() => _CustomerPayScreenState();
}

class _CustomerPayScreenState extends State<CustomerPayScreen> {
  List<Map<String, dynamic>> _bills = [];
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
      final list = await widget.api.customerBills();
      if (mounted) setState(() => _bills = list.where((b) => (b['balance_due'] as num? ?? 0) > 0).toList());
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
    if (widget.invoiceId != null && mounted) {
      _payInvoice(widget.invoiceId!);
    }
  }

  Future<void> _payInvoice(int id) async {
    try {
      final res = await widget.api.initiateBillPayment(id);
      final url = res['payment_url']?.toString();
      if (!mounted) return;
      if (url == null) {
        showSnack(context, res['message']?.toString() ?? 'Payment unavailable', isError: true);
        return;
      }
      final done = await Navigator.of(context).push<bool>(
        MaterialPageRoute(
          builder: (_) => PaymentCheckoutScreen(paymentUrl: url, title: 'Pay bill'),
        ),
      );
      if (done == true && mounted) showSnack(context, 'Payment completed — refreshing bills');
      _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Pay bill')),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_bills.isEmpty) {
      return const Center(child: Text('No due bills — you are up to date'));
    }
    return ListView.separated(
      padding: const EdgeInsets.all(14),
      itemCount: _bills.length,
      separatorBuilder: (_, _) => const SizedBox(height: 8),
      itemBuilder: (context, i) {
        final b = _bills[i];
        final id = (b['id'] as num).toInt();
        final due = (b['balance_due'] as num?)?.toDouble() ?? 0;
        return Card(
          child: ListTile(
            title: Text(b['invoice_number']?.toString() ?? 'Invoice'),
            subtitle: Text('Due ${_fmt.format(due)} BDT'),
            trailing: FilledButton(
              onPressed: () => _payInvoice(id),
              child: const Text('Pay'),
            ),
          ),
        );
      },
    );
  }
}
