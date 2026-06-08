import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../config/remote_config.dart';
import '../core/network/api_result.dart';
import '../core/network/connectivity.dart';
import '../core/theme/design_tokens.dart';
import '../core/widgets/app_refresh.dart';
import '../core/widgets/states.dart';
import '../design_system/components/radiant_glass_card.dart';
import '../design_system/components/radiant_kpi_tile.dart';
import '../design_system/components/radiant_section.dart';
import '../design_system/components/radiant_skeleton.dart';
import '../design_system/navigation/radiant_super_shell.dart';
import '../design_system/radiant_tokens.dart';
import '../features/dashboard_customer/data/customer_dashboard_repository.dart';
import '../features/dashboard_customer/domain/customer_dashboard.dart';
import '../services/api_service.dart';
import '../widgets/usage_area_chart.dart';
import 'customer_speed_test_screen.dart';
import 'customer_ai_screen.dart';
import 'customer_bills_screen.dart';
import 'customer_onu_screen.dart';
import 'customer_packages_screen.dart';
import 'customer_referral_screen.dart';
import 'customer_password_screen.dart';
import 'customer_pay_screen.dart';
import 'customer_tickets_screen.dart';
import 'customer_usage_screen.dart';
import 'login_hub_screen.dart';

/// Customer shell — Radiant 3.0 complete UI rebuild (logic preserved).
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
    _usageTimer = Timer.periodic(const Duration(seconds: 5), (_) => _pollUsage());
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
      RadiantPageRoute(page: LoginHubScreen(api: widget.api)),
      (_) => false,
    );
  }

  void _go(int i) => setState(() => _tab = i);

  void _push(Widget screen) => Navigator.push(context, RadiantPageRoute(page: screen));

  void _openPay() => _push(CustomerPayScreen(api: widget.api, active: true));
  void _openPackages() => _push(CustomerPackagesScreen(api: widget.api));
  void _openPassword() => _push(CustomerPasswordScreen(api: widget.api));
  void _openTickets() => _go(2);
  void _openPaymentHistory() => _push(CustomerBillsScreen(api: widget.api, onPay: _openPay));
  void _openSpeedTest() => _push(const CustomerSpeedTestScreen(active: true));

  @override
  Widget build(BuildContext context) {
    return RadiantSuperShell(
      tabIndex: _tab,
      onTab: _go,
      centerAction: _openPay,
      centerActionIcon: Icons.bolt_rounded,
      centerActionLabel: 'Pay',
      destinations: const [
        RadiantNavDestination(icon: Icon(Icons.grid_view_rounded), selectedIcon: Icon(Icons.grid_view_rounded), label: 'Home'),
        RadiantNavDestination(icon: Icon(Icons.receipt_long_outlined), selectedIcon: Icon(Icons.receipt_long_rounded), label: 'Billing'),
        RadiantNavDestination(icon: Icon(Icons.forum_outlined), selectedIcon: Icon(Icons.forum_rounded), label: 'Support'),
        RadiantNavDestination(icon: Icon(Icons.person_outline_rounded), selectedIcon: Icon(Icons.person_rounded), label: 'Profile'),
      ],
      pages: [
        _CustomerDashboardTab(
          api: widget.api,
          fmt: _fmt,
          onPay: _openPay,
          onPackages: _openPackages,
          onTickets: _openTickets,
          onPaymentHistory: _openPaymentHistory,
          onUsage: () => _push(CustomerUsageScreen(api: widget.api)),
          onOnu: () => _push(CustomerOnuScreen(api: widget.api)),
          onAi: () => _push(CustomerAiScreen(api: widget.api)),
          onReferral: () {
            final dash = ref.read(customerDashboardProvider).valueOrNull;
            _push(CustomerReferralScreen(
              api: widget.api,
              customerCode: dash?.code,
              customerName: dash?.name,
            ));
          },
          onSpeedTest: _openSpeedTest,
        ),
        CustomerBillsScreen(api: widget.api, active: _tab == 1, embedded: true, onPay: _openPay),
        CustomerTicketsScreen(api: widget.api, active: _tab == 2, embedded: true),
        _CustomerProfileTab(
          api: widget.api,
          onLogout: _logout,
          onPassword: _openPassword,
          onPackages: _openPackages,
          onReferral: () {
            final dash = ref.read(customerDashboardProvider).valueOrNull;
            _push(CustomerReferralScreen(
              api: widget.api,
              customerCode: dash?.code,
              customerName: dash?.name,
            ));
          },
          onAi: () => _push(CustomerAiScreen(api: widget.api)),
          onSpeedTest: _openSpeedTest,
          onUsage: () => _push(CustomerUsageScreen(api: widget.api)),
          onOnu: () => _push(CustomerOnuScreen(api: widget.api)),
        ),
      ],
    );
  }
}

