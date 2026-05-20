import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../widgets/page_scaffold.dart';
import '../widgets/state_views.dart';

class CustomerOnuScreen extends StatefulWidget {
  const CustomerOnuScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<CustomerOnuScreen> createState() => _CustomerOnuScreenState();
}

class _CustomerOnuScreenState extends State<CustomerOnuScreen> {
  Map<String, dynamic>? _onu;
  bool _loading = true;
  String? _error;
  bool _rebooting = false;

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
      final body = await widget.api.customerOnuStatus();
      if (mounted) setState(() => _onu = body['onu'] as Map<String, dynamic>?);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _reboot() async {
    setState(() => _rebooting = true);
    try {
      final res = await widget.api.customerOnuReboot();
      if (mounted) showSnack(context, res['message']?.toString() ?? 'Request sent');
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _rebooting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final linked = _onu?['linked'] == true;

    return PageScaffold(
      title: 'ONU Status',
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (_error != null) ErrorBanner(message: _error!, onRetry: _load),
                Card(
                  child: ListTile(
                    leading: Icon(linked ? Icons.router : Icons.router_outlined, color: linked ? AppTheme.success : Colors.grey),
                    title: Text(linked ? (_onu?['label']?.toString() ?? 'ONU') : 'No ONU linked'),
                    subtitle: Text(_onu?['serial']?.toString() ?? _onu?['message']?.toString() ?? ''),
                  ),
                ),
                if (linked) ...[
                  _row('RX power', _onu?['rx_dbm']?.toString()),
                  _row('TX power', _onu?['tx_dbm']?.toString()),
                  _row('Status', _onu?['status']?.toString()),
                  const SizedBox(height: 16),
                  FilledButton.icon(
                    onPressed: _rebooting ? null : _reboot,
                    icon: _rebooting
                        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Icon(Icons.restart_alt),
                    label: const Text('Request ONU reboot'),
                  ),
                ],
              ],
            ),
    );
  }

  Widget _row(String label, String? value) {
    return ListTile(title: Text(label), trailing: Text(value ?? '—'));
  }
}
