import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import 'payment_success_sheet.dart';

/// Bill collection with discount presets and sticky bottom note.
class CollectionPaymentPanel extends StatefulWidget {
  const CollectionPaymentPanel({
    super.key,
    required this.api,
    required this.customerId,
    required this.balanceDue,
    this.invoiceId,
    this.invoiceNumber,
    this.onSuccess,
  });

  final ApiService api;
  final int customerId;
  final double balanceDue;
  final int? invoiceId;
  final String? invoiceNumber;
  final VoidCallback? onSuccess;

  @override
  State<CollectionPaymentPanel> createState() => _CollectionPaymentPanelState();
}

class _CollectionPaymentPanelState extends State<CollectionPaymentPanel> {
  final _amountCtrl = TextEditingController();
  final _noteCtrl = TextEditingController();
  final _customDiscountCtrl = TextEditingController();
  final _fmt = NumberFormat('#,##0.00');

  Map<String, dynamic>? _opts;
  String _preset = 'none';
  bool _loadingOpts = true;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    if (widget.balanceDue > 0) {
      _amountCtrl.text = widget.balanceDue.toStringAsFixed(2);
    }
    _loadOpts();
    _amountCtrl.addListener(_onFieldsChanged);
    _noteCtrl.addListener(_onFieldsChanged);
    _customDiscountCtrl.addListener(_onFieldsChanged);
  }

  @override
  void dispose() {
    _amountCtrl.dispose();
    _noteCtrl.dispose();
    _customDiscountCtrl.dispose();
    super.dispose();
  }

  void _onFieldsChanged() => setState(() {});

  Future<void> _loadOpts() async {
    try {
      final data = await widget.api.staffCollectionOptions();
      if (!mounted) return;
      setState(() {
        _opts = data;
        _loadingOpts = false;
      });
    } catch (_) {
      if (mounted) setState(() => _loadingOpts = false);
    }
  }

  double get _amount => double.tryParse(_amountCtrl.text.trim()) ?? 0;

  double get _discountPreview {
    if (_opts == null || _opts!['enabled'] != true) return 0;
    // Server validates; client shows rough preset hint only
    final presets = (_opts!['presets'] as List<dynamic>?) ?? [];
    if (_preset == 'none') {
      final custom = double.tryParse(_customDiscountCtrl.text.trim()) ?? 0;
      return custom;
    }
    for (final p in presets) {
      final m = Map<String, dynamic>.from(p as Map);
      if (m['id']?.toString() == _preset) {
        final type = m['type']?.toString() ?? 'fixed';
        final amt = (m['amount'] as num?)?.toDouble() ?? 0;
        if (type == 'percent') return widget.balanceDue * amt / 100;
        return amt;
      }
    }
    return 0;
  }

  bool get _isAdvance => widget.balanceDue > 0 && _amount > widget.balanceDue + 0.001;

  bool get _noteRequired {
    if (_isAdvance) return false;
    if (_opts == null) return false;
    final partial = _amount + _discountPreview + 0.001 < widget.balanceDue;
    if (partial && _opts!['require_note_on_partial'] == true) return true;
    if (_discountPreview > 0 && _opts!['require_note_on_discount'] == true) return true;
    return false;
  }

  Future<void> _submit() async {
    if (_saving) return;

    if (_amount <= 0 && _discountPreview <= 0) {
      showSnack(context, 'Enter amount or discount', isError: true);
      return;
    }
    if (_noteRequired && _noteCtrl.text.trim().length < 3) {
      showSnack(context, 'Note required at bottom', isError: true);
      return;
    }
    setState(() => _saving = true);
    try {
      final res = await widget.api.recordCollection(
        customerId: widget.customerId,
        amount: _amount,
        invoiceId: widget.invoiceId,
        notes: _noteCtrl.text.trim().isNotEmpty ? _noteCtrl.text.trim() : null,
        discountPreset: _preset,
        discountCustom: _customDiscountCtrl.text.trim().isNotEmpty ? double.tryParse(_customDiscountCtrl.text.trim()) : null,
      );
      if (!mounted) return;
      final payment = res['payment'];
      if (payment is Map) {
        await PaymentSuccessSheet.show(
          context,
          api: widget.api,
          message: res['message']?.toString() ?? 'Collected',
          payment: Map<String, dynamic>.from(payment),
        );
      } else {
        showSnack(context, res['message']?.toString() ?? 'Collected');
      }
      widget.onSuccess?.call();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final remaining = (widget.balanceDue - _amount - _discountPreview).clamp(0.0, double.infinity);

    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            children: [
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(14),
                  gradient: const LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [AppTheme.primary, Color(0xFF5B7FC4), AppTheme.accent],
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: AppTheme.primary.withValues(alpha: 0.25),
                      blurRadius: 8,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Row(
                  children: [
                    const Icon(Icons.payments, color: Colors.white, size: 28),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            widget.invoiceNumber != null ? 'Invoice ${widget.invoiceNumber}' : 'Record collection',
                            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15),
                          ),
                          Text(
                            'Due ${_fmt.format(widget.balanceDue)} BDT',
                            style: const TextStyle(color: Colors.white70, fontSize: 13),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              TextField(
                controller: _amountCtrl,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: InputDecoration(
                  labelText: 'Cash amount (BDT)',
                  prefixIcon: const Icon(Icons.attach_money, color: AppTheme.success),
                  border: const OutlineInputBorder(),
                  filled: true,
                  fillColor: AppTheme.success.withValues(alpha: 0.06),
                ),
              ),
              if (_loadingOpts)
                const Padding(padding: EdgeInsets.all(12), child: LinearProgressIndicator())
              else if (_opts?['enabled'] == true) ...[
                const SizedBox(height: 12),
                const Text('Discount', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final p in (_opts!['presets'] as List<dynamic>? ?? []))
                      _presetChip(Map<String, dynamic>.from(p as Map)),
                  ],
                ),
                if (_opts?['allow_custom'] == true) ...[
                  const SizedBox(height: 10),
                  TextField(
                    controller: _customDiscountCtrl,
                    enabled: _preset == 'none',
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    decoration: InputDecoration(
                      labelText: 'Custom discount (BDT)',
                      prefixIcon: const Icon(Icons.percent, color: Colors.deepOrange),
                      border: const OutlineInputBorder(),
                    ),
                  ),
                ],
                if (_opts?['max_discount_bdt'] != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 6),
                    child: Text(
                      'Your max: ${_fmt.format(_opts!['max_discount_bdt'])} BDT'
                      '${_opts?['max_discount_percent_of_due'] != null ? ' · ${_opts!['max_discount_percent_of_due']}% of due' : ''}',
                      style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
                    ),
                  ),
              ],
              if (_discountPreview > 0 || _amount > 0) ...[
                const SizedBox(height: 12),
                Card(
                  color: AppTheme.info.withValues(alpha: 0.08),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Text(
                      'After pay: remaining ~${_fmt.format(remaining)} BDT'
                      '${_discountPreview > 0 ? ' · Discount ~${_fmt.format(_discountPreview)}' : ''}',
                      style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 80),
            ],
          ),
        ),
        Material(
          elevation: 12,
          color: _noteRequired ? AppTheme.warning.withValues(alpha: 0.08) : Colors.white,
          child: SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    children: [
                      Icon(Icons.edit_note, color: _noteRequired ? AppTheme.warning : AppTheme.primary, size: 22),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          _noteRequired ? 'Note required *' : 'Collection note',
                          style: TextStyle(
                            fontWeight: FontWeight.w600,
                            color: _noteRequired ? AppTheme.warning : AppTheme.primary,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Text(
                    _isAdvance
                        ? 'Advance (অগ্রিম) — note optional'
                        : (_opts?['note_hint']?.toString() ?? 'Partial or discount reason'),
                    style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _noteCtrl,
                    maxLines: 2,
                    textInputAction: TextInputAction.done,
                    decoration: InputDecoration(
                      hintText: 'যেমন: ৫০০ এর মধ্যে ২০০ নিলাম, বাকি ১৫ তারিখে',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                      filled: true,
                    ),
                  ),
                  const SizedBox(height: 10),
                  FilledButton.icon(
                    onPressed: _saving ? null : _submit,
                    icon: _saving
                        ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Icon(Icons.savings),
                    label: Text(_saving ? 'Saving…' : 'Record collection'),
                    style: FilledButton.styleFrom(
                      backgroundColor: AppTheme.success,
                      minimumSize: const Size.fromHeight(50),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _presetChip(Map<String, dynamic> p) {
    final id = p['id']?.toString() ?? '';
    final selected = _preset == id;
    return FilterChip(
      label: Text(p['label']?.toString() ?? id, style: const TextStyle(fontSize: 12)),
      selected: selected,
      onSelected: (_) => setState(() {
        _preset = id;
        if (id != 'none') _customDiscountCtrl.clear();
      }),
      selectedColor: Colors.deepOrange.withValues(alpha: 0.35),
      checkmarkColor: Colors.deepOrange.shade900,
    );
  }
}
