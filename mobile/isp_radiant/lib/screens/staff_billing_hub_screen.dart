import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/layout.dart';
import '../widgets/page_scaffold.dart';
import '../widgets/billing_client_card.dart';
import '../widgets/isp_ui_kit.dart';
import '../widgets/stat_card.dart';
import '../widgets/state_views.dart';
import 'staff_customer_detail_screen.dart';

class StaffBillingHubScreen extends StatefulWidget {
  const StaffBillingHubScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<StaffBillingHubScreen> createState() => _StaffBillingHubScreenState();
}

class _StaffBillingHubScreenState extends State<StaffBillingHubScreen> with SingleTickerProviderStateMixin {
  late final TabController _tabs = TabController(length: 4, vsync: this);
  final _fmt = NumberFormat('#,##0.00');
  Map<String, dynamic>? _billing;
  List<Map<String, dynamic>> _due = [];
  List<Map<String, dynamic>> _invoices = [];
  List<Map<String, dynamic>> _collections = [];
  Map<String, dynamic>? _collectionSummary;
  bool _loading = true;
  String? _error;
  String _invoiceFilter = 'all';

  @override
  void initState() {
    super.initState();
    _loadAll();
    _tabs.addListener(() {
      if (!_tabs.indexIsChanging) setState(() {});
    });
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  Future<void> _loadAll() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final summary = await widget.api.staffBillingSummary();
      final dueBody = await widget.api.staffBillingDue();
      final invBody = await widget.api.staffBillingInvoices(status: _invoiceFilter);
      final colBody = await widget.api.staffBillingCollections();
      if (mounted) {
        setState(() {
          _billing = summary['billing'] as Map<String, dynamic>?;
          _due = (dueBody['data'] as List<dynamic>?)?.map((e) => Map<String, dynamic>.from(e as Map)).toList() ?? [];
          _invoices = (invBody['data'] as List<dynamic>?)?.map((e) => Map<String, dynamic>.from(e as Map)).toList() ?? [];
          _collections = (colBody['data'] as List<dynamic>?)?.map((e) => Map<String, dynamic>.from(e as Map)).toList() ?? [];
          _collectionSummary = colBody['summary'] as Map<String, dynamic>?;
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not load billing data');
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _loadInvoices(String status) async {
    setState(() => _invoiceFilter = status);
    final invBody = await widget.api.staffBillingInvoices(status: status);
    if (mounted) {
      setState(() {
        _invoices = (invBody['data'] as List<dynamic>?)?.map((e) => Map<String, dynamic>.from(e as Map)).toList() ?? [];
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'Billing list',
      useGradientBody: true,
      bottom: TabBar(
        controller: _tabs,
        isScrollable: true,
        tabs: const [
          Tab(text: 'Monthly'),
          Tab(text: 'Due'),
          Tab(text: 'Invoices'),
          Tab(text: 'Collections'),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: ErrorBanner(message: _error!, onRetry: _loadAll))
              : TabBarView(
              controller: _tabs,
              children: [
                _monthlyTab(),
                _dueTab(),
                _invoicesTab(),
                _collectionsTab(),
              ],
            ),
    );
  }

  Widget _monthlyTab() {
    final b = _billing ?? {};
    return RefreshIndicator(
      onRefresh: _loadAll,
      child: ListView(
        padding: pagePadding(context),
        children: [
          if ((b['paid_clients'] as num?) != null)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceAround,
                  children: [
                    Column(children: [
                      Text('${b['paid_clients']}', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: AppTheme.success)),
                      const Text('Paid clients', style: TextStyle(fontSize: 11)),
                    ]),
                    Column(children: [
                      Text('${b['unpaid_clients']}', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: AppTheme.warning)),
                      const Text('Unpaid clients', style: TextStyle(fontSize: 11)),
                    ]),
                  ],
                ),
              ),
            ),
          const SizedBox(height: 8),
          GridView.count(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            crossAxisCount: 2,
            mainAxisSpacing: 8,
            crossAxisSpacing: 8,
            childAspectRatio: 1.35,
            children: [
              StatCard(label: 'Monthly bill', value: _fmt.format(b['monthly_bill'] ?? 0), icon: Icons.receipt, color: AppTheme.primary),
              StatCard(label: 'Collected', value: _fmt.format(b['collected_bill'] ?? 0), icon: Icons.savings, color: AppTheme.success),
              StatCard(label: 'Due', value: _fmt.format(b['due'] ?? 0), icon: Icons.schedule, color: AppTheme.warning),
              StatCard(label: 'Discount', value: _fmt.format(b['discount'] ?? 0), icon: Icons.percent, color: Colors.deepOrange),
            ],
          ),
        ],
      ),
    );
  }

  Widget _dueTab() {
    final b = _billing ?? {};
    return RefreshIndicator(
      onRefresh: _loadAll,
      child: ListView(
        padding: pagePadding(context, top: 8),
        children: [
          IspUiKit.billingSummaryStrip(
            paidCount: '${b['paid_clients'] ?? 0}',
            unpaidCount: '${b['unpaid_clients'] ?? 0}',
            received: _fmt.format(b['collected_bill'] ?? 0),
            due: _fmt.format(b['due'] ?? 0),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 6),
            child: Text(
              'Showing ${_due.length} due client(s)',
              style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
            ),
          ),
          if (_due.isEmpty)
            const EmptyState(icon: Icons.check_circle, title: 'No due customers', subtitle: 'All caught up')
          else
            ..._due.map(
              (c) => BillingClientCard(
                api: widget.api,
                client: c,
                onChanged: _loadAll,
              ),
            ),
        ],
      ),
    );
  }

  Widget _dueStat(String label, String value, Color color) {
    return Expanded(
      child: Column(
        children: [
          Text(value, style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: color)),
          Text(label, style: const TextStyle(fontSize: 10)),
        ],
      ),
    );
  }

