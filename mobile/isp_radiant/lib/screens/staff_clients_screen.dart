import 'dart:async';

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/network/api_result.dart';
import '../core/theme/design_tokens.dart';
import '../core/widgets/client_card.dart';
import '../core/widgets/skeleton.dart';
import '../core/widgets/states.dart';
import '../features/staff_customers/data/staff_customers_repository.dart';
import '../features/staff_customers/domain/customer_list_item.dart';
import '../services/api_service.dart';
import '../utils/app_nav.dart';
import '../widgets/page_scaffold.dart';
import 'staff_add_customer_screen.dart';
import 'staff_customer_detail_screen.dart';
import 'staff_customer_edit_screen.dart';

class StaffClientsScreen extends StatefulWidget {
  const StaffClientsScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<StaffClientsScreen> createState() => _StaffClientsScreenState();
}

class _StaffClientsScreenState extends State<StaffClientsScreen> {
  late final StaffCustomersRepository _repo = StaffCustomersRepository(widget.api);
  final _search = TextEditingController();
  List<CustomerListItem> _list = [];
  bool _loading = false;
  bool _loadingMore = false;
  Failure? _error;
  int _page = 1;
  int _lastPage = 1;
  int _total = 0;
  String _statusFilter = '';
  bool _dueOnly = false;
  bool _searchMode = false;
  Timer? _debounce;

  bool get _hasMore => !_searchMode && _page < _lastPage;

  @override
  void initState() {
    super.initState();
    _load();
    _search.addListener(_onSearchChanged);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.removeListener(_onSearchChanged);
    _search.dispose();
    super.dispose();
  }

