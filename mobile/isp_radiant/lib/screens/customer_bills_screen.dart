import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/network/api_result.dart';
import '../core/theme/design_tokens.dart';
import '../core/widgets/skeleton.dart';
import '../core/widgets/states.dart';
import '../features/customer/data/customer_repository.dart';
import '../features/customer/domain/customer_models.dart';
import '../services/api_service.dart';
import '../utils/layout.dart';
import '../widgets/isp_ui_kit.dart';
import 'customer_invoice_detail_screen.dart';
import 'customer_pay_screen.dart';

/// Payment history — typed models + repository, skeleton load, friendly states.
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
  late final CustomerRepository _repo = CustomerRepository(widget.api);
  List<PaymentRecord> _payments = [];
  bool _loading = true;
  Failure? _error;
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
    final res = await _repo.payments();
    if (!mounted) return;
    res.when(
      ok: (list) => setState(() {
        _payments = list;
        _loading = false;
      }),
      err: (f) => setState(() {
        _error = f;
        _loading = false;
      }),
    );
  }

  void _openPay() {
    if (widget.onPay != null) {
      widget.onPay!();
      return;
    }
    Navigator.push(context, MaterialPageRoute(builder: (_) => CustomerPayScreen(api: widget.api)))
        .then((_) => _load());
  }

  void _openInvoice(PaymentRecord p) {
    if (p.invoiceId == null) return;
    Navigator.push(
      context,
      MaterialPageRoute(
          builder: (_) => CustomerInvoiceDetailScreen(api: widget.api, invoiceId: p.invoiceId!)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final body = _buildBody();
    if (widget.embedded) {
      return Scaffold(
        appBar: AppBar(title: const Text('Payment History')),
        floatingActionButton: FloatingActionButton.extended(
          onPressed: _openPay,
          backgroundColor: DesignTokens.success,
          foregroundColor: Colors.white,
          icon: const Icon(Icons.payments_rounded),
          label: const Text('Recharge / Pay'),
        ),
        body: body,
      );
    }
    return body;
  }

  Widget _buildBody() {
    if (_loading) return const SkeletonList();
    if (_error != null) return ErrorStateView(failure: _error!, onRetry: _load);
    if (_payments.isEmpty) {
      return EmptyStateView(
        icon: Icons.receipt_long_rounded,
        title: 'No payments yet',
        message: 'Your paid bills will appear here.',
        actionLabel: 'Pay a bill',
        onAction: _openPay,
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      color: DesignTokens.primary,
      child: ListView.separated(
        padding: pagePadding(context, top: 10).copyWith(bottom: 88),
        itemCount: _payments.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (context, i) {
          final p = _payments[i];
          return IspUiKit.paymentHistoryCard(
            title: p.title,
            date: p.paidAt,
            amount: _fmt.format(p.amount),
            invoice: p.reference.isNotEmpty ? p.reference : null,
            status: p.status,
            onTap: p.invoiceId != null ? () => _openInvoice(p) : null,
          );
        },
      ),
    );
  }
}
