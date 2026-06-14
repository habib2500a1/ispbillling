import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../config/remote_config.dart';
import '../design_system/components/radiant_screen_header.dart';
import '../design_system/radiant_tokens.dart';
import '../core/theme/design_tokens.dart';
import '../services/api_service.dart';
import '../services/offline_sync_service.dart';
import '../utils/app_nav.dart';
import '../widgets/payment_success_sheet.dart';

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
  List<Map<String, dynamic>> _collectors = [];
  String _method = 'cash';
  String _preset = 'none';
  Map<String, dynamic>? _opts;
  int? _collectorId;
  bool _canPickCollector = false;
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
    _receivedCtrl.addListener(() {
      if (mounted) setState(() {});
    });
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
        _collectors = _listFrom(opts['collectors']);
        _canPickCollector = opts['can_pick_collector'] == true;
        _collectorId = (opts['default_collector_id'] as num?)?.toInt();
        if (_collectorId == null && _collectors.length == 1) {
          _collectorId = (_collectors.first['id'] as num?)?.toInt();
        }
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

  bool _shouldQueueOffline(ApiException e) {
    if (e.statusCode == null || e.statusCode! >= 500) return true;
    final msg = e.message.toLowerCase();
    return msg.contains('network') ||
        msg.contains('connection') ||
        msg.contains('timeout') ||
        msg.contains('internet');
  }

  List<Map<String, dynamic>> _listFrom(dynamic raw) {
    if (raw is! List) return [];
    return raw.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
  }

  bool get _canSubmit {
    final amount = double.tryParse(_receivedCtrl.text.trim()) ?? 0;
    if (amount <= 0) return false;
    if (_collectorId == null || _collectorId! < 1) return false;
    return true;
  }

  Future<void> _submit() async {
    final amount = double.tryParse(_receivedCtrl.text.trim()) ?? 0;
    if (amount <= 0) {
      showSnack(context, 'Enter received amount', isError: true);
      return;
    }
    if (_collectorId == null || _collectorId! < 1) {
      showSnack(context, 'Select who received this payment', isError: true);
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
        collectorUserId: _collectorId,
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
      if (RemoteConfig.offlineSync && _shouldQueueOffline(e)) {
        final offline = OfflineSyncService(widget.api);
        await offline.enqueueCollection(
          customerId: (widget.customer['id'] as num).toInt(),
          amount: amount,
          invoiceId: _invoiceId,
          method: _method,
          reference: _receiptCtrl.text.trim().isNotEmpty ? _receiptCtrl.text.trim() : null,
          notes: _buildNotes(),
          collectorUserId: _collectorId,
        );
        if (mounted) {
          showSnack(context, 'Offline — payment queued for sync');
          Navigator.pop(context, true);
        }
      } else if (mounted) {
        showSnack(context, e.message, isError: true);
      }
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
      backgroundColor: context.isDark ? RadiantTokens.darkBg : const Color(0xFFF0F4F8),
      appBar: AppBar(
        backgroundColor: RadiantTokens.brand,
        foregroundColor: Colors.white,
        elevation: 0,
        centerTitle: true,
        title: const Text('Receive Bill'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _clientCard(c, monthly),
                  const SizedBox(height: 12),
                  _receivedByField(),
                  const SizedBox(height: 12),
                  RadiantFormSection(
                    title: 'Payment method',
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
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
                              _methodPill('other', 'Other'),
                              _methodPill('cash', 'Cash'),
                              _methodPill('bkash', 'bKash'),
                              _methodPill('bank', 'Bank'),
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
                            backgroundColor: RadiantTokens.danger,
                            minimumSize: const Size.fromHeight(48),
                          ),
                          child: const Text('Cancel'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: FilledButton(
                          onPressed: (_saving || !_canSubmit) ? null : _submit,
                          style: FilledButton.styleFrom(
                            backgroundColor: _canSubmit ? RadiantTokens.brand : Colors.grey.shade400,
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

  Widget _receivedByField() {
    final collectors = _collectors;
    final selected = _collectorId;
    String? label;
    for (final c in collectors) {
      if ((c['id'] as num?)?.toInt() == selected) {
        label = c['name']?.toString() ?? c['label']?.toString();
        break;
      }
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Received By', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
        const SizedBox(height: 8),
        InputDecorator(
          decoration: InputDecoration(
            filled: true,
            fillColor: Colors.white,
            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(24)),
            enabledBorder: OutlineInputBorder(
              borderRadius: BorderRadius.circular(24),
              borderSide: BorderSide(color: Colors.grey.shade300),
            ),
          ),
          child: collectors.isEmpty
              ? Text(label ?? '—', style: const TextStyle(fontWeight: FontWeight.w600))
              : DropdownButtonHideUnderline(
                  child: DropdownButton<int>(
                    isExpanded: true,
                    value: collectors.any((c) => (c['id'] as num?)?.toInt() == selected) ? selected : null,
                    hint: const Text('Select staff'),
                    items: [
                      for (final c in collectors)
                        DropdownMenuItem<int>(
                          value: (c['id'] as num).toInt(),
                          child: Text(
                            c['name']?.toString() ?? c['label']?.toString() ?? 'Staff',
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                        ),
                    ],
                    onChanged: (_canPickCollector && collectors.length > 1)
                        ? (v) => setState(() => _collectorId = v)
                        : null,
                  ),
                ),
        ),
      ],
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
                child: _infoCol(Icons.location_on, 'Zone', c['zone']?.toString() ?? '—', valueColor: RadiantTokens.accentCyan),
              ),
              Expanded(child: _infoCol(Icons.phone, 'Mobile', c['phone']?.toString() ?? '—', valueColor: RadiantTokens.accentCyan)),
            ],
          ),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(child: _infoCol(Icons.inventory_2, 'Package', pkg, valueColor: RadiantTokens.accentCyan)),
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
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color ?? const Color(0xFFE8EEF4),
        borderRadius: BorderRadius.circular(12),
      ),
      child: child,
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
              Icon(icon, size: 18, color: RadiantTokens.brand),
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
              Expanded(child: _infoCol(Icons.schedule, 'Balance due', _fmt.format(payable), valueColor: RadiantTokens.danger)),
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
    return Material(
      color: selected ? RadiantTokens.brand : Colors.white,
      borderRadius: BorderRadius.circular(20),
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: () => setState(() => _method = code),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: selected ? RadiantTokens.brand : Colors.grey.shade300),
          ),
          child: Text(
            label,
            style: TextStyle(
              color: selected ? Colors.white : RadiantTokens.brand,
              fontWeight: FontWeight.w600,
              fontSize: 12,
            ),
          ),
        ),
      ),
    );
  }
}
