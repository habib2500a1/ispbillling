import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../design_system/radiant_tokens.dart';
import '../services/api_service.dart';
import '../widgets/legacy_softify_screen_header.dart';
import '../widgets/state_views.dart';
import 'staff_create_ticket_screen.dart';
import 'ticket_thread_screen.dart';

/// Legacy SOFTIFY Support / Ticket list.
class StaffTicketsScreen extends StatefulWidget {
  const StaffTicketsScreen({super.key, required this.api, this.active = false, this.staffUserId});

  final ApiService api;
  final bool active;
  final int? staffUserId;

  @override
  State<StaffTicketsScreen> createState() => _StaffTicketsScreenState();
}

class _StaffTicketsScreenState extends State<StaffTicketsScreen> {
  List<Map<String, dynamic>> _items = [];
  bool _loading = true;
  String? _error;
  String _filter = 'active';
  final _search = TextEditingController();
  String _query = '';

  static const _filters = [
    ('active', 'Active'),
    ('all', 'All'),
    ('open', 'Open'),
    ('in_progress', 'In progress'),
    ('unassigned', 'Unassigned'),
    ('mine', 'My tickets'),
    ('closed', 'Closed'),
  ];

  @override
  void initState() {
    super.initState();
    _search.addListener(() {
      final q = _search.text.trim().toLowerCase();
      if (q != _query) setState(() => _query = q);
    });
    _load();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  void didUpdateWidget(StaffTicketsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active && !oldWidget.active) _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final mine = _filter == 'mine';
      final unassigned = _filter == 'unassigned';
      final status = (mine || unassigned) ? 'all' : _filter;
      final list = await widget.api.staffTickets(status: status, mine: mine, unassigned: unassigned);
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

  List<Map<String, dynamic>> get _visible {
    if (_query.isEmpty) return _items;
    final q = _query;
    return _items.where((t) {
      final hay = [t['subject'], t['ticket_number'], t['customer_name'], t['customer_code'], t['assignee_name']]
          .whereType<String>()
          .join(' ')
          .toLowerCase();
      return hay.contains(q);
    }).toList();
  }

  Future<void> _openFilterSheet() async {
    final picked = await showModalBottomSheet<String>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const ListTile(title: Text('Ticket filter', style: TextStyle(fontWeight: FontWeight.w700))),
            for (final entry in _filters)
              ListTile(
                title: Text(entry.$2),
                trailing: _filter == entry.$1 ? const Icon(Icons.check, color: RadiantTokens.brand) : null,
                onTap: () => Navigator.pop(ctx, entry.$1),
              ),
            ListTile(
              leading: const Icon(Icons.add_circle_outline, color: RadiantTokens.brand),
              title: const Text('Create new ticket'),
              onTap: () {
                Navigator.pop(ctx);
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => StaffCreateTicketScreen(api: widget.api)),
                ).then((_) => _load());
              },
            ),
          ],
        ),
      ),
    );
    if (picked != null && picked != _filter) {
      setState(() => _filter = picked);
      _load();
    }
  }

  Color _statusColor(String? status) {
    switch (status) {
      case 'open':
        return const Color(0xFFFFA726);
      case 'in_progress':
        return const Color(0xFF42A5F5);
      case 'resolved':
        return const Color(0xFF66BB6A);
      case 'closed':
        return Colors.grey;
      default:
        return RadiantTokens.brand;
    }
  }

  @override
  Widget build(BuildContext context) {
    final visible = _visible;
    return Scaffold(
      backgroundColor: const Color(0xFFF0F4F8),
      body: Column(
        children: [
          LegacySoftifyScreenHeader(
            title: 'Support',
            showBack: false,
            trailing: IconButton(
              onPressed: () async {
                final ok = await Navigator.push<bool>(
                  context,
                  MaterialPageRoute(builder: (_) => StaffCreateTicketScreen(api: widget.api)),
                );
                if (ok == true) _load();
              },
              icon: const Icon(Icons.add_circle_outline, color: Colors.white),
            ),
            toolbar: LegacySoftifySearchToolbar(
              controller: _search,
              hint: 'Search ticket, client…',
              onFilter: _openFilterSheet,
              onClear: () => _search.clear(),
            ),
          ),
          if (!_loading && _error == null)
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 8, 14, 0),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      'Showing Results ${visible.length} of ${_items.length}',
                      style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                    ),
                  ),
                  if (_filter != 'active')
                    Chip(
                      label: Text(
                        _filters.firstWhere((e) => e.$1 == _filter, orElse: () => ('active', 'Active')).$2,
                        style: const TextStyle(fontSize: 10),
                      ),
                      deleteIcon: const Icon(Icons.close, size: 14),
                      onDeleted: () {
                        setState(() => _filter = 'active');
                        _load();
                      },
                      visualDensity: VisualDensity.compact,
                    ),
                ],
              ),
            ),
          Expanded(child: _buildList(visible)),
        ],
      ),
    );
  }

  Widget _buildList(List<Map<String, dynamic>> visible) {
    if (_loading) return const ListLoading();
    if (_error != null) return Center(child: ErrorBanner(message: _error!, onRetry: _load));
    if (visible.isEmpty) {
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
      color: RadiantTokens.brand,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(12, 10, 12, 24),
        itemCount: visible.length,
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (context, i) => _ticketCard(visible[i]),
      ),
    );
  }

  Widget _ticketCard(Map<String, dynamic> t) {
    final id = (t['id'] as num).toInt();
    final status = t['status']?.toString();
    final subject = t['subject']?.toString() ?? 'Ticket';
    final number = t['ticket_number']?.toString() ?? '#$id';
    final customer = t['customer_name']?.toString() ?? '';
    final assignee = t['assignee_name']?.toString() ?? 'Unassigned';
    final updated = t['updated_at']?.toString() ?? '';
    final priority = t['priority']?.toString() ?? 'medium';

    return Material(
      color: Colors.white,
      elevation: 1,
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: () async {
          await Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => TicketThreadScreen(
                api: widget.api,
                ticketId: id,
                isStaff: true,
                ticketSummary: t,
                staffUserId: widget.staffUserId,
              ),
            ),
          );
          _load();
        },
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: _statusColor(status).withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(Icons.confirmation_number, color: _statusColor(status), size: 22),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(subject, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
                    const SizedBox(height: 3),
                    Text(number, style: TextStyle(fontSize: 11, color: Colors.grey.shade600)),
                    if (customer.isNotEmpty)
                      Text('Client: $customer', style: TextStyle(fontSize: 11, color: Colors.grey.shade700)),
                    Text('Assignee: $assignee', style: TextStyle(fontSize: 11, color: Colors.grey.shade600)),
                    if (updated.isNotEmpty)
                      Text(_formatDate(updated), style: TextStyle(fontSize: 10, color: Colors.grey.shade500)),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  _chip(_statusLabel(status), _statusColor(status)),
                  const SizedBox(height: 6),
                  _chip(priority.toUpperCase(), _priorityColor(priority)),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _formatDate(String raw) {
    try {
      final dt = DateTime.parse(raw);
      return DateFormat('dd MMM, hh:mm a').format(dt.toLocal());
    } catch (_) {
      return raw;
    }
  }

  String _statusLabel(String? status) {
    if (status == null || status.isEmpty) return 'Unknown';
    return status.split('_').map((w) => w.isEmpty ? w : '${w[0].toUpperCase()}${w.substring(1)}').join(' ');
  }

  Color _priorityColor(String p) {
    switch (p) {
      case 'critical':
        return Colors.red;
      case 'high':
        return Colors.deepOrange;
      case 'medium':
        return const Color(0xFFFFA726);
      default:
        return Colors.grey;
    }
  }

  Widget _chip(String label, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.35)),
      ),
      child: Text(label, style: TextStyle(fontSize: 9, fontWeight: FontWeight.w700, color: color)),
    );
  }
}
