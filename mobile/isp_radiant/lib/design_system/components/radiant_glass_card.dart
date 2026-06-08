import 'dart:ui';

import 'package:flutter/material.dart';

import '../radiant_tokens.dart';

/// Glassmorphism surface card — premium SaaS style.
class RadiantGlassCard extends StatelessWidget {
  const RadiantGlassCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(16),
    this.onTap,
    this.borderRadius = RadiantTokens.radiusMd,
    this.blur = 12,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;
  final double borderRadius;
  final double blur;

  @override
  Widget build(BuildContext context) {
    final brand = context.radiant;
    final isDark = context.isDark;

    final content = ClipRRect(
      borderRadius: BorderRadius.circular(borderRadius),
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: blur, sigmaY: blur),
        child: Container(
          padding: padding,
          decoration: BoxDecoration(
            color: isDark
                ? brand.glass
                : Colors.white.withValues(alpha: 0.72),
            borderRadius: BorderRadius.circular(borderRadius),
            border: Border.all(
              color: brand.border.withValues(alpha: isDark ? 0.6 : 0.9),
            ),
            boxShadow: [
              BoxShadow(
                color: brand.glow.withValues(alpha: isDark ? 0.25 : 0.12),
                blurRadius: 24,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: child,
        ),
      ),
    );

    if (onTap == null) return content;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(borderRadius),
        child: content,
      ),
    );
  }
}
