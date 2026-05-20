import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../theme/app_theme.dart';

/// Search result row with duplicate-name warning when same name exists.
class CustomerSearchResultTile extends StatelessWidget {
  const CustomerSearchResultTile({
    super.key,
    required this.customer,
    required this.onTap,
    this.showDue = true,
  });

  final Map<String, dynamic> customer;
  final VoidCallback onTap;
  final bool showDue;

  @override
  Widget build(BuildContext context) {
    final fmt = NumberFormat('#,##0.00');
    final name = customer['name']?.toString() ?? '';
    final code = customer['customer_code']?.toString() ?? '';
    final due = (customer['balance_due'] as num?)?.toDouble() ?? 0;
    final hasDup = customer['has_duplicate_name'] == true;
    final hint = customer['same_name_hint']?.toString();
    final dupCount = (customer['duplicate_name_count'] as num?)?.toInt() ?? 1;
    final payState = customer['billing_payment_state']?.toString();
    final billingMode = customer['billing_mode']?.toString();

    return Card(
      margin: const EdgeInsets.only(top: 8),
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
            Text(
              showDue
                  ? '$code · Due ${fmt.format(due)} BDT${billingMode != null ? ' · ${billingMode.toUpperCase()}' : ''}'
                  : '$code · ${customer['package'] ?? customer['phone'] ?? ''}',
            ),
            if (payState == 'paid' && due <= 0.009)
              const Padding(
                padding: EdgeInsets.only(top: 2),
                child: Text('Paid (ISP Digital)', style: TextStyle(fontSize: 11, color: AppTheme.success, fontWeight: FontWeight.w600)),
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
        trailing: const Icon(Icons.chevron_right),
      ),
    );
  }
}
