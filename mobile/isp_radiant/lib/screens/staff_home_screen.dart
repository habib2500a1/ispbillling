import 'dart:async';

import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../config/remote_config.dart';
import '../core/navigation/super_app_navigator.dart';
import '../core/roles/role_resolver.dart';
import '../core/roles/staff_interface.dart';
import '../core/network/api_result.dart';
import '../core/network/connectivity.dart';
import '../core/theme/design_tokens.dart';
import '../design_system/components/radiant_glass_card.dart';
import '../design_system/components/radiant_kpi_tile.dart';
import '../design_system/components/radiant_screen_header.dart';
import '../design_system/components/radiant_section.dart';
import '../design_system/components/radiant_skeleton.dart';
import '../design_system/components/radiant_quick_action_grid.dart';
import '../design_system/navigation/radiant_super_shell.dart';
import '../design_system/radiant_tokens.dart';
import '../core/widgets/states.dart';
import '../features/dashboard_staff/data/staff_dashboard_repository.dart';
import '../features/dashboard_staff/domain/staff_dashboard.dart';
import '../services/api_service.dart';
import '../services/offline_sync_service.dart';
import '../services/realtime_service.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
import '../widgets/profile_banner.dart';
import 'login_hub_screen.dart';
import 'staff_menu_tab.dart';
import 'staff_clients_screen.dart';
import 'staff_collection_screen.dart';
import 'staff_monitoring_screen.dart';
import 'staff_noc_screen.dart';
import 'staff_add_customer_screen.dart';
import 'staff_approvals_screen.dart';
import '../widgets/staff_receipt_launcher.dart';
import 'staff_billing_hub_screen.dart';
import 'staff_money_receipt_screen.dart';
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
import 'staff_gis_map_screen.dart';
import 'staff_ai_screen.dart';
import 'staff_global_search_screen.dart';
import '../widgets/role_switcher_sheet.dart';
import '../services/mfs_sms_listener.dart';

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
  StaffReceiptRequest? _receiptOverlay;
  StaffDashboard? _dash;
  bool _loading = true;
  Failure? _error;
  final _fmt = NumberFormat('#,##0.00');
  late final StaffDashboardRepository _repo = StaffDashboardRepository(widget.api);
  late final OfflineSyncService _offline = OfflineSyncService(widget.api);
  late final RealtimeService _realtime = RealtimeService(widget.api);
  int _pendingSync = 0;
  String _mode = 'admin';
  RoleCapabilities? _roleCaps;

  @override
  void initState() {
    super.initState();
    _mode = widget.staffMode;
    _boot();
  }

  Future<void> _boot() async {
    final saved = await widget.api.staffMode;
    if (saved != null && saved.isNotEmpty) _mode = saved;
    try {
      final me = await widget.api.staffMe();
      _roleCaps = RoleCapabilities.fromMe(me, savedMode: _mode);
    } catch (_) {
      _roleCaps = RoleCapabilities.fromMe(const {}, savedMode: _mode);
    }
    if (StaffInterface.isTechnicianShell(_mode)) {
      if (!mounted) return;
      await SuperAppNavigator.switchStaffInterface(
        context,
        widget.api,
        newMode: _mode,
        loginPayload: widget.loginPayload,
      );
      return;
    }
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
      MaterialPageRoute(builder: (_) => LoginHubScreen(api: widget.api)),
      (_) => false,
    );
  }

  void _go(int i) => setState(() => _tab = i);

  void _push(Widget screen) => Navigator.push(context, RadiantPageRoute(page: screen));

  void _openCollect() => _push(StaffCollectionScreen(api: widget.api, active: true));

  void _openRoleSwitcher() {
    final caps = _roleCaps;
    if (caps == null || !caps.hasMultipleInterfaces) return;
    showRoleSwitcherSheet(
      context,
      api: widget.api,
      capabilities: caps,
      currentMode: _mode,
      loginPayload: widget.loginPayload,
    );
  }

  void _onQuickAction(String key) {
    switch (key) {
      case 'collect':
        _openCollect();
      case 'approval':
        _push(StaffApprovalsScreen(api: widget.api));
      case 'billing':
        _push(StaffBillingHubScreen(api: widget.api));
      case 'tickets':
        _push(StaffCreateTicketScreen(api: widget.api));
      case 'support':
        _go(2);
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
        _openCollect();
      case 'packages':
        _push(StaffPackagesScreen(api: widget.api));
      case 'mikrotik':
        _push(StaffMonitoringScreen(api: widget.api));
      case 'reports':
        _push(StaffReportsScreen(api: widget.api));
      case 'support':
        _go(2);
      case 'comms':
        _push(StaffCommsScreen(api: widget.api));
      case 'inventory':
        _push(StaffInventoryPosScreen(api: widget.api));
      case 'profile':
        _push(StaffProfileScreen(
          api: widget.api,
          user: _user,
          staffMode: _mode,
          roleCapabilities: _roleCaps,
          loginPayload: widget.loginPayload,
        ));
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

    return StaffReceiptLauncher(
      api: widget.api,
      openReceipt: (req) => setState(() => _receiptOverlay = req),
      child: Stack(
        fit: StackFit.expand,
        children: [
          RadiantSuperShell(
            tabIndex: _tab,
            onTab: _go,
            centerAction: _openCollect,
            centerActionIcon: Icons.payments_rounded,
            centerActionLabel: 'Collect',
            destinations: const [
              RadiantNavDestination(
                icon: Icon(Icons.grid_view_rounded),
                selectedIcon: Icon(Icons.grid_view_rounded),
                label: 'Home',
              ),
              RadiantNavDestination(
                icon: Icon(Icons.receipt_long_outlined),
                selectedIcon: Icon(Icons.receipt_long_rounded),
                label: 'Billing',
              ),
              RadiantNavDestination(
                icon: Icon(Icons.forum_outlined),
                selectedIcon: Icon(Icons.forum_rounded),
                label: 'Support',
              ),
              RadiantNavDestination(
                icon: Icon(Icons.apps_rounded),
                selectedIcon: Icon(Icons.apps_rounded),
                label: 'Menu',
              ),
            ],
            pages: [
              _buildHomeTab(online),
              StaffBillingHubScreen(api: widget.api, embedded: true),
              StaffTicketsScreen(api: widget.api, active: _tab == 2, staffUserId: _user?['id'] as int?),
              StaffMenuTab(
                api: widget.api,
                modules: _dash?.modules ?? const [],
                user: _user,
                staffMode: _mode,
                roleCapabilities: _roleCaps,
                loginPayload: widget.loginPayload,
                onModule: _openModule,
                onTasks: () => _push(StaffTasksScreen(api: widget.api, active: true)),
                active: _tab == 3,
              ),
            ],
          ),
          if (_receiptOverlay != null)
            Positioned(
              left: 0,
              right: 0,
              top: 0,
              bottom: 68,
              child: Material(
                elevation: 12,
                color: DesignTokens.lightBg,
                child: StaffMoneyReceiptScreen(
                  embedded: true,
                  api: widget.api,
                  paymentId: _receiptOverlay!.paymentId,
                  initialPdfUrl: _receiptOverlay!.initialPdfUrl,
                  seedData: _receiptOverlay!.seedData,
                  onClose: () => setState(() => _receiptOverlay = null),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildHomeTab(bool online) {
    if (_loading && _dash == null) return const RadiantDashboardSkeleton();
    if (_error != null && _dash == null) {
      return ErrorStateView(failure: _error!, onRetry: _load);
    }
    final d = _dash;
    if (d == null) return const RadiantDashboardSkeleton();

    return RefreshIndicator(
      onRefresh: _load,
      color: RadiantTokens.brand,
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          if (!online) const OfflineBanner(),
          RadiantScreenHeader(
            title: 'Operations',
            subtitle: '${_user?['name'] ?? 'Staff'} · ${RemoteConfig.appName}',
            trailing: [
              if (_roleCaps?.hasMultipleInterfaces == true)
                RadiantHeaderIcon(icon: Icons.swap_horiz_rounded, onPressed: _openRoleSwitcher, tooltip: 'Switch role'),
              RadiantHeaderIcon(icon: Icons.map_outlined, onPressed: () => _push(StaffGisMapScreen(api: widget.api)), tooltip: 'Map'),
              RadiantHeaderIcon(icon: Icons.search_rounded, onPressed: () => _push(StaffGlobalSearchScreen(api: widget.api)), tooltip: 'Search'),
              RadiantHeaderIcon(icon: Icons.auto_awesome_rounded, onPressed: () => _push(StaffAiScreen(api: widget.api)), tooltip: 'AI'),
              RadiantHeaderIcon(icon: Icons.refresh_rounded, onPressed: _load, tooltip: 'Refresh'),
              RadiantHeaderIcon(icon: Icons.logout_rounded, onPressed: _logout, tooltip: 'Sign out'),
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
                    child: RadiantGlassCard(
                      child: Row(
                        children: [
                          Icon(Icons.sync_problem_rounded, color: context.radiant.warning),
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
                    child: RadiantGlassCard(
                      onTap: () => _push(StaffTeamDiscountScreen(api: widget.api)),
                      child: Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(9),
                            decoration: BoxDecoration(
                              gradient: LinearGradient(
                                colors: [RadiantTokens.brandDeep, RadiantTokens.accentCyan],
                              ),
                              borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
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
                          Icon(Icons.chevron_right_rounded, color: context.radiant.muted),
                        ],
                      ),
                    ),
                  ),
                if (d.quickActions.isNotEmpty) ...[
                  const RadiantSectionHeader(title: 'Quick actions'),
                  RadiantQuickActionGrid(actions: d.quickActions, onAction: _onQuickAction),
                  const SizedBox(height: 16),
                ],
                const RadiantSectionHeader(title: 'Today'),
                _kpiRow(d.kpis),
                const SizedBox(height: 16),
                const RadiantSectionHeader(title: 'Collection overview'),
                _financeOverview(d.billing, d.finance),
                if (d.finance.hasExtended) ...[
                  const SizedBox(height: 16),
                  const RadiantSectionHeader(title: 'Finance & reseller'),
                  _resellerFinanceCard(d.finance),
                ],
                const SizedBox(height: 16),
                _revenueChart7d(d.revenue7d),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(child: _statusCard('Tickets', d.tickets)),
                    const SizedBox(width: 12),
                    Expanded(child: _statusCard('Tasks', d.tasks)),
                  ],
                ),
                if (d.zoneChart.isNotEmpty) ...[const SizedBox(height: 16), _zoneChart(d.zoneChart)],
                const SizedBox(height: 88),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _noticeCard(Map<String, dynamic> n) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: RadiantGlassCard(
        child: Row(
          children: [
            Icon(Icons.campaign_rounded, color: RadiantTokens.brand),
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
    return RadiantGlassCard(
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
        RadiantKpiTile(icon: Icons.account_balance_wallet_rounded, label: 'Reseller wallet', value: '৳${_fmt.format(f.resellerWallet)}', color: RadiantTokens.brand, compact: true),
        RadiantKpiTile(icon: Icons.handshake_rounded, label: 'Reseller settled (mo)', value: '৳${_fmt.format(f.resellerSettledMonth)}', color: RadiantTokens.accentCyan, compact: true),
        RadiantKpiTile(icon: Icons.badge_rounded, label: 'Paid salary (mo)', value: '৳${_fmt.format(f.paidSalaryMonth)}', color: RadiantTokens.warning, compact: true),
        RadiantKpiTile(icon: Icons.receipt_long_rounded, label: 'Expense (mo)', value: '৳${_fmt.format(f.expenseMonth)}', color: RadiantTokens.danger, compact: true),
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
      childAspectRatio: 1.45,
      children: [
        RadiantKpiTile(label: "Today's collection", value: '৳${_fmt.format(k.collectedToday)}', icon: Icons.payments_rounded, color: RadiantTokens.success, compact: true),
        RadiantKpiTile(label: 'Cash on hand', value: '৳${_fmt.format(k.cashOnHand)}', icon: Icons.account_balance_wallet_rounded, color: RadiantTokens.brand, compact: true),
        RadiantKpiTile(label: 'Online PPP', value: '${k.onlineClients}', icon: Icons.wifi_rounded, color: RadiantTokens.accentCyan, compact: true),
        RadiantKpiTile(label: 'Due clients', value: '${k.dueClients}', icon: Icons.warning_amber_rounded, color: RadiantTokens.warning, compact: true),
        RadiantKpiTile(label: 'Active clients', value: '${k.activeClients}', icon: Icons.groups_rounded, color: RadiantTokens.brand, compact: true),
        RadiantKpiTile(label: 'Expire today', value: '${k.expiringToday}', icon: Icons.event_busy_rounded, color: RadiantTokens.accent, compact: true),
      ],
    );
  }

  Widget _revenueChart7d(RevenueSeries series) {
    if (series.isEmpty) return const SizedBox.shrink();
    final collected = series.collected;
    final labels = series.labels;
    final maxY = collected.fold<double>(0, (a, b) => a > b ? a : b) * 1.2 + 1;

    return RadiantGlassCard(
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
    return RadiantGlassCard(
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

    return RadiantGlassCard(
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
