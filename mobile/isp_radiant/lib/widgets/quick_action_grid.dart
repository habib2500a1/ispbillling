import 'package:flutter/material.dart';

class QuickActionGrid extends StatelessWidget {
  const QuickActionGrid({
    super.key,
    required this.actions,
    required this.onAction,
  });

  final List<Map<String, dynamic>> actions;
  final void Function(String key) onAction;

  static const _colors = [
    Color(0xFF3B82F6),
    Color(0xFF8B5CF6),
    Color(0xFF10B981),
    Color(0xFFF59E0B),
    Color(0xFFEF4444),
    Color(0xFF06B6D4),
    Color(0xFF6366F1),
    Color(0xFFEC4899),
  ];

  IconData _icon(String? key) {
    switch (key) {
      case 'payments':
        return Icons.payments_outlined;
      case 'monitor':
        return Icons.monitor_heart_outlined;
      case 'person_add':
        return Icons.person_add_alt_1;
      case 'groups':
        return Icons.groups_outlined;
      case 'receipt':
        return Icons.receipt_long_outlined;
      case 'support':
        return Icons.support_agent;
      case 'verified':
        return Icons.verified_outlined;
      case 'account_balance':
        return Icons.account_balance_wallet_outlined;
      default:
        return Icons.apps;
    }
  }

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 4,
        mainAxisSpacing: 10,
        crossAxisSpacing: 10,
        childAspectRatio: 0.85,
      ),
      itemCount: actions.length,
      itemBuilder: (context, i) {
        final a = actions[i];
        final key = a['key']?.toString() ?? '';
        final color = _colors[i % _colors.length];
        return Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: () => onAction(key),
            borderRadius: BorderRadius.circular(14),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  width: 52,
                  height: 52,
                  decoration: BoxDecoration(
                    color: color,
                    borderRadius: BorderRadius.circular(14),
                    boxShadow: [
                      BoxShadow(
                        color: color.withValues(alpha: 0.35),
                        blurRadius: 8,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Icon(_icon(a['icon']?.toString()), color: Colors.white),
                ),
                const SizedBox(height: 6),
                Text(
                  a['label']?.toString() ?? '',
                  textAlign: TextAlign.center,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w600),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}
