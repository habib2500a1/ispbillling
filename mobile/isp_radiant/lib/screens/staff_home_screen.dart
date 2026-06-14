import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/remote_config.dart';
import '../core/navigation/super_app_navigator.dart';
import '../core/roles/role_resolver.dart';
import '../core/roles/staff_interface.dart';
import '../core/network/api_result.dart';
import '../core/network/connectivity.dart';
import '../core/theme/design_tokens.dart';
import '../design_system/components/radiant_glass_card.dart';
import '../design_system/components/radiant_skeleton.dart';
import '../design_system/navigation/radiant_super_shell.dart';
import '../design_system/radiant_tokens.dart';
import '../core/widgets/states.dart';
import '../features/dashboard_staff/data/staff_dashboard_repository.dart';
import '../features/dashboard_staff/domain/staff_dashboard.dart';
import '../services/api_service.dart';
import '../services/offline_sync_service.dart';
import '../services/realtime_service.dart';
import '../utils/app_nav.dart';
import '../widgets/radiant_legacy_dashboard_cards.dart';
import '../widgets/radiant_legacy_dashboard_header.dart';
import '../widgets/radiant_legacy_quick_actions.dart';
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
import 'staff_inventory_pos_screen.dart';
import 'staff_mfs_sms_screen.dart';
import 'staff_team_discount_screen.dart';
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
  final _scaffoldKey = GlobalKey<ScaffoldState>();
  int _tab = 0;
  StaffReceiptRequest? _receiptOverlay;
  StaffDashboard? _dash;
  bool _loading = true;
  Failure? _error;
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

  bool _hasModule(StaffDashboard d, String key) =>
      d.modules.any((m) => m['key']?.toString() == key);

  bool _showBilling(StaffDashboard? d) => d == null || _hasModule(d, 'billing');
  bool _showCollection(StaffDashboard? d) => d == null || _hasModule(d, 'collect');
  bool _showSupport(StaffDashboard? d) => d == null || _hasModule(d, 'support');
  bool _showFinance(StaffDashboard d) =>
      (_user?['user_type']?.toString() == 'Admin') || d.finance.hasExtended;

  List<_StaffTab> _tabsFor(StaffDashboard? d) {
    final tabs = <_StaffTab>[const _StaffTab(label: 'Home', icon: Icons.grid_view_rounded)];
    if (_showBilling(d)) {
      tabs.add(const _StaffTab(label: 'Billing', icon: Icons.receipt_long_outlined, selectedIcon: Icons.receipt_long_rounded));
    }
    if (_showCollection(d)) {
      tabs.add(const _StaffTab(label: 'Collection', icon: Icons.savings_outlined, selectedIcon: Icons.savings_rounded));
    }
    if (_showSupport(d)) {
      tabs.add(const _StaffTab(label: 'Support', icon: Icons.confirmation_number_outlined, selectedIcon: Icons.confirmation_number_rounded));
    }
    tabs.add(const _StaffTab(label: 'Task', icon: Icons.list_alt_outlined, selectedIcon: Icons.list_alt_rounded));
    return tabs;
  }

  Widget _pageForTab(String label, int index, int activeIndex) {
    switch (label) {
      case 'Home':
        return _buildHomeTab(ref.watch(isOnlineProvider));
      case 'Billing':
        return StaffBillingHubScreen(api: widget.api, embedded: true);
      case 'Collection':
        return StaffCollectionScreen(api: widget.api, active: activeIndex == index);
      case 'Support':
        return StaffTicketsScreen(api: widget.api, active: activeIndex == index, staffUserId: _user?['id'] as int?);
      case 'Task':
        return StaffTasksScreen(api: widget.api, active: activeIndex == index);
      default:
        return const SizedBox.shrink();
    }
  }

  @override
  Widget build(BuildContext context) {
    final online = ref.watch(isOnlineProvider);
    final tabs = _tabsFor(_dash);
    final safeTab = _tab.clamp(0, tabs.length - 1);
    if (safeTab != _tab) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) setState(() => _tab = safeTab);
      });
    }

    return StaffReceiptLauncher(
      api: widget.api,
      openReceipt: (req) => setState(() => _receiptOverlay = req),
      child: Stack(
        fit: StackFit.expand,
        children: [
          RadiantSuperShell(
            scaffoldKey: _scaffoldKey,
            legacyBottomBar: true,
            tabIndex: safeTab,
            onTab: _go,
            drawer: Drawer(
              child: StaffMenuTab(
                api: widget.api,
                modules: _dash?.modules ?? const [],
                user: _user,
                staffMode: _mode,
                roleCapabilities: _roleCaps,
                loginPayload: widget.loginPayload,
                onModule: (key) {
                  Navigator.pop(context);
                  _openModule(key);
                },
                onTasks: () {
                  Navigator.pop(context);
                  final taskIndex = tabs.indexWhere((t) => t.label == 'Task');
                  if (taskIndex >= 0) _go(taskIndex);
                },
                active: false,
              ),
            ),
            destinations: [
              for (final t in tabs)
                RadiantNavDestination(
                  icon: Icon(t.icon),
                  selectedIcon: Icon(t.selectedIcon ?? t.icon),
                  label: t.label,
                ),
            ],
            pages: [
              for (var i = 0; i < tabs.length; i++)
                _pageForTab(tabs[i].label, i, safeTab),
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
      color: RadiantLegacyDashboardHeader.primaryBlue,
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          if (!online) const OfflineBanner(),
          RadiantLegacyDashboardHeader(
            name: _user?['name']?.toString() ?? 'Staff',
            userType: _user?['user_type']?.toString() ?? 'Staff',
            status: _user?['status']?.toString() ?? 'Active',
            onSearch: () => _push(StaffGlobalSearchScreen(api: widget.api)),
            onNotifications: () {
              if (_showSupport(d)) _go(tabsIndexForLabel('Support'));
            },
            onMenu: () => _scaffoldKey.currentState?.openDrawer(),
          ),
          Container(
            color: const Color(0xFFF5F7FA),
            padding: const EdgeInsets.fromLTRB(12, 12, 12, 88),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
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
                if (d.quickActions.isNotEmpty) ...[
                  RadiantLegacyQuickActions(actions: d.quickActions, onAction: _onQuickAction),
                  const SizedBox(height: 14),
                ],
                if (_showBilling(d)) ...[
                  RadiantLegacyDashboardCards.billingSummary(d.billing),
                  const SizedBox(height: 12),
                ],
                RadiantLegacyDashboardCards.ticketTaskRow(
                  tickets: d.tickets,
                  tasks: d.tasks,
                  showTickets: _showSupport(d),
                ),
                const SizedBox(height: 12),
                if (_hasModule(d, 'reports') && d.zoneChart.isNotEmpty) ...[
                  RadiantLegacyDashboardCards.zoneChart(d.zoneChart),
                  const SizedBox(height: 12),
                ],
                if (_showFinance(d)) ...[
                  RadiantLegacyDashboardCards.resellerFinance(d.finance, d.billing),
                  const SizedBox(height: 12),
                ],
                if (_showCollection(d))
                  RadiantLegacyDashboardCards.cashOnHand(d.kpis.cashOnHand),
              ],
            ),
          ),
        ],
      ),
    );
  }

  int tabsIndexForLabel(String label) {
    final tabs = _tabsFor(_dash);
    final idx = tabs.indexWhere((t) => t.label == label);
    return idx >= 0 ? idx : 0;
  }
}

class _StaffTab {
  const _StaffTab({
    required this.label,
    required this.icon,
    this.selectedIcon,
  });

  final String label;
  final IconData icon;
  final IconData? selectedIcon;
}
