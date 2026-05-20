import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../widgets/page_scaffold.dart';

class CustomerPackagesScreen extends StatefulWidget {
  const CustomerPackagesScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<CustomerPackagesScreen> createState() => _CustomerPackagesScreenState();
}

class _CustomerPackagesScreenState extends State<CustomerPackagesScreen> {
  List<Map<String, dynamic>> _packages = [];
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
      final list = await widget.api.customerPackages();
      if (mounted) setState(() => _packages = list);
    } catch (_) {}
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _request(int packageId, String name) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Change to $name?'),
        content: const Text('Package change request will be sent to your ISP team.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Confirm')),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    try {
      final res = await widget.api.requestPackageChange(packageId);
      if (mounted) showSnack(context, res['message']?.toString() ?? 'Request sent');
      _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'Internet packages',
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView.separated(
              padding: const EdgeInsets.all(14),
              itemCount: _packages.length,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (context, i) {
                final p = _packages[i];
                final current = p['is_current'] == true;
                return Card(
                  color: current ? AppTheme.accentSoft : AppTheme.card,
                  child: ListTile(
                    leading: CircleAvatar(
                      backgroundColor: (current ? AppTheme.accent : AppTheme.primary).withValues(alpha: 0.15),
                      child: Icon(Icons.speed, color: current ? AppTheme.accent : AppTheme.primary),
                    ),
                    title: Text('${p['name']} · ${p['download_mbps']} Mbps', style: const TextStyle(fontWeight: FontWeight.bold)),
                    subtitle: Text('${_fmt.format(p['price_monthly'])} BDT/month'),
                    trailing: current
                        ? Chip(
                            label: const Text('Current'),
                            backgroundColor: AppTheme.success.withValues(alpha: 0.2),
                            labelStyle: const TextStyle(color: AppTheme.success, fontWeight: FontWeight.bold),
                          )
                        : FilledButton(
                            onPressed: () => _request((p['id'] as num).toInt(), p['name'].toString()),
                            style: FilledButton.styleFrom(backgroundColor: AppTheme.primary),
                            child: const Text('Select'),
                          ),
                  ),
                );
              },
            ),
    );
  }
}
