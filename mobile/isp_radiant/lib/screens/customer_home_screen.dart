import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../config/remote_config.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
import '../widgets/app_shell.dart';
import '../widgets/profile_banner.dart';
import '../widgets/usage_area_chart.dart';
import 'customer_bills_screen.dart';
import 'customer_packages_screen.dart';
import 'customer_password_screen.dart';
import 'customer_pay_screen.dart';
import 'customer_tickets_screen.dart';
import 'client_ping_screen.dart';
import 'customer_onu_screen.dart';
import 'customer_usage_screen.dart';
import 'customer_ai_screen.dart';
import 'login_screen.dart';

class CustomerHomeScreen extends StatefulWidget {
  const CustomerHomeScreen({super.key, required this.api, required this.loginPayload});

  final ApiService api;
  final Map<String, dynamic> loginPayload;

  @override
  State<CustomerHomeScreen> createState() => _CustomerHomeScreenState();
}

class _CustomerHomeScreenState extends State<CustomerHomeScreen> {
  int _tab = 0;
  Map<String, dynamic>? _dash;
  Map<String, dynamic>? _usage;
  bool _loading = true;
  final _fmt = NumberFormat('#,##0.00');

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final data = await widget.api.customerDashboard();
      final usage = await widget.api.customerUsageLive();
      if (mounted) {
        setState(() {
          _dash = data;
          _usage = usage['usage'] as Map<String, dynamic>?;
        });
      }
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
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

  void _openPay() => Navigator.push(context, MaterialPageRoute(builder: (_) => CustomerPayScreen(api: widget.api)));

  void _openPackages() => Navigator.push(context, MaterialPageRoute(builder: (_) => CustomerPackagesScreen(api: widget.api)));

  void _openPassword() => Navigator.push(context, MaterialPageRoute(builder: (_) => CustomerPasswordScreen(api: widget.api)));

