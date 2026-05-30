import 'package:flutter/material.dart';

import '../features/staff_comms/data/comms_repository.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../widgets/isp_ui_kit.dart';
import '../widgets/page_scaffold.dart';

class StaffCommsScreen extends StatefulWidget {
  const StaffCommsScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<StaffCommsScreen> createState() => _StaffCommsScreenState();
}

class _StaffCommsScreenState extends State<StaffCommsScreen> {
  late final CommsRepository _repo = CommsRepository(widget.api);
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
    final msg = _bulkMsgCtrl.text.trim();
    final res = await _repo.sendBulkDue(message: msg.isEmpty ? null : msg);
    if (!mounted) return;
    setState(() => _sending = false);
    res.when(
      ok: (m) => showSnack(context, m),
      err: (f) => showSnack(context, f.message, isError: true),
    );
  }

  Future<void> _broadcast() async {
    final msg = _noticeCtrl.text.trim();
    if (msg.isEmpty) {
      showSnack(context, 'Enter notice message', isError: true);
      return;
    }
    setState(() => _sending = true);
    final res = await _repo.broadcastNotice(msg, target: 'active');
    if (!mounted) return;
    setState(() => _sending = false);
    res.when(
      ok: (m) => showSnack(context, m),
      err: (f) => showSnack(context, f.message, isError: true),
    );
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'SMS & Notice',
      useGradientBody: true,
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          IspUiKit.formCard(
            title: 'Due reminder (bulk)',
            subtitle: 'SMS to all customers with balance due',
            children: [
              TextField(
                controller: _bulkMsgCtrl,
                decoration: const InputDecoration(
                  labelText: 'Custom SMS (optional)',
                  hintText: 'Leave empty for default template',
                  border: OutlineInputBorder(),
                ),
                maxLines: 2,
              ),
              const SizedBox(height: 10),
              IspUiKit.primaryButton(
                label: 'Send due reminders',
                icon: Icons.sms,
                loading: _sending,
                onPressed: _bulkDue,
              ),
            ],
          ),
          const SizedBox(height: 12),
          IspUiKit.formCard(
            title: 'Broadcast notice',
            subtitle: 'All active subscribers',
            children: [
              TextField(
                controller: _noticeCtrl,
                decoration: const InputDecoration(labelText: 'Notice message', border: OutlineInputBorder()),
                maxLines: 4,
              ),
              const SizedBox(height: 10),
              IspUiKit.primaryButton(
                label: 'Broadcast notice',
                icon: Icons.campaign,
                color: AppTheme.purple,
                loading: _sending,
                onPressed: _broadcast,
              ),
            ],
          ),
        ],
      ),
    );
  }
}
