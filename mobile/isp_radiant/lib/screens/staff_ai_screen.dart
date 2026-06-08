import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../widgets/page_scaffold.dart';

class StaffAiScreen extends StatefulWidget {
  const StaffAiScreen({super.key, required this.api, this.technicianMode = false});

  final ApiService api;
  final bool technicianMode;

  @override
  State<StaffAiScreen> createState() => _StaffAiScreenState();
}

class _StaffAiScreenState extends State<StaffAiScreen> {
  final _questionCtrl = TextEditingController();
  String? _reply;
  List<Map<String, dynamic>> _cards = [];
  bool _loading = false;

  List<String> get _samples => widget.technicianMode
      ? const [
          'Show assigned tickets',
          'Show nearby faults',
          'Show weak signal users',
        ]
      : const [
          "Show today's collection",
          'Show pending tickets',
          'Show due customers',
        ];

  Future<void> _ask([String? preset]) async {
    final q = (preset ?? _questionCtrl.text).trim();
    if (q.isEmpty) return;
    setState(() {
      _loading = true;
      _reply = null;
      _cards = [];
    });
    try {
      final res = await widget.api.staffAiAsk(q);
      if (mounted) {
        setState(() {
          _reply = res['reply']?.toString();
          _cards = (res['cards'] as List<dynamic>?)
                  ?.map((e) => Map<String, dynamic>.from(e as Map))
                  .toList() ??
              [];
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _reply = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  void dispose() {
    _questionCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'Operations AI',
      useGradientBody: true,
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: _samples.map((s) => ActionChip(label: Text(s), onPressed: () => _ask(s))).toList(),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _questionCtrl,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: 'Ask about collections, tickets, network, or customers',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: _loading ? null : () => _ask(),
            child: _loading
                ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('Ask'),
          ),
          if (_reply != null) ...[
            const SizedBox(height: 20),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Text(_reply!, style: const TextStyle(height: 1.45)),
              ),
            ),
          ],
          ..._cards.map(
            (c) => Card(
              child: ListTile(
                title: Text(c['title']?.toString() ?? ''),
                subtitle: Text(c['subtitle']?.toString() ?? ''),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
