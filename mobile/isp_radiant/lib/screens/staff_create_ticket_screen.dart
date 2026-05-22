import 'dart:async';

import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
import '../widgets/customer_search_result_tile.dart';
import '../widgets/form_field_card.dart';
import '../widgets/page_scaffold.dart';

class StaffCreateTicketScreen extends StatefulWidget {
  const StaffCreateTicketScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<StaffCreateTicketScreen> createState() => _StaffCreateTicketScreenState();
}

class _StaffCreateTicketScreenState extends State<StaffCreateTicketScreen> {
  final _searchCtrl = TextEditingController();
  final _subjectCtrl = TextEditingController();
  final _descCtrl = TextEditingController();
  List<Map<String, dynamic>> _results = [];
  Map<String, dynamic>? _selected;
  String _department = 'technical_support';
  String _priority = 'medium';
  bool _saving = false;
  bool _searching = false;
  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _searchCtrl.addListener(_onSearchChanged);
  }

  void _onSearchChanged() {
    _debounce?.cancel();
    final q = _searchCtrl.text.trim();
    if (q.length < 2) {
      setState(() => _results = []);
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 400), () => _search(silent: true));
  }

  Future<void> _search({bool silent = false}) async {
    final q = _searchCtrl.text.trim();
    if (q.length < 2) {
      if (!silent) showSnack(context, 'Type at least 2 characters', isError: true);
      return;
    }
    setState(() => _searching = true);
    try {
      final list = await widget.api.searchCustomers(q);
      if (mounted) setState(() => _results = list);
    } on ApiException catch (e) {
      if (mounted && !silent) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _searching = false);
    }
  }

  Future<void> _submit() async {
    if (_selected == null || _subjectCtrl.text.trim().isEmpty || _descCtrl.text.trim().isEmpty) {
      showSnack(context, 'Select customer and fill subject/description', isError: true);
      return;
    }
    final customerId = _selected!['id'];
    if (customerId is! num) {
      showSnack(context, 'Invalid customer — search again', isError: true);
      return;
    }
    setState(() => _saving = true);
    try {
      await widget.api.staffCreateTicket(
        customerId: customerId.toInt(),
        subject: _subjectCtrl.text.trim(),
        description: _descCtrl.text.trim(),
        department: _department,
        priority: _priority,
      );
      if (mounted) {
        showSnack(context, 'Ticket created');
        Navigator.pop(context, true);
      }
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchCtrl.removeListener(_onSearchChanged);
    _searchCtrl.dispose();
    _subjectCtrl.dispose();
    _descCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'Create ticket',
      useGradientBody: true,
      body: SingleChildScrollView(
        padding: pagePadding(context),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            FormFieldCard(
              title: 'Find customer',
              subtitle: 'Name, phone, or customer code — same search as Bill Collection',
              children: [
                TextField(
                  controller: _searchCtrl,
                  decoration: InputDecoration(
                    labelText: 'Search customer',
                    prefixIcon: const Icon(Icons.search),
                    suffixIcon: _searching
                        ? const Padding(
                            padding: EdgeInsets.all(12),
                            child: SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)),
                          )
                        : (_searchCtrl.text.isNotEmpty
                            ? IconButton(icon: const Icon(Icons.clear), onPressed: () => _searchCtrl.clear())
                            : null),
                  ),
                  onSubmitted: (_) => _search(),
                ),
              ],
            ),
            if (_selected != null)
              SelectedCustomerChip(
                name: _selected!['name']?.toString() ?? '',
                code: _selected!['customer_code']?.toString() ?? '',
                onClear: () => setState(() => _selected = null),
              ),
            ..._results.take(8).map((c) {
              final id = (c['id'] as num).toInt();
              final selected = _selected != null && (_selected!['id'] as num).toInt() == id;
              return CustomerSearchResultTile(
                customer: c,
                showDue: true,
                selected: selected,
                onTap: () => setState(() {
                  _selected = c;
                  _results = [];
                  _searchCtrl.clear();
                }),
              );
            }),
            FormFieldCard(
              title: 'Ticket details',
              children: [
                TextField(
                  controller: _subjectCtrl,
                  decoration: const InputDecoration(labelText: 'Subject'),
                  textCapitalization: TextCapitalization.sentences,
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _descCtrl,
                  decoration: const InputDecoration(
                    labelText: 'Description',
                    alignLabelWithHint: true,
                  ),
                  maxLines: 4,
                  textCapitalization: TextCapitalization.sentences,
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  value: _department,
                  decoration: const InputDecoration(labelText: 'Department'),
                  items: const [
                    DropdownMenuItem(value: 'billing', child: Text('Billing')),
                    DropdownMenuItem(value: 'technical_support', child: Text('Technical')),
                    DropdownMenuItem(value: 'field_engineer', child: Text('Field engineer')),
                    DropdownMenuItem(value: 'network', child: Text('Network')),
                  ],
                  onChanged: (v) => setState(() => _department = v ?? _department),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  value: _priority,
                  decoration: const InputDecoration(labelText: 'Priority'),
                  items: const [
                    DropdownMenuItem(value: 'low', child: Text('Low')),
                    DropdownMenuItem(value: 'medium', child: Text('Medium')),
                    DropdownMenuItem(value: 'high', child: Text('High')),
                    DropdownMenuItem(value: 'critical', child: Text('Critical')),
                  ],
                  onChanged: (v) => setState(() => _priority = v ?? _priority),
                ),
              ],
            ),
            const SizedBox(height: 8),
            SizedBox(
              height: 50,
              child: FilledButton(
                onPressed: _saving ? null : _submit,
                child: _saving
                    ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Text('Create ticket', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
