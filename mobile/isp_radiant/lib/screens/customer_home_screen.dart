import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../config/remote_config.dart';
import '../core/network/api_result.dart';
import '../core/network/connectivity.dart';
import '../core/theme/design_tokens.dart';
import '../core/widgets/app_refresh.dart';
import '../core/widgets/cards.dart';
import '../core/widgets/skeleton.dart';
import '../core/widgets/states.dart';
import '../features/dashboard_customer/data/customer_dashboard_repository.dart';
import '../features/dashboard_customer/domain/customer_dashboard.dart';
import '../services/api_service.dart';
import '../widgets/usage_area_chart.dart';
import 'client_ping_screen.dart';
import 'customer_ai_screen.dart';
import 'customer_bills_screen.dart';
import 'customer_onu_screen.dart';
import 'customer_packages_screen.dart';
import 'customer_password_screen.dart';
import 'customer_pay_screen.dart';
import 'customer_tickets_screen.dart';
import 'customer_usage_screen.dart';
import 'login_screen.dart';

class CustomerHomeScreen extends ConsumerStatefulWidget {
  const CustomerHomeScreen({super.key, required this.api, required this.loginPayload});

  final ApiService api;
  final Map<String, dynamic> loginPayload;

  @override
  ConsumerState<CustomerHomeScreen> createState() => _CustomerHomeScreenState();
}

class _CustomerHomeScreenState extends ConsumerState<CustomerHomeScreen> {
  int _tab = 0;
  Timer? _usageTimer;
  final _fmt = NumberFormat('#,##0.00');

  @override
  void initState() {
    super.initState();
    _usageTimer = Timer.periodic(const Duration(seconds: 2), (_) => _pollUsage());
  }

  @override
  void dispose() {
    _usageTimer?.cancel();
    super.dispose();
  }

