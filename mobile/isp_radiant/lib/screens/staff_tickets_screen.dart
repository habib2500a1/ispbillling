import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/layout.dart';
import '../widgets/isp_tab_screen.dart';
import '../widgets/isp_ui_kit.dart';
import '../widgets/state_views.dart';
import '../widgets/support_ticket_ui.dart';
import 'staff_create_ticket_screen.dart';
import 'ticket_thread_screen.dart';

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
  final _searchCtrl = TextEditingController();
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
    _searchCtrl.addListener(() {
      final q = _searchCtrl.text.trim().toLowerCase();
      if (q != _query) setState(() => _query = q);
    });
    _load();
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
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

  @override
  Widget build(BuildContext context) {
    final visible = _visible;

    return IspTabScreen(
      title: 'Support tickets',
      subtitle: '${_items.length} in view',
      loading: _loading,
      error: _error,
      onRetry: _load,
      onRefresh: _load,
      trailing: [
        IconButton(
          icon: const Icon(Icons.add_circle_outline, color: Colors.white),
          onPressed: () async {
            final ok = await Navigator.push<bool>(
              context,
              MaterialPageRoute(builder: (_) => StaffCreateTicketScreen(api: widget.api)),
            );
            if (ok == true) _load();
          },
        ),
      ],
      headerChild: Padding(
        padding: const EdgeInsets.only(top: 4),
        child: IspUiKit.searchBar(
          controller: _searchCtrl,
          hint: 'Search ticket, client…',
          onClear: () => _searchCtrl.clear(),
        ),
      ),
      empty: !_loading && _error == null && visible.isEmpty
          ? EmptyState(
              icon: Icons.support_agent,
              title: 'No tickets',
              subtitle: 'Try another filter or create one',
              action: () => Navigator.push(context, MaterialPageRoute(builder: (_) => StaffCreateTicketScreen(api: widget.api))).then((_) => _load()),
              actionLabel: 'Create ticket',
            )
          : null,
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
            child: SupportTicketUi.filterChips(
              options: _filters,
              selected: _filter,
              onSelected: (v) {
                setState(() => _filter = v);
                _load();
              },
            ),
          ),
          Expanded(
            child: ListView.separated(
              padding: pagePadding(context, top: 10),
              itemCount: visible.length,
              separatorBuilder: (_, _) => const SizedBox(height: 10),
              itemBuilder: (context, i) {
                final t = visible[i];
                final id = (t['id'] as num).toInt();
                return SupportTicketUi.ticketListCard(
                  ticket: t,
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
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
