import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../widgets/isp_ui_kit.dart';
import '../widgets/state_views.dart';

class CustomerPackagesScreen extends StatefulWidget {
  const CustomerPackagesScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<CustomerPackagesScreen> createState() => _CustomerPackagesScreenState();
}

class _CustomerPackagesScreenState extends State<CustomerPackagesScreen> {
  List<Map<String, dynamic>> _packages = [];
  bool _loading = true;
  String? _error;
  final _fmt = NumberFormat('#,##0');

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final list = await widget.api.customerPackages();
      if (mounted) setState(() => _packages = list);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not load packages');
    }
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
    return Scaffold(
      backgroundColor: AppTheme.background,
      appBar: AppBar(title: const Text('Package'), centerTitle: true),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: ErrorBanner(message: _error!, onRetry: _load))
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: EdgeInsets.zero,
                    children: [
                      IspUiKit.gradientHeader(
                        title: 'Internet packages',
                        subtitle: 'View plans · request upgrade',
                      ),
                      Padding(
                        padding: const EdgeInsets.all(14),
                        child: _packages.isEmpty
                            ? const EmptyState(icon: Icons.inventory_2, title: 'No packages available')
                            : Column(
                                children: [
                                  for (var i = 0; i < _packages.length; i++) ...[
                                    if (i > 0) const SizedBox(height: 12),
                                    _packageCard(_packages[i]),
                                  ],
                                ],
                              ),
                      ),
                    ],
                  ),
                ),
    );
  }

  Widget _packageCard(Map<String, dynamic> p) {
    final current = p['is_current'] == true;
    final speed = p['download_mbps'] != null ? '${p['download_mbps']} Mbps' : p['name']?.toString() ?? 'Package';
    final price = '৳ ${_fmt.format(p['price_monthly'] ?? 0)}';

    return IspUiKit.packageCard(
      title: p['name']?.toString() ?? speed,
      speedLine: 'Download ${p['download_mbps'] ?? 'N/A'} Mbps · Upload ${p['upload_mbps'] ?? 'N/A'} Mbps',
      price: price,
      isCurrent: current,
      onRequest: current ? null : () => _request((p['id'] as num).toInt(), p['name'].toString()),
    );
  }
}
