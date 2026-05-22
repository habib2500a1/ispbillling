import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
import '../widgets/staff_blue_app_bar.dart';
import '../widgets/state_views.dart';
import '../widgets/support_ticket_ui.dart';

class TicketThreadScreen extends StatefulWidget {
  const TicketThreadScreen({
    super.key,
    required this.api,
    required this.ticketId,
    required this.isStaff,
    this.ticketSummary,
    this.staffUserId,
  });

  final ApiService api;
  final int ticketId;
  final bool isStaff;
  final Map<String, dynamic>? ticketSummary;
  final int? staffUserId;

  @override
  State<TicketThreadScreen> createState() => _TicketThreadScreenState();
}

class _TicketThreadScreenState extends State<TicketThreadScreen> {
  final _replyCtrl = TextEditingController();
  final _scrollCtrl = ScrollController();
  Map<String, dynamic>? _ticket;
  List<Map<String, dynamic>> _messages = [];
  List<Map<String, dynamic>> _assignees = [];
  bool _loading = true;
  String? _error;
  bool _internal = false;
  bool _sending = false;
  bool _updating = false;

  @override
  void initState() {
    super.initState();
    _load();
    if (widget.isStaff) _loadAssignees();
  }

  @override
  void dispose() {
    _replyCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadAssignees() async {
    try {
      final list = await widget.api.staffTicketAssignees();
      if (mounted) setState(() => _assignees = list);
    } catch (_) {}
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
        _ticket = (body['ticket'] as Map<String, dynamic>?) ?? widget.ticketSummary;
        _messages = (body['messages'] as List<dynamic>?)
                ?.map((e) => Map<String, dynamic>.from(e as Map))
                .toList() ??
            [];
      });
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (_scrollCtrl.hasClients) {
          _scrollCtrl.jumpTo(_scrollCtrl.position.maxScrollExtent);
        }
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

