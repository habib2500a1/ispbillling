import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../design_system/radiant_tokens.dart';
import '../services/api_service.dart';
import '../utils/app_nav.dart';
import '../widgets/legacy_softify_screen_header.dart';
import '../widgets/state_views.dart';

/// Legacy SOFTIFY Bill Approval — pending collector & staff expenses.
class StaffApprovalsScreen extends StatefulWidget {
  const StaffApprovalsScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<StaffApprovalsScreen> createState() => _StaffApprovalsScreenState();
}

class _StaffApprovalsScreenState extends State<StaffApprovalsScreen> {
  List<Map<String, dynamic>> _items = [];
  bool _loading = true;
  String? _error;
  final _fmt = NumberFormat('#,##0.00');
  final _search = TextEditingController();
  String _query = '';

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

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final list = await widget.api.staffPendingApprovals();
      if (mounted) setState(() => _items = list);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not load approvals');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> get _visible {
    if (_query.isEmpty) return _items;
    return _items.where((e) {
      final hay = [
        e['number'],
        e['category'],
        e['collector'],
        e['submitted_by'],
        e['vendor'],
        e['description'],
      ].whereType<String>().join(' ').toLowerCase();
      return hay.contains(_query);
    }).toList();
  }

  Future<void> _approve(Map<String, dynamic> e) async {
    final id = (e['id'] as num).toInt();
    final type = e['type']?.toString() ?? 'collector_expense';
    try {
      if (type == 'staff_expense') {
        await widget.api.approveStaffExpense(id);
      } else {
        await widget.api.approveExpense(id);
      }
      if (mounted) {
        showSnack(context, 'Approved');
        _load();
      }
    } on ApiException catch (ex) {
      if (mounted) showSnack(context, ex.message, isError: true);
    }
  }

  Future<void> _reject(Map<String, dynamic> e) async {
    final id = (e['id'] as num).toInt();
    final type = e['type']?.toString() ?? 'collector_expense';
    try {
      if (type == 'staff_expense') {
        await widget.api.rejectStaffExpense(id, reason: 'Rejected on mobile');
      } else {
        await widget.api.rejectExpense(id, reason: 'Rejected on mobile');
      }
      if (mounted) {
        showSnack(context, 'Rejected');
        _load();
      }
    } on ApiException catch (ex) {
      if (mounted) showSnack(context, ex.message, isError: true);
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
            title: 'Bill Approval',
            toolbar: LegacySoftifySearchToolbar(
              controller: _search,
              hint: 'Search expense no / category…',
              onClear: () => _search.clear(),
            ),
          ),
          if (!_loading && _error == null)
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 8, 14, 0),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  'Pending: ${visible.length} of ${_items.length}',
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                ),
              ),
            ),
          Expanded(
            child: _loading
                ? const ListLoading()
                : _error != null
                    ? Center(child: ErrorBanner(message: _error!, onRetry: _load))
                    : visible.isEmpty
                        ? const EmptyState(
                            icon: Icons.check_circle_outline,
                            title: 'Nothing pending',
                            subtitle: 'All bills are approved',
                          )
                        : RefreshIndicator(
                            onRefresh: _load,
                            color: RadiantTokens.brand,
                            child: ListView.separated(
                              padding: const EdgeInsets.fromLTRB(12, 10, 12, 24),
                              itemCount: visible.length,
                              separatorBuilder: (_, _) => const SizedBox(height: 10),
                              itemBuilder: (context, i) => _approvalCard(visible[i]),
                            ),
                          ),
          ),
        ],
      ),
    );
  }

  Widget _approvalCard(Map<String, dynamic> e) {
    final amount = (e['amount'] as num?)?.toDouble() ?? 0;
    final who = e['collector'] ?? e['submitted_by'] ?? 'Staff';
    final category = e['category'] ?? 'Expense';
    final number = e['number'] ?? '';
    final source = e['expense_source_label'] ?? e['expense_source'] ?? '';
    final date = e['expense_date']?.toString() ?? '';

    return Material(
      color: Colors.white,
      elevation: 1,
      borderRadius: BorderRadius.circular(10),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: RadiantTokens.brand.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Icon(Icons.receipt_long, color: RadiantTokens.brand, size: 22),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(number, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                      Text('$who · $category', style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
                      if (source.toString().isNotEmpty)
                        Text(source.toString(), style: TextStyle(fontSize: 11, color: Colors.grey.shade500)),
                    ],
                  ),
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text('৳${_fmt.format(amount)}',
                        style: const TextStyle(color: Colors.red, fontWeight: FontWeight.w800, fontSize: 18)),
                    if (date.isNotEmpty) Text(date, style: TextStyle(fontSize: 10, color: Colors.grey.shade600)),
                  ],
                ),
              ],
            ),
            if ((e['description']?.toString() ?? '').isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(e['description'].toString(), style: TextStyle(fontSize: 12, color: Colors.grey.shade700)),
            ],
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => _reject(e),
                    style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
                    child: const Text('Reject'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: FilledButton(
                    onPressed: () => _approve(e),
                    style: FilledButton.styleFrom(backgroundColor: const Color(0xFFFF7043)),
                    child: const Text('Approve'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
