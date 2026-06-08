import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';

import '../config/remote_config.dart';
import '../core/network/api_result.dart';
import '../core/theme/design_tokens.dart';
import '../core/widgets/states.dart';
import '../services/api_service.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
import '../widgets/glass_card.dart';
import '../widgets/isp_ui_kit.dart';
import 'login_hub_screen.dart';
import 'reseller_web_portal_screen.dart';

/// Native reseller business center (Phase 2) — uses `/reseller/*` API.
class ResellerHomeScreen extends StatefulWidget {
  const ResellerHomeScreen({super.key, required this.api, required this.loginPayload});

  final ApiService api;
  final Map<String, dynamic> loginPayload;

  @override
  State<ResellerHomeScreen> createState() => _ResellerHomeScreenState();
}

class _ResellerHomeScreenState extends State<ResellerHomeScreen> {
  Map<String, dynamic>? _dash;
  bool _loading = true;
  String? _error;
  int _tab = 0;
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
      final d = await widget.api.resellerDashboard();
      if (mounted) setState(() {
        _dash = d;
        _loading = false;
      });
    } on ApiException catch (e) {
      if (mounted) setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (_) {
      if (mounted) setState(() {
        _error = 'Could not load dashboard';
        _loading = false;
      });
    }
  }

  Future<void> _logout() async {
    await widget.api.logout();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => LoginHubScreen(api: widget.api)),
      (_) => false,
    );
  }

  Map<String, dynamic> get _metrics =>
      (_dash?['metrics'] as Map?)?.cast<String, dynamic>() ?? {};

  @override
  Widget build(BuildContext context) {
    final name = widget.loginPayload['reseller']?['name']?.toString() ??
        widget.loginPayload['user']?['name']?.toString() ??
        'Partner';

    return Scaffold(
      body: IndexedStack(
        index: _tab,
        children: [
          _buildDashboard(name),
          _ResellerListTab(api: widget.api, loader: widget.api.resellerCustomers, title: 'Customers'),
          _ResellerListTab(api: widget.api, loader: widget.api.resellerCommissions, title: 'Commissions'),
          _buildMoreTab(name),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.dashboard_outlined), label: 'Home'),
          NavigationDestination(icon: Icon(Icons.people_outline), label: 'Customers'),
          NavigationDestination(icon: Icon(Icons.payments_outlined), label: 'Commission'),
          NavigationDestination(icon: Icon(Icons.more_horiz), label: 'More'),
        ],
      ),
    );
  }

  Widget _buildDashboard(String name) {
    if (_loading && _dash == null) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null && _dash == null) {
      return ErrorStateView(
        failure: Failure(_error!),
        onRetry: _load,
      );
    }

    final m = _metrics;
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          IspUiKit.gradientHeader(
            title: 'Partner center',
            subtitle: '$name · ${RemoteConfig.appName}',
            trailing: [
              IconButton(icon: const Icon(Icons.refresh, color: Colors.white), onPressed: _load),
              IconButton(icon: const Icon(Icons.logout, color: Colors.white), onPressed: _logout),
            ],
          ),
          Padding(
            padding: pagePadding(context),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(child: _kpi('Revenue', _fmt.format(m['revenue_mtd'] ?? m['collected_mtd'] ?? 0))),
                    const SizedBox(width: 10),
                    Expanded(child: _kpi('Customers', '${m['active_customers'] ?? m['customers'] ?? 0}')),
                  ],
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(child: _kpi('Due', _fmt.format(m['due_total'] ?? m['total_due'] ?? 0))),
                    const SizedBox(width: 10),
                    Expanded(child: _kpi('Collection', _fmt.format(m['collected_mtd'] ?? 0))),
                  ],
                ),
                const SizedBox(height: 16),
                GlassCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Quick actions', style: TextStyle(fontWeight: FontWeight.w800)),
                      const SizedBox(height: 10),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          ActionChip(
                            label: const Text('Full portal (web)'),
                            onPressed: () {
                              final url = RemoteConfig.resellerLoginUrl;
                              if (url == null) return;
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => ResellerWebPortalScreen(initialUrl: url, title: 'Partner portal'),
                                ),
                              );
                            },
                          ),
                          ActionChip(
                            label: const Text('Due report'),
                            onPressed: () async {
                              try {
                                final due = await widget.api.resellerDueAccount();
                                if (!mounted) return;
                                showDialog(
                                  context: context,
                                  builder: (_) => AlertDialog(
                                    title: const Text('Due account'),
                                    content: Text(due.toString()),
                                  ),
                                );
                              } on ApiException catch (e) {
                                if (mounted) showSnack(context, e.message, isError: true);
                              }
                            },
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _kpi(String label, String value) {
    return GlassCard(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: TextStyle(fontSize: 11, color: Colors.grey.shade600)),
          const SizedBox(height: 4),
          Text(value, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
        ],
      ),
    );
  }

  Widget _buildMoreTab(String name) {
    return ListView(
      padding: pagePadding(context),
      children: [
        const SizedBox(height: 16),
        ListTile(
          leading: const Icon(Icons.language),
          title: const Text('Open web portal'),
          onTap: () {
            final url = RemoteConfig.resellerLoginUrl;
            if (url == null) return;
            Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => ResellerWebPortalScreen(initialUrl: url, title: 'Partner portal')),
            );
          },
        ),
        ListTile(leading: const Icon(Icons.logout), title: const Text('Sign out'), onTap: _logout),
      ],
    );
  }
}

class _ResellerListTab extends StatefulWidget {
  const _ResellerListTab({required this.api, required this.loader, required this.title});

  final ApiService api;
  final Future<List<Map<String, dynamic>>> Function() loader;
  final String title;

  @override
  State<_ResellerListTab> createState() => _ResellerListTabState();
}

class _ResellerListTabState extends State<_ResellerListTab> {
  List<Map<String, dynamic>> _items = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final list = await widget.loader();
      if (mounted) setState(() {
        _items = list;
        _loading = false;
      });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: pagePadding(context),
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 12, bottom: 8),
            child: Text(widget.title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
          ),
          ..._items.map((e) => Card(
                child: ListTile(
                  title: Text(e['name']?.toString() ?? e['customer']?['name']?.toString() ?? 'Item'),
                  subtitle: Text(e.toString().length > 80 ? e.toString().substring(0, 80) : e.toString()),
                ),
              )),
          if (_items.isEmpty) const Padding(padding: EdgeInsets.all(32), child: Text('No data', textAlign: TextAlign.center)),
        ],
      ),
    );
  }
}
