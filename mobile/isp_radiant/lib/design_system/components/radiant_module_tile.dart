import 'package:flutter/material.dart';

import '../radiant_tokens.dart';
import 'radiant_glass_card.dart';

/// Radiant 3.0 module tile — glass surface, brand accent (not legacy rainbow gradients).
class RadiantModuleTile extends StatelessWidget {
  const RadiantModuleTile({
    super.key,
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.onTap,
    this.color,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final VoidCallback onTap;
  final Color? color;

  static IconData iconFromKey(String key) {
    switch (key) {
      case 'groups':
        return Icons.groups_rounded;
      case 'receipt':
        return Icons.receipt_long_rounded;
      case 'payments':
        return Icons.payments_rounded;
      case 'inventory':
        return Icons.inventory_2_outlined;
      case 'router':
        return Icons.router_rounded;
      case 'analytics':
        return Icons.analytics_outlined;
      case 'support':
        return Icons.support_agent_rounded;
      case 'sms':
        return Icons.sms_outlined;
      case 'person':
        return Icons.person_outline_rounded;
      default:
        return Icons.apps_rounded;
    }
  }

  static Color colorFromKey(String key) {
    switch (key) {
      case 'orange':
        return RadiantTokens.warning;
      case 'red':
        return RadiantTokens.danger;
      case 'green':
        return RadiantTokens.success;
      case 'purple':
        return RadiantTokens.accent;
      case 'blue':
        return RadiantTokens.brand;
      case 'teal':
        return RadiantTokens.accentCyan;
      case 'indigo':
        return RadiantTokens.brandDeep;
      case 'pink':
        return const Color(0xFFEC4899);
      case 'amber':
        return RadiantTokens.warning;
      default:
        return RadiantTokens.brand;
    }
  }

  @override
  Widget build(BuildContext context) {
    final accent = color ?? RadiantTokens.brand;
    final text = Theme.of(context).textTheme;
    final brand = context.radiant;

    return RadiantGlassCard(
      onTap: onTap,
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [accent.withValues(alpha: 0.22), accent.withValues(alpha: 0.08)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
              border: Border.all(color: accent.withValues(alpha: 0.28)),
            ),
            child: Icon(icon, color: accent, size: 22),
          ),
          const Spacer(),
          Text(title, style: text.titleSmall?.copyWith(fontWeight: FontWeight.w700, letterSpacing: -0.2)),
          const SizedBox(height: 3),
          Text(
            subtitle,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: text.labelSmall?.copyWith(color: brand.muted, height: 1.3),
          ),
        ],
      ),
    );
  }
}
