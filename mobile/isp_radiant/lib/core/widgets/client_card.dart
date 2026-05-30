import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../features/staff_customers/domain/customer_list_item.dart';
import '../theme/design_tokens.dart';

/// Client list row — mirrors the reference layout (status dot, name, M.bill,
/// IP/ID · package · code, zone, status pill, Mikrotik toggle, call/SMS) in the
/// premium theme.
class ClientCard extends StatelessWidget {
  const ClientCard({
    super.key,
    required this.client,
    this.onTap,
    this.onLongPress,
    this.onToggleNetwork,
    this.onCall,
    this.onSms,
  });

  final CustomerListItem client;
  final VoidCallback? onTap;
  final VoidCallback? onLongPress;
  final ValueChanged<bool>? onToggleNetwork;
  final VoidCallback? onCall;
  final VoidCallback? onSms;

  @override
  Widget build(BuildContext context) {
    final fmt = NumberFormat('#,##0.0');
    final c = client;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Material(
        color: context.cs.surface,
        borderRadius: BorderRadius.circular(DesignTokens.radius),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          onLongPress: onLongPress,
          child: Container(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(DesignTokens.radius),
              border: Border.all(color: context.brand.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(14, 12, 14, 10),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        margin: const EdgeInsets.only(top: 5),
                        width: 9,
                        height: 9,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: c.isOnline ? DesignTokens.success : context.brand.textMuted,
                          boxShadow: c.isOnline
                              ? [BoxShadow(color: DesignTokens.success.withValues(alpha: 0.6), blurRadius: 6)]
                              : null,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(c.name,
                                style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
                            const SizedBox(height: 3),
                            _kv(context, 'IP/ID', c.username),
                            if (c.packageName.isNotEmpty) _kv(context, 'Package', c.packageName),
                            if (c.customerCode.isNotEmpty) _kv(context, 'Code', c.customerCode),
                            if (c.zone.isNotEmpty)
                              Padding(
                                padding: const EdgeInsets.only(top: 2),
                                child: Row(children: [
                                  Icon(Icons.place_rounded, size: 12, color: DesignTokens.warning),
                                  const SizedBox(width: 3),
                                  Flexible(
                                    child: Text(c.zone,
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: const TextStyle(
                                            fontSize: 11, color: DesignTokens.warning, fontWeight: FontWeight.w600)),
                                  ),
                                ]),
                              ),
                          ],
                        ),
                      ),
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.end,
                        children: [
                          Text('৳${fmt.format(c.monthlyBill)}',
                              style: const TextStyle(
                                  color: DesignTokens.danger, fontWeight: FontWeight.w800, fontSize: 15)),
                          Text('M.bill', style: TextStyle(fontSize: 10, color: context.brand.textMuted)),
                          const SizedBox(height: 6),
                          _statusPill(context, c),
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
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
                  child: Row(
                    children: [
                      Text('MikroTik',
                          style: TextStyle(
                              fontSize: 11, fontWeight: FontWeight.w600, color: context.brand.textMuted)),
                      Switch.adaptive(
                        value: c.networkOn,
                        onChanged: onToggleNetwork,
                      ),
                      const Spacer(),
                      if (c.phone.isNotEmpty)
                        IconButton(
                          icon: const Icon(Icons.phone_rounded, color: DesignTokens.success, size: 21),
                          onPressed: onCall,
                          tooltip: c.phone,
                        ),
                      IconButton(
                        icon: const Icon(Icons.sms_rounded, color: DesignTokens.info, size: 20),
                        onPressed: onSms,
                        tooltip: 'Send SMS',
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _kv(BuildContext context, String k, String v) {
    return Text.rich(
      TextSpan(children: [
        TextSpan(text: '$k: ', style: TextStyle(fontSize: 11.5, color: context.brand.textMuted)),
        TextSpan(text: v, style: TextStyle(fontSize: 11.5, color: context.cs.onSurface)),
      ]),
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
    );
  }

  Widget _statusPill(BuildContext context, CustomerListItem c) {
    final color = c.isActive ? DesignTokens.success : DesignTokens.danger;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: 0.35)),
      ),
      child: Text(c.status[0].toUpperCase() + c.status.substring(1),
          style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w700)),
    );
  }
}
