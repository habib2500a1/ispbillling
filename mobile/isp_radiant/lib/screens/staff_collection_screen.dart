import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../config/remote_config.dart';
import '../services/api_service.dart';
import '../services/offline_sync_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
import '../widgets/customer_search_result_tile.dart';
import '../widgets/state_views.dart';
import 'staff_billing_hub_screen.dart';
import 'staff_customer_detail_screen.dart';
import 'staff_expense_screen.dart';

class StaffCollectionScreen extends StatefulWidget {
  const StaffCollectionScreen({super.key, required this.api, this.active = false});

  final ApiService api;
  final bool active;

  @override
  State<StaffCollectionScreen> createState() => _StaffCollectionScreenState();
}

class _StaffCollectionScreenState extends State<StaffCollectionScreen> {
  final _searchCtrl = TextEditingController();
  List<Map<String, dynamic>> _results = [];
  Map<String, dynamic>? _wallet;
  bool _searching = false;
  late final OfflineSyncService _offline = OfflineSyncService(widget.api);
  int _pending = 0;
  String? _walletError;
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _loadWallet();
    _refreshPending();
    _searchCtrl.addListener(_onSearchChanged);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchCtrl.removeListener(_onSearchChanged);
    _searchCtrl.dispose();
    super.dispose();
  }

  void _onSearchChanged() {
    _debounce?.cancel();
    final q = _searchCtrl.text.trim();
    if (q.length < 2) {
      setState(() => _results = []);
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 400), () => _search(silent: true));
  }

  Future<void> _refreshPending() async {
    final n = await _offline.pendingCount();
    if (mounted) setState(() => _pending = n);
  }

  @override
  void didUpdateWidget(StaffCollectionScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active && !oldWidget.active) _loadWallet();
  }

  Future<void> _loadWallet() async {
    try {
      final w = await widget.api.collectorWallet();
      if (mounted) setState(() {
        _wallet = w;
        _walletError = null;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _walletError = e.message);
    } catch (_) {
      if (mounted) setState(() => _walletError = 'Could not load wallet');
    }
  }

  Future<void> _search({bool silent = false}) async {
    final q = _searchCtrl.text.trim();
    if (q.length < 2) {
      if (!silent) showSnack(context, 'Type at least 2 characters', isError: true);
      return;
    }
    setState(() => _searching = true);
    try {
      final list = await widget.api.searchCustomers(q);
      if (mounted) setState(() => _results = list);
    } on ApiException catch (e) {
      if (mounted && !silent) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _searching = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final fmt = NumberFormat('#,##0.00');
    final balance = (_wallet?['cash_in_hand'] as num?)?.toDouble() ?? (_wallet?['balance'] as num?)?.toDouble();

    return ListView(
      padding: pagePadding(context),
      children: [
        if (_pending > 0 && RemoteConfig.offlineSync)
          Card(
            color: AppTheme.warning.withValues(alpha: 0.12),
            child: ListTile(
              title: Text('$_pending queued collection(s)'),
              trailing: TextButton(
                onPressed: () async {
                  await _offline.flush();
                  await _refreshPending();
                  if (mounted) showSnack(context, 'Sync attempted');
                },
                child: const Text('Sync'),
              ),
            ),
          ),
        if (_walletError != null)
          Card(
            color: AppTheme.warning.withValues(alpha: 0.12),
            child: ListTile(
              leading: const Icon(Icons.info_outline, color: AppTheme.warning),
              title: Text(_walletError!, style: const TextStyle(fontSize: 13)),
              subtitle: const Text('Collection still works — search a client below'),
            ),
          )
        else if (balance != null)
          Card(
            color: AppTheme.success.withValues(alpha: 0.1),
            child: ListTile(
              title: const Text('Collector wallet'),
              trailing: Text('${fmt.format(balance)} BDT', style: const TextStyle(fontWeight: FontWeight.bold)),
            ),
          ),
        Row(
          children: [
            Expanded(
              child: OutlinedButton.icon(
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => StaffBillingHubScreen(api: widget.api)),
                ),
                icon: const Icon(Icons.history),
                label: const Text('All collections'),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: OutlinedButton.icon(
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => StaffExpenseScreen(api: widget.api)),
                ),
                icon: const Icon(Icons.receipt_long),
                label: const Text('Expense'),
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        const SectionTitle('Bill Receive'),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              children: [
                TextField(
                  controller: _searchCtrl,
                  decoration: InputDecoration(
                    labelText: 'Search customer',
                    prefixIcon: const Icon(Icons.search),
                    suffixIcon: _searching
                        ? const Padding(
                            padding: EdgeInsets.all(12),
                            child: SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)),
                          )
                        : (_searchCtrl.text.isNotEmpty
                            ? IconButton(icon: const Icon(Icons.clear), onPressed: () => _searchCtrl.clear())
                            : null),
                  ),
                  onSubmitted: (_) => _search(),
                ),
                const SizedBox(height: 4),
                Text(
                  'Same name হলে automatically দেখাবে — code দিয়ে select করুন',
                  style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
                ),
              ],
            ),
          ),
        ),
        ..._results.map((c) {
          final id = (c['id'] as num).toInt();
          return CustomerSearchResultTile(
            customer: c,
            showDue: true,
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => StaffCustomerDetailScreen(api: widget.api, customerId: id)),
            ),
          );
        }),
      ],
    );
  }
}
