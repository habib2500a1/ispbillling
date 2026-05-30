import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/network/api_result.dart';
import '../core/theme/design_tokens.dart';
import '../core/widgets/cards.dart';
import '../core/widgets/skeleton.dart';
import '../core/widgets/states.dart';
import '../features/customer/data/customer_repository.dart';
import '../features/customer/domain/customer_models.dart';
import '../services/api_service.dart';
import 'customer_pay_screen.dart';

class CustomerInvoiceDetailScreen extends StatefulWidget {
  const CustomerInvoiceDetailScreen({super.key, required this.api, required this.invoiceId});

  final ApiService api;
  final int invoiceId;

  @override
  State<CustomerInvoiceDetailScreen> createState() => _CustomerInvoiceDetailScreenState();
}

class _CustomerInvoiceDetailScreenState extends State<CustomerInvoiceDetailScreen> {
  late final CustomerRepository _repo = CustomerRepository(widget.api);
  InvoiceDetail? _inv;
  bool _loading = true;
  Failure? _error;
  final _fmt = NumberFormat('#,##0.00');

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final res = await _repo.invoiceDetail(widget.invoiceId);
    if (!mounted) return;
    res.when(
      ok: (d) => setState(() {
        _inv = d;
        _loading = false;
      }),
      err: (f) => setState(() {
        _error = f;
        _loading = false;
      }),
    );
  }

  void _recharge() {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => CustomerPayScreen(api: widget.api, invoiceId: widget.invoiceId)),
    ).then((_) => _load());
  }

  @override
  Widget build(BuildContext context) {
    final inv = _inv;
    return Scaffold(
      appBar: AppBar(title: Text('Invoice of ${inv?.periodLabel ?? ''}')),
      floatingActionButton: (inv?.canPay ?? false)
          ? FloatingActionButton.extended(
              onPressed: _recharge,
              backgroundColor: DesignTokens.success,
              foregroundColor: Colors.white,
              icon: const Icon(Icons.account_balance_wallet_rounded),
              label: const Text('Recharge / Pay'),
            )
          : null,
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_loading) {
      return ListView(padding: const EdgeInsets.all(14), children: const [
        SkeletonCard(height: 220),
        SizedBox(height: 12),
        SkeletonCard(height: 110),
        SizedBox(height: 12),
        SkeletonCard(height: 70),
      ]);
    }
    if (_error != null) return ErrorStateView(failure: _error!, onRetry: _load);
    final inv = _inv;
    if (inv == null) return ErrorStateView(failure: const Failure('No data'), onRetry: _load);

    return RefreshIndicator(
      onRefresh: _load,
      color: DesignTokens.primary,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(14, 10, 14, 90),
        children: [
          _summaryCard(inv),
          const SizedBox(height: 12),
          _customerCard(inv),
          if (inv.payments.isNotEmpty) ...[
            const SizedBox(height: 16),
            const SectionHeader(title: 'Payments'),
            ...inv.payments.map((p) => _lineCard(p, Icons.account_balance_wallet_rounded, DesignTokens.success)),
          ],
          if (inv.items.isNotEmpty) ...[
            const SizedBox(height: 8),
            const SectionHeader(title: 'Items'),
            ...inv.items.map((it) => _lineCard(it, Icons.public_rounded, DesignTokens.primary)),
          ],
          if (inv.note.isNotEmpty) ...[
            const SizedBox(height: 8),
            const SectionHeader(title: 'Note'),
            AppCard(
              child: Row(
                children: [
                  Icon(Icons.info_outline_rounded, size: 18, color: context.brand.textMuted),
                  const SizedBox(width: 10),
                  Expanded(child: Text(inv.note, style: const TextStyle(fontSize: 13))),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _summaryCard(InvoiceDetail inv) {
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Invoice total', style: TextStyle(fontSize: 12, color: context.brand.textMuted)),
                    Text('৳${_fmt.format(inv.total)}',
                        style: const TextStyle(
                            fontSize: 26, fontWeight: FontWeight.w800, color: DesignTokens.info)),
                  ],
                ),
              ),
              if (inv.canPay)
                FilledButton(onPressed: _recharge, child: const Text('Recharge')),
            ],
          ),
          const SizedBox(height: 10),
          _dateRow(context, 'Generation Date', inv.generationDate),
          _dateRow(context, 'Expire Date', inv.expireDate),
          Divider(height: 22, color: context.brand.border),
          _line('Sub-Total', inv.subtotal),
          _line('Previous Due', inv.previousDue),
          _line('Total Amount', inv.total),
          _line('Paid Amount', inv.amountPaid),
          Divider(height: 22, color: context.brand.border),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Balance Due', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
              Text('৳${_fmt.format(inv.balanceDue)}',
                  style: TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 18,
                      color: inv.balanceDue > 0 ? DesignTokens.warning : DesignTokens.success)),
            ],
          ),
        ],
      ),
    );
  }

  Widget _customerCard(InvoiceDetail inv) {
    return AppCard(
      padding: const EdgeInsets.all(14),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(child: _info(Icons.person_outline_rounded, 'Name', inv.customerName)),
              Expanded(child: _info(Icons.badge_outlined, 'Client Code', inv.customerCode)),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: _info(Icons.dns_outlined, 'Server', inv.server)),
              Expanded(child: _info(Icons.phone_outlined, 'Mobile', inv.phone)),
            ],
          ),
        ],
      ),
    );
  }

  Widget _info(IconData icon, String label, String value) {
    return Row(
      children: [
        Icon(icon, size: 18, color: DesignTokens.primary),
        const SizedBox(width: 8),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: TextStyle(fontSize: 10, color: context.brand.textMuted)),
              Text(value,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
            ],
          ),
        ),
      ],
    );
  }

  Widget _lineCard(InvoiceLine line, IconData icon, Color color) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: AppCard(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(DesignTokens.radiusSm)),
              child: Icon(icon, color: color, size: 18),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(line.title, style: const TextStyle(fontWeight: FontWeight.w700)),
                  if (line.subtitle.isNotEmpty)
                    Text(line.subtitle, style: TextStyle(fontSize: 11, color: context.brand.textMuted)),
                ],
              ),
            ),
            Text('৳${_fmt.format(line.amount)}',
                style: const TextStyle(color: DesignTokens.success, fontWeight: FontWeight.w800)),
          ],
        ),
      ),
    );
  }

  Widget _dateRow(BuildContext context, String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(fontSize: 12, color: context.brand.textMuted)),
          Text(value, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }

  Widget _line(String k, double v) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(k, style: TextStyle(fontSize: 13, color: context.brand.textMuted)),
          Text(_fmt.format(v), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
        ],
      ),
    );
  }
}
