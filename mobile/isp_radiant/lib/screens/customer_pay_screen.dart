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
import '../utils/app_nav.dart';
import '../utils/layout.dart';
import 'payment_checkout_screen.dart';

/// Client pay: all due invoices, full amount only. Typed [Payables] + repository.
class CustomerPayScreen extends StatefulWidget {
  const CustomerPayScreen({super.key, required this.api, this.invoiceId});

  final ApiService api;
  final int? invoiceId;

  @override
  State<CustomerPayScreen> createState() => _CustomerPayScreenState();
}

class _CustomerPayScreenState extends State<CustomerPayScreen> {
  late final CustomerRepository _repo = CustomerRepository(widget.api);
  Payables? _payables;
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
    final res = await _repo.payables();
    if (!mounted) return;
    await res.when(
      ok: (data) async {
        setState(() {
          _payables = data;
          _loading = false;
        });
        // Deep-link: auto-start payment for a specific invoice if it is due.
        if (widget.invoiceId != null && data.dueInvoices.any((e) => e.id == widget.invoiceId)) {
          final gw = data.gatewayOptions.keys.isNotEmpty ? data.gatewayOptions.keys.first : 'bkash';
          await _payInvoice(widget.invoiceId!, gw);
        }
      },
      err: (f) async => setState(() {
        _error = f;
        _loading = false;
      }),
    );
  }

  Future<void> _payInvoice(int id, String gateway) async {
    final res = await _repo.payInvoice(id, gateway: gateway);
    if (!mounted) return;
    await res.when(
      ok: (data) async {
        final url = data['payment_url']?.toString();
        if (url == null) {
          showSnack(context, data['message']?.toString() ?? 'Payment unavailable', isError: true);
          return;
        }
        final amount = data['amount']?.toString() ?? '';
        final done = await Navigator.of(context).push<bool>(
          MaterialPageRoute(
            builder: (_) => PaymentCheckoutScreen(
              paymentUrl: url,
              title: amount.isNotEmpty ? 'Pay $amount BDT' : 'Pay bill',
            ),
          ),
        );
        if (done == true && mounted) showSnack(context, 'Payment completed — refreshing');
        _load();
      },
      err: (f) async => showSnack(context, f.message, isError: true),
    );
  }

  Future<void> _chooseGatewayAndPay(DueInvoice invoice) async {
    final options = _payables?.gatewayOptions ?? {};
    if (options.isEmpty) {
      showSnack(context, 'Online payment is not available', isError: true);
      return;
    }
    if (options.length == 1) {
      await _payInvoice(invoice.id, options.keys.first);
      return;
    }
    final picked = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
              child: Text('Pay ${invoice.invoiceNumber}',
                  style: const TextStyle(fontWeight: FontWeight.w700)),
            ),
            ...options.entries.map(
              (e) => ListTile(
                leading: const Icon(Icons.account_balance_wallet_rounded),
                title: Text(e.value),
                onTap: () => Navigator.pop(ctx, e.key),
              ),
            ),
          ],
        ),
      ),
    );
    if (picked != null) await _payInvoice(invoice.id, picked);
  }

  Future<void> _payPrepay(int months, String gateway) async {
    final res = await _repo.payPrepay(months: months, gateway: gateway);
    if (!mounted) return;
    await res.when(
      ok: (data) async {
        final url = data['payment_url']?.toString();
        if (url == null) {
          showSnack(context, data['message']?.toString() ?? 'Payment unavailable', isError: true);
          return;
        }
        final amount = data['amount']?.toString() ?? '';
        final done = await Navigator.of(context).push<bool>(
          MaterialPageRoute(
            builder: (_) => PaymentCheckoutScreen(
              paymentUrl: url,
              title: amount.isNotEmpty ? 'Advance pay $amount BDT' : 'Advance payment',
            ),
          ),
        );
        if (done == true && mounted) showSnack(context, 'Payment completed — refreshing');
        _load();
      },
      err: (f) async => showSnack(context, f.message, isError: true),
    );
  }

  Future<void> _chooseGatewayAndPrepay(int months) async {
    final quote = _payables?.prepay.quoteFor(months);
    if (quote == null) {
      showSnack(context, 'Advance payment is not available', isError: true);
      return;
    }
    final options = _payables?.gatewayOptions ?? {};
    if (options.isEmpty) {
      showSnack(context, 'Online payment is not available', isError: true);
      return;
    }
    if (options.length == 1) {
      await _payPrepay(months, options.keys.first);
      return;
    }
    final picked = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
              child: Text('Pay $months month(s) · ৳${_fmt.format(quote.totalAmount)}',
                  style: const TextStyle(fontWeight: FontWeight.w700)),
            ),
            ...options.entries.map(
              (e) => ListTile(
                leading: const Icon(Icons.calendar_month_rounded),
                title: Text(e.value),
                onTap: () => Navigator.pop(ctx, e.key),
              ),
            ),
          ],
        ),
      ),
    );
    if (picked != null) await _payPrepay(months, picked);
  }

  Widget _prepaySection(PrepaySection prepay) {
    if (!prepay.enabled || prepay.quickMonths.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const SectionHeader(title: 'Advance payment'),
        if (prepay.packageName.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Text(
              '${prepay.packageName} · ৳${_fmt.format(prepay.monthlyRate)}/mo',
              style: TextStyle(fontSize: 12, color: context.brand.textMuted),
            ),
          ),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: prepay.quickMonths.map((months) {
            final quote = prepay.quoteFor(months);
            if (quote == null) return const SizedBox.shrink();
            return ActionChip(
              avatar: const Icon(Icons.event_available_rounded, size: 18),
              label: Text('$months mo · ৳${_fmt.format(quote.totalAmount)}'),
              onPressed: prepay.canPayOnline
                  ? () => _chooseGatewayAndPrepay(months)
                  : null,
            );
          }).toList(),
        ),
        if (!prepay.canPayOnline)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Text(
              'Online payment is not enabled. Contact your ISP.',
              style: TextStyle(fontSize: 11, color: context.brand.textMuted),
            ),
          ),
        const SizedBox(height: 18),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Pay dues')),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_loading) {
      return ListView(padding: pagePadding(context), children: const [
        SkeletonCard(height: 110),
        SizedBox(height: 12),
        SkeletonCard(height: 80),
        SizedBox(height: 16),
        SkeletonCard(height: 90),
        SizedBox(height: 12),
        SkeletonCard(height: 90),
      ]);
    }
    if (_error != null) return ErrorStateView(failure: _error!, onRetry: _load);
    final p = _payables;
    if (p == null) return ErrorStateView(failure: const Failure('No data'), onRetry: _load);

    return RefreshIndicator(
      onRefresh: _load,
      color: DesignTokens.primary,
      child: ListView(
        padding: pagePadding(context),
        children: [
          AppCard(
            gradient: LinearGradient(
              colors: context.brand.heroGradient,
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Total due', style: TextStyle(color: Colors.white70, fontSize: 13)),
                const SizedBox(height: 4),
                Text('৳${_fmt.format(p.totalDue)}',
                    style: const TextStyle(color: Colors.white, fontSize: 30, fontWeight: FontWeight.w800)),
                if (p.message.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Text(p.message, style: const TextStyle(color: Colors.white, fontSize: 12)),
                ],
              ],
            ),
          ),
          const SizedBox(height: 12),
          AppCard(
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(9),
                  decoration: BoxDecoration(
                      color: DesignTokens.primary.withValues(alpha: 0.14),
                      borderRadius: BorderRadius.circular(DesignTokens.radiusSm)),
                  child: const Icon(Icons.account_balance_wallet_rounded, color: DesignTokens.primary),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Wallet balance',
                          style: TextStyle(fontSize: 11, color: context.brand.textMuted)),
                      Text('৳${_fmt.format(p.walletBalance)}',
                          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
                      Text('Line turns on only when all dues below are cleared.',
                          style: TextStyle(fontSize: 10, color: context.brand.textMuted)),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          _prepaySection(p.prepay),
          const SectionHeader(title: 'Outstanding invoices'),
          if (p.dueInvoices.isEmpty)
            EmptyStateView(
              icon: Icons.check_circle_rounded,
              title: 'No due bills',
              message: 'You are up to date.',
            )
          else
            ...p.dueInvoices.map((inv) => Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: AppCard(
                    onTap: () => _chooseGatewayAndPay(inv),
                    child: Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(inv.invoiceNumber,
                                  style: const TextStyle(fontWeight: FontWeight.w700)),
                              const SizedBox(height: 2),
                              Text('Due ${inv.dueDate} · ${inv.status}',
                                  style: TextStyle(fontSize: 12, color: context.brand.textMuted)),
                            ],
                          ),
                        ),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text('৳${_fmt.format(inv.balanceDue)}',
                                style: const TextStyle(
                                    fontWeight: FontWeight.w800, color: DesignTokens.danger)),
                            const SizedBox(height: 4),
                            const StatusPill(label: 'Pay now', color: DesignTokens.success, icon: Icons.bolt_rounded),
                          ],
                        ),
                      ],
                    ),
                  ),
                )),
        ],
      ),
    );
  }
}
