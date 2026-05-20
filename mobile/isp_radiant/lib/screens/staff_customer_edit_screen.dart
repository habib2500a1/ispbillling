import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../support/customer_status.dart';
import '../utils/app_nav.dart';
import '../widgets/page_scaffold.dart';

class StaffCustomerEditScreen extends StatefulWidget {
  const StaffCustomerEditScreen({super.key, required this.api, required this.customer});

  final ApiService api;
  final Map<String, dynamic> customer;

  @override
  State<StaffCustomerEditScreen> createState() => _StaffCustomerEditScreenState();
}

class _StaffCustomerEditScreenState extends State<StaffCustomerEditScreen> {
  late final TextEditingController _nameCtrl = TextEditingController(text: widget.customer['name']?.toString());
  late final TextEditingController _phoneCtrl = TextEditingController(text: widget.customer['phone']?.toString());
  late final TextEditingController _emailCtrl = TextEditingController(text: widget.customer['email']?.toString());
  late final TextEditingController _addressCtrl = TextEditingController(text: widget.customer['address']?.toString());
  late final TextEditingController _notesCtrl = TextEditingController(text: widget.customer['notes']?.toString());
  late String _status = widget.customer['status']?.toString() ?? 'active';
  bool _saving = false;

  @override
  void dispose() {
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    _emailCtrl.dispose();
    _addressCtrl.dispose();
    _notesCtrl.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await widget.api.staffUpdateCustomer((widget.customer['id'] as num).toInt(), {
        'name': _nameCtrl.text.trim(),
        'phone': _phoneCtrl.text.trim(),
        'email': _emailCtrl.text.trim().isEmpty ? null : _emailCtrl.text.trim(),
        'address': _addressCtrl.text.trim().isEmpty ? null : _addressCtrl.text.trim(),
        'status': _status,
        'notes': _notesCtrl.text.trim().isEmpty ? null : _notesCtrl.text.trim(),
      });
      if (mounted) {
        showSnack(context, 'Customer updated');
        Navigator.pop(context, true);
      }
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'Edit customer',
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            TextField(controller: _nameCtrl, decoration: const InputDecoration(labelText: 'Name', border: OutlineInputBorder())),
            const SizedBox(height: 12),
            TextField(controller: _phoneCtrl, decoration: const InputDecoration(labelText: 'Phone', border: OutlineInputBorder())),
            const SizedBox(height: 12),
            TextField(controller: _emailCtrl, decoration: const InputDecoration(labelText: 'Email', border: OutlineInputBorder())),
            const SizedBox(height: 12),
            TextField(controller: _addressCtrl, decoration: const InputDecoration(labelText: 'Address', border: OutlineInputBorder()), maxLines: 2),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              value: _status,
              decoration: const InputDecoration(labelText: 'Status', border: OutlineInputBorder()),
              items: CustomerStatus.options.entries
                  .map((e) => DropdownMenuItem(value: e.key, child: Text(e.value)))
                  .toList(),
              onChanged: (v) => setState(() => _status = v ?? _status),
            ),
            const SizedBox(height: 12),
            TextField(controller: _notesCtrl, decoration: const InputDecoration(labelText: 'Notes', border: OutlineInputBorder()), maxLines: 3),
            const SizedBox(height: 24),
            FilledButton(
              onPressed: _saving ? null : _save,
              child: _saving ? const CircularProgressIndicator() : const Text('Save changes'),
            ),
          ],
        ),
      ),
    );
  }
}