  Widget _invoicesTab() {
    return Column(
      children: [
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          child: Row(
            children: [
              for (final f in ['all', 'due', 'open', 'paid', 'partial'])
                Padding(
                  padding: const EdgeInsets.only(right: 6),
                  child: FilterChip(
                    label: Text(f.toUpperCase()),
                    selected: _invoiceFilter == f,
                    onSelected: (_) => _loadInvoices(f),
                  ),
                ),
            ],
          ),
        ),
        Expanded(
          child: _invoices.isEmpty
              ? const EmptyState(icon: Icons.receipt_long, title: 'No invoices', subtitle: '')
              : ListView.separated(
                  padding: pagePadding(context, top: 0),
                  itemCount: _invoices.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 6),
                  itemBuilder: (context, i) {
                    final inv = _invoices[i];
                    final due = (inv['balance_due'] as num?)?.toDouble() ?? 0;
                    return Card(
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      child: ListTile(
                        leading: CircleAvatar(
                          backgroundColor: AppTheme.warning.withValues(alpha: 0.15),
                          child: const Icon(Icons.receipt, color: AppTheme.warning, size: 22),
                        ),
                        title: Text(inv['invoice_number']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w700)),
                        subtitle: Text('${inv['customer_name']} · Due ${inv['due_date'] ?? ''}'),
                        trailing: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text('৳${_fmt.format(due)}', style: const TextStyle(fontWeight: FontWeight.w700, color: AppTheme.danger)),
                            Text(inv['status']?.toString() ?? '', style: const TextStyle(fontSize: 10)),
                          ],
                        ),
                        onTap: () {
                          final cid = inv['customer_id'];
                          if (cid is num) {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => StaffCustomerDetailScreen(api: widget.api, customerId: cid.toInt()),
                              ),
                            );
                          }
                        },
                      ),
                    );
                  },
                ),
        ),
      ],
    );
  }

  Widget _collectionsTab() {
    final s = _collectionSummary ?? {};
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Expanded(child: _miniStat('Transactions', s['transaction_count'] ?? _collections.length)),
              const SizedBox(width: 8),
              Expanded(child: _miniStat('Collected', s['period_collected'] ?? s['month_collected'])),
              const SizedBox(width: 8),
              Expanded(child: _miniStat('This month', s['month_collected'])),
            ],
          ),
        ),
        Expanded(
          child: _collections.isEmpty
              ? const EmptyState(icon: Icons.payments, title: 'No collections', subtitle: '')
              : ListView.separated(
                  padding: pagePadding(context, top: 0),
                  itemCount: _collections.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 6),
                  itemBuilder: (context, i) {
                    final p = _collections[i];
                    return IspUiKit.collectionRowCard(
                      name: p['customer_name']?.toString() ?? '',
                      codeLine: '${p['customer_code']} · ${p['receipt_number'] ?? ''}',
                      amount: '৳${_fmt.format((p['amount'] as num?) ?? 0)}',
                      meta: 'Bill ${p['bill_date'] ?? '—'} · ${p['method'] ?? ''}',
                      dateLine: '${p['paid_at'] ?? ''} · ${p['recorded_by'] ?? '—'}',
                    );
                  },
                ),
        ),
      ],
    );
  }

  Widget _miniStat(String label, dynamic value) {
    return Card(
      color: AppTheme.primary.withValues(alpha: 0.08),
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: const TextStyle(fontSize: 11)),
            Text('৳${_fmt.format((value as num?) ?? 0)}', style: const TextStyle(fontWeight: FontWeight.bold)),
          ],
        ),
      ),
    );
  }
}
