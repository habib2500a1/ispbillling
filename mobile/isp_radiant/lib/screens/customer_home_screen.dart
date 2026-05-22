import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';

import '../config/remote_config.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
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
  Timer? _usageTimer;
  final _fmt = NumberFormat('#,##0.00');

  @override
  void initState() {
    super.initState();
    _load();
    _usageTimer = Timer.periodic(const Duration(seconds: 2), (_) => _pollUsage());
  }

  @override
  void dispose() {
    _usageTimer?.cancel();
    super.dispose();
  }

  Future<void> _pollUsage() async {
    if (_tab != 0) return;
    try {
      final usage = await widget.api.customerUsageLive();
      if (!mounted) return;
      final live = usage['usage'] as Map<String, dynamic>?;
      setState(() {
        _usage = live;
        if (live != null && _dash != null) {
          _dash!['traffic'] = {
            'download_bps': live['download_bps'],
            'upload_bps': live['upload_bps'],
            'download_human': live['download_human'],
            'upload_human': live['upload_human'],
            'chart': live['chart'],
            'month_download': live['total_download'] ?? live['today_download'],
            'month_upload': live['total_upload'] ?? live['today_upload'],
          };
        }
      });
    } catch (_) {}
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

  void _openPackages() => Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => CustomerPackagesScreen(api: widget.api)),
      );

  void _openPassword() => Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => CustomerPasswordScreen(api: widget.api)),
      );

  void _openTickets() => Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => CustomerTicketsScreen(api: widget.api)),
      );

  @override
  Widget build(BuildContext context) {
    final titles = ['', 'Ping', 'Payment History'];

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light.copyWith(statusBarColor: Colors.transparent),
      child: Scaffold(
        backgroundColor: const Color(0xFFF0F4FF),
        appBar: _tab == 0
            ? null
            : AppBar(
                title: Text(titles[_tab]),
                centerTitle: true,
              ),
        body: IndexedStack(
          index: _tab,
          children: [
            _buildHomeTab(),
            ClientPingScreen(active: _tab == 1),
            CustomerBillsScreen(api: widget.api, active: _tab == 2, onPay: _openPay, embedded: true),
          ],
        ),
        bottomNavigationBar: NavigationBar(
          selectedIndex: _tab,
          onDestinationSelected: _go,
          height: 68,
          destinations: const [
            NavigationDestination(icon: Icon(Icons.home_outlined), selectedIcon: Icon(Icons.home), label: 'Home'),
            NavigationDestination(icon: Icon(Icons.speed_outlined), selectedIcon: Icon(Icons.speed), label: 'Ping'),
            NavigationDestination(
              icon: Icon(Icons.credit_card_outlined),
              selectedIcon: Icon(Icons.credit_card),
              label: 'Payment History',
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildHomeTab() {
    final fallbackCustomer = widget.loginPayload['customer'] as Map<String, dynamic>?;
    if (_loading && _dash == null && fallbackCustomer == null) {
      return const Center(child: CircularProgressIndicator());
    }

    final customer = (_dash?['customer'] ?? fallbackCustomer) as Map<String, dynamic>?;
    final summary = _dash?['summary'] as Map<String, dynamic>? ?? {};
    final traffic = (_usage ?? _dash?['traffic']) as Map<String, dynamic>? ?? {};
    final connected = summary['status']?.toString() == 'Connected';
    final name = customer?['name']?.toString() ?? 'Client';
    final code = customer?['customer_code']?.toString() ?? '';
    final notices = _dash?['notices'] as List<dynamic>? ?? [];

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          _blueHeader(name, code, summary, connected),
          Padding(
            padding: pagePadding(context),
            child: Column(
              children: [
                const SizedBox(height: 12),
                _quickActions(),
                const SizedBox(height: 12),
                _accountSummary(summary),
                const SizedBox(height: 12),
                _actionGrid(notices),
                const SizedBox(height: 12),
                _usageRow(traffic, connected),
                const SizedBox(height: 8),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        UsageAreaChart(chart: traffic['chart'] as Map<String, dynamic>?),
                        const SizedBox(height: 6),
                        Text(
                          'Download : ${traffic['download_human'] ?? '—'} · Upload : ${traffic['upload_human'] ?? '—'}',
                          style: const TextStyle(fontSize: 11, color: Colors.grey),
                        ),
                        TextButton(
                          onPressed: () => Navigator.push(
                            context,
                            MaterialPageRoute(builder: (_) => CustomerUsageScreen(api: widget.api)),
                          ),
                          child: const Text('Full usage details'),
                        ),
                      ],
                    ),
                  ),
                ),
                if (notices.isNotEmpty) ...[
                  const Align(
                    alignment: Alignment.centerLeft,
                    child: Padding(
                      padding: EdgeInsets.only(top: 8, bottom: 6),
                      child: Text('News And Events', style: TextStyle(color: Colors.grey, fontSize: 12)),
                    ),
                  ),
                  ...notices.take(3).map(_noticeCard),
                ],
                const SizedBox(height: 24),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _blueHeader(String name, String code, Map<String, dynamic> summary, bool connected) {
    return Container(
      width: double.infinity,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [AppTheme.primary, AppTheme.purple, AppTheme.pink],
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
        ),
        borderRadius: BorderRadius.only(bottomLeft: Radius.circular(24), bottomRight: Radius.circular(24)),
      ),
      padding: EdgeInsets.fromLTRB(16, MediaQuery.paddingOf(context).top + 12, 16, 20),
      child: Row(
        children: [
          CircleAvatar(
            radius: 28,
            backgroundColor: Colors.white24,
            child: Text(name.isNotEmpty ? name[0].toUpperCase() : '?', style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.bold)),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(name, style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold)),
                Text('Client Code : $code', style: const TextStyle(color: Colors.white70, fontSize: 12)),
                Row(
                  children: [
                    const Text('Status : ', style: TextStyle(color: Colors.white70, fontSize: 12)),
                    Text(
                      summary['status']?.toString() ?? '—',
                      style: TextStyle(color: connected ? Colors.lightGreenAccent : Colors.orangeAccent, fontSize: 12, fontWeight: FontWeight.bold),
                    ),
                  ],
                ),
              ],
            ),
          ),
          IconButton(icon: const Icon(Icons.refresh, color: Colors.white), onPressed: _load),
          IconButton(icon: const Icon(Icons.logout, color: Colors.white), onPressed: _logout),
        ],
      ),
    );
  }

  Widget _quickActions() {
    return Card(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceAround,
          children: [
            _quick(Icons.credit_card, 'Recharge / Pay', _openPay),
            _quick(Icons.inventory_2_outlined, 'Change Package', _openPackages),
            _quick(Icons.lock_outline, 'Change password', _openPassword),
            _quick(Icons.confirmation_number_outlined, 'Create Ticket', _openTickets),
          ],
        ),
      ),
    );
  }

  Widget _quick(IconData icon, String label, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: SizedBox(
        width: 76,
        child: Column(
          children: [
            Icon(icon, color: AppTheme.primary, size: 26),
            const SizedBox(height: 4),
            Text(label, textAlign: TextAlign.center, style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w600)),
          ],
        ),
      ),
    );
  }

  Widget _accountSummary(Map<String, dynamic> summary) {
    return Card(
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          children: [
            Row(
              children: [
                Expanded(child: _kpi(Icons.receipt_long, 'Monthly Bill', _fmt.format(summary['monthly_bill'] ?? 0))),
                Expanded(child: _kpi(Icons.paid_outlined, 'Paid', _fmt.format(summary['paid'] ?? 0))),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(child: _kpi(Icons.desktop_windows_outlined, 'Package', summary['package_name']?.toString() ?? '—')),
                Expanded(child: _kpi(Icons.event_outlined, 'Expire Date', summary['expire_date']?.toString() ?? '—')),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _kpi(IconData icon, String label, String value) {
    return Row(
      children: [
        Icon(icon, color: AppTheme.primary.withValues(alpha: 0.7), size: 22),
        const SizedBox(width: 8),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: const TextStyle(fontSize: 10, color: Colors.grey)),
              Text(value, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
            ],
          ),
        ),
      ],
    );
  }

  Widget _actionGrid(List<dynamic> notices) {
    return Column(
      children: [
        Row(
          children: [
            Expanded(child: _colorTile('Internet Packages', Colors.red.shade400, Icons.inventory_2, _openPackages)),
            const SizedBox(width: 8),
            Expanded(
              child: _colorTile(
                'News & Event',
                AppTheme.info,
                Icons.newspaper,
                () {
                  if (notices.isEmpty) {
                    showSnack(context, 'No news at the moment');
                    return;
                  }
                  showModalBottomSheet<void>(
                    context: context,
                    showDragHandle: true,
                    builder: (ctx) => ListView(
                      padding: const EdgeInsets.all(16),
                      shrinkWrap: true,
                      children: notices.map((n) => _noticeCard(Map<String, dynamic>.from(n as Map))).toList(),
                    ),
                  );
                },
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            Expanded(child: _colorTile('Payment History', Colors.green.shade600, Icons.history, () => _go(2))),
            const SizedBox(width: 8),
            Expanded(child: _colorTile('Support & ticket', Colors.orange.shade500, Icons.chat_bubble_outline, _openTickets)),
          ],
        ),
        if (RemoteConfig.aiAssistant) ...[
          const SizedBox(height: 8),
          Card(
            child: ListTile(
              leading: const Icon(Icons.router, color: AppTheme.primary),
              title: const Text('ONU signal & reboot'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CustomerOnuScreen(api: widget.api))),
            ),
          ),
          ListTile(
            leading: const Icon(Icons.smart_toy_outlined, color: AppTheme.purple),
            title: const Text('AI assistant'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CustomerAiScreen(api: widget.api))),
          ),
        ],
      ],
    );
  }

  Widget _colorTile(String label, Color color, IconData icon, VoidCallback onTap) {
    return Material(
      color: color,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: SizedBox(
          height: 72,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, color: Colors.white, size: 28),
              const SizedBox(height: 4),
              Text(label, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _usageRow(Map<String, dynamic> traffic, bool connected) {
    final up = _humanBytes(traffic['month_upload'] ?? traffic['today_upload']);
    final down = _humanBytes(traffic['month_download'] ?? traffic['today_download']);
    final uptime = _usage?['connection_duration']?.toString() ?? (_dash?['connection']?['session_uptime']?.toString()) ?? '—';

    return Card(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceAround,
          children: [
            _trafficCol('Upload', up, Icons.arrow_upward),
            Column(
              children: [
                Icon(Icons.sync, color: connected ? Colors.green : Colors.grey, size: 22),
                const SizedBox(height: 4),
                Text('Up Time', style: TextStyle(fontSize: 10, color: connected ? Colors.green.shade700 : Colors.grey)),
                Text(uptime, style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w600)),
              ],
            ),
            _trafficCol('Download', down, Icons.arrow_downward),
          ],
        ),
      ),
    );
  }

  String _humanBytes(dynamic raw) {
    if (raw == null) return '—';
    final n = (raw is num) ? raw.toDouble() : double.tryParse(raw.toString()) ?? 0;
    if (n >= 1e9) return '${(n / 1e9).toStringAsFixed(1)} Gb';
    if (n >= 1e6) return '${(n / 1e6).toStringAsFixed(1)} Mb';
    if (n >= 1e3) return '${(n / 1e3).toStringAsFixed(1)} Kb';
    return '${n.toStringAsFixed(0)} b';
  }

  Widget _trafficCol(String label, String value, IconData icon) {
    return Column(
      children: [
        Icon(icon, size: 20, color: AppTheme.primary),
        Text(label, style: const TextStyle(fontSize: 10, color: Colors.grey)),
        Text(value, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
      ],
    );
  }

  Widget _noticeCard(dynamic n) {
    final m = n as Map<String, dynamic>;
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        title: Text(m['title']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.bold)),
        subtitle: Text(m['body']?.toString() ?? ''),
        trailing: Icon(Icons.notifications_active, color: Colors.red.shade400),
      ),
    );
  }
}
