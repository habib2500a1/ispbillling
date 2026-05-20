import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../utils/app_nav.dart';
import '../widgets/page_scaffold.dart';

class StaffCommsScreen extends StatefulWidget {
  const StaffCommsScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<StaffCommsScreen> createState() => _StaffCommsScreenState();
}

class _StaffCommsScreenState extends State<StaffCommsScreen> {
  final _noticeCtrl = TextEditingController();
  final _bulkMsgCtrl = TextEditingController();
  bool _sending = false;

  @override
  void dispose() {
    _noticeCtrl.dispose();
    _bulkMsgCtrl.dispose();
    super.dispose();
  }

  Future<void> _bulkDue() async {
    setState(() => _sending = true);
    try {
      final res = await widget.api.staffSmsBulkDue(message: _bulkMsgCtrl.text.trim().isEmpty ? null : _bulkMsgCtrl.text.trim());
      if (mounted) showSnack(context, res['message']?.toString() ?? 'Sent');
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _broadcast() async {
    final msg = _noticeCtrl.text.trim();
    if (msg.isEmpty) {
      showSnack(context, 'Enter notice message', isError: true);
      return;
    }
    setState(() => _sending = true);
    try {
      final res = await widget.api.staffBroadcastNotice(msg, target: 'active');
      if (mounted) showSnack(context, res['message']?.toString() ?? 'Sent');
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'SMS & Notice',
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text('Due reminder (bulk)', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          const SizedBox(height: 8),
          const Text('Sends invoice-due template to all customers with balance due.', style: TextStyle(fontSize: 12, color: Colors.grey)),
          const SizedBox(height: 8),
          TextField(
            controller: _bulkMsgCtrl,
            decoration: const InputDecoration(
              labelText: 'Custom SMS (optional)',
              hintText: 'Leave empty for default due template',
              border: OutlineInputBorder(),
            ),
            maxLines: 2,
          ),
          const SizedBox(height: 8),
          FilledButton.icon(
            onPressed: _sending ? null : _bulkDue,
            icon: const Icon(Icons.sms),
            label: const Text('Send due reminders'),
          ),
          const Divider(height: 32),
          const Text('Notice to all active', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
          const SizedBox(height: 8),
          TextField(
            controller: _noticeCtrl,
            decoration: const InputDecoration(labelText: 'Notice message', border: OutlineInputBorder()),
            maxLines: 4,
          ),
          const SizedBox(height: 8),
          FilledButton.icon(
            onPressed: _sending ? null : _broadcast,
            icon: const Icon(Icons.campaign),
            label: const Text('Broadcast notice'),
          ),
        ],
      ),
    );
  }
}
