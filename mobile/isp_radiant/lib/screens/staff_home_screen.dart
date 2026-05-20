import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../config/remote_config.dart';
import '../services/api_service.dart';
import '../services/offline_sync_service.dart';
import '../services/realtime_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
import '../widgets/app_shell.dart';
import '../widgets/profile_banner.dart';
import '../widgets/quick_action_grid.dart';
import '../widgets/state_views.dart';
import '../widgets/stat_card.dart';
import 'login_screen.dart';
import 'staff_clients_screen.dart';
import 'staff_collection_screen.dart';
import 'staff_monitoring_screen.dart';
import 'staff_noc_screen.dart';
import 'staff_add_customer_screen.dart';
import 'staff_approvals_screen.dart';
import 'staff_billing_hub_screen.dart';
import 'staff_expense_screen.dart';
import 'staff_create_ticket_screen.dart';
import 'staff_tasks_screen.dart';
import 'staff_tickets_screen.dart';
import 'staff_packages_screen.dart';
import 'staff_reports_screen.dart';
import 'staff_comms_screen.dart';
import 'staff_profile_screen.dart';
import '../widgets/module_tile.dart';

class StaffHomeScreen extends StatefulWidget {
  const StaffHomeScreen({super.key, required this.api, required this.loginPayload, this.staffMode = 'admin'});

  final ApiService api;
  final Map<String, dynamic> loginPayload;
  final String staffMode;

  @override
  State<StaffHomeScreen> createState() => _StaffHomeScreenState();
}

class _StaffHomeScreenState extends State<StaffHomeScreen> {
  int _tab = 0;
  Map<String, dynamic>? _data;
  bool _loading = true;
  String? _error;
  final _fmt = NumberFormat('#,##0.00');
  late final OfflineSyncService _offline = OfflineSyncService(widget.api);
  late final RealtimeService _realtime = RealtimeService(widget.api);
  int _pendingSync = 0;
  String _mode = 'admin';

  @override
  void initState() {
    super.initState();
    _mode = widget.staffMode;
    _boot();
  }

  Future<void> _boot() async {
    final saved = await widget.api.staffMode;
    if (saved != null && saved.isNotEmpty) _mode = saved;
    _realtime.onTick = () => _load(silent: true);
    await _realtime.start();
    await _flushOffline();
    await _load();
  }

  @override
  void dispose() {
    _realtime.stop();
    super.dispose();
  }

  Future<void> _flushOffline() async {
    if (!RemoteConfig.offlineSync) return;
    try {
      final result = await _offline.flush();
      final pending = await _offline.pendingCount();
      if (mounted) {
        setState(() => _pendingSync = pending);
        if (result != null && (result['synced'] as num? ?? 0) > 0) {
          showSnack(context, 'Synced ${result['synced']} offline item(s)');
        }
      }
    } catch (_) {}
  }

