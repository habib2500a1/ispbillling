import 'dart:async';

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/network/api_result.dart';
import '../core/widgets/skeleton.dart';
import '../core/widgets/states.dart';
import '../design_system/radiant_tokens.dart';
import '../features/staff_customers/data/staff_customers_repository.dart';
import '../features/staff_customers/domain/customer_list_item.dart';
import '../services/api_service.dart';
import '../utils/app_nav.dart';
import '../widgets/legacy_client_card.dart';
import 'staff_add_customer_screen.dart';
import 'staff_customer_detail_screen.dart';
import 'staff_customer_edit_screen.dart';

enum _ClientListFilter { all, active, due, suspended, expired }

/// Legacy SOFTIFY Client List — search, filters (all/active/due/suspended/expired), edit, Mikrotik toggle.
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
  _ClientListFilter _filter = _ClientListFilter.all;
  bool _searchMode = false;
  Timer? _debounce;

  bool get _hasMore => !_searchMode && _page < _lastPage;
  bool get _showDue => _filter == _ClientListFilter.due;

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
    setState(() {});
    _debounce?.cancel();
    final q = _search.text.trim();
    if (q.length < 2) {
      if (_searchMode) _load();
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 400), () => _load(q: q));
  }

  String? get _statusParam {
    switch (_filter) {
      case _ClientListFilter.active:
        return 'active';
      case _ClientListFilter.expired:
        return 'expired';
      case _ClientListFilter.due:
      case _ClientListFilter.suspended:
      case _ClientListFilter.all:
        return null;
    }
  }

  bool get _dueOnly => _filter == _ClientListFilter.due;
  bool get _networkSuspended => _filter == _ClientListFilter.suspended;

  String get _filterLabel {
    switch (_filter) {
      case _ClientListFilter.all:
        return 'All clients';
      case _ClientListFilter.active:
        return 'Active';
      case _ClientListFilter.due:
        return 'Due list';
      case _ClientListFilter.suspended:
        return 'Suspended';
      case _ClientListFilter.expired:
        return 'Expired';
    }
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
          _total = list.length;
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
    final res = await _repo.list(
      page: 1,
      status: _statusParam,
      dueOnly: _dueOnly,
      networkSuspended: _networkSuspended,
    );
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
    final res = await _repo.list(
      page: _page + 1,
      status: _statusParam,
      dueOnly: _dueOnly,
      networkSuspended: _networkSuspended,
    );
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

  void _setFilter(_ClientListFilter f) {
    setState(() => _filter = f);
    _search.clear();
    _load();
  }

  Future<void> _openFilterSheet() async {
    final picked = await showModalBottomSheet<_ClientListFilter>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const ListTile(title: Text('Client list filter', style: TextStyle(fontWeight: FontWeight.w700))),
            for (final f in _ClientListFilter.values)
              ListTile(
                title: Text(_labelFor(f)),
                trailing: _filter == f ? const Icon(Icons.check, color: RadiantTokens.brand) : null,
                onTap: () => Navigator.pop(ctx, f),
              ),
            ListTile(
              leading: const Icon(Icons.person_add_alt_1, color: RadiantTokens.brand),
              title: const Text('Add new client'),
              onTap: () {
                Navigator.pop(ctx);
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => StaffAddCustomerScreen(api: widget.api)),
                ).then((_) => _load());
              },
            ),
          ],
        ),
      ),
    );
    if (picked != null && picked != _filter) _setFilter(picked);
  }

  String _labelFor(_ClientListFilter f) {
    switch (f) {
      case _ClientListFilter.all:
        return 'All clients';
      case _ClientListFilter.active:
        return 'Active clients';
      case _ClientListFilter.due:
        return 'Due list';
      case _ClientListFilter.suspended:
        return 'Suspended (Mikrotik off)';
      case _ClientListFilter.expired:
        return 'Expired';
    }
  }

  Future<void> _openDetail(CustomerListItem c) async {
    await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => StaffCustomerDetailScreen(api: widget.api, customerId: c.id)),
    );
    _load(q: _searchMode && _search.text.trim().length >= 2 ? _search.text.trim() : null);
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
    return Scaffold(
      backgroundColor: const Color(0xFFF0F4F8),
      body: Column(
        children: [
          _header(),
          if (!_loading && _error == null)
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 8, 14, 0),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  _searchMode
                      ? 'Showing result: ${_list.length}'
                      : 'Showing result: ${_list.length} of $_total',
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                ),
              ),
            ),
          if (_filter != _ClientListFilter.all)
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 4, 14, 0),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Chip(
                  label: Text(_filterLabel, style: const TextStyle(fontSize: 11)),
                  deleteIcon: const Icon(Icons.close, size: 16),
                  onDeleted: () => _setFilter(_ClientListFilter.all),
                ),
              ),
            ),
          Expanded(child: _buildList()),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        backgroundColor: RadiantTokens.brand,
        onPressed: () => Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => StaffAddCustomerScreen(api: widget.api)),
        ).then((_) => _load()),
        child: const Icon(Icons.person_add, color: Colors.white),
      ),
    );
  }

  Widget _header() {
    return Container(
      color: RadiantTokens.brand,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(4, 4, 12, 14),
          child: Column(
            children: [
              Row(
                children: [
                  IconButton(
                    onPressed: () => Navigator.maybePop(context),
                    icon: const Icon(Icons.arrow_back, color: Colors.white),
                  ),
                  const Expanded(
                    child: Text(
                      'Client List',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w600),
                    ),
                  ),
                  const SizedBox(width: 48),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
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
                                    _load();
                                  },
                                )
                              : null,
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
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildList() {
    if (_loading) return const SkeletonList(count: 6, rowHeight: 120);
    if (_error != null && _list.isEmpty) return ErrorStateView(failure: _error!, onRetry: _load);
    if (_list.isEmpty) {
      return const EmptyStateView(icon: Icons.people_outline, title: 'No clients found');
    }

    return RefreshIndicator(
      onRefresh: _load,
      color: RadiantTokens.brand,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(12, 10, 12, 88),
        itemCount: _list.length + (_hasMore ? 1 : 0),
        itemBuilder: (context, i) {
          if (i >= _list.length) {
            return Padding(
              padding: const EdgeInsets.all(8),
              child: Center(
                child: _loadingMore
                    ? const CircularProgressIndicator()
                    : OutlinedButton(onPressed: _loadMore, child: const Text('Load more')),
              ),
            );
          }
          final c = _list[i];
          return LegacyClientCard(
            client: c,
            showDue: _showDue || c.due > 0,
            onTap: () => _openDetail(c),
            onEdit: () => _openEdit(c),
            onToggleNetwork: (_) => _toggle(c),
            onCall: c.phone.isEmpty ? null : () => _call(c),
            onSms: () => _sms(c),
          );
        },
      ),
    );
  }
}
