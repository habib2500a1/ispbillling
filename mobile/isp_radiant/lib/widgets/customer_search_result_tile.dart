import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../theme/app_theme.dart';

/// Search result row with due before opening customer (updates after payment).
class CustomerSearchResultTile extends StatelessWidget {
  const CustomerSearchResultTile({
    super.key,
    required this.customer,
    required this.onTap,
    this.showDue = true,
    this.selected = false,
  });

  final Map<String, dynamic> customer;
  final VoidCallback onTap;
  final bool showDue;
  final bool selected;

  @override
  Widget build(BuildContext context) {
    final fmt = NumberFormat('#,##0.00');
    final name = customer['name']?.toString() ?? '';
    final code = customer['customer_code']?.toString() ?? '';
    final due = (customer['balance_due'] as num?)?.toDouble() ?? 0;
    final hasDue = due > 0.009;
    final hasDup = customer['has_duplicate_name'] == true;
    final hint = customer['same_name_hint']?.toString();
    final dupCount = (customer['duplicate_name_count'] as num?)?.toInt() ?? 1;
    final openBills = (customer['open_invoices'] as num?)?.toInt() ?? 0;
    final billingMode = customer['billing_mode']?.toString();

    return Card(
      margin: const EdgeInsets.only(top: 8),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: selected
            ? const BorderSide(color: AppTheme.primary, width: 2)
            : BorderSide.none,
      ),
      child: ListTile(
        onTap: onTap,
        title: Row(
          children: [
            Expanded(child: Text(name, style: const TextStyle(fontWeight: FontWeight.w600))),
            if (hasDup)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: AppTheme.warning.withValues(alpha: 0.2),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  '×$dupCount',
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Colors.orange.shade900),
                ),
              ),
          ],
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('$code${billingMode != null ? ' · ${billingMode.toUpperCase()}' : ''}'),
            if (showDue && openBills > 0)
              Text(
                '$openBills open bill(s)',
                style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
              ),
            if (hasDup && hint != null && hint.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text(
                  hint,
                  style: TextStyle(fontSize: 11, color: Colors.orange.shade800),
                ),
              ),
          ],
        ),
        trailing: showDue
            ? Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    fmt.format(due),
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                      color: hasDue ? AppTheme.warning : AppTheme.success,
                    ),
                  ),
                  Text(
                    hasDue ? 'BDT due' : 'Paid',
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w600,
                      color: hasDue ? AppTheme.warning : AppTheme.success,
                    ),
                  ),
                  const Icon(Icons.chevron_right, size: 18),
                ],
              )
            : const Icon(Icons.chevron_right),
      ),
    );
  }
}
