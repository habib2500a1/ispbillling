import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import 'staff_authenticated_pdf_screen.dart';

/// Shown after successful bill collection — invoice + receipt PDF links.
class PaymentSuccessSheet extends StatelessWidget {
  const PaymentSuccessSheet({
    super.key,
    required this.api,
    required this.message,
    required this.payment,
    this.customerDue,
  });

  final ApiService api;
  final String message;
  final Map<String, dynamic> payment;
  final double? customerDue;

  static Future<void> show(
    BuildContext context, {
    required ApiService api,
    required String message,
    required Map<String, dynamic> payment,
    double? customerDue,
  }) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => PaymentSuccessSheet(api: api, message: message, payment: payment, customerDue: customerDue),
    );
  }

  @override
  Widget build(BuildContext context) {
    final fmt = NumberFormat('#,##0.00');
    final amount = (payment['amount'] as num?)?.toDouble() ?? 0;
    final receipt = payment['receipt_number']?.toString() ?? '—';
    final invoice = payment['invoice'] as Map<String, dynamic>?;
    final invoicePdf = invoice?['pdf_url']?.toString() ?? payment['receipt_pdf_url']?.toString();
    final receiptPdf = payment['receipt_pdf_url']?.toString();

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                const Icon(Icons.check_circle, color: AppTheme.success, size: 32),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Payment recorded',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.bold),
                  ),
                ),
                IconButton(onPressed: () => Navigator.pop(context), icon: const Icon(Icons.close)),
              ],
            ),
            const SizedBox(height: 8),
            Text(message, style: TextStyle(color: Colors.grey.shade700)),
            const SizedBox(height: 16),
            Card(
              color: AppTheme.success.withValues(alpha: 0.08),
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Receipt: $receipt', style: const TextStyle(fontFamily: 'monospace', fontWeight: FontWeight.w600)),
                    Text('Amount: ${fmt.format(amount)} BDT', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                    if (invoice != null) ...[
                      const SizedBox(height: 8),
                      Text(
                        'Invoice: ${invoice['invoice_number'] ?? '—'}',
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                      Text(
                        'Paid ${fmt.format(invoice['amount_paid'] ?? 0)} / ${fmt.format(invoice['total'] ?? 0)} BDT · Bill due ${fmt.format(invoice['balance_due'] ?? 0)}',
                        style: const TextStyle(fontSize: 12),
                      ),
                    ],
                    if (customerDue != null) ...[
                      const SizedBox(height: 8),
                      Text(
                        customerDue! <= 0.009
                            ? 'Total due: ${fmt.format(customerDue)} BDT · Paid'
                            : 'Total due: ${fmt.format(customerDue)} BDT',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.bold,
                          color: customerDue! <= 0.009 ? AppTheme.success : AppTheme.warning,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),
            if (invoicePdf != null && invoicePdf.isNotEmpty)
              FilledButton.icon(
                onPressed: () {
                  Navigator.pop(context);
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => StaffAuthenticatedPdfScreen(
                        api: api,
                        url: invoicePdf,
                        title: 'Invoice PDF',
                      ),
                    ),
                  );
                },
                icon: const Icon(Icons.picture_as_pdf),
                label: const Text('View invoice PDF'),
              ),
            if (receiptPdf != null && receiptPdf.isNotEmpty) ...[
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: () {
                  Navigator.pop(context);
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => StaffAuthenticatedPdfScreen(
                        api: api,
                        url: receiptPdf,
                        title: 'Payment receipt',
                      ),
                    ),
                  );
                },
                icon: const Icon(Icons.receipt_long),
                label: const Text('View receipt PDF'),
              ),
            ],
            const SizedBox(height: 8),
            TextButton(onPressed: () => Navigator.pop(context), child: const Text('Done')),
          ],
        ),
      ),
    );
  }
}