  Future<void> _patchTicket({String? status, int? assignedTo, bool clearAssignee = false}) async {
    setState(() => _updating = true);
    try {
      final body = await widget.api.staffUpdateTicket(
        widget.ticketId,
        status: status,
        assignedTo: assignedTo,
        clearAssignee: clearAssignee,
      );
      if (mounted) {
        setState(() => _ticket = body['ticket'] as Map<String, dynamic>? ?? _ticket);
      }
      await _load();
      if (mounted) showSnack(context, 'Ticket updated');
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _updating = false);
    }
  }

  Future<void> _callCustomer() async {
    final phone = _ticket?['customer_phone']?.toString().replaceAll(RegExp(r'\s+'), '') ?? '';
    if (phone.isEmpty) {
      showSnack(context, 'No phone on file', isError: true);
      return;
    }
    final uri = Uri.parse(phone.startsWith('+') ? 'tel:$phone' : 'tel:$phone');
    if (!await launchUrl(uri)) {
      if (mounted) showSnack(context, 'Could not open dialer', isError: true);
    }
  }

  void _showAssignSheet() {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Padding(
              padding: EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: Text('Assign technician', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            ),
            ListTile(
              leading: const Icon(Icons.person_off, color: Color(0xFF94A3B8)),
              title: const Text('Unassign'),
              onTap: () {
                Navigator.pop(ctx);
                _patchTicket(clearAssignee: true);
              },
            ),
            ..._assignees.map(
              (u) => ListTile(
                leading: CircleAvatar(
                  backgroundColor: AppTheme.primary.withValues(alpha: 0.12),
                  child: Text(
                    (u['name']?.toString().isNotEmpty == true ? u['name'].toString()[0] : '?').toUpperCase(),
                    style: const TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold),
                  ),
                ),
                title: Text(u['name']?.toString() ?? ''),
                onTap: () {
                  Navigator.pop(ctx);
                  _patchTicket(assignedTo: (u['id'] as num).toInt());
                },
              ),
            ),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
  }

  Future<void> _confirmClose() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Close ticket?'),
        content: const Text('Customer will see this ticket as closed. You can reopen from admin if needed.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: FilledButton.styleFrom(backgroundColor: const Color(0xFF64748B)),
            child: const Text('Close ticket'),
          ),
        ],
      ),
    );
    if (ok == true) await _patchTicket(status: 'closed');
  }

  @override
  Widget build(BuildContext context) {
    final t = _ticket ?? widget.ticketSummary ?? {};
    final status = t['status']?.toString() ?? '';
    final isClosed = status == 'closed' || status == 'resolved';
    final title = t['subject']?.toString() ?? 'Ticket';

    return Scaffold(
      backgroundColor: AppTheme.background,
      appBar: StaffBlueAppBar(
        title: title,
        onBack: () => Navigator.pop(context),
        actions: widget.isStaff && !_loading
            ? [
                if (_updating)
                  const Padding(
                    padding: EdgeInsets.all(14),
                    child: SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)),
                  )
                else
                  PopupMenuButton<String>(
                    icon: const Icon(Icons.more_vert, color: Colors.white),
                    onSelected: (v) async {
                      switch (v) {
                        case 'pending':
                          await _patchTicket(status: 'pending');
                        case 'open':
                          await _patchTicket(status: 'open');
                      }
                    },
                    itemBuilder: (_) => [
                      const PopupMenuItem(value: 'pending', child: Text('Mark pending')),
                      const PopupMenuItem(value: 'open', child: Text('Re-open')),
                    ],
                  ),
              ]
            : null,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: ErrorBanner(message: _error!, onRetry: _load))
              : Column(
                  children: [
                    if (widget.isStaff) ...[
                      Padding(
                        padding: const EdgeInsets.fromLTRB(12, 10, 12, 0),
                        child: SupportTicketUi.detailHeader(
                          ticket: t,
                          onCall: _callCustomer,
                        ),
                      ),
                      SupportTicketUi.actionBar(
                        canClose: true,
                        isClosed: isClosed,
                        onAssign: _showAssignSheet,
                        onAssignMe: widget.staffUserId != null
                            ? () => _patchTicket(assignedTo: widget.staffUserId)
                            : null,
                        onInProgress: () => _patchTicket(status: 'in_progress'),
                        onResolve: () => _patchTicket(status: 'resolved'),
                        onClose: _confirmClose,
                      ),
                    ] else
                      Padding(
                        padding: const EdgeInsets.fromLTRB(12, 10, 12, 0),
                        child: Row(
                          children: [
                            SupportTicketUi.statusChip(status),
                            const SizedBox(width: 8),
                            Text(
                              t['ticket_number']?.toString() ?? '',
                              style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
                            ),
                          ],
                        ),
                      ),
                    Expanded(
                      child: _messages.isEmpty
                          ? const Center(
                              child: Text('No messages yet', style: TextStyle(color: Color(0xFF94A3B8))),
                            )
                          : ListView.builder(
                              controller: _scrollCtrl,
                              padding: pagePadding(context, top: 8),
                              itemCount: _messages.length,
                              itemBuilder: (context, i) {
                                final m = _messages[i];
                                final isStaffSide = widget.isStaff
                                    ? m['from_staff'] == true
                                    : m['from_customer'] == true;
                                return SupportTicketUi.chatBubble(
                                  author: m['author']?.toString() ?? (isStaffSide ? 'You' : 'Support'),
                                  body: m['body']?.toString() ?? '',
                                  isStaffSide: isStaffSide,
                                  isInternal: m['is_internal'] == true,
                                  time: _formatMsgTime(m['created_at']),
                                );
                              },
                            ),
                    ),
                    if (!isClosed || !widget.isStaff)
                      _replyBar(isClosed),
                  ],
                ),
    );
  }

  Widget _replyBar(bool closed) {
    if (closed && !widget.isStaff) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(16),
        color: AppTheme.success.withValues(alpha: 0.1),
        child: const Text(
          'This ticket is closed. Open a new ticket if you need more help.',
          textAlign: TextAlign.center,
          style: TextStyle(fontSize: 13, color: AppTheme.success, fontWeight: FontWeight.w600),
        ),
      );
    }
    if (closed) return const SizedBox.shrink();

    return Material(
      elevation: 12,
      color: Colors.white,
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (widget.isStaff)
                Align(
                  alignment: Alignment.centerLeft,
                  child: FilterChip(
                    label: const Text('Internal note', style: TextStyle(fontSize: 12)),
                    selected: _internal,
                    onSelected: (v) => setState(() => _internal = v),
                    selectedColor: AppTheme.warning.withValues(alpha: 0.2),
                  ),
                ),
              Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Expanded(
                    child: TextField(
                      controller: _replyCtrl,
                      decoration: InputDecoration(
                        hintText: widget.isStaff ? 'Reply to customer…' : 'Write your message…',
                        filled: true,
                        fillColor: const Color(0xFFF1F5F9),
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(14),
                          borderSide: BorderSide.none,
                        ),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                      ),
                      maxLines: 4,
                      minLines: 1,
                    ),
                  ),
                  const SizedBox(width: 8),
                  FilledButton(
                    onPressed: _sending ? null : _sendReply,
                    style: FilledButton.styleFrom(
                      padding: const EdgeInsets.all(14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                    child: _sending
                        ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Icon(Icons.send_rounded),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  String? _formatMsgTime(dynamic iso) {
    if (iso == null) return null;
    try {
      final dt = DateTime.parse(iso.toString()).toLocal();
      return '${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
    } catch (_) {
      return null;
    }
  }
}
