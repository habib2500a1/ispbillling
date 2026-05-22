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
import '../widgets/isp_ui_kit.dart';
import '../widgets/state_views.dart';
import 'staff_billing_hub_screen.dart';
import 'staff_customer_detail_screen.dart';
import 'staff_expense_screen.dart';
import 'staff_receive_bill_screen.dart';

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

  Future<void> _openReceiveBill(BuildContext context, int customerId) async {
    try {
      final detail = await widget.api.staffCustomerDetail(customerId);
      if (!mounted) return;
      final ok = await Navigator.push<bool>(
        context,
        MaterialPageRoute(
          builder: (_) => StaffReceiveBillScreen(api: widget.api, customer: detail),
        ),
      );
      if (ok == true) {
        _loadWallet();
        _refreshPending();
        if (_searchCtrl.text.trim().length >= 2) await _search(silent: true);
      }
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
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
      padding: EdgeInsets.zero,
      children: [
        IspUiKit.gradientHeader(
          title: 'Collection',
          subtitle: 'Bill receive · daily cash',
          trailing: [
            IconButton(
              icon: const Icon(Icons.history, color: Colors.white),
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => StaffBillingHubScreen(api: widget.api)),
              ),
            ),
          ],
          child: Row(
            children: [
              Expanded(
                child: _headerStat(
                  'Cash on hand',
                  balance != null ? '${fmt.format(balance)} BDT' : '—',
                  Icons.account_balance_wallet,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _headerStat(
                  'Queued',
                  '$_pending',
                  Icons.cloud_upload_outlined,
                ),
              ),
            ],
          ),
        ),
        Padding(
          padding: pagePadding(context),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
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
                    subtitle: const Text('Search a client below to receive payment'),
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
                      icon: const Icon(Icons.list_alt),
                      label: const Text('Billing list'),
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
              const SizedBox(height: 14),
              const SectionTitle('Bill receive'),
              const SizedBox(height: 6),
              IspUiKit.searchBar(
                controller: _searchCtrl,
                hint: 'Name, code, phone, username…',
                loading: _searching,
                onSearch: _search,
                onClear: () => _searchCtrl.clear(),
              ),
              const SizedBox(height: 6),
              Text(
                'Select customer → Receive Bill (cash / bKash / Nagad / bank)',
                style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
              ),
              const SizedBox(height: 10),
              ..._results.map((c) {
                final id = (c['id'] as num).toInt();
                return Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: CustomerSearchResultTile(
                    customer: c,
                    showDue: true,
                    selected: false,
                    onTap: () => _openReceiveBill(context, id),
                  ),
                );
              }),
            ],
          ),
        ),
      ],
    );
  }

  Widget _headerStat(String label, String value, IconData icon) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(icon, color: Colors.white, size: 22),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: const TextStyle(color: Colors.white70, fontSize: 10)),
                Text(value, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
