import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../widgets/payment_success_sheet.dart';
import '../widgets/staff_blue_app_bar.dart';

/// Reference "Receive Bill" screen — client card, payment method, summary grid, submit.
class StaffReceiveBillScreen extends StatefulWidget {
  const StaffReceiveBillScreen({
    super.key,
    required this.api,
    required this.customer,
    this.invoice,
  });

  final ApiService api;
  final Map<String, dynamic> customer;
  final Map<String, dynamic>? invoice;

  @override
  State<StaffReceiveBillScreen> createState() => _StaffReceiveBillScreenState();
}

class _StaffReceiveBillScreenState extends State<StaffReceiveBillScreen> {
  final _receivedCtrl = TextEditingController();
  final _discountCtrl = TextEditingController();
  final _vatCtrl = TextEditingController(text: '0.00');
  final _receiptCtrl = TextEditingController();
  final _noteCtrl = TextEditingController();
  final _fmt = NumberFormat('#,##0.00');

  List<Map<String, dynamic>> _methods = [];
  String _method = 'cash';
  String _preset = 'none';
  Map<String, dynamic>? _opts;
  bool _sendSms = true;
  bool _nextBilling = false;
  bool _loading = true;
  bool _saving = false;

  double get _payable =>
      widget.invoice != null
          ? ((widget.invoice!['balance_due'] as num?)?.toDouble() ?? 0)
          : ((widget.customer['balance_due'] as num?)?.toDouble() ?? 0);

  int? get _invoiceId => (widget.invoice?['id'] as num?)?.toInt();

  @override
  void initState() {
    super.initState();
    if (_payable > 0) _receivedCtrl.text = _payable.toStringAsFixed(2);
    _load();
  }

