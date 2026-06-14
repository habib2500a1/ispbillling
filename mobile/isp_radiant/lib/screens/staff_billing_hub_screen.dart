import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/network/api_result.dart';
import '../core/widgets/collection_card.dart';
import '../core/widgets/due_client_card.dart';
import '../core/widgets/skeleton.dart';
import '../core/widgets/states.dart';
import '../design_system/radiant_tokens.dart';
import '../features/staff_billing/data/staff_billing_repository.dart';
import '../features/staff_billing/domain/billing_models.dart';
import '../services/api_service.dart';
import '../utils/app_nav.dart';
import '../widgets/legacy_softify_screen_header.dart';
import '../widgets/staff_receipt_launcher.dart';
import 'staff_customer_detail_screen.dart';
import 'staff_receive_bill_screen.dart';

enum _BillingView { due, invoices, collections }

/// Legacy SOFTIFY Billing List — stats, due clients, pay, Mikrotik toggle, bulk SMS.
class StaffBillingHubScreen extends StatefulWidget {
  const StaffBillingHubScreen({super.key, required this.api, this.embedded = false});

  final ApiService api;
  final bool embedded;

  @override
  State<StaffBillingHubScreen> createState() => _StaffBillingHubScreenState();
}

class _StaffBillingHubScreenState extends State<StaffBillingHubScreen> {
  late final StaffBillingRepository _repo = StaffBillingRepository(widget.api);
  final _search = TextEditingController();
  final _fmt = NumberFormat('#,##0.00');
  final _selected = <int>{};

  BillingSummary _summary = BillingSummary.empty;
  List<DueClient> _due = [];
  List<InvoiceRow> _invoices = [];
  List<CollectionRecord> _collections = [];
  CollectionSummary _collectionSummary = CollectionSummary.empty;

  bool _loading = true;
  bool _loadingMore = false;
  Failure? _error;
  int _page = 1;
  int _lastPage = 1;
  int _total = 0;
  _BillingView _view = _BillingView.due;
  String _invoiceFilter = 'all';
  bool _showSearch = false;
  Timer? _debounce;

  bool get _hasMore => _view == _BillingView.due && _page < _lastPage;

  @override
  void initState() {
    super.initState();
    _search.addListener(_onSearchChanged);
    _load();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.removeListener(_onSearchChanged);
    _search.dispose();
    super.dispose();
  }

