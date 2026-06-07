import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../config/server_config.dart';
import '../services/api_service.dart';

/// Change billing server domain without reinstalling APK.
class ServerSetupScreen extends StatefulWidget {
  const ServerSetupScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<ServerSetupScreen> createState() => _ServerSetupScreenState();
}

class _ServerSetupScreenState extends State<ServerSetupScreen> {
  final _controller = TextEditingController();
  bool _saving = false;
  String? _error;
  String? _success;

  @override
  void initState() {
    super.initState();
    _controller.text = ServerConfig.siteRootFromApiBase();
  }

  Future<void> _save() async {
    setState(() {
      _saving = true;
      _error = null;
      _success = null;
    });

    final apiBase = ServerConfig.normalizeApiBaseUrl(_controller.text);
    try {
      final res = await http
          .get(Uri.parse('$apiBase/mobile/config'), headers: {'Accept': 'application/json'})
          .timeout(const Duration(seconds: 12));
      if (res.statusCode < 200 || res.statusCode >= 300) {
        throw Exception('Server returned ${res.statusCode}');
      }
      await ServerConfig.saveApiBaseUrl(apiBase);
      await widget.api.loadRemoteConfig();
      if (!mounted) return;
      setState(() {
        _success = 'Connected to $apiBase';
        _saving = false;
      });
      await Future<void>.delayed(const Duration(milliseconds: 600));
      if (mounted) Navigator.of(context).pop(true);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = 'Could not reach server. Check domain and try again.';
        _saving = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Server settings')),
      body: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'Enter your ISP billing domain. Use this when you moved to a new domain — no APK reinstall needed.',
              style: TextStyle(fontSize: 15),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _controller,
              decoration: const InputDecoration(
                labelText: 'Billing domain',
                hintText: 'https://anetbd.com',
                border: OutlineInputBorder(),
              ),
              keyboardType: TextInputType.url,
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(_error!, style: const TextStyle(color: Colors.red)),
            ],
            if (_success != null) ...[
              const SizedBox(height: 12),
              Text(_success!, style: const TextStyle(color: Colors.green)),
            ],
            const SizedBox(height: 20),
            FilledButton(
              onPressed: _saving ? null : _save,
              child: _saving
                  ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Save & connect'),
            ),
          ],
        ),
      ),
    );
  }
}
