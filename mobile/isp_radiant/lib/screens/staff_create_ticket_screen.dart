import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../utils/app_nav.dart';
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

  Future<void> _search() async {
    final q = _searchCtrl.text.trim();
    if (q.length < 2) return;
    final list = await widget.api.searchCustomers(q);
    if (mounted) setState(() => _results = list);
  }

  Future<void> _submit() async {
    if (_selected == null || _subjectCtrl.text.trim().isEmpty || _descCtrl.text.trim().isEmpty) {
      showSnack(context, 'Select customer and fill subject/description', isError: true);
      return;
    }
    setState(() => _saving = true);
    try {
      await widget.api.staffCreateTicket(
        customerId: (_selected!['id'] as num).toInt(),
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
    _searchCtrl.dispose();
    _subjectCtrl.dispose();
    _descCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'Create ticket',
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextField(
              controller: _searchCtrl,
              decoration: const InputDecoration(labelText: 'Find customer', prefixIcon: Icon(Icons.search), border: OutlineInputBorder()),
              onSubmitted: (_) => _search(),
            ),
            TextButton(onPressed: _search, child: const Text('Search')),
            if (_selected != null)
              Chip(
                label: Text('${_selected!['name']} · ${_selected!['customer_code']}'),
                onDeleted: () => setState(() => _selected = null),
              ),
            ..._results.take(5).map((c) => ListTile(
                  title: Text(c['name']?.toString() ?? ''),
                  subtitle: Text(c['customer_code']?.toString() ?? ''),
                  onTap: () => setState(() {
                    _selected = c;
                    _results = [];
                  }),
                )),
            const SizedBox(height: 12),
            TextField(controller: _subjectCtrl, decoration: const InputDecoration(labelText: 'Subject', border: OutlineInputBorder())),
            const SizedBox(height: 12),
            TextField(controller: _descCtrl, decoration: const InputDecoration(labelText: 'Description', border: OutlineInputBorder()), maxLines: 4),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              value: _department,
              decoration: const InputDecoration(labelText: 'Department', border: OutlineInputBorder()),
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
              decoration: const InputDecoration(labelText: 'Priority', border: OutlineInputBorder()),
              items: const [
                DropdownMenuItem(value: 'low', child: Text('Low')),
                DropdownMenuItem(value: 'medium', child: Text('Medium')),
                DropdownMenuItem(value: 'high', child: Text('High')),
                DropdownMenuItem(value: 'critical', child: Text('Critical')),
              ],
              onChanged: (v) => setState(() => _priority = v ?? _priority),
            ),
            const SizedBox(height: 20),
            FilledButton(onPressed: _saving ? null : _submit, child: _saving ? const CircularProgressIndicator() : const Text('Create ticket')),
          ],
        ),
      ),
    );
  }
}
