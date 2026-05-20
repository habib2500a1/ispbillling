import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
import '../widgets/page_scaffold.dart';
import '../widgets/state_views.dart';

class TicketThreadScreen extends StatefulWidget {
  const TicketThreadScreen({
    super.key,
    required this.api,
    required this.ticketId,
    required this.isStaff,
    this.ticketSummary,
  });

  final ApiService api;
  final int ticketId;
  final bool isStaff;
  final Map<String, dynamic>? ticketSummary;

  @override
  State<TicketThreadScreen> createState() => _TicketThreadScreenState();
}

class _TicketThreadScreenState extends State<TicketThreadScreen> {
  final _replyCtrl = TextEditingController();
  Map<String, dynamic>? _ticket;
  List<Map<String, dynamic>> _messages = [];
  bool _loading = true;
  String? _error;
  bool _internal = false;
  bool _sending = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _replyCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final body = widget.isStaff
          ? await widget.api.staffTicketDetail(widget.ticketId)
          : await widget.api.customerTicketDetail(widget.ticketId);
      if (!mounted) return;
      setState(() {
        _ticket = (body['ticket'] as Map<String, dynamic>?) ??
            widget.ticketSummary;
        _messages = (body['messages'] as List<dynamic>?)
                ?.map((e) => Map<String, dynamic>.from(e as Map))
                .toList() ??
            [];
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not load ticket');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _sendReply() async {
    final text = _replyCtrl.text.trim();
    if (text.isEmpty) return;
    setState(() => _sending = true);
    try {
      if (widget.isStaff) {
        await widget.api.staffReplyTicket(widget.ticketId, text, internal: _internal);
      } else {
        await widget.api.replyTicket(widget.ticketId, text);
      }
      _replyCtrl.clear();
      await _load();
      if (mounted) showSnack(context, 'Reply sent');
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _setStatus(String status) async {
    try {
      await widget.api.staffUpdateTicket(widget.ticketId, status: status);
      await _load();
      if (mounted) showSnack(context, 'Status updated');
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = _ticket ?? widget.ticketSummary ?? {};
    final title = t['subject']?.toString() ?? 'Ticket';

    return PageScaffold(
      title: title,
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: ErrorBanner(message: _error!, onRetry: _load))
              : Column(
                  children: [
                    if (widget.isStaff)
                      Padding(
                        padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
                        child: Wrap(
                          spacing: 6,
                          children: [
                            ActionChip(
                              label: Text(t['status']?.toString() ?? 'status'),
                              onPressed: () => _showStatusPicker(),
                            ),
                            FilterChip(
                              label: const Text('Internal note'),
                              selected: _internal,
                              onSelected: (v) => setState(() => _internal = v),
                            ),
                          ],
                        ),
                      ),
                    Expanded(
                      child: ListView.builder(
                        padding: pagePadding(context, top: 8),
                        itemCount: _messages.length,
                        itemBuilder: (context, i) {
                          final m = _messages[i];
                          final isMine = widget.isStaff
                              ? m['from_staff'] == true
                              : m['from_customer'] == true;
                          final align = isMine ? Alignment.centerRight : Alignment.centerLeft;
                          final bg = align == Alignment.centerRight
                              ? AppTheme.primary.withValues(alpha: 0.12)
                              : Colors.grey.shade200;
                          return Align(
                            alignment: align,
                            child: Container(
                              margin: const EdgeInsets.only(bottom: 8),
                              padding: const EdgeInsets.all(12),
                              constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.85),
                              decoration: BoxDecoration(
                                color: bg,
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    m['author']?.toString() ?? (isMine ? 'You' : (widget.isStaff ? 'Customer' : 'Support')),
                                    style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600),
                                  ),
                                  if (m['is_internal'] == true)
                                    const Text('Internal', style: TextStyle(fontSize: 10, color: Colors.orange)),
                                  const SizedBox(height: 4),
                                  Text(m['body']?.toString() ?? ''),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                    Material(
                      elevation: 8,
                      child: SafeArea(
                        child: Padding(
                          padding: const EdgeInsets.all(8),
                          child: Row(
                            children: [
                              Expanded(
                                child: TextField(
                                  controller: _replyCtrl,
                                  decoration: const InputDecoration(
                                    hintText: 'Write a reply…',
                                    border: OutlineInputBorder(),
                                    isDense: true,
                                  ),
                                  maxLines: 3,
                                  minLines: 1,
                                ),
                              ),
                              const SizedBox(width: 8),
                              FilledButton(
                                onPressed: _sending ? null : _sendReply,
                                child: _sending
                                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                                    : const Icon(Icons.send),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
    );
  }

  void _showStatusPicker() {
    const statuses = ['open', 'in_progress', 'pending', 'resolved', 'closed'];
    showModalBottomSheet<void>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: statuses
              .map((s) => ListTile(
                    title: Text(s.replaceAll('_', ' ')),
                    onTap: () {
                      Navigator.pop(ctx);
                      _setStatus(s);
                    },
                  ))
              .toList(),
        ),
      ),
    );
  }
}
