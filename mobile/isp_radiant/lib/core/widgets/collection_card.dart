import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../features/staff_billing/domain/billing_models.dart';
import '../theme/design_tokens.dart';

/// Daily Bill Collection row — mirrors the reference layout: green check, name,
/// code, discount, due, address, big received amount, print/call, and a
/// Received-by / Created-at footer.
class CollectionCard extends StatelessWidget {
  const CollectionCard({super.key, required this.record, this.onPrint, this.onCall});

  final CollectionRecord record;
  final VoidCallback? onPrint;
  final VoidCallback? onCall;

  @override
  Widget build(BuildContext context) {
    final fmt = NumberFormat('#,##0.0');
    final r = record;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Container(
        decoration: BoxDecoration(
          color: context.cs.surface,
          borderRadius: BorderRadius.circular(DesignTokens.radius),
          border: Border.all(color: context.brand.border),
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 12, 14, 10),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Padding(
                    padding: EdgeInsets.only(top: 2, right: 8),
                    child: Icon(Icons.verified_rounded, color: DesignTokens.success, size: 22),
                  ),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(r.name,
                            style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
                        const SizedBox(height: 2),
                        if (r.customerCode.isNotEmpty) _kv(context, 'Client Code', r.customerCode),
                        _kv(context, 'Discount', fmt.format(r.discount)),
                        _kv(context, 'Due', fmt.format(r.due)),
                        if (r.address.isNotEmpty) _kv(context, 'Address', r.address, accent: true),
                      ],
                    ),
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text('৳${fmt.format(r.amount)}',
                          style: const TextStyle(
                              color: DesignTokens.success, fontWeight: FontWeight.w800, fontSize: 18)),
                      Text('R. Amount', style: TextStyle(fontSize: 10, color: context.brand.textMuted)),
                      const SizedBox(height: 6),
                      Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          IconButton(
                            icon: const Icon(Icons.print_rounded, size: 20, color: DesignTokens.info),
                            visualDensity: VisualDensity.compact,
                            onPressed: onPrint,
                          ),
                          if (r.phone.isNotEmpty)
                            IconButton(
                              icon: const Icon(Icons.phone_rounded, size: 20, color: DesignTokens.success),
                              visualDensity: VisualDensity.compact,
                              onPressed: onCall,
                            ),
                        ],
                      ),
                    ],
                  ),
                ],
              ),
            ),
            Container(
              decoration: BoxDecoration(
                color: DesignTokens.primary.withValues(alpha: 0.06),
                border: Border(top: BorderSide(color: context.brand.border)),
              ),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
              child: Row(
                children: [
                  Expanded(
                    child: Text('Received: ${r.receivedBy}',
                        style: TextStyle(fontSize: 11, color: context.brand.textMuted)),
                  ),
                  Text('Created: ${r.createdAt}',
                      style: TextStyle(fontSize: 11, color: context.brand.textMuted)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _kv(BuildContext context, String k, String v, {bool accent = false}) {
    return Text.rich(
      TextSpan(children: [
        TextSpan(text: '$k: ', style: TextStyle(fontSize: 11.5, color: context.brand.textMuted)),
        TextSpan(
            text: v,
            style: TextStyle(
                fontSize: 11.5,
                color: accent ? DesignTokens.warning : context.cs.onSurface,
                fontWeight: accent ? FontWeight.w600 : FontWeight.w400)),
      ]),
      maxLines: 2,
      overflow: TextOverflow.ellipsis,
    );
  }
}
