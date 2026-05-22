import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../widgets/isp_ui_kit.dart';
import '../widgets/page_scaffold.dart';
import '../widgets/state_views.dart';

class StaffExpenseScreen extends StatefulWidget {
  const StaffExpenseScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<StaffExpenseScreen> createState() => _StaffExpenseScreenState();
}

class _StaffExpenseScreenState extends State<StaffExpenseScreen> {
  final _amountCtrl = TextEditingController();
  final _descCtrl = TextEditingController();
  final _fmt = NumberFormat('#,##0.00');
  List<Map<String, dynamic>> _categories = [];
  List<Map<String, dynamic>> _history = [];
  int? _categoryId;
  bool _loading = true;
  String? _error;
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _amountCtrl.dispose();
    _descCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final cats = await widget.api.collectorExpenseCategories();
      final hist = await widget.api.staffExpenses();
      if (mounted) {
        setState(() {
          _categories = cats;
          _history = hist;
          if (cats.isNotEmpty) _categoryId = (cats.first['id'] as num).toInt();
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not load expense data');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _submit() async {
    final amount = double.tryParse(_amountCtrl.text.trim());
    if (amount == null || amount <= 0 || _categoryId == null) {
      showSnack(context, 'Enter amount and category', isError: true);
      return;
    }
    setState(() => _submitting = true);
    try {
      await widget.api.submitCollectorExpense(
        amount: amount,
        categoryId: _categoryId!,
        description: _descCtrl.text.trim().isEmpty ? null : _descCtrl.text.trim(),
        expenseDate: DateFormat('yyyy-MM-dd').format(DateTime.now()),
      );
      if (mounted) {
        showSnack(context, 'Expense submitted for approval');
        _amountCtrl.clear();
        _descCtrl.clear();
        _load();
      }
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Color _statusColor(String? s) {
    switch (s) {
      case 'approved':
        return AppTheme.success;
      case 'rejected':
        return Colors.red;
      default:
        return AppTheme.warning;
    }
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'Expense',
      useGradientBody: true,
      actions: [IconButton(icon: const Icon(Icons.refresh), onPressed: _load)],
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: ErrorBanner(message: _error!, onRetry: _load))
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      IspUiKit.formCard(
                        title: 'New expense',
                        subtitle: 'Travel, fuel, field costs — manager approves',
                        children: [
                      DropdownButtonFormField<int>(
                        value: _categoryId,
                        decoration: const InputDecoration(labelText: 'Category', border: OutlineInputBorder()),
                        items: _categories
                            .map((c) => DropdownMenuItem(
                                  value: (c['id'] as num).toInt(),
                                  child: Text(c['name']?.toString() ?? ''),
                                ))
                            .toList(),
                        onChanged: (v) => setState(() => _categoryId = v),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _amountCtrl,
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        decoration: const InputDecoration(labelText: 'Amount (BDT)', border: OutlineInputBorder()),
                      ),
                      const SizedBox(height: 12),
                      TextField(
                        controller: _descCtrl,
                        decoration: const InputDecoration(labelText: 'Description', border: OutlineInputBorder()),
                        maxLines: 2,
                      ),
                      const SizedBox(height: 12),
                      IspUiKit.primaryButton(
                        label: 'Submit expense',
                        loading: _submitting,
                        onPressed: _submit,
                      ),
                        ],
                      ),
                      IspUiKit.sectionTitle('Recent expenses'),
                      const SizedBox(height: 8),
                      if (_history.isEmpty)
                        const Text('No expenses yet', style: TextStyle(color: Colors.grey)),
                      ..._history.map((e) {
                        final st = e['status']?.toString() ?? '';
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: Material(
                            color: AppTheme.card,
                            borderRadius: BorderRadius.circular(14),
                            child: ListTile(
                              title: Text('৳${_fmt.format((e['amount'] as num?) ?? 0)} · ${e['category'] ?? ''}', style: const TextStyle(fontWeight: FontWeight.w700)),
                              subtitle: Text('${e['expense_date'] ?? ''} · ${e['description'] ?? ''}'),
                              trailing: IspUiKit.statusBadge(st, _statusColor(st), compact: true),
                            ),
                          ),
                        );
                      }),
                    ],
                  ),
                ),
    );
  }
}