  void _onSearchChanged() {
    _debounce?.cancel();
    final q = _search.text.trim();
    if (q.length < 2) {
      if (_searchMode) _load();
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 400), () => _load(q: q));
  }

  Future<void> _load({String? q}) async {
    setState(() {
      _loading = true;
      _error = null;
    });
    if (q != null && q.length >= 2) {
      final res = await _repo.search(q);
      if (!mounted) return;
      res.when(
        ok: (list) => setState(() {
          _list = list;
          _searchMode = true;
          _loading = false;
        }),
        err: (f) => setState(() {
          _error = f;
          _loading = false;
        }),
      );
      return;
    }
    _page = 1;
    final res = await _repo.list(page: 1, status: _statusFilter, dueOnly: _dueOnly);
    if (!mounted) return;
    res.when(
      ok: (p) => setState(() {
        _searchMode = false;
        _list = p.items;
        _lastPage = p.lastPage;
        _total = p.total;
        _loading = false;
      }),
      err: (f) => setState(() {
        _error = f;
        _loading = false;
      }),
    );
  }

  Future<void> _loadMore() async {
    if (_loadingMore || !_hasMore) return;
    setState(() => _loadingMore = true);
    final res = await _repo.list(page: _page + 1, status: _statusFilter, dueOnly: _dueOnly);
    if (!mounted) return;
    res.when(
      ok: (p) => setState(() {
        _page = p.page;
        _lastPage = p.lastPage;
        _list = [..._list, ...p.items];
        _loadingMore = false;
      }),
      err: (_) => setState(() => _loadingMore = false),
    );
  }

  void _setFilter({String status = '', bool dueOnly = false}) {
    setState(() {
      _statusFilter = status;
      _dueOnly = dueOnly;
    });
    _load();
  }

  Future<void> _openDetail(CustomerListItem c) async {
    await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => StaffCustomerDetailScreen(api: widget.api, customerId: c.id)),
    );
    _load(q: _searchMode ? _search.text.trim() : null);
  }

  Future<void> _openEdit(CustomerListItem c) async {
    final res = await _repo.detail(c.id);
    if (!mounted) return;
    await res.when(
      ok: (detail) async {
        final ok = await Navigator.push<bool>(
          context,
          MaterialPageRoute(builder: (_) => StaffCustomerEditScreen(api: widget.api, customer: detail)),
        );
        if (ok == true) _load();
      },
      err: (f) async => showSnack(context, f.message, isError: true),
    );
  }

  Future<void> _toggle(CustomerListItem c) async {
    final res = await _repo.toggleNetwork(c.id);
    if (!mounted) return;
    res.when(ok: (_) => _load(), err: (f) => showSnack(context, f.message, isError: true));
  }

  Future<void> _call(CustomerListItem c) async {
    final uri = Uri.parse('tel:${c.phone}');
    if (await canLaunchUrl(uri)) await launchUrl(uri);
  }

  Future<void> _sms(CustomerListItem c) async {
    final res = await _repo.smsReminder(c.id);
    if (!mounted) return;
    res.when(
      ok: (_) => showSnack(context, 'SMS sent'),
      err: (f) => showSnack(context, f.message, isError: true),
    );
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'Client list',
      useGradientBody: true,
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => StaffAddCustomerScreen(api: widget.api)),
        ).then((_) => _load()),
        backgroundColor: DesignTokens.primary,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.person_add_rounded),
        label: const Text('New'),
      ),
      body: Column(
        children: [
          Container(
            color: context.cs.surface,
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 8),
            child: Column(
              children: [
                TextField(
                  controller: _search,
                  decoration: InputDecoration(
                    hintText: 'Name / Code / Mobile / User ID / IP',
                    prefixIcon: const Icon(Icons.search_rounded),
                    suffixIcon: _search.text.isEmpty
                        ? null
                        : IconButton(
                            icon: const Icon(Icons.close_rounded),
                            onPressed: () {
                              _search.clear();
                              _load();
                            },
                          ),
                    isDense: true,
                  ),
                ),
                const SizedBox(height: 8),
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      _chip('All', _statusFilter.isEmpty && !_dueOnly, () => _setFilter()),
                      _chip('Active', _statusFilter == 'active', () => _setFilter(status: 'active')),
                      _chip('Suspended', _statusFilter == 'suspended', () => _setFilter(status: 'suspended')),
                      _chip('Due only', _dueOnly, () => _setFilter(dueOnly: true)),
                    ],
                  ),
                ),
              ],
            ),
          ),
          if (!_loading && _error == null)
            Align(
              alignment: Alignment.centerLeft,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
                child: Text(
                  _searchMode
                      ? 'Search · ${_list.length} result(s)'
                      : 'Showing ${_list.length}${_total > 0 ? ' of $_total' : ''}',
                  style: TextStyle(fontSize: 12, color: context.brand.textMuted),
                ),
              ),
            ),
          Expanded(child: _buildList()),
        ],
      ),
    );
  }

  Widget _chip(String label, bool selected, VoidCallback onTap) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ChoiceChip(
        label: Text(label),
        selected: selected,
        onSelected: (_) => onTap(),
        selectedColor: DesignTokens.primary.withValues(alpha: 0.2),
        labelStyle: TextStyle(
          color: selected ? DesignTokens.primary : context.brand.textMuted,
          fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
        ),
      ),
    );
  }

  Widget _buildList() {
    if (_loading) return const SkeletonList(count: 6, rowHeight: 120);
    if (_error != null && _list.isEmpty) return ErrorStateView(failure: _error!, onRetry: _load);
    if (_list.isEmpty) {
      return const EmptyStateView(icon: Icons.people_outline_rounded, title: 'No clients found');
    }

    return RefreshIndicator(
      onRefresh: _load,
      color: DesignTokens.primary,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
        itemCount: _list.length + (_hasMore ? 1 : 0),
        itemBuilder: (context, i) {
          if (i >= _list.length) {
            return Padding(
              padding: const EdgeInsets.all(8),
              child: OutlinedButton(
                onPressed: _loadingMore ? null : _loadMore,
                child: _loadingMore
                    ? const SizedBox(
                        width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Text('Load more customers'),
              ),
            );
          }
          final c = _list[i];
          return ClientCard(
            client: c,
            onTap: () => _openDetail(c),
            onLongPress: () => _openEdit(c),
            onToggleNetwork: (_) => _toggle(c),
            onCall: c.phone.isEmpty ? null : () => _call(c),
            onSms: () => _sms(c),
          );
        },
      ),
    );
  }
}