class _CustomerDashboardTab extends ConsumerWidget {
  const _CustomerDashboardTab({
    required this.api,
    required this.fmt,
    required this.onPay,
    required this.onPackages,
    required this.onTickets,
    required this.onPaymentHistory,
    required this.onUsage,
    required this.onOnu,
    required this.onAi,
    required this.onReferral,
    required this.onSpeedTest,
  });

  final ApiService api;
  final NumberFormat fmt;
  final VoidCallback onPay;
  final VoidCallback onPackages;
  final VoidCallback onTickets;
  final VoidCallback onPaymentHistory;
  final VoidCallback onUsage;
  final VoidCallback onOnu;
  final VoidCallback onAi;
  final VoidCallback onReferral;
  final VoidCallback onSpeedTest;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(customerDashboardProvider);
    final online = ref.watch(isOnlineProvider);

    return AppRefresh(
      onRefresh: () => ref.read(customerDashboardProvider.notifier).refresh(),
      child: async.when(
        skipLoadingOnRefresh: true,
        skipLoadingOnReload: true,
        loading: () => const RadiantDashboardSkeleton(),
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
        data: (d) => _build(context, ref, d, online),
      ),
    );
  }

  Widget _build(BuildContext context, WidgetRef ref, CustomerDashboard d, bool online) {
    final brand = context.radiant;
    final pad = MediaQuery.paddingOf(context).top;

    return ListView(
      padding: EdgeInsets.zero,
      children: [
        if (!online) const OfflineBanner(),
        RadiantMeshBackground(
          child: Padding(
            padding: EdgeInsets.fromLTRB(20, pad + 12, 20, 28),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      width: 52,
                      height: 52,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        gradient: LinearGradient(
                          colors: [
                            RadiantTokens.brand.withValues(alpha: 0.9),
                            RadiantTokens.accent,
                          ],
                        ),
                        boxShadow: [
                          BoxShadow(
                            color: brand.glow,
                            blurRadius: 16,
                            offset: const Offset(0, 4),
                          ),
                        ],
                      ),
                      alignment: Alignment.center,
                      child: Text(
                        d.name.isNotEmpty ? d.name[0].toUpperCase() : '?',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 22,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Welcome back',
                            style: context.text.labelMedium?.copyWith(
                              color: brand.muted,
                              fontWeight: FontWeight.w500,
                            ),
                          ),
                          Text(
                            d.name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: context.text.headlineSmall?.copyWith(
                              fontWeight: FontWeight.w800,
                              letterSpacing: -0.5,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    RadiantStatusChip(
                      label: d.status,
                      color: d.connected ? brand.success : brand.warning,
                      icon: d.connected ? Icons.wifi_rounded : Icons.wifi_off_rounded,
                    ),
                    if (d.code.isNotEmpty)
                      RadiantStatusChip(
                        label: d.code,
                        color: RadiantTokens.brand,
                        icon: Icons.tag_rounded,
                      ),
                  ],
                ),
                const SizedBox(height: 20),
                RadiantGlassCard(
                  padding: const EdgeInsets.all(18),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Amount due',
                              style: context.text.labelMedium?.copyWith(color: brand.muted),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '৳${fmt.format(d.totalDue)}',
                              style: context.text.headlineMedium?.copyWith(
                                fontWeight: FontWeight.w800,
                                letterSpacing: -0.8,
                              ),
                            ),
                            Text(
                              d.packageName,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: context.text.bodySmall?.copyWith(color: brand.muted),
                            ),
                          ],
                        ),
                      ),
                      FilledButton.icon(
                        onPressed: onPay,
                        icon: const Icon(Icons.bolt_rounded, size: 18),
                        label: const Text('Pay now'),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 100),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              RadiantGlassCard(
                padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceAround,
                  children: [
                    RadiantQuickChip(icon: Icons.receipt_long_rounded, label: 'Invoices', onTap: onPaymentHistory, color: RadiantTokens.brand),
                    RadiantQuickChip(icon: Icons.swap_horiz_rounded, label: 'Package', onTap: onPackages, color: RadiantTokens.accent),
                    RadiantQuickChip(icon: Icons.speed_rounded, label: 'Speed', onTap: onSpeedTest, color: RadiantTokens.accentCyan),
                    RadiantQuickChip(icon: Icons.router_rounded, label: 'Network', onTap: onOnu, color: brand.info),
                  ],
                ),
              ),
              const SizedBox(height: 22),
              const RadiantSectionHeader(title: 'Account overview'),
              GridView.count(
                crossAxisCount: 2,
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                mainAxisSpacing: 12,
                crossAxisSpacing: 12,
                childAspectRatio: 1.45,
                children: [
                  RadiantKpiTile(
                    icon: Icons.payments_outlined,
                    label: 'Monthly bill',
                    value: '৳${fmt.format(d.monthlyBill)}',
                    color: RadiantTokens.brand,
                  ),
                  RadiantKpiTile(
                    icon: Icons.check_circle_outline_rounded,
                    label: 'Paid this cycle',
                    value: '৳${fmt.format(d.paid)}',
                    color: brand.success,
                  ),
                  RadiantKpiTile(
                    icon: Icons.speed_rounded,
                    label: 'Plan speed',
                    value: d.packageName,
                    color: RadiantTokens.accentCyan,
                    compact: true,
                  ),
                  RadiantKpiTile(
                    icon: Icons.event_outlined,
                    label: 'Valid until',
                    value: d.expireDate,
                    color: brand.warning,
                    compact: true,
                  ),
                ],
              ),
              const SizedBox(height: 22),
              const RadiantSectionHeader(title: 'Live connection'),
              RadiantGlassCard(
                child: Column(
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceAround,
                      children: [
                        _trafficStat(context, 'Down', d.traffic.downloadHuman, Icons.south_west_rounded, RadiantTokens.accentCyan),
                        _trafficStat(context, 'Up', d.traffic.uploadHuman, Icons.north_east_rounded, brand.success),
                        _trafficStat(context, 'Uptime', d.traffic.uptime, Icons.schedule_rounded, RadiantTokens.brand),
                      ],
                    ),
                    const SizedBox(height: 12),
                    UsageAreaChart(chart: d.traffic.chart),
                    Align(
                      alignment: Alignment.centerRight,
                      child: TextButton(onPressed: onUsage, child: const Text('Usage analytics')),
                    ),
                  ],
                ),
              ),
              if (RemoteConfig.aiAssistant) ...[
                const SizedBox(height: 16),
                RadiantGlassCard(
                  onTap: onAi,
                  child: Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            colors: [
                              RadiantTokens.accent.withValues(alpha: 0.2),
                              RadiantTokens.brand.withValues(alpha: 0.15),
                            ],
                          ),
                          borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
                        ),
                        child: const Icon(Icons.auto_awesome_rounded, color: RadiantTokens.accent),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('AI Assistant', style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
                            Text(
                              'Ask about bills, invoices, or open a ticket',
                              style: context.text.bodySmall?.copyWith(color: brand.muted),
                            ),
                          ],
                        ),
                      ),
                      Icon(Icons.chevron_right_rounded, color: brand.muted),
                    ],
                  ),
                ),
              ],
              if (d.notices.isNotEmpty) ...[
                const SizedBox(height: 22),
                const RadiantSectionHeader(title: 'Updates'),
                ...d.notices.take(3).map((n) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: RadiantGlassCard(
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Icon(Icons.notifications_active_outlined, color: RadiantTokens.brand, size: 20),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(n.title, style: context.text.bodyMedium?.copyWith(fontWeight: FontWeight.w600)),
                                  if (n.body.isNotEmpty)
                                    Text(n.body, style: context.text.bodySmall?.copyWith(color: brand.muted)),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    )),
              ],
            ],
          ),
        ),
      ],
    );
  }

  Widget _trafficStat(BuildContext context, String label, String value, IconData icon, Color color) {
    return Column(
      children: [
        Icon(icon, size: 18, color: color),
        const SizedBox(height: 6),
        Text(value, style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
        Text(label, style: context.text.labelSmall?.copyWith(color: context.radiant.muted)),
      ],
    );
  }
}

