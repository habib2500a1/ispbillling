import 'package:flutter/material.dart';

import '../design_system/components/radiant_glass_card.dart';
import '../design_system/components/radiant_section.dart';
import '../core/theme/design_tokens.dart';
import '../design_system/radiant_tokens.dart';

/// Payment history row — Radiant style (replaces IspUiKit.paymentHistoryCard).
class RadiantPaymentRow extends StatelessWidget {
  const RadiantPaymentRow({
    super.key,
    required this.title,
    required this.date,
    required this.amount,
    this.invoice,
    this.status,
    this.onTap,
  });

  final String title;
  final String date;
  final String amount;
  final String? invoice;
  final String? status;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final brand = context.radiant;
    return RadiantGlassCard(
      onTap: onTap,
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: brand.success.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
            ),
            child: Icon(Icons.receipt_long_rounded, color: brand.success, size: 20),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: context.text.bodyMedium?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: 2),
                Text(date, style: context.text.bodySmall?.copyWith(color: brand.muted)),
                if (invoice != null && invoice!.isNotEmpty)
                  Text(invoice!, style: context.text.labelSmall?.copyWith(color: brand.muted)),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text('৳$amount', style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w800, color: brand.success)),
              if (status != null && status!.isNotEmpty)
                Container(
                  margin: const EdgeInsets.only(top: 4),
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: RadiantTokens.brand.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(status!, style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: RadiantTokens.brand)),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Ticket list row — Radiant style.
class RadiantTicketRow extends StatelessWidget {
  const RadiantTicketRow({
    super.key,
    required this.subject,
    required this.status,
    required this.updated,
    this.priority,
    required this.onTap,
  });

  final String subject;
  final String status;
  final String updated;
  final String? priority;
  final VoidCallback onTap;

  Color _statusColor(BuildContext context) {
    final s = status.toLowerCase();
    if (s.contains('open') || s.contains('pending')) return context.radiant.warning;
    if (s.contains('closed') || s.contains('resolved')) return context.radiant.success;
    return RadiantTokens.brand;
  }

  @override
  Widget build(BuildContext context) {
    final brand = context.radiant;
    final color = _statusColor(context);
    return RadiantGlassCard(
      onTap: onTap,
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          Container(
            width: 4,
            height: 44,
            decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(4)),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(subject, maxLines: 2, overflow: TextOverflow.ellipsis,
                    style: context.text.bodyMedium?.copyWith(fontWeight: FontWeight.w700)),
                const SizedBox(height: 4),
                Text(updated, style: context.text.labelSmall?.copyWith(color: brand.muted)),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              RadiantStatusChip(label: status, color: color),
              if (priority != null && priority!.isNotEmpty) ...[
                const SizedBox(height: 4),
                Text(priority!, style: context.text.labelSmall?.copyWith(color: brand.muted)),
              ],
            ],
          ),
        ],
      ),
    );
  }
}
