import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../design_system/radiant_tokens.dart';
import '../features/staff_customers/domain/customer_list_item.dart';

/// Legacy SOFTIFY client list card — status dot, M.bill, zone, Mikrotik toggle, call/SMS/edit.
class LegacyClientCard extends StatelessWidget {
  const LegacyClientCard({
    super.key,
    required this.client,
    this.showDue = false,
    this.onTap,
    this.onEdit,
    this.onToggleNetwork,
    this.onCall,
    this.onSms,
  });

  final CustomerListItem client;
  final bool showDue;
  final VoidCallback? onTap;
  final VoidCallback? onEdit;
  final ValueChanged<bool>? onToggleNetwork;
  final VoidCallback? onCall;
  final VoidCallback? onSms;

  @override
  Widget build(BuildContext context) {
    final fmt = NumberFormat('#,##0.0');
    final c = client;
    final topAmount = showDue && c.due > 0 ? c.due : c.monthlyBill;
    final topLabel = showDue && c.due > 0 ? 'Due' : 'M.bill';

    Color statusColor;
    if (!c.networkOn) {
      statusColor = const Color(0xFFFF7043);
    } else if (c.status.toLowerCase() == 'expired' || (showDue && c.due > 0)) {
      statusColor = RadiantTokens.danger;
    } else if (c.isActive) {
      statusColor = const Color(0xFF66BB6A);
    } else {
      statusColor = Colors.grey.shade700;
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: Colors.white,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(10),
          side: const BorderSide(color: RadiantTokens.legacyCardBorder),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(10),
          onTap: onTap,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 10, 12, 8),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Container(
                        width: 10,
                        height: 10,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: c.isOnline ? const Color(0xFF66BB6A) : Colors.grey.shade400,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(c.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                          const SizedBox(height: 4),
                          _line('IP/ID', c.username),
                          if (c.packageLabel.isNotEmpty) _line('Package', c.packageLabel),
                          if (c.customerCode.isNotEmpty) _line('Client Code', c.customerCode),
                          if (c.zone.isNotEmpty)
                            _line('Zone', c.zone, valueColor: const Color(0xFFFF7043)),
                        ],
                      ),
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(
                          fmt.format(topAmount),
                          style: const TextStyle(color: Colors.red, fontWeight: FontWeight.w800, fontSize: 16),
                        ),
                        Text(topLabel, style: TextStyle(fontSize: 10, color: Colors.grey.shade600)),
                        const SizedBox(height: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                          decoration: BoxDecoration(
                            color: statusColor.withValues(alpha: 0.15),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Text(
                            c.networkOn ? c.statusLabel : 'Suspended',
                            style: TextStyle(color: statusColor, fontSize: 11, fontWeight: FontWeight.w700),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              Container(
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: const BorderRadius.vertical(bottom: Radius.circular(10)),
                  border: Border(top: BorderSide(color: RadiantTokens.legacyCardBorder)),
                ),
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 0),
                child: Row(
                  children: [
                    const Text('Mikrotik:', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                      Switch.adaptive(
                        value: c.networkOn,
                        activeColor: RadiantTokens.brand,
                        onChanged: onToggleNetwork,
                      ),
                    const Spacer(),
                    if (onEdit != null)
                      IconButton(
                        visualDensity: VisualDensity.compact,
                        icon: const Icon(Icons.edit_outlined, color: RadiantTokens.brand, size: 20),
                        onPressed: onEdit,
                        tooltip: 'Edit',
                      ),
                    if (c.phone.isNotEmpty && onCall != null)
                      IconButton(
                        visualDensity: VisualDensity.compact,
                        icon: const Icon(Icons.phone, color: RadiantTokens.brand, size: 20),
                        onPressed: onCall,
                      ),
                    if (onSms != null)
                      IconButton(
                        visualDensity: VisualDensity.compact,
                        icon: const Icon(Icons.chat_bubble_outline, color: RadiantTokens.brand, size: 20),
                        onPressed: onSms,
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _line(String label, String value, {Color? valueColor}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 2),
      child: RichText(
        text: TextSpan(
          style: TextStyle(fontSize: 12, color: Colors.grey.shade700),
          children: [
            TextSpan(text: '$label: '),
            TextSpan(
              text: value.isNotEmpty ? value : '—',
              style: TextStyle(color: valueColor ?? Colors.black87, fontWeight: FontWeight.w500),
            ),
          ],
        ),
      ),
    );
  }
}
