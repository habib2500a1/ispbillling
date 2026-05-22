import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/layout.dart';
import '../widgets/page_scaffold.dart';
import '../widgets/state_views.dart';
import 'staff_billing_hub_screen.dart';
import 'staff_customer_detail_screen.dart';

class StaffReportsScreen extends StatefulWidget {
  const StaffReportsScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<StaffReportsScreen> createState() => _StaffReportsScreenState();
}

class _StaffReportsScreenState extends State<StaffReportsScreen> with SingleTickerProviderStateMixin {
  late final TabController _tabs = TabController(length: 3, vsync: this);
  final _fmt = NumberFormat('#,##0.00');
  List<Map<String, dynamic>> _expiring = [];
  List<Map<String, dynamic>> _due = [];
  Map<String, dynamic>? _collectionReport;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final exp = await widget.api.staffExpiringReport(days: 7);
      final dueBody = await widget.api.staffBillingDue();
      final col = await widget.api.staffCollectionsReport();
      if (mounted) {
        setState(() {
          _expiring = exp;
          _due = (dueBody['data'] as List<dynamic>?)?.map((e) => Map<String, dynamic>.from(e as Map)).toList() ?? [];
          _collectionReport = col['report'] as Map<String, dynamic>?;
        });
      }
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'Reports',
      useGradientBody: true,
      bottom: TabBar(
        controller: _tabs,
        tabs: const [Tab(text: 'Collection'), Tab(text: 'Due'), Tab(text: 'Expiring')],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : TabBarView(
              controller: _tabs,
              children: [_collectionTab(), _dueTab(), _expiringTab()],
            ),
    );
  }

  Widget _collectionTab() {
    final byDay = (_collectionReport?['by_day'] as List<dynamic>?) ?? [];
    final byMethod = (_collectionReport?['by_method'] as List<dynamic>?) ?? [];
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: pagePadding(context),
        children: [
          FilledButton.icon(
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => StaffBillingHubScreen(api: widget.api))),
            icon: const Icon(Icons.receipt_long),
            label: const Text('Full billing hub'),
          ),
          const SizedBox(height: 16),
          const Text('By day (this month)', style: TextStyle(fontWeight: FontWeight.bold)),
          ...byDay.take(14).map((d) {
            final row = Map<String, dynamic>.from(d as Map);
            return ListTile(
              title: Text(row['date']?.toString() ?? row['day']?.toString() ?? ''),
              trailing: Text('৳${_fmt.format((row['amount'] as num?) ?? row['total'] ?? 0)}', style: const TextStyle(fontWeight: FontWeight.w600)),
            );
          }),
          const SizedBox(height: 12),
          const Text('By method', style: TextStyle(fontWeight: FontWeight.bold)),
          ...byMethod.map((m) {
            final row = Map<String, dynamic>.from(m as Map);
            return ListTile(
              title: Text(row['method']?.toString() ?? ''),
              trailing: Text('৳${_fmt.format((row['amount'] as num?) ?? row['total'] ?? 0)}'),
            );
          }),
        ],
      ),
    );
  }

  Widget _dueTab() {
    if (_due.isEmpty) return const EmptyState(icon: Icons.check, title: 'No due clients', subtitle: '');
    return ListView.builder(
      padding: pagePadding(context, top: 8),
      itemCount: _due.length,
      itemBuilder: (context, i) {
        final c = _due[i];
        return Card(
          child: ListTile(
            title: Text(c['name']?.toString() ?? ''),
            subtitle: Text(c['customer_code']?.toString() ?? ''),
            trailing: Text('৳${_fmt.format((c['balance_due'] as num?) ?? 0)}', style: const TextStyle(color: AppTheme.warning, fontWeight: FontWeight.bold)),
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => StaffCustomerDetailScreen(api: widget.api, customerId: (c['id'] as num).toInt()))),
          ),
        );
      },
    );
  }

  Widget _expiringTab() {
    if (_expiring.isEmpty) return const EmptyState(icon: Icons.event, title: 'No expiring soon', subtitle: 'Next 7 days');
    return ListView.builder(
      padding: pagePadding(context, top: 8),
      itemCount: _expiring.length,
      itemBuilder: (context, i) {
        final c = _expiring[i];
        return Card(
          child: ListTile(
            title: Text(c['name']?.toString() ?? ''),
            subtitle: Text('Expires ${c['service_expires_at']} · ${c['days_left']} days'),
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => StaffCustomerDetailScreen(api: widget.api, customerId: (c['id'] as num).toInt()))),
          ),
        );
      },
    );
  }
}