  @override
  void dispose() {
    _receivedCtrl.dispose();
    _discountCtrl.dispose();
    _vatCtrl.dispose();
    _receiptCtrl.dispose();
    _noteCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final methods = await widget.api.staffPaymentMethods();
      final opts = await widget.api.staffCollectionOptions();
      if (!mounted) return;
      setState(() {
        _methods = methods;
        if (_methods.any((m) => m['code'] == 'cash')) _method = 'cash';
        _opts = opts;
        _loading = false;
      });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _setQuickAmount(double v) {
    _receivedCtrl.text = v.toStringAsFixed(2);
    setState(() {});
  }

  String? _buildNotes() {
    final parts = <String>[];
    if (_noteCtrl.text.trim().isNotEmpty) parts.add(_noteCtrl.text.trim());
    if (_sendSms) parts.add('[send_sms]');
    if (_nextBilling) parts.add('[next_billing]');
    final vat = double.tryParse(_vatCtrl.text.trim());
    if (vat != null && vat > 0) parts.add('vat:$vat');
    return parts.isEmpty ? null : parts.join(' | ');
  }

  Future<void> _submit() async {
    final amount = double.tryParse(_receivedCtrl.text.trim()) ?? 0;
    if (amount <= 0) {
      showSnack(context, 'Enter received amount', isError: true);
      return;
    }
    setState(() => _saving = true);
    try {
      final id = (widget.customer['id'] as num).toInt();
      final res = await widget.api.recordCollection(
        customerId: id,
        amount: amount,
        invoiceId: _invoiceId,
        method: _method,
        reference: _receiptCtrl.text.trim().isNotEmpty ? _receiptCtrl.text.trim() : null,
        notes: _buildNotes(),
        discountPreset: _preset,
        discountCustom: double.tryParse(_discountCtrl.text.trim()),
      );
      if (!mounted) return;
      final payment = res['payment'];
      if (payment is Map) {
        await PaymentSuccessSheet.show(
          context,
          api: widget.api,
          message: res['message']?.toString() ?? 'Payment recorded',
          payment: Map<String, dynamic>.from(payment),
          customerDue: ((res['customer'] as Map?)?['balance_due'] as num?)?.toDouble(),
        );
      } else {
        showSnack(context, res['message']?.toString() ?? 'Saved');
      }
      if (mounted) Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = widget.customer;
    final monthly = (c['monthly_bill'] as num?)?.toDouble();
    final gross = _payable;

    return Scaffold(
      appBar: const StaffBlueAppBar(title: 'Receive Bill'),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _clientCard(c, monthly),
                  const SizedBox(height: 12),
                  _sectionCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Payment method', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                        const SizedBox(height: 10),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            for (final m in _methods)
                              _methodPill(
                                m['code']?.toString() ?? '',
                                m['label']?.toString() ?? m['code']?.toString() ?? '',
                              ),
                            if (_methods.isEmpty) ...[
                              _methodPill('cash', 'Cash'),
                              _methodPill('bank', 'Bank transfer'),
                              _methodPill('bkash', 'bKash'),
                              _methodPill('nagad', 'Nagad'),
                              _methodPill('rocket', 'Rocket'),
                            ],
                          ],
                        ),
                        const SizedBox(height: 12),
                        CheckboxListTile(
                          contentPadding: EdgeInsets.zero,
                          title: const Text('Is it next billing date?', style: TextStyle(fontSize: 13)),
                          value: _nextBilling,
                          onChanged: (v) => setState(() => _nextBilling = v ?? false),
                          controlAffinity: ListTileControlAffinity.leading,
                        ),
                        CheckboxListTile(
                          contentPadding: EdgeInsets.zero,
                          title: const Text('Send SMS?', style: TextStyle(fontSize: 13)),
                          value: _sendSms,
                          onChanged: (v) => setState(() => _sendSms = v ?? true),
                          controlAffinity: ListTileControlAffinity.leading,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                  _summaryGrid(_payable, gross),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    children: [
                      for (final amt in [_payable, 500.0, 1000.0, 1500.0].where((a) => a > 0).toSet())
                        ActionChip(
                          label: Text('৳${amt.toStringAsFixed(0)}'),
                          onPressed: () => _setQuickAmount(amt),
                        ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          controller: _receivedCtrl,
                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                          decoration: const InputDecoration(
                            labelText: 'Received amount',
                            filled: true,
                            fillColor: Colors.white,
                            border: OutlineInputBorder(),
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: TextField(
                          controller: _discountCtrl,
                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                          decoration: const InputDecoration(
                            labelText: 'Discount',
                            filled: true,
                            fillColor: Colors.white,
                            border: OutlineInputBorder(),
                          ),
                        ),
                      ),
                    ],
                  ),
                  if (_opts?['enabled'] == true) ...[
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      runSpacing: 6,
                      children: [
                        for (final p in (_opts!['presets'] as List<dynamic>? ?? []))
                          FilterChip(
                            label: Text(p['label']?.toString() ?? '', style: const TextStyle(fontSize: 11)),
                            selected: _preset == p['id']?.toString(),
                            onSelected: (_) => setState(() => _preset = p['id']?.toString() ?? 'none'),
                          ),
                      ],
                    ),
                  ],
                  const SizedBox(height: 10),
                  TextField(
                    controller: _vatCtrl,
                    decoration: const InputDecoration(
                      labelText: 'VAT amount',
                      filled: true,
                      fillColor: Colors.white,
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: _receiptCtrl,
                    decoration: const InputDecoration(
                      labelText: 'Money receipt',
                      filled: true,
                      fillColor: Colors.white,
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: _noteCtrl,
                    maxLines: 4,
                    decoration: const InputDecoration(
                      labelText: 'Remark/note',
                      hintText: 'Write here....',
                      filled: true,
                      fillColor: Colors.white,
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 20),
                  Row(
                    children: [
                      Expanded(
                        child: FilledButton(
                          onPressed: _saving ? null : () => Navigator.pop(context),
                          style: FilledButton.styleFrom(
                            backgroundColor: AppTheme.danger,
                            minimumSize: const Size.fromHeight(48),
                          ),
                          child: const Text('Cancel'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: FilledButton(
                          onPressed: _saving ? null : _submit,
                          style: FilledButton.styleFrom(
                            backgroundColor: AppTheme.primary,
                            minimumSize: const Size.fromHeight(48),
                          ),
                          child: _saving
                              ? const SizedBox(
                                  width: 22,
                                  height: 22,
                                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                )
                              : const Text('Submit'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),
                ],
              ),
            ),
    );
  }

  Widget _clientCard(Map<String, dynamic> c, double? monthly) {
    final speed = c['package_speed'];
    final pkg = speed != null ? '${speed}Mbps' : (c['package']?.toString() ?? '—');
    return _sectionCard(
      child: Column(
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(child: _infoCol(Icons.people, 'Client Name', c['name']?.toString() ?? '—')),
              Expanded(child: _infoCol(Icons.person_outline, 'Username', c['username']?.toString() ?? '—')),
            ],
          ),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: _infoCol(Icons.location_on, 'Zone', c['zone']?.toString() ?? '—', valueColor: AppTheme.accent),
              ),
              Expanded(child: _infoCol(Icons.phone, 'Mobile', c['phone']?.toString() ?? '—', valueColor: AppTheme.accent)),
            ],
          ),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(child: _infoCol(Icons.inventory_2, 'Package', pkg, valueColor: AppTheme.accent)),
              Expanded(
                child: _infoCol(
                  Icons.payments,
                  'Monthly Bill',
                  monthly != null ? monthly.toStringAsFixed(1) : '—',
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _sectionCard({required Widget child, Color? color}) {
    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: Theme.of(context).colorScheme.outline),
      ),
      color: color ?? Theme.of(context).colorScheme.surface,
      child: Padding(padding: const EdgeInsets.all(14), child: child),
    );
  }

  Widget _infoCol(IconData icon, String label, String value, {Color? valueColor}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10, right: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 18, color: AppTheme.primary),
              const SizedBox(width: 6),
              Text(label, style: TextStyle(fontSize: 11, color: Colors.grey.shade700)),
            ],
          ),
          const SizedBox(height: 4),
          Text(value, style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13, color: valueColor)),
        ],
      ),
    );
  }

  Widget _summaryGrid(double payable, double gross) {
    return _sectionCard(
      color: Colors.white,
      child: Column(
        children: [
          Row(
            children: [
              Expanded(child: _infoCol(Icons.receipt_long, 'Payable amount', _fmt.format(payable))),
              Expanded(child: _infoCol(Icons.schedule, 'Balance due', _fmt.format(payable), valueColor: AppTheme.danger)),
            ],
          ),
          Row(
            children: [
              Expanded(child: _infoCol(Icons.calculate, 'Applied VAT', '0.00% (0.00)')),
              Expanded(child: _infoCol(Icons.payments, 'Gross amount', _fmt.format(gross))),
            ],
          ),
        ],
      ),
    );
  }

  Widget _methodPill(String code, String label) {
    final selected = _method == code;
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) => setState(() => _method = code),
      selectedColor: AppTheme.primary,
      labelStyle: TextStyle(
        color: selected ? Colors.white : AppTheme.primary,
        fontWeight: FontWeight.w600,
        fontSize: 12,
      ),
    );
  }
}
