import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../features/staff_billing/domain/billing_models.dart';
import '../theme/design_tokens.dart';

/// Billing List row (due client) — mirrors the reference layout: name, code,
/// user id, zone, address, big red Due + amber Pay, then Ex.Date · package ·
/// MikroTik toggle · call · SMS.
class DueClientCard extends StatelessWidget {
  const DueClientCard({
    super.key,
    required this.client,
    this.onPay,
    this.onToggleNetwork,
    this.onExtend,
    this.onCall,
    this.onSms,
  });

  final DueClient client;
  final VoidCallback? onPay;
  final ValueChanged<bool>? onToggleNetwork;
  final VoidCallback? onExtend;
  final VoidCallback? onCall;
  final VoidCallback? onSms;

  @override
  Widget build(BuildContext context) {
    final fmt = NumberFormat('#,##0.0');
    final c = client;
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
              padding: const EdgeInsets.fromLTRB(14, 12, 14, 10),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(c.name,
                            style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                        const SizedBox(height: 3),
                        _kv(context, 'Client Code', c.customerCode),
                        _kv(context, 'User ID/IP', c.username),
                        if (c.zone.isNotEmpty) _kv(context, 'Zone', c.zone, accent: true),
                        if (c.address.isNotEmpty) _kv(context, 'Address', c.address, accent: true),
                      ],
                    ),
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text('৳${fmt.format(c.balanceDue)}',
                          style: const TextStyle(
                              color: DesignTokens.danger, fontWeight: FontWeight.w800, fontSize: 20)),
                      Text('Due', style: TextStyle(fontSize: 10, color: context.brand.textMuted)),
                      const SizedBox(height: 8),
                      _payButton(),
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
                  IconButton(
                    tooltip: 'Extend 30 days',
                    icon: const Icon(Icons.autorenew_rounded, size: 19, color: DesignTokens.primary),
                    visualDensity: VisualDensity.compact,
                    onPressed: onExtend,
                  ),
                  Text('Ex: ${c.expireDay}',
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(c.package,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(fontSize: 11, color: context.brand.textMuted)),
                  ),
                  Switch.adaptive(value: c.networkOn, onChanged: onToggleNetwork),
                  if (c.phone.isNotEmpty)
                    IconButton(
                      icon: const Icon(Icons.phone_rounded, color: DesignTokens.success, size: 20),
                      visualDensity: VisualDensity.compact,
                      onPressed: onCall,
                    ),
                  IconButton(
                    icon: const Icon(Icons.sms_rounded, color: DesignTokens.info, size: 19),
                    visualDensity: VisualDensity.compact,
                    onPressed: onSms,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _payButton() {
    return Material(
      color: DesignTokens.warning,
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: onPay,
        borderRadius: BorderRadius.circular(999),
        child: const Padding(
          padding: EdgeInsets.symmetric(horizontal: 22, vertical: 7),
          child: Text('Pay',
              style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 13)),
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
            fontWeight: accent ? FontWeight.w600 : FontWeight.w400,
          ),
        ),
      ]),
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
    );
  }
}