  @override
  Widget build(BuildContext context) {
    final customer = (_dash?['customer'] ?? widget.loginPayload['customer']) as Map<String, dynamic>?;
    final summary = _dash?['summary'] as Map<String, dynamic>? ?? {};
    final traffic = _dash?['traffic'] as Map<String, dynamic>? ?? {};
    final connected = summary['status']?.toString() == 'Connected';
    final name = customer?['name']?.toString() ?? 'Client';
    final code = customer?['customer_code']?.toString() ?? '';

    return AppShell(
      tabIndex: _tab,
      onTab: _go,
      title: RemoteConfig.appName,
      actions: [
        IconButton(icon: const Icon(Icons.refresh), onPressed: _load),
        IconButton(icon: const Icon(Icons.logout), onPressed: _logout),
      ],
      floatingActionButton: _tab == 2
          ? FloatingActionButton.extended(
              onPressed: () => CustomerTicketsScreen.showCreateDialog(context, widget.api, onCreated: _load),
              icon: const Icon(Icons.add),
              label: const Text('New ticket'),
            )
          : null,
      destinations: const [
        NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home), label: 'Home'),
        NavigationDestination(icon: Icon(Icons.network_ping), selectedIcon: Icon(Icons.network_ping), label: 'Ping'),
        NavigationDestination(icon: Icon(Icons.support_agent_outlined), selectedIcon: Icon(Icons.support_agent), label: 'Support'),
        NavigationDestination(icon: Icon(Icons.payment_outlined), selectedIcon: Icon(Icons.payment), label: 'Payment'),
      ],
      pages: [
        _buildHome(name, code, summary, traffic, connected),
        ClientPingScreen(active: _tab == 1),
        CustomerTicketsScreen(api: widget.api, active: _tab == 2),
        CustomerBillsScreen(api: widget.api, active: _tab == 3, onPay: _openPay),
      ],
    );
  }

  Widget _buildHome(String name, String code, Map<String, dynamic> summary, Map<String, dynamic> traffic, bool connected) {
    if (_loading) return const Center(child: CircularProgressIndicator());

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: pagePadding(context),
        children: [
          ProfileBanner(
            name: name,
            subtitle: 'Client Code: $code',
            status: 'Status: ${summary['status'] ?? '—'}',
            statusColor: connected ? Colors.lightGreenAccent : Colors.white54,
          ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Wrap(
                alignment: WrapAlignment.spaceAround,
                spacing: 8,
                runSpacing: 12,
                children: [
                  _quick(Icons.payment, 'Recharge', _openPay),
                  _quick(Icons.inventory_2, 'Package', _openPackages),
                  _quick(Icons.lock_outline, 'Password', _openPassword),
                  _quick(Icons.support_agent, 'Ticket', () => _go(2)),
                  if (RemoteConfig.aiAssistant)
                    _quick(Icons.smart_toy_outlined, 'AI', () {
                      Navigator.push(context, MaterialPageRoute(builder: (_) => CustomerAiScreen(api: widget.api)));
                    }),
                ],
              ),
            ),
          ),
          if (RemoteConfig.aiAssistant)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Card(
                child: ListTile(
                  leading: const Icon(Icons.router, color: AppTheme.primary),
                  title: const Text('ONU signal & reboot'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CustomerOnuScreen(api: widget.api))),
                ),
              ),
            ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                children: [
                  _sumRow('Monthly Bill', _fmt.format(summary['monthly_bill'] ?? 0)),
                  _sumRow('Paid', _fmt.format(summary['paid'] ?? 0)),
                  _sumRow('Package', summary['package_name']?.toString() ?? '—'),
                  _sumRow('Expire Date', summary['expire_date']?.toString() ?? '—'),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: _tile('Internet\nPackages', Colors.red.shade400, Icons.inventory_2, _openPackages)),
              const SizedBox(width: 8),
              Expanded(child: _tile('Payment\nHistory', Colors.green.shade500, Icons.history, () => _go(3))),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(child: _tile('Support &\nTicket', Colors.orange.shade400, Icons.chat, () => _go(2))),
              const SizedBox(width: 8),
              Expanded(child: _tile('Pay bill', Colors.blue.shade600, Icons.payment, _openPay)),
            ],
          ),
          const SizedBox(height: 12),
          Card(
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceAround,
                children: [
                  _trafficCol('Upload', traffic['upload_human']?.toString() ?? '—', Icons.arrow_upward),
                  Column(
                    children: [
                      Icon(Icons.sync, color: connected ? Colors.green : Colors.grey),
                      const SizedBox(height: 4),
                      Text(
                        connected ? 'Up Time' : 'Offline',
                        style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w600),
                      ),
                      Text(
                        _usage?['session_started']?.toString() ?? '—',
                        style: const TextStyle(fontSize: 9, color: Colors.grey),
                      ),
                    ],
                  ),
                  _trafficCol('Download', traffic['download_human']?.toString() ?? '—', Icons.arrow_downward),
                ],
              ),
            ),
          ),
          ListTile(
            leading: const Icon(Icons.speed, color: AppTheme.primary),
            title: const Text('Live bandwidth & usage'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => CustomerUsageScreen(api: widget.api)),
            ),
          ),
          const SizedBox(height: 8),
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Data usage', style: TextStyle(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 8),
                  UsageAreaChart(chart: _usage?['chart'] as Map<String, dynamic>?),
                  const SizedBox(height: 6),
                  Text(
                    '↓ ${_usage?['download_human'] ?? '—'} · ↑ ${_usage?['upload_human'] ?? '—'}',
                    style: const TextStyle(fontSize: 11, color: Colors.grey),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _quick(IconData icon, String label, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      child: Column(
        children: [
          Icon(icon, color: AppTheme.primary, size: 26),
          const SizedBox(height: 4),
          Text(label, style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }

  Widget _sumRow(String k, String v) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [Text(k, style: const TextStyle(color: Colors.grey)), Text(v, style: const TextStyle(fontWeight: FontWeight.bold))],
      ),
    );
  }

  Widget _tile(String label, Color color, IconData icon, VoidCallback onTap) {
    return Material(
      color: color,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: SizedBox(
          height: 76,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, color: Colors.white),
              const SizedBox(height: 4),
              Text(label, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _trafficCol(String label, String value, IconData icon) {
    return Column(
      children: [
        Icon(icon, size: 18, color: AppTheme.primary),
        Text(label, style: const TextStyle(fontSize: 10, color: Colors.grey)),
        Text(value, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
      ],
    );
  }
}
