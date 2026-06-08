import 'package:flutter/material.dart';

import '../core/theme/design_tokens.dart';
import '../core/theme/design_tokens.dart';
import '../design_system/components/radiant_screen_header.dart';
import '../design_system/components/radiant_skeleton.dart';
import '../design_system/navigation/radiant_super_shell.dart';
import '../design_system/radiant_tokens.dart';
import '../features/customer/data/customer_repository.dart';
import '../services/api_service.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
import '../widgets/radiant_list_tiles.dart';
import '../widgets/state_views.dart';
import 'ticket_thread_screen.dart';

class CustomerTicketsScreen extends StatefulWidget {
  const CustomerTicketsScreen({
    super.key,
    required this.api,
    this.active = false,
    this.embedded = false,
  });

  final ApiService api;
  final bool active;
  final bool embedded;

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
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(RadiantTokens.radiusMd)),
        title: Row(
          children: [
            Icon(Icons.support_agent_rounded, color: RadiantTokens.brand),
            const SizedBox(width: 8),
            const Text('New support ticket'),
          ],
        ),
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

    final res = await CustomerRepository(api).createTicket(
      subject: subjectCtrl.text.trim(),
      description: descCtrl.text.trim(),
    );
    if (!context.mounted) return;
    res.when(
      ok: (msg) {
        showSnack(context, msg);
        onCreated?.call();
      },
      err: (f) => showSnack(context, f.message, isError: true),
    );
  }
}

class _CustomerTicketsScreenState extends State<CustomerTicketsScreen> {
  late final CustomerRepository _repo = CustomerRepository(widget.api);
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
    final res = await _repo.tickets();
    if (!mounted) return;
    res.when(
      ok: (list) => setState(() {
        _tickets = list;
        _loading = false;
      }),
      err: (f) => setState(() {
        _error = f.message;
        _loading = false;
      }),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        RadiantScreenHeader(
          title: 'Support',
          subtitle: 'Tickets & help',
          compact: true,
          trailing: [
            RadiantHeaderIcon(
              icon: Icons.add_comment_outlined,
              onPressed: () => CustomerTicketsScreen.showCreateDialog(context, widget.api, onCreated: _load),
              tooltip: 'New ticket',
            ),
          ],
        ),
        Expanded(child: _buildBody()),
      ],
    );
  }

  Widget _buildBody() {
    if (_loading) return const RadiantDashboardSkeleton();
    if (_error != null) {
      return Center(child: Text(_error!, style: TextStyle(color: context.radiant.danger)));
    }
    if (_tickets.isEmpty) {
      return EmptyState(
        icon: Icons.support_agent_rounded,
        title: 'No tickets yet',
        subtitle: 'Create a support ticket — we will reply in the app',
        action: () => CustomerTicketsScreen.showCreateDialog(context, widget.api, onCreated: _load),
        actionLabel: 'New ticket',
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      color: RadiantTokens.brand,
      child: ListView.separated(
        padding: pagePadding(context, top: 10).copyWith(bottom: 100),
        itemCount: _tickets.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (context, i) {
          final t = _tickets[i];
          final id = (t['id'] as num).toInt();
          return RadiantTicketRow(
            subject: t['subject']?.toString() ?? 'Ticket #$id',
            status: t['status']?.toString() ?? 'open',
            updated: t['updated_at']?.toString() ?? t['created_at']?.toString() ?? '',
            priority: t['priority']?.toString(),
            onTap: () {
              Navigator.push(
                context,
                RadiantPageRoute(
                  page: TicketThreadScreen(
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
