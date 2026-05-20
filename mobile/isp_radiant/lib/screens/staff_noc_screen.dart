import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../widgets/page_scaffold.dart';
import '../widgets/state_views.dart';

class StaffNocScreen extends StatefulWidget {
  const StaffNocScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<StaffNocScreen> createState() => _StaffNocScreenState();
}

class _StaffNocScreenState extends State<StaffNocScreen> {
  Map<String, dynamic>? _data;
  bool _loading = true;
  String? _error;

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
      final data = await widget.api.nocDashboard();
      if (mounted) setState(() => _data = data);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Failed to load NOC dashboard');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final alerts = (_data?['alerts'] as List<dynamic>?) ?? [];

    return PageScaffold(
      title: 'NOC Dashboard',
      actions: [IconButton(icon: const Icon(Icons.refresh), onPressed: _load)],
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(14),
                children: [
                  if (_error != null) ErrorBanner(message: _error!, onRetry: _load),
                  GridView.count(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    crossAxisCount: 2,
                    mainAxisSpacing: 8,
                    crossAxisSpacing: 8,
                    childAspectRatio: 1.5,
                    children: [
                      _stat('OLT', _data?['olt_count']),
                      _stat('ONU', _data?['onu_count']),
                      _stat('Online', _data?['customers_online']),
                      _stat('Weak signal', _data?['onu_weak_count']),
                    ],
                  ),
                  const SizedBox(height: 16),
                  const Text('Alerts', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                  const SizedBox(height: 8),
                  if (alerts.isEmpty)
                    const Card(child: ListTile(title: Text('No active alerts'), leading: Icon(Icons.check_circle, color: AppTheme.success)))
                  else
                    ...alerts.map((a) {
                      final m = Map<String, dynamic>.from(a as Map);
                      return Card(
                        child: ListTile(
                          leading: Icon(
                            m['severity'] == 'critical' ? Icons.error : Icons.warning_amber,
                            color: m['severity'] == 'critical' ? Colors.red : AppTheme.warning,
                          ),
                          title: Text(m['title']?.toString() ?? 'Alert'),
                          subtitle: Text(m['message']?.toString() ?? ''),
                        ),
                      );
                    }),
                ],
              ),
            ),
    );
  }

  Widget _stat(String label, dynamic value) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text('$value', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold)),
            Text(label, style: const TextStyle(color: Colors.black54)),
          ],
        ),
      ),
    );
  }
}
