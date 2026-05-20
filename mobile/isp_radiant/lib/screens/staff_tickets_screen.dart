import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/layout.dart';
import '../widgets/state_views.dart';
import 'staff_create_ticket_screen.dart';
import 'ticket_thread_screen.dart';

class StaffTicketsScreen extends StatefulWidget {
  const StaffTicketsScreen({super.key, required this.api, this.active = false});

  final ApiService api;
  final bool active;

  @override
  State<StaffTicketsScreen> createState() => _StaffTicketsScreenState();
}

class _StaffTicketsScreenState extends State<StaffTicketsScreen> {
  List<Map<String, dynamic>> _items = [];
  bool _loading = true;
  String? _error;
  String _filter = 'all';

  static const _filters = [
    ('all', 'All'),
    ('active', 'Active'),
    ('open', 'Open'),
    ('in_progress', 'In progress'),
    ('complete', 'Complete'),
    ('closed', 'Closed'),
  ];

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void didUpdateWidget(StaffTicketsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active && !oldWidget.active) _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final list = await widget.api.staffTickets(status: _filter);
      if (mounted) {
        setState(() {
          _items = list;
          _error = null;
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not load tickets');
    }
    if (mounted) setState(() => _loading = false);
  }

  Color _statusColor(String? s) {
    switch (s) {
      case 'open':
        return Colors.orange;
      case 'in_progress':
        return Colors.blue;
      case 'resolved':
      case 'closed':
        return AppTheme.success;
      default:
        return Colors.grey;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
          child: Row(
            children: [
              Expanded(
                child: SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: _filters
                        .map((f) => Padding(
                              padding: const EdgeInsets.only(right: 6),
                              child: FilterChip(
                                label: Text(f.$2),
                                selected: _filter == f.$1,
                                onSelected: (_) {
                                  setState(() => _filter = f.$1);
                                  _load();
                                },
                              ),
                            ))
                        .toList(),
                  ),
                ),
              ),
              IconButton(
                icon: const Icon(Icons.add_circle, color: AppTheme.primary),
                onPressed: () async {
                  final ok = await Navigator.push<bool>(
                    context,
                    MaterialPageRoute(builder: (_) => StaffCreateTicketScreen(api: widget.api)),
                  );
                  if (ok == true) _load();
                },
              ),
            ],
          ),
        ),
        Expanded(child: _body()),
      ],
    );
  }

  Widget _body() {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return Center(child: Padding(padding: const EdgeInsets.all(24), child: ErrorBanner(message: _error!, onRetry: _load)));
    }
    if (_items.isEmpty) {
      return EmptyState(
        icon: Icons.support_agent,
        title: 'No tickets',
        subtitle: 'Try another filter or create one',
        action: () => Navigator.push(context, MaterialPageRoute(builder: (_) => StaffCreateTicketScreen(api: widget.api))).then((_) => _load()),
        actionLabel: 'Create ticket',
      );
    }
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        padding: pagePadding(context, top: 8),
        itemCount: _items.length,
        separatorBuilder: (_, _) => const SizedBox(height: 6),
        itemBuilder: (context, i) {
          final t = _items[i];
          final status = t['status']?.toString() ?? '';
          return Card(
            child: ListTile(
              leading: CircleAvatar(
                backgroundColor: _statusColor(status).withValues(alpha: 0.15),
                child: Icon(Icons.confirmation_number, color: _statusColor(status), size: 20),
              ),
              title: Text(t['subject']?.toString() ?? ''),
              subtitle: Text('#${t['ticket_number']} · ${t['customer_name'] ?? ''}'),
              trailing: Chip(
                label: Text(status, style: const TextStyle(fontSize: 10)),
                backgroundColor: _statusColor(status).withValues(alpha: 0.12),
              ),
              onTap: () {
                final id = (t['id'] as num).toInt();
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => TicketThreadScreen(
                      api: widget.api,
                      ticketId: id,
                      isStaff: true,
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
