import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import 'barcode_scan_screen.dart';

/// Bottom sheet: add catalog hardware line to an open customer invoice.
class StaffInvoiceHardwareSheet extends StatefulWidget {
  const StaffInvoiceHardwareSheet({
    super.key,
    required this.api,
    required this.invoiceId,
    required this.invoiceNumber,
    required this.onDone,
  });

  final ApiService api;
  final int invoiceId;
  final String invoiceNumber;
  final VoidCallback onDone;

  static Future<void> show(
    BuildContext context, {
    required ApiService api,
    required int invoiceId,
    required String invoiceNumber,
    required VoidCallback onDone,
  }) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(ctx).bottom),
        child: StaffInvoiceHardwareSheet(
          api: api,
          invoiceId: invoiceId,
          invoiceNumber: invoiceNumber,
          onDone: onDone,
        ),
      ),
    );
  }

  @override
  State<StaffInvoiceHardwareSheet> createState() => _StaffInvoiceHardwareSheetState();
}

class _StaffInvoiceHardwareSheetState extends State<StaffInvoiceHardwareSheet> {
  final _barcodeCtrl = TextEditingController();
  final _qtyCtrl = TextEditingController(text: '1');
  final _priceCtrl = TextEditingController();
  final _fmt = NumberFormat('#,##0.00');

  Map<String, dynamic>? _options;
  Map<String, dynamic>? _selected;
  int? _warehouseId;
  bool _issueStock = true;
  bool _loading = true;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _barcodeCtrl.dispose();
    _qtyCtrl.dispose();
    _priceCtrl.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final opts = await widget.api.staffInvoiceHardwareOptions(widget.invoiceId);
      if (mounted) {
        setState(() {
          _options = opts;
          _warehouseId = opts['default_warehouse_id'] as int?;
          _loading = false;
        });
      }
    } on ApiException catch (e) {
      if (mounted) {
        showSnack(context, e.message, isError: true);
        Navigator.pop(context);
      }
    }
  }

  Future<void> _scanBarcode() async {
    final code = await Navigator.push<String>(
      context,
      MaterialPageRoute(builder: (_) => const BarcodeScanScreen()),
    );
    if (code == null || !mounted) return;
    _barcodeCtrl.text = code;
    await _lookupBarcode(code);
  }

  Future<void> _lookupBarcode(String code) async {
    setState(() => _busy = true);
    try {
      final res = await widget.api.staffInvoiceHardwareLookup(
        widget.invoiceId,
        barcode: code,
        warehouseId: _warehouseId,
      );
      final data = res['data'] as Map<String, dynamic>?;
      if (data == null) {
        if (mounted) showSnack(context, 'Product not found', isError: true);
        return;
      }
      if (mounted) {
        setState(() {
          _selected = data;
          _priceCtrl.text = '${data['sell_price'] ?? ''}';
        });
      }
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _pickProduct(Map<String, dynamic> p) {
    setState(() {
      _selected = p;
      _priceCtrl.text = '${p['sell_price'] ?? ''}';
    });
  }

  Future<void> _submit() async {
    final productId = (_selected?['id'] as num?)?.toInt();
    if (productId == null) {
      showSnack(context, 'Select or scan a product', isError: true);
      return;
    }
    setState(() => _busy = true);
    try {
      await widget.api.staffInvoiceAddHardwareLine(
        widget.invoiceId,
        productId: productId,
        quantity: int.tryParse(_qtyCtrl.text.trim()) ?? 1,
        unitPrice: double.tryParse(_priceCtrl.text.trim()),
        warehouseId: _warehouseId,
        issueStock: _issueStock,
      );
      if (!mounted) return;
      showSnack(context, 'Hardware added to ${widget.invoiceNumber}');
      widget.onDone();
      Navigator.pop(context);
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const SizedBox(height: 200, child: Center(child: CircularProgressIndicator()));
    }

    final warehouses = (_options?['warehouses'] as List<dynamic>?) ?? [];
    final products = (_options?['products'] as List<dynamic>?) ?? [];

    return SizedBox(
      height: MediaQuery.sizeOf(context).height * 0.88,
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    'Add hardware · ${widget.invoiceNumber}',
                    style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold),
                  ),
                ),
                IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(context)),
              ],
            ),
          ),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              children: [
                  DropdownButtonFormField<int>(
                    value: _warehouseId,
                    decoration: const InputDecoration(labelText: 'Warehouse', border: OutlineInputBorder()),
                    items: warehouses
                        .map((w) => DropdownMenuItem<int>(
                              value: (w['id'] as num).toInt(),
                              child: Text(w['label']?.toString() ?? ''),
                            ))
                        .toList(),
                    onChanged: (v) => setState(() => _warehouseId = v),
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          controller: _barcodeCtrl,
                          decoration: const InputDecoration(
                            labelText: 'Barcode / SKU',
                            border: OutlineInputBorder(),
                          ),
                          onSubmitted: _lookupBarcode,
                        ),
                      ),
                      const SizedBox(width: 8),
                      IconButton.filled(
                        onPressed: _busy ? null : _scanBarcode,
                        icon: const Icon(Icons.qr_code_scanner),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _qtyCtrl,
                    decoration: const InputDecoration(labelText: 'Quantity', border: OutlineInputBorder()),
                    keyboardType: TextInputType.number,
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _priceCtrl,
                    decoration: const InputDecoration(labelText: 'Unit price (BDT)', border: OutlineInputBorder()),
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  ),
                  SwitchListTile(
                    value: _issueStock,
                    onChanged: (v) => setState(() => _issueStock = v),
                    title: const Text('Deduct stock from warehouse'),
                  ),
                  if (_selected != null)
                    Card(
                      color: AppTheme.primary.withValues(alpha: 0.08),
                      child: ListTile(
                        title: Text(_selected!['name']?.toString() ?? ''),
                        subtitle: Text(
                          'WH stock ${_selected!['stock_at_warehouse']} · ${_fmt.format(_selected!['sell_price'] ?? 0)} BDT',
                        ),
                      ),
                    ),
                  const Text('Products', style: TextStyle(fontWeight: FontWeight.w600)),
                  ...products.take(30).map((p) {
                    final m = Map<String, dynamic>.from(p as Map);
                    return ListTile(
                      dense: true,
                      title: Text(m['name']?.toString() ?? ''),
                      subtitle: Text('Stock ${m['stock_at_warehouse']} · ${_fmt.format(m['sell_price'] ?? 0)}'),
                      onTap: () => _pickProduct(m),
                    );
                  }),
                const SizedBox(height: 80),
              ],
            ),
          ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: FilledButton(
                onPressed: _busy ? null : _submit,
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(48),
                  backgroundColor: AppTheme.primary,
                ),
                child: _busy
                    ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Text('Add to invoice'),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
