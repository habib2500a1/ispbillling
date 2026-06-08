import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/theme/design_tokens.dart';
import '../design_system/components/radiant_glass_card.dart';
import '../design_system/components/radiant_kpi_tile.dart';
import '../design_system/components/radiant_section.dart';
import '../design_system/radiant_tokens.dart';
import '../services/api_service.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
import '../widgets/barcode_scan_screen.dart';
import '../widgets/state_views.dart';

class _CartLine {
  _CartLine({
    required this.productId,
    required this.name,
    required this.unitPrice,
    required this.qty,
    required this.maxStock,
  });

  final int productId;
  final String name;
  final double unitPrice;
  int qty;
  final int maxStock;

  double get lineTotal => unitPrice * qty;
}

class StaffInventoryPosScreen extends StatefulWidget {
  const StaffInventoryPosScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<StaffInventoryPosScreen> createState() => _StaffInventoryPosScreenState();
}

class _StaffInventoryPosScreenState extends State<StaffInventoryPosScreen> {
  final _barcodeCtrl = TextEditingController();
  final _searchCtrl = TextEditingController();
  final _nameCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _fmt = NumberFormat('#,##0.00');

  Map<String, dynamic>? _bootstrap;
  List<Map<String, dynamic>> _searchHits = [];
  final List<_CartLine> _cart = [];
  int? _warehouseId;
  String _payment = 'cash';
  bool _loading = true;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadBootstrap();
  }

  @override
  void dispose() {
    _barcodeCtrl.dispose();
    _searchCtrl.dispose();
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadBootstrap() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await widget.api.staffInventoryBootstrap();
      if (mounted) {
        setState(() {
          _bootstrap = data;
          _warehouseId = data['default_warehouse_id'] as int?;
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not load inventory');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openCameraScan() async {
    final code = await Navigator.push<String>(
      context,
      MaterialPageRoute(builder: (_) => const BarcodeScanScreen()),
    );
    if (code == null || !mounted) return;
    _barcodeCtrl.text = code;
    await _lookupBarcode();
  }

  Future<void> _lookupBarcode() async {
    final code = _barcodeCtrl.text.trim();
    if (code.isEmpty) return;
    await _fetchProducts(barcode: code);
    if (_searchHits.isNotEmpty) {
      _addProduct(_searchHits.first);
      _barcodeCtrl.clear();
    } else if (mounted) {
      showSnack(context, 'No product for: $code', isError: true);
    }
  }

  Future<void> _searchProducts() async {
    final q = _searchCtrl.text.trim();
    if (q.length < 2) {
      setState(() => _searchHits = []);
      return;
    }
    await _fetchProducts(query: q);
  }

  Future<void> _fetchProducts({String? barcode, String? query}) async {
    setState(() => _busy = true);
    try {
      final list = await widget.api.staffInventoryProducts(
        barcode: barcode,
        query: query,
        warehouseId: _warehouseId,
      );
      if (mounted) setState(() => _searchHits = list);
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _addProduct(Map<String, dynamic> p) {
    final id = p['id'] as int;
    final stock = (p['stock_at_warehouse'] as num?)?.toInt() ?? 0;
    if (stock < 1) {
      showSnack(context, 'No stock at this warehouse', isError: true);
      return;
    }
    _CartLine? existing;
    for (final c in _cart) {
      if (c.productId == id) {
        existing = c;
        break;
      }
    }
    if (existing != null) {
      final line = existing;
      if (line.qty >= line.maxStock) {
        showSnack(context, 'Max stock reached', isError: true);
        return;
      }
      setState(() => line.qty++);
      return;
    }
    setState(() {
      _cart.add(_CartLine(
        productId: id,
        name: p['name']?.toString() ?? 'Product',
        unitPrice: (p['sell_price'] as num?)?.toDouble() ?? 0,
        qty: 1,
        maxStock: stock,
      ));
      _searchHits = [];
      _searchCtrl.clear();
    });
  }

  double get _subtotal => _cart.fold(0.0, (s, c) => s + c.lineTotal);

  Future<void> _submit() async {
    if (_warehouseId == null) {
      showSnack(context, 'Select warehouse', isError: true);
      return;
    }
    if (_cart.isEmpty) {
      showSnack(context, 'Cart is empty', isError: true);
      return;
    }
    setState(() => _busy = true);
    try {
      final result = await widget.api.staffInventorySale(
        warehouseId: _warehouseId!,
        paymentMethod: _payment,
        lines: _cart
            .map((c) => {
                  'product_id': c.productId,
                  'quantity': c.qty,
                  'unit_price': c.unitPrice,
                })
            .toList(),
        customerName: _nameCtrl.text.trim().isEmpty ? null : _nameCtrl.text.trim(),
        customerPhone: _phoneCtrl.text.trim().isEmpty ? null : _phoneCtrl.text.trim(),
      );
      if (!mounted) return;
      final note = result['wallet_note']?.toString();
      showSnack(
        context,
        '${result['message']}${note != null ? '\n$note' : ''}',
      );
      setState(() {
        _cart.clear();
        _nameCtrl.clear();
        _phoneCtrl.clear();
      });
      await _loadBootstrap();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  InputDecoration _field(String label, {Widget? suffixIcon}) {
    return InputDecoration(
      labelText: label,
      filled: true,
      fillColor: context.isDark ? RadiantTokens.darkSurface : RadiantTokens.lightSurface,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(RadiantTokens.radiusSm)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
        borderSide: BorderSide(color: context.radiant.border),
      ),
      suffixIcon: suffixIcon,
    );
  }

  @override
  Widget build(BuildContext context) {
    final warehouses = (_bootstrap?['warehouses'] as List<dynamic>?) ?? [];
    final methods = (_bootstrap?['payment_methods'] as List<dynamic>?) ?? [];
    final summary = _bootstrap?['summary'] as Map<String, dynamic>? ?? {};
    final brand = context.radiant;

    return Scaffold(
      backgroundColor: context.isDark ? RadiantTokens.darkBg : RadiantTokens.lightBg,
      appBar: AppBar(
        title: const Text('Retail POS'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _loadBootstrap),
        ],
      ),
      body: _loading
          ? const ListLoading()
          : Column(
              children: [
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.all(8),
                    child: ErrorBanner(message: _error!, onRetry: _loadBootstrap),
                  ),
                Expanded(
                  child: ListView(
                    padding: pagePadding(context),
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: RadiantKpiTile(
                              compact: true,
                              label: 'Stock value',
                              value: '${_fmt.format(summary['stock_value'] ?? 0)} BDT',
                              icon: Icons.inventory_2_outlined,
                              color: RadiantTokens.brand,
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: RadiantKpiTile(
                              compact: true,
                              label: 'Month sales',
                              value: '${_fmt.format(summary['month_sales'] ?? 0)} BDT',
                              icon: Icons.point_of_sale_outlined,
                              color: RadiantTokens.success,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<int>(
                        value: _warehouseId,
                        decoration: _field('Warehouse'),
                        items: warehouses
                            .map((w) => DropdownMenuItem<int>(
                                  value: w['id'] as int,
                                  child: Text(w['label']?.toString() ?? w['name']?.toString() ?? ''),
                                ))
                            .toList(),
                        onChanged: (v) => setState(() => _warehouseId = v),
                      ),
                      const SizedBox(height: 10),
                      TextField(
                        controller: _barcodeCtrl,
                        decoration: _field(
                          'Barcode / SKU scan',
                          suffixIcon: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              IconButton(
                                icon: const Icon(Icons.qr_code_scanner),
                                tooltip: 'Camera scan',
                                onPressed: _busy ? null : _openCameraScan,
                              ),
                              IconButton(
                                icon: const Icon(Icons.search),
                                onPressed: _busy ? null : _lookupBarcode,
                              ),
                            ],
                          ),
                        ),
                        textInputAction: TextInputAction.done,
                        onSubmitted: (_) => _lookupBarcode(),
                      ),
                      const SizedBox(height: 10),
                      TextField(
                        controller: _searchCtrl,
                        decoration: _field(
                          'Search product',
                          suffixIcon: IconButton(
                            icon: const Icon(Icons.search),
                            onPressed: _busy ? null : _searchProducts,
                          ),
                        ),
                        onSubmitted: (_) => _searchProducts(),
                      ),
                      if (_searchHits.isNotEmpty) ...[
                        const SizedBox(height: 10),
                        ..._searchHits.map((p) => Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: RadiantGlassCard(
                                onTap: () => _addProduct(p),
                                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                                child: Row(
                                  children: [
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(p['name']?.toString() ?? '', style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w600)),
                                          Text(
                                            'WH stock ${p['stock_at_warehouse']} · ${_fmt.format(p['sell_price'] ?? 0)} BDT',
                                            style: context.text.bodySmall?.copyWith(color: brand.muted),
                                          ),
                                        ],
                                      ),
                                    ),
                                    const Icon(Icons.add_circle_outline, color: RadiantTokens.brand),
                                  ],
                                ),
                              ),
                            )),
                      ],
                      const SizedBox(height: 14),
                      const RadiantSectionHeader(title: 'Cart'),
                      if (_cart.isEmpty)
                        RadiantGlassCard(
                          child: Text(
                            'Scan or search to add items',
                            textAlign: TextAlign.center,
                            style: context.text.bodyMedium?.copyWith(color: brand.muted),
                          ),
                        )
                      else
                        ..._cart.map((c) => Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: RadiantGlassCard(
                                child: Row(
                                  children: [
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(c.name, style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
                                          Text('${_fmt.format(c.unitPrice)} × ${c.qty}', style: context.text.bodySmall?.copyWith(color: brand.muted)),
                                        ],
                                      ),
                                    ),
                                    IconButton(
                                      icon: const Icon(Icons.remove_circle_outline),
                                      onPressed: () {
                                        setState(() {
                                          if (c.qty > 1) {
                                            c.qty--;
                                          } else {
                                            _cart.remove(c);
                                          }
                                        });
                                      },
                                    ),
                                    Text('${c.qty}', style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
                                    IconButton(
                                      icon: const Icon(Icons.add_circle_outline),
                                      onPressed: c.qty >= c.maxStock ? null : () => setState(() => c.qty++),
                                    ),
                                  ],
                                ),
                              ),
                            )),
                      const SizedBox(height: 10),
                      DropdownButtonFormField<String>(
                        value: _payment,
                        decoration: _field('Payment'),
                        items: methods
                            .map((m) => DropdownMenuItem<String>(
                                  value: m['code']?.toString(),
                                  child: Text(m['label']?.toString() ?? m['code']?.toString() ?? ''),
                                ))
                            .toList(),
                        onChanged: (v) {
                          if (v != null) setState(() => _payment = v);
                        },
                      ),
                      const SizedBox(height: 10),
                      TextField(
                        controller: _nameCtrl,
                        decoration: _field('Customer name (optional)'),
                      ),
                      const SizedBox(height: 10),
                      TextField(
                        controller: _phoneCtrl,
                        decoration: _field('Phone (optional)'),
                        keyboardType: TextInputType.phone,
                      ),
                    ],
                  ),
                ),
                RadiantGlassCard(
                  borderRadius: 0,
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                  child: SafeArea(
                    top: false,
                    child: Row(
                      children: [
                        Expanded(
                          child: Text(
                            'Total ${_fmt.format(_subtotal)} BDT',
                            style: context.text.titleMedium?.copyWith(fontWeight: FontWeight.w800),
                          ),
                        ),
                        FilledButton(
                          onPressed: _busy || _cart.isEmpty ? null : _submit,
                          style: FilledButton.styleFrom(backgroundColor: RadiantTokens.brand),
                          child: _busy
                              ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                              : const Text('Complete sale'),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
    );
  }
}
