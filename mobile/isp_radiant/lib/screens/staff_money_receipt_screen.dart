import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';

import '../core/theme/design_tokens.dart';
import '../services/api_service.dart';
import '../widgets/isp_ui_kit.dart';
import '../widgets/staff_document_actions_bar.dart';

/// Money receipt — ISP billing desk layout with fixed Print/Share dock.
class StaffMoneyReceiptScreen extends StatefulWidget {
  const StaffMoneyReceiptScreen({
    super.key,
    required this.api,
    required this.paymentId,
    this.initialPdfUrl,
    this.seedData,
    this.embedded = false,
    this.onClose,
  });

  final ApiService api;
  final int paymentId;
  final String? initialPdfUrl;
  final Map<String, dynamic>? seedData;
  /// When true, fills parent above bottom nav (no system AppBar).
  final bool embedded;
  final VoidCallback? onClose;

  @override
  State<StaffMoneyReceiptScreen> createState() => _StaffMoneyReceiptScreenState();
}

class _StaffMoneyReceiptScreenState extends State<StaffMoneyReceiptScreen> {
  Map<String, dynamic>? _data;
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
      final body = await widget.api.staffPaymentReceiptDetail(widget.paymentId);
      if (!mounted) return;
      setState(() {
        _data = Map<String, dynamic>.from(body['data'] as Map? ?? body);
        _loading = false;
      });
    } on ApiException catch (e) {
      if (mounted) _applyFallbackOrError(e.message);
    } catch (_) {
      if (mounted) _applyFallbackOrError('Could not load receipt');
    }
  }

  void _applyFallbackOrError(String message) {
    final fallback = _buildFallbackFromSeed();
    if (fallback != null) {
      setState(() {
        _data = fallback;
        _loading = false;
        _error = null;
      });
    } else {
      setState(() {
        _error = message;
        _loading = false;
      });
    }
  }

  Map<String, dynamic>? _buildFallbackFromSeed() {
    final seed = widget.seedData;
    final pdf = widget.initialPdfUrl;
    if (seed == null && (pdf == null || pdf.isEmpty)) return null;

    final amount = (seed?['amount'] as num?)?.toDouble() ?? 0.0;
    return {
      'receipt_number': seed?['receipt_number'] ?? '—',
      'paid_at': seed?['paid_at'] ?? seed?['created_at'] ?? '—',
      'method': seed?['method'] ?? '—',
      'received_by': seed?['recorded_by'] ?? seed?['received_by'] ?? '—',
      'receipt_pdf_url': pdf ?? seed?['receipt_pdf_url'],
      'footer_note': '',
      'branding': <String, dynamic>{'company_name': 'ISP'},
      'customer': {
        'customer_code': seed?['customer_code'] ?? '—',
        'name': seed?['customer_name'] ?? seed?['name'] ?? '—',
        'username': seed?['username'] ?? '—',
        'phone': seed?['phone'] ?? '—',
      },
      'amounts': {
        'total_bill': amount,
        'paid_amount': amount,
        'discount': (seed?['discount'] as num?)?.toDouble() ?? 0,
        'due_amount': (seed?['due'] as num?)?.toDouble() ?? 0,
        'vat_amount': 0,
        'advance': 0,
      },
    };
  }

  String get _pdfUrl =>
      _data?['receipt_pdf_url']?.toString() ?? widget.initialPdfUrl ?? '';

  String get _filename {
    final no = _data?['receipt_number']?.toString() ?? 'receipt-${widget.paymentId}';
    return '$no.pdf';
  }

  void _close() {
    if (widget.onClose != null) {
      widget.onClose!();
    } else {
      Navigator.maybePop(context);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: ColoredBox(
        color: DesignTokens.lightBg,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _header(),
            Expanded(
              child: _loading
                  ? const Center(child: CircularProgressIndicator(color: DesignTokens.primary))
                  : _error != null
                      ? _errorView()
                      : _receiptScroll(),
            ),
            if (!_loading && _error == null)
              StaffDocumentActionsBar(
                api: widget.api,
                pdfUrl: _pdfUrl,
                filename: _filename,
                pdfTitle: 'Money Receipt',
              ),
          ],
        ),
      ),
    );
  }

  Widget _header() {
    return IspUiKit.gradientHeader(
      title: 'Money Receipt',
      subtitle: _data?['receipt_number']?.toString(),
      trailing: [
        IconButton(
          icon: const Icon(Icons.close_rounded, color: Colors.white),
          onPressed: _close,
          tooltip: 'Close',
        ),
      ],
      child: _loading || _error != null
          ? null
          : _paidHero(),
    );
  }

  Widget _paidHero() {
    final paid = (_data?['amounts'] as Map?)?['paid_amount'] as num? ?? 0;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.white24),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: DesignTokens.success.withValues(alpha: 0.9),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(Icons.check_rounded, color: Colors.white, size: 26),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Paid amount', style: TextStyle(color: Colors.white70, fontSize: 12)),
                Text(
                  '৳${_fmt.format(paid.toDouble())}',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 26,
                    fontWeight: FontWeight.w900,
                    height: 1.1,
                  ),
                ),
              ],
            ),
          ),
          if ((_data?['method']?.toString() ?? '').isNotEmpty)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.18),
                borderRadius: BorderRadius.circular(999),
              ),
              child: Text(
                _data!['method'].toString(),
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 11),
              ),
            ),
        ],
      ),
    );
  }

  Widget _errorView() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.receipt_long_rounded, size: 48, color: Colors.grey.shade400),
            const SizedBox(height: 12),
            Text(_error!, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton(onPressed: _load, child: const Text('Retry')),
          ],
        ),
      ),
    );
  }

  Widget _receiptScroll() {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 8),
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 420),
          child: _receiptPaper(),
        ),
      ),
    );
  }

  Widget _receiptPaper() {
    final d = _data!;
    final branding = Map<String, dynamic>.from(d['branding'] as Map? ?? {});
    final customer = Map<String, dynamic>.from(d['customer'] as Map? ?? {});
    final amounts = Map<String, dynamic>.from(d['amounts'] as Map? ?? {});

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(DesignTokens.radius),
        border: Border.all(color: DesignTokens.lightBorder),
        boxShadow: [
          BoxShadow(
            color: DesignTokens.primary.withValues(alpha: 0.08),
            blurRadius: 24,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _brandBlock(branding),
          _dashedLine(),
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 10),
            child: Text(
              'MONEY RECEIPT',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w900,
                letterSpacing: 2,
                color: DesignTokens.primaryDeep,
              ),
            ),
          ),
          _dashedLine(),
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 4),
            child: Column(
              children: [
                _section('Client information', [
                  _row('Client Code', customer['customer_code']),
                  _row('Client Name', customer['name']),
                  _row('User Name', customer['username']),
                  _row('Mobile No.', customer['phone']),
                ]),
                const SizedBox(height: 10),
                _section('Payment details', [
                  _row('Receipt No.', d['receipt_number']),
                  _row('Pay. Date', d['paid_at']),
                  _row('P. Method', d['method']),
                ]),
                const SizedBox(height: 10),
                _amountsTable(amounts),
                const SizedBox(height: 10),
                _section('Received by', [
                  _row('Payment Received By', d['received_by']),
                ]),
                if ((d['footer_note']?.toString() ?? '').isNotEmpty) ...[
                  const SizedBox(height: 14),
                  Text(
                    d['footer_note'].toString(),
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 11, color: Colors.grey.shade600, height: 1.45),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 8),
          _receiptFooterStrip(),
        ],
      ),
    );
  }

  Widget _brandBlock(Map<String, dynamic> branding) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 18, 16, 14),
      child: Column(
        children: [
          if ((branding['logo_url']?.toString() ?? '').isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Image.network(
                branding['logo_url'].toString(),
                height: 52,
                errorBuilder: (_, _, _) => const SizedBox.shrink(),
              ),
            ),
          Text(
            branding['company_name']?.toString() ?? 'ISP',
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: DesignTokens.lightText),
          ),
          if ((branding['address']?.toString() ?? '').isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text(branding['address'].toString(), textAlign: TextAlign.center, style: _muted()),
            ),
          if ((branding['email']?.toString() ?? '').isNotEmpty)
            Text('Email: ${branding['email']}', textAlign: TextAlign.center, style: _muted()),
          if ((branding['phone']?.toString() ?? '').isNotEmpty)
            Text('Mobile: ${branding['phone']}', textAlign: TextAlign.center, style: _muted()),
        ],
      ),
    );
  }

  Widget _receiptFooterStrip() {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10),
      decoration: BoxDecoration(
        color: DesignTokens.primary.withValues(alpha: 0.06),
        borderRadius: const BorderRadius.vertical(bottom: Radius.circular(DesignTokens.radius)),
      ),
      child: const Text(
        'Thank you for your payment',
        textAlign: TextAlign.center,
        style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: DesignTokens.primary),
      ),
    );
  }

  Widget _dashedLine() {
    return const Padding(
      padding: EdgeInsets.symmetric(horizontal: 14),
      child: Divider(height: 1, thickness: 1, color: Color(0xFFCBD5E1)),
    );
  }

  Widget _section(String title, List<Widget> rows) {
    return Container(
      decoration: BoxDecoration(
        color: DesignTokens.lightSurfaceAlt,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: DesignTokens.lightBorder),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: DesignTokens.primary.withValues(alpha: 0.08),
              border: const Border(bottom: BorderSide(color: DesignTokens.lightBorder)),
            ),
            child: Text(
              title.toUpperCase(),
              style: const TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.w800,
                letterSpacing: 0.8,
                color: DesignTokens.primaryDeep,
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            child: Column(children: rows),
          ),
        ],
      ),
    );
  }

  Widget _row(String label, dynamic value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 128,
            child: Text(label, style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
          ),
          Expanded(
            child: Text(
              value?.toString() ?? '—',
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: DesignTokens.lightText),
            ),
          ),
        ],
      ),
    );
  }

  Widget _amountsTable(Map<String, dynamic> amounts) {
    final lines = [
      ('Total Bill', amounts['total_bill'], false),
      ('Paid Amount', amounts['paid_amount'], true),
      ('Discount', amounts['discount'], false),
      ('Due Amount', amounts['due_amount'], false),
      ('VAT Amount', amounts['vat_amount'], false),
      ('Advance', amounts['advance'], false),
    ];

    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: DesignTokens.lightBorder),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            color: DesignTokens.primary.withValues(alpha: 0.08),
            child: const Text(
              'AMOUNTS (BDT)',
              style: TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: DesignTokens.primaryDeep),
            ),
          ),
          for (var i = 0; i < lines.length; i++)
            Container(
              color: lines[i].$3 ? DesignTokens.success.withValues(alpha: 0.08) : Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                border: i < lines.length - 1
                    ? const Border(bottom: BorderSide(color: DesignTokens.lightBorder))
                    : null,
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      lines[i].$1,
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: lines[i].$3 ? FontWeight.w700 : FontWeight.w500,
                        color: lines[i].$3 ? DesignTokens.success : Colors.grey.shade700,
                      ),
                    ),
                  ),
                  Text(
                    _fmt.format((lines[i].$2 as num?)?.toDouble() ?? 0),
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                      color: lines[i].$3 ? DesignTokens.success : DesignTokens.lightText,
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  TextStyle _muted() => TextStyle(fontSize: 11, color: Colors.grey.shade600, height: 1.35);
}
