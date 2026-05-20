import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/layout.dart';
import '../widgets/state_views.dart';

class CustomerUsageScreen extends StatefulWidget {
  const CustomerUsageScreen({
    super.key,
    required this.api,
    this.active = false,
  });

  final ApiService api;
  final bool active;

  @override
  State<CustomerUsageScreen> createState() => _CustomerUsageScreenState();
}

class _CustomerUsageScreenState extends State<CustomerUsageScreen> {
  Map<String, dynamic>? _usage;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void didUpdateWidget(CustomerUsageScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active && !oldWidget.active) _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final body = await widget.api.customerUsageLive();
      if (mounted) {
        setState(() => _usage = body['usage'] as Map<String, dynamic>? ?? body);
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not load live usage');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: ErrorBanner(message: _error!, onRetry: _load),
        ),
      );
    }

    final u = _usage ?? {};
    final online = u['online'] == true;

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: pagePadding(context),
        children: [
          Card(
            color: online ? Colors.green.shade50 : Colors.grey.shade100,
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                children: [
                  Icon(
                    online ? Icons.wifi : Icons.wifi_off,
                    size: 56,
                    color: online ? AppTheme.success : Colors.grey,
                  ),
                  const SizedBox(height: 10),
                  Text(
                    online ? 'Connected' : 'Offline',
                    style: TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.bold,
                      color: online ? AppTheme.success : Colors.grey.shade700,
                    ),
                  ),
                  if (u['framed_ip'] != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: Text('IP ${u['framed_ip']}', style: const TextStyle(color: Colors.grey)),
                    ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          const SectionTitle('Live speed'),
          _metric('Download', u['download_human']?.toString() ?? '—', Icons.download),
          _metric('Upload', u['upload_human']?.toString() ?? '—', Icons.upload),
          const SectionTitle('Today'),
          _metric('Download today', '${u['today_download'] ?? '—'}'),
          _metric('Upload today', '${u['today_upload'] ?? '—'}'),
          _metric('Session', u['session_started']?.toString() ?? '—'),
          const SizedBox(height: 8),
          Text(
            'Live data from your connection — pull to refresh',
            style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  Widget _metric(String label, String value, [IconData? icon]) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: ListTile(
        leading: icon != null ? Icon(icon, color: AppTheme.primary) : null,
        title: Text(label, style: const TextStyle(fontSize: 13, color: Colors.grey)),
        trailing: Text(value, style: const TextStyle(fontWeight: FontWeight.bold)),
      ),
    );
  }
}
