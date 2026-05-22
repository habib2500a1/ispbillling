import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
import '../widgets/isp_ui_kit.dart';
import '../widgets/state_views.dart';
import 'payment_checkout_screen.dart';

/// Client pay: all due invoices, full amount only (no manual partial). Wallet shown separately.
class CustomerPayScreen extends StatefulWidget {
  const CustomerPayScreen({super.key, required this.api, this.invoiceId});

  final ApiService api;
  final int? invoiceId;

  @override
  State<CustomerPayScreen> createState() => _CustomerPayScreenState();
}

class _CustomerPayScreenState extends State<CustomerPayScreen> {
  Map<String, dynamic>? _payables;
  bool _loading = true;
  String? _error;
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
    try {
      final data = await widget.api.customerPayables();
      if (mounted) setState(() => _payables = data);
      if (widget.invoiceId != null && mounted) {
        final due = (data['due_invoices'] as List<dynamic>?) ?? [];
        final match = due.cast<Map>().any((e) => (e['id'] as num?)?.toInt() == widget.invoiceId);
        if (match) {
          await _payInvoice(widget.invoiceId!, _pickGateway(data));
        }
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not load dues');
    }
    if (mounted) setState(() => _loading = false);
  }

  String _pickGateway(Map<String, dynamic> data) {
    final g = data['gateways'] as Map<String, dynamic>? ?? {};
    if (g['bkash'] == true) return 'bkash';
    if (g['piprapay'] == true) return 'piprapay';
    if (g['nagad'] == true) return 'nagad';
    if (g['sslcommerz'] == true) return 'sslcommerz';
    if (g['rocket'] == true) return 'rocket';
    return 'bkash';
  }

  Future<void> _payInvoice(int id, String gateway) async {
    try {
      final res = await widget.api.initiateBillPayment(id, gateway: gateway);
      final url = res['payment_url']?.toString();
      if (!mounted) return;
      if (url == null) {
        showSnack(context, res['message']?.toString() ?? 'Payment unavailable', isError: true);
        return;
      }
      final amount = res['amount']?.toString() ?? '';
      final done = await Navigator.of(context).push<bool>(
        MaterialPageRoute(
          builder: (_) => PaymentCheckoutScreen(
            paymentUrl: url,
            title: amount.isNotEmpty ? 'Pay $amount BDT' : 'Pay bill',
          ),
        ),
      );
      if (done == true && mounted) {
        showSnack(context, 'Payment completed — refreshing');
      }
      _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    }
  }

  Future<void> _chooseGatewayAndPay(Map<String, dynamic> invoice) async {
    final gateways = (_payables?['gateways'] as Map<String, dynamic>?) ?? {};
    final options = <String, String>{
      if (gateways['bkash'] == true) 'bkash': 'bKash',
      if (gateways['nagad'] == true) 'nagad': 'Nagad',
      if (gateways['sslcommerz'] == true) 'sslcommerz': 'Card / SSLCommerz',
      if (gateways['rocket'] == true) 'rocket': 'Rocket',
      if (gateways['piprapay'] == true) 'piprapay': 'PipraPay',
    };
    if (options.isEmpty) {
      showSnack(context, 'Online payment is not available', isError: true);
      return;
    }
    if (options.length == 1) {
      await _payInvoice((invoice['id'] as num).toInt(), options.keys.first);
      return;
    }
    final picked = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: options.entries
              .map(
                (e) => ListTile(
                  title: Text(e.value),
                  onTap: () => Navigator.pop(ctx, e.key),
                ),
              )
              .toList(),
        ),
      ),
    );
    if (picked != null) {
      await _payInvoice((invoice['id'] as num).toInt(), picked);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      appBar: AppBar(title: const Text('Pay dues'), centerTitle: true),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return Center(child: Padding(padding: const EdgeInsets.all(24), child: ErrorBanner(message: _error!, onRetry: _load)));
    }

    final totalDue = (_payables?['total_due'] as num?)?.toDouble() ?? 0;
    final wallet = (_payables?['wallet_balance'] as num?)?.toDouble() ?? 0;
    final dueList = (_payables?['due_invoices'] as List<dynamic>?)?.cast<Map<String, dynamic>>() ?? [];
    final message = _payables?['message']?.toString() ?? '';

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: pagePadding(context),
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              gradient: const LinearGradient(colors: [AppTheme.primary, AppTheme.purple]),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Total due', style: TextStyle(color: Colors.white70, fontSize: 12)),
                Text(
                  '${_fmt.format(totalDue)} BDT',
                  style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 8),
                Text(
                  message,
                  style: const TextStyle(color: Colors.white, fontSize: 12),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          Card(
            color: AppTheme.info.withValues(alpha: 0.08),
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Row(
                children: [
                  const Icon(Icons.account_balance_wallet, color: AppTheme.primary),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Wallet balance', style: TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                        Text('${_fmt.format(wallet)} BDT', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                        const Text(
                          'Top-up any amount on the web portal. Line turns on only when all dues below are cleared.',
                          style: TextStyle(fontSize: 10, color: Color(0xFF64748B)),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          const Text('Outstanding invoices', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          const SizedBox(height: 4),
          const Text(
            'Full invoice amount only — no manual partial payment',
            style: TextStyle(fontSize: 11, color: Color(0xFF64748B)),
          ),
          const SizedBox(height: 10),
          if (dueList.isEmpty)
            const EmptyState(icon: Icons.check_circle, title: 'No due bills', subtitle: 'You are up to date')
          else
            ...dueList.map((inv) {
              final due = (inv['balance_due'] as num?)?.toDouble() ?? 0;
              return Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: IspUiKit.collectionRowCard(
                  name: inv['invoice_number']?.toString() ?? 'Invoice',
                  codeLine: 'Due ${inv['due_date'] ?? '—'} · ${inv['status'] ?? ''}',
                  amount: '${_fmt.format(due)} BDT',
                  meta: 'Pay full amount via bKash / Nagad / card',
                  onTap: () => _chooseGatewayAndPay(inv),
                ),
              );
            }),
        ],
      ),
    );
  }
}
