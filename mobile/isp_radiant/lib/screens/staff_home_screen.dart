import 'dart:async';

import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../config/remote_config.dart';
import '../core/network/api_result.dart';
import '../core/network/connectivity.dart';
import '../core/theme/design_tokens.dart';
import '../core/widgets/cards.dart';
import '../core/widgets/skeleton.dart';
import '../core/widgets/states.dart';
import '../features/dashboard_staff/data/staff_dashboard_repository.dart';
import '../features/dashboard_staff/domain/staff_dashboard.dart';
import '../services/api_service.dart';
import '../services/offline_sync_service.dart';
import '../services/realtime_service.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
import '../widgets/app_shell.dart';
import '../widgets/profile_banner.dart';
import '../widgets/quick_action_grid.dart';
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
import 'staff_team_discount_screen.dart';
import 'staff_inventory_pos_screen.dart';
import 'staff_mfs_sms_screen.dart';
import '../services/mfs_sms_listener.dart';
import '../widgets/isp_ui_kit.dart';
import '../widgets/module_tile.dart';

class StaffHomeScreen extends ConsumerStatefulWidget {
  const StaffHomeScreen({super.key, required this.api, required this.loginPayload, this.staffMode = 'admin'});

  final ApiService api;
  final Map<String, dynamic> loginPayload;
  final String staffMode;

  @override
  ConsumerState<StaffHomeScreen> createState() => _StaffHomeScreenState();
}

class _StaffHomeScreenState extends ConsumerState<StaffHomeScreen> {
  int _tab = 0;
  StaffDashboard? _dash;
  bool _loading = true;
  Failure? _error;
  final _fmt = NumberFormat('#,##0.00');
  late final StaffDashboardRepository _repo = StaffDashboardRepository(widget.api);
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
    if (RemoteConfig.mfsSmsStaff && (_mode == 'admin' || _mode == 'collector')) {
      unawaited(MfsSmsListener.instance.start(widget.api));
    }
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
    if (silent) {
      try {
        await widget.api.loadRemoteConfig();
      } catch (_) {}
    }
    final res = await _repo.load();
    if (!mounted) return;
    res.when(
      ok: (d) => setState(() {
        _dash = d;
        if (!silent) _loading = false;
      }),
      err: (f) => setState(() {
        if (!silent) {
          _error = f;
          _loading = false;
        }
      }),
    );
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

  void _push(Widget screen) => Navigator.push(context, MaterialPageRoute(builder: (_) => screen));

  void _onQuickAction(String key) {
    switch (key) {
      case 'collect':
        _go(2);
      case 'approval':
        _push(StaffApprovalsScreen(api: widget.api));
      case 'billing':
        _push(StaffBillingHubScreen(api: widget.api));
      case 'tickets':
        _push(StaffCreateTicketScreen(api: widget.api));
      case 'support':
        _go(3);
      case 'monitoring':
        _push(_mode == 'noc' ? StaffNocScreen(api: widget.api) : StaffMonitoringScreen(api: widget.api));
      case 'noc':
        _push(StaffNocScreen(api: widget.api));
      case 'clients':
        _push(StaffClientsScreen(api: widget.api));
      case 'add_client':
        _push(StaffAddCustomerScreen(api: widget.api));
      case 'expense':
        _push(StaffExpenseScreen(api: widget.api));
      default:
        _go(0);
    }
  }

  void _openModule(String key) {
    switch (key) {
      case 'clients':
        _push(StaffClientsScreen(api: widget.api));
      case 'billing':
        _push(StaffBillingHubScreen(api: widget.api));
      case 'collect':
        _go(2);
      case 'packages':
        _push(StaffPackagesScreen(api: widget.api));
      case 'mikrotik':
        _push(StaffMonitoringScreen(api: widget.api));
      case 'reports':
        _push(StaffReportsScreen(api: widget.api));
      case 'support':
        _go(3);
      case 'comms':
        _push(StaffCommsScreen(api: widget.api));
      case 'inventory':
        _push(StaffInventoryPosScreen(api: widget.api));
      case 'profile':
        _push(StaffProfileScreen(api: widget.api, user: _user));
      case 'staff_discounts':
        _push(StaffTeamDiscountScreen(api: widget.api));
      case 'mfs_sms':
        _push(StaffMfsSmsScreen(api: widget.api));
      default:
        break;
    }
  }