class _CustomerProfileTab extends ConsumerWidget {
  const _CustomerProfileTab({
    required this.api,
    required this.onLogout,
    required this.onPassword,
    required this.onPackages,
    required this.onReferral,
    required this.onAi,
    required this.onSpeedTest,
    required this.onUsage,
    required this.onOnu,
  });

  final ApiService api;
  final VoidCallback onLogout;
  final VoidCallback onPassword;
  final VoidCallback onPackages;
  final VoidCallback onReferral;
  final VoidCallback onAi;
  final VoidCallback onSpeedTest;
  final VoidCallback onUsage;
  final VoidCallback onOnu;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dash = ref.watch(customerDashboardProvider).valueOrNull;
    final brand = context.radiant;

    final items = <(IconData, String, VoidCallback)>[
      (Icons.lock_outline_rounded, 'Change password', onPassword),
      (Icons.inventory_2_outlined, 'My package', onPackages),
      (Icons.analytics_outlined, 'Usage analytics', onUsage),
      (Icons.router_outlined, 'Network status', onOnu),
      (Icons.speed_outlined, 'Speed test', onSpeedTest),
      (Icons.card_giftcard_outlined, 'Refer a friend', onReferral),
      if (RemoteConfig.aiAssistant) (Icons.auto_awesome_outlined, 'AI assistant', onAi),
    ];

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 100),
      children: [
        Text('Profile', style: context.text.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
        const SizedBox(height: 6),
        Text(
          dash?.name ?? 'Customer account',
          style: context.text.bodyMedium?.copyWith(color: brand.muted),
        ),
        const SizedBox(height: 20),
        RadiantGlassCard(
          child: Column(
            children: [
              for (var i = 0; i < items.length; i++) ...[
                if (i > 0) Divider(height: 1, color: brand.border),
                ListTile(
                  leading: Icon(items[i].$1, color: RadiantTokens.brand),
                  title: Text(items[i].$2),
                  trailing: Icon(Icons.chevron_right_rounded, color: brand.muted, size: 20),
                  onTap: items[i].$3,
                  contentPadding: EdgeInsets.zero,
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 16),
        OutlinedButton.icon(
          onPressed: onLogout,
          icon: const Icon(Icons.logout_rounded),
          label: const Text('Sign out'),
          style: OutlinedButton.styleFrom(
            foregroundColor: brand.danger,
            side: BorderSide(color: brand.danger.withValues(alpha: 0.5)),
            minimumSize: const Size.fromHeight(48),
          ),
        ),
      ],
    );
  }
}
