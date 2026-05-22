import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../widgets/page_scaffold.dart';
import '../widgets/state_views.dart';

class StaffPackagesScreen extends StatefulWidget {
  const StaffPackagesScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<StaffPackagesScreen> createState() => _StaffPackagesScreenState();
}

class _StaffPackagesScreenState extends State<StaffPackagesScreen> {
  List<Map<String, dynamic>> _packages = [];
  bool _loading = true;
  String? _error;
  final _fmt = NumberFormat('#,##0.00');

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
      final list = await widget.api.staffPackagesList();
      if (mounted) setState(() => _packages = list);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not load packages');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _addPackage() async {
    final nameCtrl = TextEditingController();
    final speedCtrl = TextEditingController(text: '10');
    final priceCtrl = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('New package'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(controller: nameCtrl, decoration: const InputDecoration(labelText: 'Name')),
            TextField(controller: speedCtrl, decoration: const InputDecoration(labelText: 'Download Mbps'), keyboardType: TextInputType.number),
            TextField(controller: priceCtrl, decoration: const InputDecoration(labelText: 'Monthly price'), keyboardType: TextInputType.number),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Save')),
        ],
      ),
    );
    if (ok != true || !mounted) return;
    try {
      await widget.api.staffCreatePackage(
        name: nameCtrl.text.trim(),
        downloadMbps: double.tryParse(speedCtrl.text) ?? 10,
        priceMonthly: double.tryParse(priceCtrl.text) ?? 0,
      );
      if (mounted) {
        showSnack(context, 'Package created');
        _load();
      }
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'Packages',
      useGradientBody: true,
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _addPackage,
        icon: const Icon(Icons.add),
        label: const Text('New'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: ErrorBanner(message: _error!, onRetry: _load))
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView.builder(
                    padding: const EdgeInsets.all(12),
                    itemCount: _packages.length,
                    itemBuilder: (context, i) {
                      final p = _packages[i];
                      final active = p['is_active'] == true;
                      return Card(
                        child: ListTile(
                          leading: CircleAvatar(
                            backgroundColor: (active ? AppTheme.success : Colors.grey).withValues(alpha: 0.15),
                            child: Icon(Icons.speed, color: active ? AppTheme.success : Colors.grey),
                          ),
                          title: Text(p['name']?.toString() ?? '', style: const TextStyle(fontWeight: FontWeight.w600)),
                          subtitle: Text('${p['download_mbps']} Mbps · ৳${_fmt.format((p['price_monthly'] as num?) ?? 0)}/mo'),
                          trailing: Chip(
                            label: Text(active ? 'Active' : 'Off', style: const TextStyle(fontSize: 10)),
                          ),
                        ),
                      );
                    },
                  ),
                ),
    );
  }
}