  Map<String, dynamic>? get _user =>
      (_dash?.user ?? widget.loginPayload['user']) as Map<String, dynamic>?;

  @override
  Widget build(BuildContext context) {
    final online = ref.watch(isOnlineProvider);
    final d = _dash;

    return AppShell(
      tabIndex: _tab,
      onTab: _go,
      title: '${RemoteConfig.appName} · ${_mode.toUpperCase()}',
      actions: [
        IconButton(
          icon: const Icon(Icons.search),
          onPressed: () => _push(StaffClientsScreen(api: widget.api)),
        ),
        IconButton(icon: const Icon(Icons.refresh), onPressed: _load),
        IconButton(icon: const Icon(Icons.logout), onPressed: _logout),
      ],
      destinations: const [
        NavigationDestination(icon: Icon(Icons.grid_view, color: DesignTokens.primary), label: 'Home'),
        NavigationDestination(icon: Icon(Icons.receipt_long, color: DesignTokens.success), label: 'Billing'),
        NavigationDestination(icon: Icon(Icons.account_balance_wallet, color: DesignTokens.teal), label: 'Collection'),
        NavigationDestination(icon: Icon(Icons.confirmation_number, color: DesignTokens.info), label: 'Support'),
        NavigationDestination(icon: Icon(Icons.task_alt, color: DesignTokens.pink), label: 'Task'),
      ],
      pages: [
        _buildHomeTab(online),
        _buildBillingTab(d?.billing ?? const StaffBilling(monthlyBill: 0, collected: 0, due: 0, discount: 0)),
        StaffCollectionScreen(api: widget.api, active: _tab == 2),
        StaffTicketsScreen(api: widget.api, active: _tab == 3, staffUserId: _user?['id'] as int?),
        StaffTasksScreen(api: widget.api, active: _tab == 4),
      ],
    );
  }

