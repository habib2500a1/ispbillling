import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
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
        title: const Text('New support ticket'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: subjectCtrl,
                decoration: const InputDecoration(labelText: 'Subject'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: descCtrl,
                decoration: const InputDecoration(labelText: 'Describe your issue'),
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

  Color _statusColor(String? status) {
    switch (status) {
      case 'open':
        return Colors.orange;
      case 'in_progress':
        return Colors.blue;
      case 'resolved':
      case 'closed':
        return Colors.green;
      default:
        return Colors.grey;
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: ErrorBanner(message: _error!, onRetry: _load),
        ),
      );
    }
    if (_tickets.isEmpty) {
      return EmptyState(
        icon: Icons.support_agent,
        title: 'No tickets yet',
        subtitle: 'Create a support ticket from the app',
        action: () => CustomerTicketsScreen.showCreateDialog(context, widget.api, onCreated: _load),
        actionLabel: 'New ticket',
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: pagePadding(context, top: 8),
        itemCount: _tickets.length,
        separatorBuilder: (_, _) => const SizedBox(height: 8),
        itemBuilder: (context, i) {
          final t = _tickets[i];
          final status = t['status']?.toString() ?? '';
          return Card(
            child: ListTile(
              leading: CircleAvatar(
                backgroundColor: _statusColor(status).withValues(alpha: 0.15),
                child: Icon(Icons.confirmation_number, color: _statusColor(status), size: 20),
              ),
              title: Text(t['subject']?.toString() ?? 'Ticket'),
              subtitle: Text('#${t['ticket_number'] ?? t['id']} · $status'),
              trailing: const Icon(Icons.chevron_right),
              onTap: () {
                final id = (t['id'] as num).toInt();
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
            ),
          );
        },
      ),
    );
  }
}
