import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
import '../widgets/isp_tab_screen.dart';
import '../widgets/support_ticket_ui.dart';
import '../widgets/state_views.dart';
import 'ticket_thread_screen.dart';

class CustomerTicketsScreen extends StatefulWidget {
  const CustomerTicketsScreen({
    super.key,
    required this.api,
    this.active = false,
  });

  final ApiService api;
  final bool active;

  @override
  State<CustomerTicketsScreen> createState() => _CustomerTicketsScreenState();

  static Future<void> showCreateDialog(
    BuildContext context,
    ApiService api, {
    VoidCallback? onCreated,
  }) async {
    final subjectCtrl = TextEditingController();
    final descCtrl = TextEditingController();

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Row(
          children: [
            Icon(Icons.support_agent, color: AppTheme.primary),
            SizedBox(width: 8),
            Text('New support ticket'),
          ],
        ),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: subjectCtrl,
                decoration: const InputDecoration(
                  labelText: 'Subject',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: descCtrl,
                decoration: const InputDecoration(
                  labelText: 'Describe your issue',
                  border: OutlineInputBorder(),
                ),
                maxLines: 4,
              ),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Submit')),
        ],
      ),
    );

    if (ok != true || !context.mounted) return;
    if (subjectCtrl.text.trim().isEmpty || descCtrl.text.trim().isEmpty) {
      showSnack(context, 'Fill subject and description', isError: true);
      return;
    }

    try {
      await api.createTicket(
        subject: subjectCtrl.text.trim(),
        description: descCtrl.text.trim(),
      );
      if (context.mounted) {
        showSnack(context, 'Ticket submitted successfully');
        onCreated?.call();
      }
    } on ApiException catch (e) {
      if (context.mounted) showSnack(context, e.message, isError: true);
    }
  }
}

class _CustomerTicketsScreenState extends State<CustomerTicketsScreen> {
  List<Map<String, dynamic>> _tickets = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void didUpdateWidget(CustomerTicketsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active && !oldWidget.active) _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final list = await widget.api.customerTickets();
      if (mounted) setState(() => _tickets = list);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not load tickets');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return IspTabScreen(
      title: 'Support',
      subtitle: 'Tickets & help',
      loading: _loading,
      error: _error,
      onRetry: _load,
      onRefresh: _load,
      trailing: [
        IconButton(
          onPressed: () => CustomerTicketsScreen.showCreateDialog(context, widget.api, onCreated: _load),
          icon: const Icon(Icons.add_comment_outlined, color: Colors.white),
        ),
      ],
      empty: !_loading && _error == null && _tickets.isEmpty
          ? EmptyState(
              icon: Icons.support_agent,
              title: 'No tickets yet',
              subtitle: 'Create a support ticket — we will reply in the app',
              action: () => CustomerTicketsScreen.showCreateDialog(context, widget.api, onCreated: _load),
              actionLabel: 'New ticket',
            )
          : null,
      child: ListView.separated(
        padding: pagePadding(context, top: 10),
        itemCount: _tickets.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (context, i) {
          final t = _tickets[i];
          final id = (t['id'] as num).toInt();
          return SupportTicketUi.ticketListCard(
            ticket: {...t, 'customer_name': 'You'},
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => TicketThreadScreen(
                    api: widget.api,
                    ticketId: id,
                    isStaff: false,
                    ticketSummary: t,
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
