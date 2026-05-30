import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/network/api_result.dart';
import '../core/theme/design_tokens.dart';
import '../core/widgets/cards.dart';
import '../core/widgets/collection_card.dart';
import '../core/widgets/due_client_card.dart';
import '../core/widgets/skeleton.dart';
import '../core/widgets/states.dart';
import '../features/staff_billing/data/staff_billing_repository.dart';
import '../features/staff_billing/domain/billing_models.dart';
import '../services/api_service.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
import '../widgets/page_scaffold.dart';
import 'staff_customer_detail_screen.dart';
import 'staff_receive_bill_screen.dart';

class StaffBillingHubScreen extends StatefulWidget {
  const StaffBillingHubScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<StaffBillingHubScreen> createState() => _StaffBillingHubScreenState();
}

class _StaffBillingHubScreenState extends State<StaffBillingHubScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs = TabController(length: 4, vsync: this);
  late final StaffBillingRepository _repo = StaffBillingRepository(widget.api);
  final _fmt = NumberFormat('#,##0.00');

  BillingBundle? _data;
  bool _loading = true;
  Failure? _error;
  String _invoiceFilter = 'all';

  @override
  void initState() {
    super.initState();
    _loadAll();
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
    final res = await _repo.loadAll(invoiceStatus: _invoiceFilter);
    if (!mounted) return;
    res.when(
      ok: (b) => setState(() {
        _data = b;
        _loading = false;
      }),
      err: (f) => setState(() {
        _error = f;
        _loading = false;
      }),
    );
  }

  Future<void> _loadInvoices(String status) async {
    setState(() => _invoiceFilter = status);
    final res = await _repo.invoices(status);
    if (!mounted) return;
    res.when(
      ok: (list) => setState(() => _data = _data == null
          ? _data
          : BillingBundle(
              summary: _data!.summary,
              due: _data!.due,
              invoices: list,
              collections: _data!.collections,
              collectionSummary: _data!.collectionSummary,
            )),
      err: (f) => showSnack(context, f.message, isError: true),
    );
  }

  Future<void> _openReceiveBill(DueClient c) async {
    final res = await _repo.customerDetail(c.id);
    if (!mounted) return;
    await res.when(
      ok: (detail) async {
        final ok = await Navigator.push<bool>(
          context,
          MaterialPageRoute(builder: (_) => StaffReceiveBillScreen(api: widget.api, customer: detail)),
        );
        if (ok == true) _loadAll();
      },
      err: (f) async => showSnack(context, f.message, isError: true),
    );
  }

  Future<void> _toggle(DueClient c) async {
    final res = await _repo.toggleNetwork(c.id);
    if (!mounted) return;
    res.when(ok: (_) => _loadAll(), err: (f) => showSnack(context, f.message, isError: true));
  }

  Future<void> _extend(DueClient c) async {
    final res = await _repo.extendService(c.id);
    if (!mounted) return;
    res.when(
      ok: (_) {
        showSnack(context, 'Service extended 30 days');
        _loadAll();
      },
      err: (f) => showSnack(context, f.message, isError: true),
    );
  }

  Future<void> _sms(DueClient c) async {
    final res = await _repo.smsReminder(c.id);
    if (!mounted) return;
    res.when(
      ok: (_) => showSnack(context, 'SMS sent'),
      err: (f) => showSnack(context, f.message, isError: true),
    );
  }

  Future<void> _call(String phone) async {
    final uri = Uri.parse('tel:$phone');
    if (await canLaunchUrl(uri)) await launchUrl(uri);
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'Billing list',
      useGradientBody: true,
      bottom: TabBar(
        controller: _tabs,
        isScrollable: true,
        tabAlignment: TabAlignment.start,
        indicatorColor: DesignTokens.primary,
        tabs: const [
          Tab(text: 'Monthly'),
          Tab(text: 'Due'),
          Tab(text: 'Invoices'),
          Tab(text: 'Collections'),
        ],
      ),
      body: _loading
          ? const SkeletonList(count: 5, rowHeight: 110)
          : _error != null
              ? ErrorStateView(failure: _error!, onRetry: _loadAll)
              : TabBarView(
                  controller: _tabs,
                  children: [_monthlyTab(), _dueTab(), _invoicesTab(), _collectionsTab()],
                ),
    );
  }

  BillingSummary get _summary => _data?.summary ?? BillingSummary.empty;

  Widget _statGrid() {
    final s = _summary;
    return GridView.count(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisCount: 2,
      mainAxisSpacing: 12,
      crossAxisSpacing: 12,
      childAspectRatio: 1.5,
      children: [
        StatCard(
            icon: Icons.person_rounded,
            label: 'Paid clients',
            value: '${s.paidClients}',
            color: DesignTokens.success),
        StatCard(
            icon: Icons.group_rounded,
            label: 'Unpaid clients',
            value: '${s.unpaidClients}',
            color: DesignTokens.danger),
        StatCard(
            icon: Icons.verified_rounded,
            label: 'Received bill',
            value: _fmt.format(s.collected),
            color: DesignTokens.primary),
        StatCard(
            icon: Icons.schedule_rounded,
            label: 'Due amount',
            value: _fmt.format(s.due),
            color: DesignTokens.warning),
      ],
    );
  }

  Widget _monthlyTab() {
    final s = _summary;
    return RefreshIndicator(
      onRefresh: _loadAll,
      color: DesignTokens.primary,
      child: ListView(
        padding: pagePadding(context),
        children: [
          _statGrid(),
          const SizedBox(height: 12),
          GridView.count(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            crossAxisCount: 2,
            mainAxisSpacing: 12,
            crossAxisSpacing: 12,
            childAspectRatio: 1.5,
            children: [
              StatCard(
                  icon: Icons.receipt_long_rounded,
                  label: 'Monthly bill',
                  value: _fmt.format(s.monthlyBill),
                  color: DesignTokens.primary),
              StatCard(
                  icon: Icons.percent_rounded,
                  label: 'Discount',
                  value: _fmt.format(s.discount),
                  color: DesignTokens.pink),
            ],
          ),
        ],
      ),
    );
  }

  Widget _dueTab() {
    final due = _data?.due ?? const [];
    return RefreshIndicator(
      onRefresh: _loadAll,
      color: DesignTokens.primary,
      child: ListView(
        padding: pagePadding(context, top: 8),
        children: [
          _statGrid(),
          const SizedBox(height: 12),
          SectionHeader(title: 'Due clients (${due.length})'),
          if (due.isEmpty)
            const EmptyStateView(
                icon: Icons.check_circle_rounded, title: 'No due customers', message: 'All caught up.')
          else
            ...due.map((c) => DueClientCard(
                  client: c,
                  onPay: () => _openReceiveBill(c),
                  onToggleNetwork: (_) => _toggle(c),
                  onExtend: () => _extend(c),
                  onCall: c.phone.isEmpty ? null : () => _call(c.phone),
                  onSms: () => _sms(c),
                )),
        ],
      ),
    );
  }

  Widget _invoicesTab() {
    final invoices = _data?.invoices ?? const [];
    return Column(
      children: [
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          child: Row(
            children: [
              for (final f in ['all', 'due', 'open', 'paid', 'partial'])
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text(f.toUpperCase()),
                    selected: _invoiceFilter == f,
                    selectedColor: DesignTokens.primary.withValues(alpha: 0.2),
                    onSelected: (_) => _loadInvoices(f),
                  ),
                ),
            ],
          ),
        ),
        Expanded(
          child: invoices.isEmpty
              ? const EmptyStateView(icon: Icons.receipt_long_rounded, title: 'No invoices')
              : ListView.separated(
                  padding: pagePadding(context, top: 0),
                  itemCount: invoices.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 10),
                  itemBuilder: (context, i) => _invoiceRow(invoices[i]),
                ),
        ),
      ],
    );
  }

  Widget _invoiceRow(InvoiceRow inv) {
    return AppCard(
      onTap: inv.customerId == null
          ? null
          : () => Navigator.push(
                context,
                MaterialPageRoute(
                    builder: (_) => StaffCustomerDetailScreen(api: widget.api, customerId: inv.customerId!)),
              ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(9),
            decoration: BoxDecoration(
                color: DesignTokens.warning.withValues(alpha: 0.14),
                borderRadius: BorderRadius.circular(DesignTokens.radiusSm)),
            child: const Icon(Icons.receipt_rounded, color: DesignTokens.warning, size: 20),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(inv.invoiceNumber, style: const TextStyle(fontWeight: FontWeight.w700)),
                Text('${inv.customerName} · Due ${inv.dueDate}',
                    style: TextStyle(fontSize: 12, color: context.brand.textMuted)),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text('৳${_fmt.format(inv.balanceDue)}',
                  style: const TextStyle(fontWeight: FontWeight.w800, color: DesignTokens.danger)),
              Text(inv.status, style: TextStyle(fontSize: 10, color: context.brand.textMuted)),
            ],
          ),
        ],
      ),
    );
  }

  Widget _collectionsTab() {
    final cols = _data?.collections ?? const [];
    final cs = _data?.collectionSummary ?? CollectionSummary.empty;
    return RefreshIndicator(
      onRefresh: _loadAll,
      color: DesignTokens.primary,
      child: ListView(
        padding: pagePadding(context, top: 12),
        children: [
          Row(
            children: [
              Expanded(
                child: StatCard(
                    icon: Icons.receipt_long_rounded,
                    label: 'Total transaction',
                    value: '${cs.transactionCount}',
                    color: DesignTokens.primary),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: StatCard(
                    icon: Icons.savings_rounded,
                    label: 'Collected amount',
                    value: _fmt.format(cs.collected),
                    color: DesignTokens.success),
              ),
            ],
          ),
          const SizedBox(height: 14),
          if (cols.isEmpty)
            const EmptyStateView(icon: Icons.payments_rounded, title: 'No collections yet')
          else
            ...cols.map((r) => CollectionCard(
                  record: r,
                  onCall: r.phone.isEmpty ? null : () => _call(r.phone),
                  onPrint: () => showSnack(context, 'Receipt printing is on the web portal'),
                )),
        ],
      ),
    );
  }
}