  Future<void> _pollUsage() async {
    if (_tab != 0 || !mounted) return;
    final t = await ref.read(customerDashboardRepositoryProvider).liveTraffic();
    if (t != null && mounted) {
      ref.read(customerDashboardProvider.notifier).applyTraffic(t);
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

  void _push(Widget screen) =>
      Navigator.push(context, MaterialPageRoute(builder: (_) => screen));

  void _openPay() => _push(CustomerPayScreen(api: widget.api));
  void _openPackages() => _push(CustomerPackagesScreen(api: widget.api));
  void _openPassword() => _push(CustomerPasswordScreen(api: widget.api));
  void _openTickets() => _push(CustomerTicketsScreen(api: widget.api));

  @override
  Widget build(BuildContext context) {
    const titles = ['', 'Ping', 'Payment History'];

    return Scaffold(
      appBar: _tab == 0 ? null : AppBar(title: Text(titles[_tab])),
      body: IndexedStack(
        index: _tab,
        children: [
          _HomeTab(
            api: widget.api,
            fmt: _fmt,
            onLogout: _logout,
            onPay: _openPay,
            onPackages: _openPackages,
            onPassword: _openPassword,
            onTickets: _openTickets,
            onPaymentHistory: () => _go(2),
            onUsage: () => _push(CustomerUsageScreen(api: widget.api)),
            onOnu: () => _push(CustomerOnuScreen(api: widget.api)),
            onAi: () => _push(CustomerAiScreen(api: widget.api)),
          ),
          ClientPingScreen(active: _tab == 1),
          CustomerBillsScreen(api: widget.api, active: _tab == 2, onPay: _openPay, embedded: true),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: _go,
        destinations: const [
          NavigationDestination(
              icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home_rounded), label: 'Home'),
          NavigationDestination(
              icon: Icon(Icons.speed_outlined), selectedIcon: Icon(Icons.speed_rounded), label: 'Ping'),
          NavigationDestination(
              icon: Icon(Icons.credit_card_outlined),
              selectedIcon: Icon(Icons.credit_card_rounded),
              label: 'Payments'),
        ],
      ),
    );
  }
}

class _HomeTab extends ConsumerWidget {
  const _HomeTab({
    required this.api,
    required this.fmt,
    required this.onLogout,
    required this.onPay,
    required this.onPackages,
    required this.onPassword,
    required this.onTickets,
    required this.onPaymentHistory,
    required this.onUsage,
    required this.onOnu,
    required this.onAi,
  });

  final ApiService api;
  final NumberFormat fmt;
  final VoidCallback onLogout;
  final VoidCallback onPay;
  final VoidCallback onPackages;
  final VoidCallback onPassword;
  final VoidCallback onTickets;
  final VoidCallback onPaymentHistory;
  final VoidCallback onUsage;
  final VoidCallback onOnu;
  final VoidCallback onAi;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(customerDashboardProvider);
    final online = ref.watch(isOnlineProvider);

    return AppRefresh(
      onRefresh: () => ref.read(customerDashboardProvider.notifier).refresh(),
      child: async.when(
        skipLoadingOnRefresh: true,
        skipLoadingOnReload: true,
        loading: () => const _DashboardSkeleton(),
        error: (e, _) => ListView(
          children: [
            SizedBox(
              height: MediaQuery.sizeOf(context).height * 0.7,
              child: ErrorStateView(
                failure: e is Failure ? e : Failure.from(e),
                onRetry: () => ref.read(customerDashboardProvider.notifier).refresh(),
              ),
            ),
          ],
        ),
        data: (d) => _content(context, ref, d, online),
      ),
    );
  }

  Widget _content(BuildContext context, WidgetRef ref, CustomerDashboard d, bool online) {
    return ListView(
      padding: EdgeInsets.zero,
      children: [
        if (!online) const OfflineBanner(),
        _header(context, d),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _quickActions(context),
              const SizedBox(height: 18),
              const SectionHeader(title: 'Account summary'),
              _summaryGrid(context, d),
              const SizedBox(height: 18),
              const SectionHeader(title: 'Services'),
              _serviceTiles(context),
              const SizedBox(height: 18),
              const SectionHeader(title: 'Live usage'),
              _usageCard(context, d),
              if (d.notices.isNotEmpty) ...[
                const SizedBox(height: 18),
                const SectionHeader(title: 'News & events'),
                ...d.notices.take(3).map((n) => _noticeCard(context, n)),
              ],
            ],
          ),
        ),
      ],
    );
  }

  Widget _header(BuildContext context, CustomerDashboard d) {
    final pad = MediaQuery.paddingOf(context).top;
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: context.brand.heroGradient,
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: const BorderRadius.vertical(bottom: Radius.circular(28)),
      ),
      padding: EdgeInsets.fromLTRB(16, pad + 14, 16, 22),
      child: Column(
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 26,
                backgroundColor: Colors.white.withValues(alpha: 0.22),
                child: Text(
                  d.name.isNotEmpty ? d.name[0].toUpperCase() : '?',
                  style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.bold),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(d.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w800)),
                    if (d.code.isNotEmpty)
                      Text('ID: ${d.code}',
                          style: TextStyle(color: Colors.white.withValues(alpha: 0.85), fontSize: 12)),
                  ],
                ),
              ),
              IconButton(
                icon: const Icon(Icons.logout_rounded, color: Colors.white),
                onPressed: onLogout,
                tooltip: 'Logout',
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              StatusPill(
                label: d.status,
                color: d.connected ? DesignTokens.success : DesignTokens.warning,
                icon: d.connected ? Icons.check_circle_rounded : Icons.error_rounded,
              ),
              const Spacer(),
              if (d.totalDue > 0)
                StatusPill(
                  label: 'Due ৳${fmt.format(d.totalDue)}',
                  color: Colors.white,
                  icon: Icons.account_balance_wallet_rounded,
                ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _quickActions(BuildContext context) {
    final items = [
      (Icons.bolt_rounded, 'Pay', onPay, DesignTokens.success),
      (Icons.swap_horiz_rounded, 'Package', onPackages, DesignTokens.primary),
      (Icons.lock_outline_rounded, 'Password', onPassword, DesignTokens.info),
      (Icons.support_agent_rounded, 'Ticket', onTickets, DesignTokens.warning),
    ];
    return AppCard(
      padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 6),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceAround,
        children: items.map((i) {
          return InkWell(
            onTap: i.$3,
            borderRadius: BorderRadius.circular(DesignTokens.radiusSm),
            child: SizedBox(
              width: 72,
              child: Column(
                children: [
                  Container(
                    padding: const EdgeInsets.all(11),
                    decoration: BoxDecoration(
                      color: i.$4.withValues(alpha: 0.14),
                      borderRadius: BorderRadius.circular(DesignTokens.radiusSm),
                    ),
                    child: Icon(i.$1, color: i.$4, size: 22),
                  ),
                  const SizedBox(height: 6),
                  Text(i.$2,
                      textAlign: TextAlign.center,
                      style: context.text.labelSmall?.copyWith(fontWeight: FontWeight.w600)),
                ],
              ),
            ),
          );
        }).toList(),
      ),
    );
  }

  Widget _summaryGrid(BuildContext context, CustomerDashboard d) {
    final cards = [
      StatCard(
          icon: Icons.receipt_long_rounded,
          label: 'Monthly bill',
          value: '৳${fmt.format(d.monthlyBill)}',
          color: DesignTokens.primary),
      StatCard(
          icon: Icons.paid_rounded,
          label: 'Paid',
          value: '৳${fmt.format(d.paid)}',
          color: DesignTokens.success),
      StatCard(
          icon: Icons.wifi_rounded,
          label: 'Package',
          value: d.packageName,
          color: DesignTokens.info),
      StatCard(
          icon: Icons.event_rounded,
          label: 'Expires',
          value: d.expireDate,
          color: DesignTokens.warning),
    ];
    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: 12,
      crossAxisSpacing: 12,
      childAspectRatio: 1.55,
      children: cards,
    );
  }

  Widget _serviceTiles(BuildContext context) {
    final tiles = <Widget>[
      _serviceTile(context, 'Packages', Icons.inventory_2_rounded, DesignTokens.primary, onPackages),
      _serviceTile(context, 'Payments', Icons.history_rounded, DesignTokens.success, onPaymentHistory),
      _serviceTile(context, 'Support', Icons.chat_bubble_outline_rounded, DesignTokens.warning, onTickets),
      _serviceTile(context, 'ONU / Signal', Icons.router_rounded, DesignTokens.info, onOnu),
    ];
    if (RemoteConfig.aiAssistant) {
      tiles.add(_serviceTile(context, 'AI assistant', Icons.smart_toy_rounded, DesignTokens.pink, onAi));
    }
    return Wrap(spacing: 12, runSpacing: 12, children: [
      for (final t in tiles) SizedBox(width: (MediaQuery.sizeOf(context).width - 32 - 12) / 2, child: t),
    ]);
  }

  Widget _serviceTile(BuildContext context, String label, IconData icon, Color color, VoidCallback onTap) {
    return AppCard(
      onTap: onTap,
      padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 14),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(9),
            decoration: BoxDecoration(
                color: color.withValues(alpha: 0.14),
                borderRadius: BorderRadius.circular(DesignTokens.radiusSm)),
            child: Icon(icon, color: color, size: 20),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: context.text.bodyMedium?.copyWith(fontWeight: FontWeight.w600)),
          ),
          Icon(Icons.chevron_right_rounded, color: context.brand.textMuted, size: 18),
        ],
      ),
    );
  }

  Widget _usageCard(BuildContext context, CustomerDashboard d) {
    final t = d.traffic;
    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _trafficCol(context, 'Download', t.downloadHuman, Icons.south_rounded, DesignTokens.info),
              Container(width: 1, height: 36, color: context.brand.border),
              _trafficCol(context, 'Upload', t.uploadHuman, Icons.north_rounded, DesignTokens.success),
              Container(width: 1, height: 36, color: context.brand.border),
              _trafficCol(context, 'Uptime', t.uptime, Icons.timer_outlined, DesignTokens.primary),
            ],
          ),
          const SizedBox(height: 8),
          UsageAreaChart(chart: t.chart),
          Align(
            alignment: Alignment.centerRight,
            child: TextButton(onPressed: onUsage, child: const Text('Full usage details')),
          ),
        ],
      ),
    );
  }

  Widget _trafficCol(BuildContext context, String label, String value, IconData icon, Color color) {
    return Column(
      children: [
        Icon(icon, size: 20, color: color),
        const SizedBox(height: 4),
        Text(value, style: context.text.bodyMedium?.copyWith(fontWeight: FontWeight.w700)),
        Text(label, style: context.text.labelSmall?.copyWith(color: context.brand.textMuted)),
      ],
    );
  }

  Widget _noticeCard(BuildContext context, DashboardNotice n) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: AppCard(
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(9),
              decoration: BoxDecoration(
                  color: DesignTokens.primary.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(DesignTokens.radiusSm)),
              child: const Icon(Icons.campaign_rounded, color: DesignTokens.primary, size: 20),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(n.title, style: context.text.bodyMedium?.copyWith(fontWeight: FontWeight.w700)),
                  if (n.body.isNotEmpty)
                    Text(n.body,
                        style: context.text.bodySmall?.copyWith(color: context.brand.textMuted)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Skeleton placeholder shown on first load.
class _DashboardSkeleton extends StatelessWidget {
  const _DashboardSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Row(children: [
          const Skeleton.circle(size: 52),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: const [
              Skeleton(width: 140, height: 18),
              SizedBox(height: 8),
              Skeleton(width: 90, height: 12),
            ]),
          ),
        ]),
        const SizedBox(height: 20),
        const SkeletonCard(height: 84),
        const SizedBox(height: 16),
        GridView.count(
          crossAxisCount: 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 12,
          crossAxisSpacing: 12,
          childAspectRatio: 1.55,
          children: const [SkeletonCard(), SkeletonCard(), SkeletonCard(), SkeletonCard()],
        ),
        const SizedBox(height: 16),
        const SkeletonCard(height: 180),
      ],
    );
  }
}
