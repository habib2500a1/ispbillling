import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/layout.dart';
import '../widgets/isp_ui_kit.dart';
import '../widgets/state_views.dart';
import 'customer_invoice_detail_screen.dart';
import 'customer_pay_screen.dart';

/// Payment history tab — matches Radiant client app reference UI.
class CustomerBillsScreen extends StatefulWidget {
  const CustomerBillsScreen({
    super.key,
    required this.api,
    this.active = false,
    this.onPay,
    this.embedded = false,
  });

  final ApiService api;
  final bool active;
  final VoidCallback? onPay;
  final bool embedded;

  @override
  State<CustomerBillsScreen> createState() => _CustomerBillsScreenState();
}

class _CustomerBillsScreenState extends State<CustomerBillsScreen> {
  List<Map<String, dynamic>> _payments = [];
  bool _loading = true;
  String? _error;
  final _fmt = NumberFormat('#,##0.0');

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
      final list = await widget.api.customerPayments();
      if (mounted) setState(() => _payments = list);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not load payment history');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _openPay() {
    if (widget.onPay != null) {
      widget.onPay!();
      return;
    }
    Navigator.push(context, MaterialPageRoute(builder: (_) => CustomerPayScreen(api: widget.api))).then((_) => _load());
  }

  void _openInvoice(Map<String, dynamic> p) {
    final id = p['invoice_id'];
    if (id == null) return;
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => CustomerInvoiceDetailScreen(api: widget.api, invoiceId: (id as num).toInt())),
    );
  }

  @override
  Widget build(BuildContext context) {
    final body = _buildBody();
    if (widget.embedded) {
      return Scaffold(
        backgroundColor: AppTheme.background,
        appBar: AppBar(
          title: const Text('Payment History'),
          centerTitle: true,
        ),
        floatingActionButton: FloatingActionButton.extended(
          onPressed: _openPay,
          backgroundColor: AppTheme.success,
          icon: const Icon(Icons.payments_outlined),
          label: const Text('Recharge/Pay'),
        ),
        body: body,
      );
    }
    return body;
  }

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return Center(child: Padding(padding: const EdgeInsets.all(24), child: ErrorBanner(message: _error!, onRetry: _load)));
    }
    if (_payments.isEmpty) {
      return const EmptyState(icon: Icons.receipt_long, title: 'No payments yet');
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: pagePadding(context, top: 10).copyWith(bottom: 88),
        itemCount: _payments.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (context, i) => _paymentCard(_payments[i]),
      ),
    );
  }

  Widget _paymentCard(Map<String, dynamic> p) {
    final amount = (p['amount'] as num?)?.toDouble() ?? 0;
    final inv = p['invoice_number']?.toString() ?? p['receipt_number']?.toString() ?? '';
    return IspUiKit.paymentHistoryCard(
      title: p['title']?.toString() ?? 'Monthly Bill',
      date: p['paid_at']?.toString() ?? '',
      amount: _fmt.format(amount),
      invoice: inv.isNotEmpty ? inv : null,
      status: p['status']?.toString() ?? 'Paid',
      onTap: p['invoice_id'] != null ? () => _openInvoice(p) : null,
    );
  }
}
