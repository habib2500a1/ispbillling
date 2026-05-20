import 'dart:async';

import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../widgets/customer_search_result_tile.dart';
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
  final _search = TextEditingController();
  List<Map<String, dynamic>> _list = [];
  bool _loading = false;
  int _page = 1;
  bool _hasMore = true;
  String _statusFilter = '';
  bool _dueOnly = false;
  Timer? _debounce;
  bool _searchMode = false;

  Future<void> _load({String? q, bool reset = true}) async {
    if (reset) {
      _page = 1;
      _hasMore = true;
    }
    setState(() => _loading = true);
    try {
      if (q != null && q.length >= 2) {
        final list = await widget.api.searchCustomers(q);
        if (mounted) setState(() {
          _list = list;
          _searchMode = true;
        });
      } else {
        if (mounted) setState(() => _searchMode = false);
        final body = await widget.api.staffCustomers(
          page: _page,
          status: _statusFilter.isEmpty ? null : _statusFilter,
          dueOnly: _dueOnly,
        );
        final list = (body['data'] as List<dynamic>?)?.map((e) => Map<String, dynamic>.from(e as Map)).toList() ?? [];
        final meta = body['meta'] as Map<String, dynamic>? ?? {};
        final lastPage = (meta['last_page'] as num?)?.toInt() ?? 1;
        if (mounted) {
          setState(() {
            if (reset) {
              _list = list;
            } else {
              _list = [..._list, ...list];
            }
            _hasMore = _page < lastPage;
          });
        }
      }
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  void initState() {
    super.initState();
    _load();
    _search.addListener(_onSearchChanged);
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

  @override
  void dispose() {
    _debounce?.cancel();
    _search.removeListener(_onSearchChanged);
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'Client list',
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => StaffAddCustomerScreen(api: widget.api)),
        ),
        icon: const Icon(Icons.person_add),
        label: const Text('New'),
      ),
      body: Column(
        children: [
          Material(
            color: AppTheme.card,
            elevation: 2,
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _search,
                      decoration: const InputDecoration(
                        hintText: 'Search code, name, phone…',
                        prefixIcon: Icon(Icons.search),
                        isDense: true,
                      ),
                      onSubmitted: (v) => _load(q: v),
                    ),
                  ),
                  const SizedBox(width: 8),
                  FilledButton(onPressed: () => _load(q: _search.text.trim()), child: const Text('Search')),
                ],
              ),
            ),
          ),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            child: Row(
              children: [
                FilterChip(label: const Text('All'), selected: _statusFilter.isEmpty && !_dueOnly, onSelected: (_) => setState(() { _statusFilter = ''; _dueOnly = false; _load(); })),
                FilterChip(label: const Text('Active'), selected: _statusFilter == 'active', onSelected: (_) => setState(() { _statusFilter = 'active'; _dueOnly = false; _load(); })),
                FilterChip(label: const Text('Suspended'), selected: _statusFilter == 'suspended', onSelected: (_) => setState(() { _statusFilter = 'suspended'; _dueOnly = false; _load(); })),
                FilterChip(label: const Text('Due only'), selected: _dueOnly, onSelected: (_) => setState(() { _dueOnly = true; _statusFilter = ''; _load(); })),
              ],
            ),
          ),
          if (_loading) const LinearProgressIndicator(minHeight: 3, color: AppTheme.accent),
          Expanded(
            child: ListView.builder(
              padding: const EdgeInsets.all(12),
              itemCount: _list.length + (_hasMore && _search.text.trim().length < 2 ? 1 : 0),
              itemBuilder: (context, i) {
                if (i >= _list.length) {
                  return Padding(
                    padding: const EdgeInsets.all(8),
                    child: OutlinedButton(
                      onPressed: _loading
                          ? null
                          : () {
                              _page++;
                              _load(reset: false);
                            },
                      child: const Text('Load more customers'),
                    ),
                  );
                }
                final c = _list[i];
                final id = (c['id'] as num).toInt();
                if (_searchMode) {
                  return CustomerSearchResultTile(
                    customer: c,
                    showDue: true,
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => StaffCustomerDetailScreen(api: widget.api, customerId: id),
                      ),
                    ).then((_) => _load(q: _search.text.trim().length >= 2 ? _search.text.trim() : null)),
                  );
                }
                final online = c['is_online'] == true;
                return Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    leading: CircleAvatar(
                      backgroundColor: (online ? AppTheme.success : Colors.grey).withValues(alpha: 0.15),
                      child: Icon(Icons.person, color: online ? AppTheme.success : Colors.grey),
                    ),
                    title: Text(c['name']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w600)),
                    subtitle: Text('${c['customer_code']} · ${c['package'] ?? c['phone'] ?? ''}'),
                    trailing: Icon(
                      Icons.circle,
                      size: 12,
                      color: online ? AppTheme.success : Colors.grey.shade400,
                    ),
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => StaffCustomerDetailScreen(api: widget.api, customerId: id),
                      ),
                    ).then((_) => _load()),
                    onLongPress: () async {
                      final detail = await widget.api.staffCustomerDetail(id);
                      if (!context.mounted) return;
                      final ok = await Navigator.push<bool>(
                        context,
                        MaterialPageRoute(
                          builder: (_) => StaffCustomerEditScreen(api: widget.api, customer: detail),
                        ),
                      );
                      if (ok == true) _load();
                    },
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
