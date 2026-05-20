import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../widgets/page_scaffold.dart';

class CustomerAiScreen extends StatefulWidget {
  const CustomerAiScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<CustomerAiScreen> createState() => _CustomerAiScreenState();
}

class _CustomerAiScreenState extends State<CustomerAiScreen> {
  final _questionCtrl = TextEditingController();
  String? _reply;
  List<String> _hints = [];
  bool _loading = false;

  static const _samples = [
    'Why is my internet slow?',
    'What is my ONU signal?',
    'How do I pay my bill?',
  ];

  Future<void> _ask([String? preset]) async {
    final q = (preset ?? _questionCtrl.text).trim();
    if (q.isEmpty) return;
    setState(() {
      _loading = true;
      _reply = null;
      _hints = [];
    });
    try {
      final res = await widget.api.customerAiAsk(q);
      if (mounted) {
        setState(() {
          _reply = res['reply']?.toString();
          _hints = (res['hints'] as List<dynamic>?)?.map((e) => e.toString()).toList() ?? [];
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _reply = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'AI Assistant',
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
              labelText: 'Ask about billing, speed, or ONU',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: _loading ? null : () => _ask(),
            child: _loading ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Text('Ask'),
          ),
          if (_reply != null) ...[
            const SizedBox(height: 20),
            Card(
              color: AppTheme.primary.withValues(alpha: 0.08),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Text(_reply!, style: const TextStyle(height: 1.4)),
              ),
            ),
          ],
          ..._hints.map((h) => ListTile(dense: true, leading: const Icon(Icons.lightbulb_outline, size: 18), title: Text(h))),
        ],
      ),
    );
  }
}
