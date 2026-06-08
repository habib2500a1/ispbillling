import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/network/api_result.dart';
import '../config/remote_config.dart';
import '../design_system/components/radiant_glass_card.dart';
import '../design_system/components/radiant_screen_header.dart';
import '../design_system/components/radiant_section.dart';
import '../core/theme/design_tokens.dart';
import '../design_system/radiant_tokens.dart';
import '../services/api_service.dart';
import '../services/offline_sync_service.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
import '../widgets/customer_search_result_tile.dart';
import '../design_system/navigation/radiant_super_shell.dart';
import 'staff_billing_hub_screen.dart';
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
        RadiantPageRoute(page: StaffReceiveBillScreen(api: widget.api, customer: detail)),
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
    final brand = context.radiant;

    return ListView(
      padding: EdgeInsets.zero,
      children: [
        RadiantScreenHeader(
          title: 'Collection',
          subtitle: 'Search client · receive payment',
          trailing: [
            RadiantHeaderIcon(
              icon: Icons.history_rounded,
              onPressed: () => Navigator.push(context, RadiantPageRoute(page: StaffBillingHubScreen(api: widget.api))),
              tooltip: 'History',
            ),
          ],
          child: Row(
            children: [
              Expanded(
                child: RadiantGlassCard(
                  padding: const EdgeInsets.all(12),
                  child: Row(
                    children: [
                      Icon(Icons.account_balance_wallet_rounded, color: RadiantTokens.brand, size: 22),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('Cash on hand', style: context.text.labelSmall?.copyWith(color: brand.muted)),
                            Text(
                              balance != null ? '${fmt.format(balance)} BDT' : '—',
                              style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w800),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: RadiantGlassCard(
                  padding: const EdgeInsets.all(12),
                  child: Row(
                    children: [
                      Icon(Icons.cloud_upload_outlined, color: RadiantTokens.warning, size: 22),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('Queued sync', style: context.text.labelSmall?.copyWith(color: brand.muted)),
                            Text('$_pending', style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                          ],
                        ),
                      ),
                    ],
                  ),
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
                RadiantGlassCard(
                  child: Row(
                    children: [
                      Icon(Icons.sync_rounded, color: brand.warning),
                      const SizedBox(width: 10),
                      Expanded(child: Text('$_pending queued collection(s)')),
                      TextButton(
                        onPressed: () async {
                          await _offline.flush();
                          await _refreshPending();
                          if (mounted) showSnack(context, 'Sync attempted');
                        },
                        child: const Text('Sync'),
                      ),
                    ],
                  ),
                ),
              if (_walletError != null)
                Padding(
                  padding: const EdgeInsets.only(top: 10),
                  child: RadiantGlassCard(
                    child: Row(
                      children: [
                        Icon(Icons.info_outline_rounded, color: brand.warning),
                        const SizedBox(width: 10),
                        Expanded(child: Text(_walletError!, style: const TextStyle(fontSize: 13))),
                      ],
                    ),
                  ),
                ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => Navigator.push(context, RadiantPageRoute(page: StaffBillingHubScreen(api: widget.api))),
                      icon: const Icon(Icons.list_alt_rounded),
                      label: const Text('Billing list'),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => Navigator.push(context, RadiantPageRoute(page: StaffExpenseScreen(api: widget.api))),
                      icon: const Icon(Icons.receipt_long_rounded),
                      label: const Text('Expense'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              const RadiantSectionHeader(title: 'Bill receive'),
              RadiantSearchField(
                controller: _searchCtrl,
                hint: 'Name, code, phone, username…',
                loading: _searching,
                onSearch: _search,
                onClear: () => _searchCtrl.clear(),
              ),
              const SizedBox(height: 8),
              Text(
                'Select customer → receive payment (cash / bKash / Nagad / bank)',
                style: context.text.labelSmall?.copyWith(color: brand.muted),
              ),
              const SizedBox(height: 12),
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
              const SizedBox(height: 80),
            ],
          ),
        ),
      ],
    );
  }
}
