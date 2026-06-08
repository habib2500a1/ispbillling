import 'package:flutter/material.dart';

import '../core/theme/design_tokens.dart';

/// Subtle glassmorphism card (Phase 5) — light blur simulation without heavy GPU cost.
class GlassCard extends StatelessWidget {
  const GlassCard({
    super.key,
    required this.child,
    this.onTap,
    this.padding = const EdgeInsets.all(16),
    this.margin,
  });

  final Widget child;
  final VoidCallback? onTap;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry? margin;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final surface = isDark ? Colors.white.withValues(alpha: 0.06) : Colors.white.withValues(alpha: 0.72);
    final border = isDark ? Colors.white.withValues(alpha: 0.12) : Colors.white.withValues(alpha: 0.9);

    final card = Container(
      margin: margin,
      padding: padding,
      decoration: BoxDecoration(
        color: surface,
        borderRadius: BorderRadius.circular(DesignTokens.radius),
        border: Border.all(color: border),
        boxShadow: [
          BoxShadow(
            color: DesignTokens.primary.withValues(alpha: isDark ? 0.08 : 0.06),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: child,
    );

    if (onTap == null) return card;
    return Material(
      color: Colors.transparent,
      child: InkWell(onTap: onTap, borderRadius: BorderRadius.circular(DesignTokens.radius), child: card),
    );
  }
}