  Widget _buildHomeTab(bool online) {
    if (_loading && _dash == null) return const _StaffDashboardSkeleton();
    if (_error != null && _dash == null) {
      return ErrorStateView(failure: _error!, onRetry: _load);
    }
    final d = _dash;
    if (d == null) return const _StaffDashboardSkeleton();

    return RefreshIndicator(
      onRefresh: _load,
      color: DesignTokens.primary,
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          if (!online) const OfflineBanner(),
          if (_mode == 'admin')
            IspUiKit.gradientHeader(
              title: 'Admin dashboard',
              subtitle: '${_user?['name'] ?? 'Staff'} · ${RemoteConfig.appName}',
              trailing: [
                IconButton(
                  icon: const Icon(Icons.search, color: Colors.white),
                  onPressed: () => _push(StaffClientsScreen(api: widget.api)),
                ),
              ],
            ),
          Padding(
            padding: pagePadding(context),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (_mode != 'admin')
                  ProfileBanner(
                    name: _user?['name']?.toString() ?? 'Staff',
                    subtitle: '${_user?['user_type'] ?? 'Staff'} · Mode ${_mode.toUpperCase()}',
                    status: 'Status: ${_user?['status'] ?? 'Active'}',
                    statusColor: DesignTokens.warning,
                  ),
                const SizedBox(height: 12),
                ...RemoteConfig.notices.map(_noticeCard),
                if (_pendingSync > 0)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: AppCard(
                      borderColor: DesignTokens.warning.withValues(alpha: 0.4),
                      child: Row(
                        children: [
                          const Icon(Icons.sync_problem_rounded, color: DesignTokens.warning),
                          const SizedBox(width: 10),
                          Expanded(child: Text('$_pendingSync offline payment(s) pending')),
                          TextButton(onPressed: _flushOffline, child: const Text('Sync now')),
                        ],
                      ),
                    ),
                  ),
                if (_mode == 'admin')
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: AppCard(
                      onTap: () => _push(StaffTeamDiscountScreen(api: widget.api)),
                      child: Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(9),
                            decoration: BoxDecoration(
                              gradient: const LinearGradient(colors: [DesignTokens.primaryDeep, DesignTokens.info]),
                              borderRadius: BorderRadius.circular(DesignTokens.radiusSm),
                            ),
                            child: const Icon(Icons.percent_rounded, color: Colors.white, size: 20),
                          ),
                          const SizedBox(width: 12),
                          const Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('Collection discounts', style: TextStyle(fontWeight: FontWeight.w700)),
                                Text('Set max discount per collector / staff', style: TextStyle(fontSize: 12)),
                              ],
                            ),
                          ),
                          Icon(Icons.chevron_right_rounded, color: context.brand.textMuted),
                        ],
                      ),
                    ),
                  ),
                if (d.quickActions.isNotEmpty) ...[
                  const SectionHeader(title: 'Quick actions'),
                  QuickActionGrid(actions: d.quickActions, onAction: _onQuickAction),
                  const SizedBox(height: 16),
                ],
                const SectionHeader(title: 'Today'),
                _kpiRow(d.kpis),
                const SizedBox(height: 16),
                const SectionHeader(title: 'Collection overview'),
                _financeOverview(d.billing, d.finance),
                if (d.finance.hasExtended) ...[
                  const SizedBox(height: 16),
                  const SectionHeader(title: 'Finance & reseller'),
                  _resellerFinanceCard(d.finance),
                ],
                const SizedBox(height: 16),
                _revenueChart7d(d.revenue7d),
                const SizedBox(height: 16),
                const SectionHeader(title: 'Modules'),
                GridView.builder(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    mainAxisSpacing: 12,
                    crossAxisSpacing: 12,
                    childAspectRatio: 1.05,
                  ),
                  itemCount: d.modules.length,
                  itemBuilder: (context, i) {
                    final m = d.modules[i];
                    return ModuleTile(
                      title: m['title']?.toString() ?? '',
                      subtitle: m['subtitle']?.toString() ?? '',
                      icon: ModuleTile.iconFromKey(m['icon']?.toString() ?? ''),
                      color: ModuleTile.colorFromKey(m['color']?.toString() ?? 'blue'),
                      onTap: () => _openModule(m['key']?.toString() ?? ''),
                    );
                  },
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(child: _statusCard('Tickets', d.tickets)),
                    const SizedBox(width: 12),
                    Expanded(child: _statusCard('Tasks', d.tasks)),
                  ],
                ),
                if (d.zoneChart.isNotEmpty) ...[const SizedBox(height: 16), _zoneChart(d.zoneChart)],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBillingTab(StaffBilling b) {
    return ListView(
      padding: pagePadding(context),
      children: [
        GridView.count(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          crossAxisCount: 2,
          mainAxisSpacing: 12,
          crossAxisSpacing: 12,
          childAspectRatio: 1.35,
          children: [
            StatCard(label: 'Monthly Bill', value: _fmt.format(b.monthlyBill), icon: Icons.receipt_long, color: DesignTokens.primary),
            StatCard(label: 'Collected', value: _fmt.format(b.collected), icon: Icons.savings, color: DesignTokens.success),
            StatCard(label: 'Due', value: _fmt.format(b.due), icon: Icons.warning_amber, color: DesignTokens.warning),
            StatCard(label: 'Discount', value: _fmt.format(b.discount), icon: Icons.percent, color: DesignTokens.pink),
          ],
        ),
        const SizedBox(height: 12),
        AppCard(
          onTap: () => _push(StaffBillingHubScreen(api: widget.api)),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(9),
                decoration: BoxDecoration(
                    color: DesignTokens.primary.withValues(alpha: 0.14),
                    borderRadius: BorderRadius.circular(DesignTokens.radiusSm)),
                child: const Icon(Icons.list_alt_rounded, color: DesignTokens.primary),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Billing list', style: TextStyle(fontWeight: FontWeight.w700)),
                    Text('Due clients, invoices, daily collections', style: TextStyle(fontSize: 12)),
                  ],
                ),
              ),
              Icon(Icons.chevron_right_rounded, color: context.brand.textMuted),
            ],
          ),
        ),
        const SizedBox(height: 12),
        AppCard(
          onTap: () => _go(2),
          borderColor: DesignTokens.success.withValues(alpha: 0.4),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(9),
                decoration: BoxDecoration(
                    color: DesignTokens.success.withValues(alpha: 0.14),
                    borderRadius: BorderRadius.circular(DesignTokens.radiusSm)),
                child: const Icon(Icons.payments_rounded, color: DesignTokens.success),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Receive bill', style: TextStyle(fontWeight: FontWeight.w700)),
                    Text('Search & collect payment', style: TextStyle(fontSize: 12)),
                  ],
                ),
              ),
              Icon(Icons.chevron_right_rounded, color: context.brand.textMuted),
            ],
          ),
        ),
      ],
    );
  }

  Widget _noticeCard(Map<String, dynamic> n) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: AppCard(
        child: Row(
          children: [
            const Icon(Icons.campaign_rounded, color: DesignTokens.primary),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(n['title']?.toString() ?? 'Notice',
                      style: const TextStyle(fontWeight: FontWeight.w700)),
                  if ((n['body']?.toString() ?? '').isNotEmpty)
                    Text(n['body'].toString(),
                        style: TextStyle(fontSize: 12, color: context.brand.textMuted)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _financeOverview(StaffBilling b, FinanceSummary f) {
    final target = b.monthlyBill <= 0 ? 1.0 : b.monthlyBill;
    final rate = (b.collected / target).clamp(0.0, 1.0);
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text('This month', style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
              const Spacer(),
              Text('${(rate * 100).toStringAsFixed(0)}% collected',
                  style: const TextStyle(color: DesignTokens.success, fontWeight: FontWeight.w700, fontSize: 12)),
            ],
          ),
          const SizedBox(height: 10),
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: LinearProgressIndicator(
              value: rate,
              minHeight: 10,
              backgroundColor: context.brand.surfaceAlt,
              valueColor: const AlwaysStoppedAnimation(DesignTokens.success),
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(child: _financeStat('Monthly', b.monthlyBill, DesignTokens.primary)),
              Expanded(child: _financeStat('Collected', b.collected, DesignTokens.success)),
              Expanded(child: _financeStat('Due', b.due, DesignTokens.warning)),
              Expanded(child: _financeStat('Discount', b.discount, DesignTokens.pink)),
            ],
          ),
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: Divider(height: 1, color: context.brand.border),
          ),
          Row(
            children: [
              Expanded(child: _financeStat('Expense', f.expenseMonth, DesignTokens.danger)),
              Expanded(
                child: _financeStat(
                  f.netMonth >= 0 ? 'Net profit' : 'Net loss',
                  f.netMonth,
                  f.netMonth >= 0 ? DesignTokens.success : DesignTokens.danger,
                ),
              ),
              const Spacer(),
            ],
          ),
        ],
      ),
    );
  }

  Widget _resellerFinanceCard(FinanceSummary f) {
    return GridView.count(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisCount: 2,
      mainAxisSpacing: 12,
      crossAxisSpacing: 12,
      childAspectRatio: 1.5,
      children: [
        StatCard(
            icon: Icons.account_balance_wallet_rounded,
            label: 'Reseller wallet',
            value: _fmt.format(f.resellerWallet),
            color: DesignTokens.primary),
        StatCard(
            icon: Icons.handshake_rounded,
            label: 'Reseller settled (mo)',
            value: _fmt.format(f.resellerSettledMonth),
            color: DesignTokens.info),
        StatCard(
            icon: Icons.badge_rounded,
            label: 'Paid salary (mo)',
            value: _fmt.format(f.paidSalaryMonth),
            color: DesignTokens.warning),
        StatCard(
            icon: Icons.receipt_long_rounded,
            label: 'Expense (mo)',
            value: _fmt.format(f.expenseMonth),
            color: DesignTokens.danger),
      ],
    );
  }

  Widget _financeStat(String label, double value, Color color) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(width: 22, height: 3, decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(2))),
        const SizedBox(height: 6),
        FittedBox(
          fit: BoxFit.scaleDown,
          alignment: Alignment.centerLeft,
          child: Text('৳${_fmt.format(value)}',
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13)),
        ),
        Text(label, style: TextStyle(fontSize: 10, color: context.brand.textMuted)),
      ],
    );
  }

  Widget _kpiRow(StaffKpis k) {
    return GridView.count(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisCount: 2,
      mainAxisSpacing: 12,
      crossAxisSpacing: 12,
      childAspectRatio: 1.5,
      children: [
        StatCard(label: "Today's collection", value: _fmt.format(k.collectedToday), icon: Icons.payments, color: DesignTokens.success),
        StatCard(label: 'Cash on hand', value: _fmt.format(k.cashOnHand), icon: Icons.account_balance_wallet, color: DesignTokens.primary),
        StatCard(label: 'Online PPP', value: '${k.onlineClients}', icon: Icons.wifi, color: DesignTokens.success),
        StatCard(label: 'Due clients', value: '${k.dueClients}', icon: Icons.warning_amber, color: DesignTokens.warning),
        StatCard(label: 'Active clients', value: '${k.activeClients}', icon: Icons.people, color: DesignTokens.primary),
        StatCard(label: 'Expire today', value: '${k.expiringToday}', icon: Icons.event_busy, color: DesignTokens.pink),
      ],
    );
  }

  Widget _revenueChart7d(RevenueSeries series) {
    if (series.isEmpty) return const SizedBox.shrink();
    final collected = series.collected;
    final labels = series.labels;
    final maxY = collected.fold<double>(0, (a, b) => a > b ? a : b) * 1.2 + 1;

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Revenue — last 7 days',
              style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 12),
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
                          ? Text(labels[v.toInt()],
                              style: TextStyle(fontSize: 9, color: context.brand.textMuted))
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
                    color: DesignTokens.primary,
                    barWidth: 3,
                    dotData: const FlDotData(show: true),
                    belowBarData: BarAreaData(show: true, color: DesignTokens.primary.withValues(alpha: 0.14)),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _statusCard(String title, CountStat stat) {
    final maxVal = stat.total == 0 ? 1 : stat.total;
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('${stat.total} $title', style: const TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          Text('${stat.pending} Pending', style: const TextStyle(fontSize: 11)),
          const SizedBox(height: 3),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: stat.pending / maxVal,
              minHeight: 6,
              backgroundColor: context.brand.surfaceAlt,
              color: DesignTokens.warning,
            ),
          ),
          const SizedBox(height: 8),
          Text('${stat.process} Process', style: const TextStyle(fontSize: 11)),
          const SizedBox(height: 3),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: stat.process / maxVal,
              minHeight: 6,
              backgroundColor: context.brand.surfaceAlt,
              color: DesignTokens.info,
            ),
          ),
        ],
      ),
    );
  }

  Widget _zoneChart(List<ZoneRow> rows) {
    final maxY = rows.fold<double>(0, (a, r) => [a, r.paid, r.unpaid].reduce((x, y) => x > y ? x : y)) * 1.2 + 1;

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _legend('Unpaid', DesignTokens.pink),
              const SizedBox(width: 12),
              _legend('Paid', DesignTokens.info),
            ],
          ),
          const SizedBox(height: 12),
          SizedBox(
            height: 200,
            child: BarChart(
              BarChartData(
                maxY: maxY,
                barGroups: List.generate(rows.length, (i) {
                  return BarChartGroupData(
                    x: i,
                    barRods: [
                      BarChartRodData(toY: rows[i].unpaid, color: DesignTokens.pink, width: 10),
                      BarChartRodData(toY: rows[i].paid, color: DesignTokens.info, width: 10),
                    ],
                  );
                }),
                titlesData: FlTitlesData(
                  leftTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                  topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                  rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                  bottomTitles: AxisTitles(
                    sideTitles: SideTitles(
                      showTitles: true,
                      getTitlesWidget: (v, _) {
                        final i = v.toInt();
                        if (i < 0 || i >= rows.length) return const SizedBox.shrink();
                        return Text(rows[i].zone,
                            style: TextStyle(fontSize: 8, color: context.brand.textMuted));
                      },
                    ),
                  ),
                ),
                gridData: const FlGridData(show: false),
                borderData: FlBorderData(show: false),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _legend(String label, Color color) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(width: 10, height: 10, decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(3))),
        const SizedBox(width: 5),
        Text(label, style: const TextStyle(fontSize: 11)),
      ],
    );
  }
}

class _StaffDashboardSkeleton extends StatelessWidget {
  const _StaffDashboardSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        const SkeletonCard(height: 90),
        const SizedBox(height: 16),
        GridView.count(
          crossAxisCount: 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 12,
          crossAxisSpacing: 12,
          childAspectRatio: 1.5,
          children: const [SkeletonCard(), SkeletonCard(), SkeletonCard(), SkeletonCard()],
        ),
        const SizedBox(height: 16),
        const SkeletonCard(height: 180),
      ],
    );
  }
}
