import 'package:flutter/material.dart';

import '../radiant_tokens.dart';

/// Radiant 3.0 quick actions — soft glass chips, unified brand palette.
class RadiantQuickActionGrid extends StatelessWidget {
  const RadiantQuickActionGrid({
    super.key,
    required this.actions,
    required this.onAction,
  });

  final List<Map<String, dynamic>> actions;
  final void Function(String key) onAction;

  static const _accents = [
    RadiantTokens.brand,
    RadiantTokens.accent,
    RadiantTokens.success,
    RadiantTokens.warning,
    RadiantTokens.danger,
    RadiantTokens.accentCyan,
    RadiantTokens.brandDeep,
    Color(0xFFEC4899),
  ];

  IconData _icon(String? key) {
    switch (key) {
      case 'payments':
        return Icons.payments_outlined;
      case 'monitor':
        return Icons.monitor_heart_outlined;
      case 'person_add':
        return Icons.person_add_alt_1_outlined;
      case 'groups':
        return Icons.groups_outlined;
      case 'receipt':
        return Icons.receipt_long_outlined;
      case 'support':
        return Icons.support_agent_outlined;
      case 'verified':
        return Icons.verified_outlined;
      case 'account_balance':
        return Icons.account_balance_wallet_outlined;
      default:
        return Icons.apps_rounded;
    }
  }

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 4,
        mainAxisSpacing: 10,
        crossAxisSpacing: 10,
        childAspectRatio: 0.88,
      ),
      itemCount: actions.length,
      itemBuilder: (context, i) {
        final a = actions[i];
        final key = a['key']?.toString() ?? '';
        final accent = _accents[i % _accents.length];

        return Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: () => onAction(key),
            borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
            child: Ink(
              decoration: BoxDecoration(
                color: context.isDark ? RadiantTokens.darkSurface : RadiantTokens.lightSurface,
                borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
                border: Border.all(color: accent.withValues(alpha: 0.22)),
                boxShadow: [
                  BoxShadow(
                    color: accent.withValues(alpha: context.isDark ? 0.12 : 0.08),
                    blurRadius: 12,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      color: accent.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(RadiantTokens.radiusXs),
                    ),
                    child: Icon(_icon(a['icon']?.toString()), color: accent, size: 22),
                  ),
                  const SizedBox(height: 6),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: Text(
                      a['label']?.toString() ?? '',
                      textAlign: TextAlign.center,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: text.labelSmall?.copyWith(fontWeight: FontWeight.w600, fontSize: 10),
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
