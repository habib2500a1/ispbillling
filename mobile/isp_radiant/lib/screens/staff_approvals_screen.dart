import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
import '../widgets/isp_ui_kit.dart';
import '../widgets/page_scaffold.dart';
import '../widgets/state_views.dart';

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

  @override
  void initState() {
    super.initState();
    _load();
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

  Future<void> _approve(int id) async {
    try {
      await widget.api.approveExpense(id);
      if (mounted) {
        showSnack(context, 'Approved');
        _load();
      }
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    }
  }

  Future<void> _reject(int id) async {
    try {
      await widget.api.rejectExpense(id, reason: 'Rejected on mobile');
      if (mounted) {
        showSnack(context, 'Rejected');
        _load();
      }
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'Pending approvals',
      useGradientBody: true,
      body: _loading
          ? const ListLoading()
          : _error != null
              ? Center(child: ErrorBanner(message: _error!, onRetry: _load))
              : _items.isEmpty
                  ? const EmptyState(
                      icon: Icons.check_circle_outline,
                      title: 'Nothing pending',
                      subtitle: 'All expenses are approved',
                    )
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: pagePadding(context, top: 12),
                        itemCount: _items.length,
                        separatorBuilder: (_, _) => const SizedBox(height: 10),
                        itemBuilder: (context, i) {
                          final e = _items[i];
                          final id = (e['id'] as num).toInt();
                          final amount = (e['amount'] as num?)?.toDouble() ?? 0;
                          return IspUiKit.approvalCard(
                            amountLine: '৳${_fmt.format(amount)} · ${e['category'] ?? 'Expense'}',
                            metaLine: '${e['collector'] ?? ''} · ${e['number'] ?? ''}',
                            description: e['description']?.toString(),
                            onApprove: () => _approve(id),
                            onReject: () => _reject(id),
                          );
                        },
                      ),
                    ),
    );
  }
}