  void _onSearchChanged() {
    if (_view != _BillingView.due) return;
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () => _loadDue(reset: true));
  }

  Future<void> _load() async {
    if (_view == _BillingView.due) {
      await _loadDue(reset: true);
      return;
    }
    if (_view == _BillingView.invoices) {
      await _loadInvoices();
      return;
    }
    await _loadCollections();
  }

  Future<void> _loadDue({bool reset = true}) async {
    if (reset) {
      setState(() {
        _loading = true;
        _error = null;
        _page = 1;
      });
    }
    final summaryRes = await _repo.loadSummary();
    final dueRes = await _repo.loadDue(page: 1, q: _search.text.trim());
    if (!mounted) return;
    summaryRes.when(
      ok: (s) => _summary = s,
      err: (_) {},
    );
    dueRes.when(
      ok: (p) => setState(() {
        _due = p.items;
        _total = p.total;
        _lastPage = p.lastPage;
        _page = p.page;
        _loading = false;
        _selected.removeWhere((id) => !_due.any((c) => c.id == id));
      }),
      err: (f) => setState(() {
        _error = f;
        _loading = false;
      }),
    );
  }

  Future<void> _loadMoreDue() async {
    if (_loadingMore || !_hasMore) return;
    setState(() => _loadingMore = true);
    final res = await _repo.loadDue(page: _page + 1, q: _search.text.trim());
    if (!mounted) return;
    res.when(
      ok: (p) => setState(() {
        _due = [..._due, ...p.items];
        _page = p.page;
        _lastPage = p.lastPage;
        _loadingMore = false;
      }),
      err: (_) => setState(() => _loadingMore = false),
    );
  }

  Future<void> _loadInvoices() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final res = await _repo.invoices(_invoiceFilter);
    if (!mounted) return;
    res.when(
      ok: (list) => setState(() {
        _invoices = list;
        _loading = false;
      }),
      err: (f) => setState(() {
        _error = f;
        _loading = false;
      }),
    );
  }

  Future<void> _loadCollections() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final res = await _repo.loadAll();
    if (!mounted) return;
    res.when(
      ok: (b) => setState(() {
        _collections = b.collections;
        _collectionSummary = b.collectionSummary;
        _summary = b.summary;
        _loading = false;
      }),
      err: (f) => setState(() {
        _error = f;
        _loading = false;
      }),
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
        if (ok == true) _load();
      },
      err: (f) async => showSnack(context, f.message, isError: true),
    );
  }

  Future<void> _toggle(DueClient c) async {
    final res = await _repo.toggleNetwork(c.id);
    if (!mounted) return;
    res.when(ok: (_) => _load(), err: (f) => showSnack(context, f.message, isError: true));
  }

  Future<void> _extend(DueClient c) async {
    final res = await _repo.extendService(c.id);
    if (!mounted) return;
    res.when(
      ok: (_) {
        showSnack(context, 'Service extended 30 days');
        _load();
      },
      err: (f) => showSnack(context, f.message, isError: true),
    );
  }

  Future<void> _call(String phone) async {
    final uri = Uri.parse('tel:$phone');
    if (await canLaunchUrl(uri)) await launchUrl(uri);
  }

  Future<void> _smsSelected() async {
    if (_selected.isEmpty) {
      final ok = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('SMS due clients'),
          content: const Text('No clients selected. Send due reminder to all due clients?'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
            FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Send all')),
          ],
        ),
      );
      if (ok != true) return;
      try {
        final res = await widget.api.staffSmsBulkDue();
        if (mounted) showSnack(context, res['message']?.toString() ?? 'SMS sent');
      } on ApiException catch (e) {
        if (mounted) showSnack(context, e.message, isError: true);
      }
      return;
    }

    var sent = 0;
    for (final id in _selected) {
      try {
        await widget.api.staffSmsReminder(id);
        sent++;
      } catch (_) {}
    }
    if (mounted) showSnack(context, 'SMS sent to $sent client(s)');
  }

  Future<void> _openFilterSheet() async {
    final picked = await showModalBottomSheet<_BillingView>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const ListTile(title: Text('Billing view', style: TextStyle(fontWeight: FontWeight.w700))),
            ListTile(
              title: const Text('Due clients'),
              trailing: _view == _BillingView.due ? const Icon(Icons.check, color: RadiantTokens.brand) : null,
              onTap: () => Navigator.pop(ctx, _BillingView.due),
            ),
            ListTile(
              title: const Text('Invoices'),
              trailing: _view == _BillingView.invoices ? const Icon(Icons.check, color: RadiantTokens.brand) : null,
              onTap: () => Navigator.pop(ctx, _BillingView.invoices),
            ),
            ListTile(
              title: const Text('Collections'),
              trailing: _view == _BillingView.collections ? const Icon(Icons.check, color: RadiantTokens.brand) : null,
              onTap: () => Navigator.pop(ctx, _BillingView.collections),
            ),
          ],
        ),
      ),
    );
    if (picked != null && picked != _view) {
      setState(() => _view = picked);
      _load();
    }
  }

  void _toggleSelect(DueClient c, bool v) {
    setState(() {
      if (v) {
        _selected.add(c.id);
      } else {
        _selected.remove(c.id);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return LegacySoftifyPage(
      child: Scaffold(
        backgroundColor: RadiantTokens.legacyPageBg,
      body: Column(
        children: [
          LegacySoftifyScreenHeader(
            title: 'Billing List',
            showBack: !widget.embedded,
            toolbar: _view == _BillingView.due
                ? Row(
                    children: [
                      Expanded(
                        child: Material(
                          color: RadiantTokens.legacyHeaderBlue,
                          borderRadius: BorderRadius.circular(20),
                          child: InkWell(
                            borderRadius: BorderRadius.circular(20),
                            onTap: _smsSelected,
                            child: const Padding(
                              padding: EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(Icons.send, color: Colors.white, size: 18),
                                  SizedBox(width: 6),
                                  Flexible(
                                    child: Text(
                                      'Sms Selected Clients',
                                      style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 12),
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Material(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(8),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(8),
                          onTap: () => setState(() => _showSearch = !_showSearch),
                          child: SizedBox(
                            width: 44,
                            height: 44,
                            child: Icon(
                              _showSearch ? Icons.search_off : Icons.search,
                              color: Colors.grey.shade700,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Material(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(8),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(8),
                          onTap: _openFilterSheet,
                          child: const SizedBox(
                            width: 44,
                            height: 44,
                            child: Icon(Icons.filter_list, color: RadiantTokens.brand),
                          ),
                        ),
                      ),
                    ],
                  )
                : LegacySoftifySearchToolbar(
                    controller: _search,
                    onFilter: _openFilterSheet,
                    onClear: () {
                      _search.clear();
                      _load();
                    },
                  ),
          ),
          if (_view == _BillingView.due && _showSearch)
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 0),
              child: Material(
                color: Colors.white,
                borderRadius: BorderRadius.circular(8),
                child: TextField(
                  controller: _search,
                  style: const TextStyle(fontSize: 14),
                  decoration: InputDecoration(
                    hintText: 'Name/C.Code/Mobile/UserID/IP',
                    hintStyle: TextStyle(fontSize: 13, color: Colors.grey.shade500),
                    border: InputBorder.none,
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                    suffixIcon: _search.text.isNotEmpty
                        ? IconButton(
                            icon: const Icon(Icons.close, color: Colors.red, size: 20),
                            onPressed: () {
                              _search.clear();
                              _loadDue(reset: true);
                            },
                          )
                        : null,
                  ),
                ),
              ),
            ),
          if (_view == _BillingView.due && !_loading && _error == null) ...[
            LegacyBillingStatsGrid(
              paidClients: _summary.paidClients,
              unpaidClients: _summary.unpaidClients,
              receivedBill: _summary.collected,
              dueAmount: _summary.due,
              formatter: (v) => _fmt.format(v),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 8, 14, 0),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  'Showing Results ${_due.length} of $_total',
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                ),
              ),
            ),
          ],
          if (_view != _BillingView.due)
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 8, 14, 0),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Chip(
                  label: Text(
                    _view == _BillingView.invoices ? 'Invoices' : 'Collections',
                    style: const TextStyle(fontSize: 11),
                  ),
                  deleteIcon: const Icon(Icons.close, size: 16),
                  onDeleted: () {
                    setState(() => _view = _BillingView.due);
                    _load();
                  },
                ),
              ),
            ),
          Expanded(child: _buildBody()),
        ],
      ),
      ),
    );
  }

  Widget _buildBody() {
    if (_loading) return const SkeletonList(count: 6, rowHeight: 130);
    if (_error != null) return ErrorStateView(failure: _error!, onRetry: _load);

    switch (_view) {
      case _BillingView.due:
        return _dueList();
      case _BillingView.invoices:
        return _invoicesList();
      case _BillingView.collections:
        return _collectionsList();
    }
  }

  Widget _dueList() {
    if (_due.isEmpty) {
      return const EmptyStateView(icon: Icons.check_circle_rounded, title: 'No due customers', message: 'All caught up.');
    }
    return RefreshIndicator(
      onRefresh: _load,
      color: RadiantTokens.brand,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(12, 10, 12, 24),
        itemCount: _due.length + (_hasMore ? 1 : 0),
        itemBuilder: (context, i) {
          if (i >= _due.length) {
            return Padding(
              padding: const EdgeInsets.all(8),
              child: Center(
                child: _loadingMore
                    ? const CircularProgressIndicator()
                    : OutlinedButton(onPressed: _loadMoreDue, child: const Text('Load more')),
              ),
            );
          }
          final c = _due[i];
          return DueClientCard(
            client: c,
            selected: _selected.contains(c.id),
            onSelect: (v) => _toggleSelect(c, v),
            onPay: () => _openReceiveBill(c),
            onToggleNetwork: (_) => _toggle(c),
            onExtend: () => _extend(c),
            onCall: c.phone.isEmpty ? null : () => _call(c.phone),
            onInfo: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => StaffCustomerDetailScreen(api: widget.api, customerId: c.id)),
            ),
          );
        },
      ),
    );
  }

  Widget _invoicesList() {
    if (_invoices.isEmpty) {
      return const EmptyStateView(icon: Icons.receipt_long_rounded, title: 'No invoices');
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.all(12),
        itemCount: _invoices.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (context, i) {
          final inv = _invoices[i];
          return Material(
            color: Colors.white,
            borderRadius: BorderRadius.circular(10),
            child: ListTile(
              title: Text(inv.invoiceNumber, style: const TextStyle(fontWeight: FontWeight.w700)),
              subtitle: Text('${inv.customerName} · ${inv.dueDate}'),
              trailing: Text('৳${_fmt.format(inv.balanceDue)}',
                  style: const TextStyle(color: Colors.red, fontWeight: FontWeight.w800)),
              onTap: inv.customerId == null
                  ? null
                  : () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => StaffCustomerDetailScreen(api: widget.api, customerId: inv.customerId!),
                        ),
                      ),
            ),
          );
        },
      ),
    );
  }

  Widget _collectionsList() {
    if (_collections.isEmpty) {
      return const EmptyStateView(icon: Icons.payments_rounded, title: 'No collections yet');
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(12),
        children: [
          Row(
            children: [
              Expanded(child: _miniKpi('Transactions', '${_collectionSummary.transactionCount}')),
              const SizedBox(width: 10),
              Expanded(child: _miniKpi('Collected', '৳${_fmt.format(_collectionSummary.collected)}')),
            ],
          ),
          const SizedBox(height: 12),
          ..._collections.map((r) => CollectionCard(
                record: r,
                onCall: r.phone.isEmpty ? null : () => _call(r.phone),
                onPrint: r.paymentId > 0
                    ? () => StaffReceiptLauncher.open(
                          context,
                          api: widget.api,
                          paymentId: r.paymentId,
                          initialPdfUrl: r.receiptPdfUrl.isNotEmpty ? r.receiptPdfUrl : null,
                          seedData: {
                            'receipt_number': r.receiptNumber,
                            'customer_name': r.name,
                            'customer_code': r.customerCode,
                            'phone': r.phone,
                            'amount': r.amount,
                            'discount': r.discount,
                            'due': r.due,
                            'method': r.method,
                            'recorded_by': r.receivedBy,
                            'paid_at': r.createdAt,
                            'receipt_pdf_url': r.receiptPdfUrl,
                          },
                        )
                    : null,
              )),
        ],
      ),
    );
  }

  Widget _miniKpi(String label, String value) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: RadiantTokens.legacyCardBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: TextStyle(fontSize: 11, color: Colors.grey.shade600)),
          Text(value, style: const TextStyle(fontWeight: FontWeight.w800)),
        ],
      ),
    );
  }
}