  Future<void> _load({bool silent = false}) async {
    if (!silent) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      if (silent) await widget.api.loadRemoteConfig();
      final data = await widget.api.staffDashboard();
      if (mounted) setState(() => _data = data);
    } on ApiException catch (e) {
      if (mounted && !silent) setState(() => _error = e.message);
    } catch (_) {
      if (mounted && !silent) setState(() => _error = 'Failed to load dashboard');
    } finally {
      if (mounted && !silent) setState(() => _loading = false);
    }
  }

  Future<void> _logout() async {
    await widget.api.logout();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => LoginScreen(api: widget.api)),
      (_) => false,
    );
  }

  void _go(int i) => setState(() => _tab = i);

  void _onQuickAction(String key) {
    switch (key) {
      case 'collect':
        _go(2);
        break;
      case 'approval':
        Navigator.push(context, MaterialPageRoute(builder: (_) => StaffApprovalsScreen(api: widget.api)));
        break;
      case 'billing':
        Navigator.push(context, MaterialPageRoute(builder: (_) => StaffBillingHubScreen(api: widget.api)));
        break;
      case 'tickets':
        Navigator.push(context, MaterialPageRoute(builder: (_) => StaffCreateTicketScreen(api: widget.api)));
        break;
      case 'support':
        _go(3);
        break;
      case 'monitoring':
        if (_mode == 'noc') {
          Navigator.push(context, MaterialPageRoute(builder: (_) => StaffNocScreen(api: widget.api)));
        } else {
          Navigator.push(context, MaterialPageRoute(builder: (_) => StaffMonitoringScreen(api: widget.api)));
        }
        break;
      case 'noc':
        Navigator.push(context, MaterialPageRoute(builder: (_) => StaffNocScreen(api: widget.api)));
        break;
      case 'clients':
        Navigator.push(context, MaterialPageRoute(builder: (_) => StaffClientsScreen(api: widget.api)));
        break;
      case 'add_client':
        Navigator.push(context, MaterialPageRoute(builder: (_) => StaffAddCustomerScreen(api: widget.api)));
        break;
      case 'expense':
        Navigator.push(context, MaterialPageRoute(builder: (_) => StaffExpenseScreen(api: widget.api)));
        break;
      default:
        _go(0);
    }
  }

  void _openModule(String key) {
    switch (key) {
      case 'clients':
        Navigator.push(context, MaterialPageRoute(builder: (_) => StaffClientsScreen(api: widget.api)));
        break;
      case 'billing':
        Navigator.push(context, MaterialPageRoute(builder: (_) => StaffBillingHubScreen(api: widget.api)));
        break;
      case 'collect':
        _go(2);
        break;
      case 'packages':
        Navigator.push(context, MaterialPageRoute(builder: (_) => StaffPackagesScreen(api: widget.api)));
        break;
      case 'mikrotik':
        Navigator.push(context, MaterialPageRoute(builder: (_) => StaffMonitoringScreen(api: widget.api)));
        break;
      case 'reports':
        Navigator.push(context, MaterialPageRoute(builder: (_) => StaffReportsScreen(api: widget.api)));
        break;
      case 'support':
        _go(3);
        break;
      case 'comms':
        Navigator.push(context, MaterialPageRoute(builder: (_) => StaffCommsScreen(api: widget.api)));
        break;
      case 'profile':
        final user = (_data?['user'] ?? widget.loginPayload['user']) as Map<String, dynamic>?;
        Navigator.push(context, MaterialPageRoute(builder: (_) => StaffProfileScreen(api: widget.api, user: user)));
        break;
      default:
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = (_data?['user'] ?? widget.loginPayload['user']) as Map<String, dynamic>?;
    final billing = _data?['billing'] as Map<String, dynamic>? ?? {};
    final tickets = _data?['tickets'] as Map<String, dynamic>? ?? {};
    final tasks = _data?['tasks'] as Map<String, dynamic>? ?? {};
    final chart = (_data?['zone_collection_chart'] as List<dynamic>?) ?? [];
    final actions = (_data?['quick_actions'] as List<dynamic>?) ?? [];
    final kpis = _data?['kpis'] as Map<String, dynamic>? ?? {};
    final revenue7 = _data?['revenue_chart_7d'] as Map<String, dynamic>? ?? {};
    final modules = (_data?['app_modules'] as List<dynamic>?) ?? [];

    return AppShell(
      tabIndex: _tab,
      onTab: _go,
      title: '${RemoteConfig.appName} · ${_mode.toUpperCase()}',
      actions: [
        IconButton(
          icon: const Icon(Icons.search),
          onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => StaffClientsScreen(api: widget.api))),
        ),
        IconButton(icon: const Icon(Icons.refresh), onPressed: _load),
        IconButton(icon: const Icon(Icons.logout), onPressed: _logout),
      ],
      destinations: const [
        NavigationDestination(icon: Icon(Icons.grid_view), label: 'Home'),
        NavigationDestination(icon: Icon(Icons.receipt_long), label: 'Billing'),
        NavigationDestination(icon: Icon(Icons.account_balance_wallet), label: 'Collection'),
        NavigationDestination(icon: Icon(Icons.confirmation_number), label: 'Support'),
        NavigationDestination(icon: Icon(Icons.task_alt), label: 'Task'),
      ],
      pages: [
        _buildHomeTab(user, billing, tickets, tasks, chart, actions, kpis, revenue7, modules),
        _buildBillingTab(billing),
        StaffCollectionScreen(api: widget.api, active: _tab == 2),
        StaffTicketsScreen(api: widget.api, active: _tab == 3),
        StaffTasksScreen(api: widget.api, active: _tab == 4),
      ],
    );
  }

  Widget _buildHomeTab(
    Map<String, dynamic>? user,
    Map<String, dynamic> billing,
    Map<String, dynamic> tickets,
    Map<String, dynamic> tasks,
    List<dynamic> chart,
    List<dynamic> actions,
    Map<String, dynamic> kpis,
    Map<String, dynamic> revenue7,
    List<dynamic> modules,
  ) {
    if (_loading) return const Center(child: CircularProgressIndicator());

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: pagePadding(context),
        children: [
          ProfileBanner(
            name: user?['name']?.toString() ?? 'Staff',
            subtitle: '${user?['user_type'] ?? 'Staff'} · Mode ${_mode.toUpperCase()}',
            status: 'Status: ${user?['status'] ?? 'Active'}',
            statusColor: Colors.amber,
          ),
          const SizedBox(height: 12),
          if (_error != null) ErrorBanner(message: _error!, onRetry: () => _load()),
          ...RemoteConfig.notices.map(_noticeCard),
          if (_pendingSync > 0)
            Card(
              color: AppTheme.warning.withValues(alpha: 0.15),
              child: ListTile(
                title: Text('$_pendingSync offline payment(s) pending'),
                trailing: TextButton(onPressed: _flushOffline, child: const Text('Sync now')),
              ),
            ),
          if (_mode == 'noc')
            Card(
              child: ListTile(
                leading: const Icon(Icons.dashboard, color: AppTheme.primary),
                title: const Text('Open NOC wall'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => StaffNocScreen(api: widget.api))),
              ),
            ),
          _kpiRow(kpis),
          const SizedBox(height: 12),
          _revenueChart7d(revenue7),
          const SizedBox(height: 16),
          const Text('Modules', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
              childAspectRatio: 1.05,
            ),
            itemCount: modules.length,
            itemBuilder: (context, i) {
              final m = Map<String, dynamic>.from(modules[i] as Map);
              return ModuleTile(
                title: m['title']?.toString() ?? '',
                subtitle: m['subtitle']?.toString() ?? '',
                icon: ModuleTile.iconFromKey(m['icon']?.toString() ?? ''),
                color: ModuleTile.colorFromKey(m['color']?.toString() ?? 'blue'),
                onTap: () => _openModule(m['key']?.toString() ?? ''),
              );
            },
          ),
          const SizedBox(height: 12),
          GridView.count(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            crossAxisCount: 2,
            mainAxisSpacing: 8,
            crossAxisSpacing: 8,
            childAspectRatio: 1.5,
            children: [
              StatCard(label: 'Monthly bill', value: _fmt.format(billing['monthly_bill'] ?? 0), icon: Icons.receipt, color: AppTheme.primary),
              StatCard(label: 'Month collected', value: _fmt.format(billing['collected_bill'] ?? 0), icon: Icons.savings, color: AppTheme.success),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: _statusCard('Tickets', tickets)),
              const SizedBox(width: 10),
              Expanded(child: _statusCard('Tasks', tasks)),
            ],
          ),
          if (chart.isNotEmpty) ...[const SizedBox(height: 12), _zoneChart(chart)],
        ],
      ),
    );
  }

  Widget _buildBillingTab(Map<String, dynamic> billing) {
    return ListView(
      padding: pagePadding(context),
      children: [
        StatCard(label: 'Monthly Bill', value: '${_fmt.format(billing['monthly_bill'] ?? 0)} BDT', icon: Icons.receipt_long, color: AppTheme.primary),
        const SizedBox(height: 8),
        StatCard(label: 'Collected', value: '${_fmt.format(billing['collected_bill'] ?? 0)} BDT', icon: Icons.savings, color: AppTheme.success),
        const SizedBox(height: 8),
        StatCard(label: 'Due', value: '${_fmt.format(billing['due'] ?? 0)} BDT', icon: Icons.warning_amber, color: AppTheme.warning),
        const SizedBox(height: 8),
        StatCard(label: 'Discount', value: '${_fmt.format(billing['discount'] ?? 0)} BDT', icon: Icons.percent, color: Colors.deepOrange),
        const SizedBox(height: 16),
        FilledButton.icon(
          onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => StaffBillingHubScreen(api: widget.api))),
          icon: const Icon(Icons.analytics),
          label: const Text('Full billing & collections'),
        ),
        const SizedBox(height: 8),
        OutlinedButton.icon(
          onPressed: () => _go(2),
          icon: const Icon(Icons.payments),
          label: const Text('Receive payment'),
        ),
      ],
    );
  }

  Widget _noticeCard(Map<String, dynamic> n) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      color: AppTheme.primary.withValues(alpha: 0.08),
      child: ListTile(
        leading: const Icon(Icons.campaign_outlined, color: AppTheme.primary),
        title: Text(n['title']?.toString() ?? 'Notice', style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Text(n['body']?.toString() ?? ''),
      ),
    );
  }

  Widget _kpiRow(Map<String, dynamic> kpis) {
    return GridView.count(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisCount: 2,
      mainAxisSpacing: 8,
      crossAxisSpacing: 8,
      childAspectRatio: 1.55,
      children: [
        StatCard(label: "Today's collection", value: _fmt.format(kpis['collected_today'] ?? 0), icon: Icons.payments, color: AppTheme.success),
        StatCard(label: 'Active clients', value: '${kpis['active_clients'] ?? 0}', icon: Icons.people, color: AppTheme.primary),
        StatCard(label: 'Due clients', value: '${kpis['due_clients'] ?? 0}', icon: Icons.warning_amber, color: AppTheme.warning),
        StatCard(label: 'Expire today', value: '${kpis['expiring_today'] ?? 0}', icon: Icons.event_busy, color: Colors.deepOrange),
      ],
    );
  }

  Widget _revenueChart7d(Map<String, dynamic> chart) {
    final labels = (chart['labels'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? [];
    final collected = (chart['collected'] as List<dynamic>?)?.map((e) => (e as num).toDouble()).toList() ?? [];
    if (collected.isEmpty) return const SizedBox.shrink();
    final maxY = collected.fold<double>(0, (a, b) => a > b ? a : b) * 1.2 + 1;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Revenue — last 7 days', style: TextStyle(fontWeight: FontWeight.w600)),
            const SizedBox(height: 8),
            SizedBox(
              height: 160,
              child: LineChart(
                LineChartData(
                  minY: 0,
                  maxY: maxY,
                  titlesData: FlTitlesData(
                    bottomTitles: AxisTitles(
                      sideTitles: SideTitles(
                        showTitles: true,
                        interval: 1,
                        getTitlesWidget: (v, _) => labels.length > v.toInt()
                            ? Text(labels[v.toInt()], style: const TextStyle(fontSize: 9))
                            : const SizedBox.shrink(),
                      ),
                    ),
                    leftTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                    topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                    rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                  ),
                  gridData: const FlGridData(show: false),
                  borderData: FlBorderData(show: false),
                  lineBarsData: [
                    LineChartBarData(
                      spots: List.generate(collected.length, (i) => FlSpot(i.toDouble(), collected[i])),
                      isCurved: true,
                      color: AppTheme.accent,
                      barWidth: 3,
                      dotData: const FlDotData(show: true),
                      belowBarData: BarAreaData(show: true, color: AppTheme.accent.withValues(alpha: 0.12)),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _statusCard(String title, Map<String, dynamic> stats) {
    final total = (stats['total'] as num?)?.toInt() ?? 0;
    final pending = (stats['pending'] as num?)?.toInt() ?? 0;
    final process = (stats['process'] as num?)?.toInt() ?? 0;
    final maxVal = total == 0 ? 1 : total;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('$total $title', style: const TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 6),
            Text('$pending Pending', style: const TextStyle(fontSize: 11)),
            LinearProgressIndicator(value: pending / maxVal, minHeight: 5, color: Colors.orange),
            const SizedBox(height: 4),
            Text('$process Process', style: const TextStyle(fontSize: 11)),
            LinearProgressIndicator(value: process / maxVal, minHeight: 5, color: AppTheme.accent),
          ],
        ),
      ),
    );
  }

  Widget _zoneChart(List<dynamic> rows) {
    final paid = rows.map((r) => (r['paid'] as num?)?.toDouble() ?? 0).toList();
    final unpaid = rows.map((r) => (r['unpaid'] as num?)?.toDouble() ?? 0).toList();
    final labels = rows.map((r) => (r['zone']?.toString() ?? '')).toList();
    final maxY = [...paid, ...unpaid].fold<double>(0, (a, b) => a > b ? a : b) * 1.2 + 1;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                _legend('Unpaid', const Color(0xFFF472B6)),
                const SizedBox(width: 12),
                _legend('Paid', const Color(0xFF22D3EE)),
              ],
            ),
            const SizedBox(height: 12),
            SizedBox(
              height: 200,
              child: BarChart(
                BarChartData(
                  maxY: maxY,
                  barGroups: List.generate(rows.length, (i) => BarChartGroupData(
                    x: i,
                    barRods: [
                      BarChartRodData(toY: unpaid[i], color: const Color(0xFFF472B6), width: 10),
                      BarChartRodData(toY: paid[i], color: const Color(0xFF22D3EE), width: 10),
                    ],
                  )),
                  titlesData: FlTitlesData(
                    bottomTitles: AxisTitles(
                      sideTitles: SideTitles(
                        showTitles: true,
                        getTitlesWidget: (v, _) {
                          final i = v.toInt();
                          if (i < 0 || i >= labels.length) return const SizedBox.shrink();
                          return Text(labels[i], style: const TextStyle(fontSize: 8));
                        },
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _legend(String label, Color color) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(width: 10, height: 10, color: color),
        const SizedBox(width: 4),
        Text(label, style: const TextStyle(fontSize: 11)),
      ],
    );
  }
}
