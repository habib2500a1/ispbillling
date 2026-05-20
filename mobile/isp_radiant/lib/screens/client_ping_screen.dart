import 'dart:io';

import 'package:flutter/material.dart';

import '../theme/app_theme.dart';
import '../utils/layout.dart';
import '../widgets/state_views.dart';

/// Ping tab — network check to a host (no browser).
class ClientPingScreen extends StatefulWidget {
  const ClientPingScreen({super.key, this.active = false});

  final bool active;

  @override
  State<ClientPingScreen> createState() => _ClientPingScreenState();
}

class _ClientPingScreenState extends State<ClientPingScreen> {
  final _hostCtrl = TextEditingController(text: '8.8.8.8');
  final _lines = <String>[];
  bool _running = false;

  Future<void> _run() async {
    final host = _hostCtrl.text.trim();
    if (host.isEmpty) return;
    setState(() {
      _running = true;
      _lines.clear();
      _lines.add('Pinging $host ...');
    });
    for (var i = 1; i <= 4; i++) {
      final start = DateTime.now();
      var ok = false;
      try {
        final socket = await Socket.connect(host, 53, timeout: const Duration(seconds: 3));
        await socket.close();
        ok = true;
      } catch (_) {
        try {
          final socket = await Socket.connect(host, 443, timeout: const Duration(seconds: 3));
          await socket.close();
          ok = true;
        } catch (_) {
          try {
            final addrs = await InternetAddress.lookup(host).timeout(const Duration(seconds: 3));
            ok = addrs.isNotEmpty;
          } catch (_) {
            ok = false;
          }
        }
      }
      final ms = DateTime.now().difference(start).inMilliseconds;
      if (!mounted) return;
      setState(() {
        _lines.add(ok ? 'Reply $i: ${ms}ms' : 'Reply $i: timeout');
      });
      await Future<void>.delayed(const Duration(milliseconds: 400));
    }
    if (mounted) setState(() => _running = false);
  }

  @override
  void dispose() {
    _hostCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: pagePadding(context),
      children: [
        const SectionTitle('Connection test'),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              children: [
                TextField(
                  controller: _hostCtrl,
                  decoration: const InputDecoration(
                    labelText: 'Host (IP or domain)',
                    prefixIcon: Icon(Icons.dns_outlined),
                  ),
                  onSubmitted: (_) => _run(),
                ),
                const SizedBox(height: 10),
                FilledButton.icon(
                  onPressed: _running ? null : _run,
                  icon: _running
                      ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Icon(Icons.network_ping),
                  label: const Text('Run ping'),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Results', style: TextStyle(fontWeight: FontWeight.bold)),
                const SizedBox(height: 8),
                if (_lines.isEmpty)
                  Text('Enter a host and tap Run ping', style: TextStyle(color: Colors.grey.shade600, fontSize: 13))
                else
                  ..._lines.map(
                    (l) => Padding(
                      padding: const EdgeInsets.symmetric(vertical: 3),
                      child: Text(l, style: const TextStyle(fontFamily: 'monospace', fontSize: 13)),
                    ),
                  ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 8),
        Text(
          'Tip: open the Usage tab for live Mbps from your ISP line.',
          style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
        ),
      ],
    );
  }
}
