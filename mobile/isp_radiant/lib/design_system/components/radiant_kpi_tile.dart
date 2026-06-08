import 'package:flutter/material.dart';

import '../radiant_tokens.dart';

/// Modern KPI metric tile — NOT legacy StatCard layout.
class RadiantKpiTile extends StatelessWidget {
  const RadiantKpiTile({
    super.key,
    required this.label,
    required this.value,
    required this.icon,
    this.trend,
    this.color,
    this.onTap,
    this.compact = false,
  });

  final String label;
  final String value;
  final IconData icon;
  final String? trend;
  final Color? color;
  final VoidCallback? onTap;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final brand = context.radiant;
    final accent = color ?? RadiantTokens.brand;
    final cs = Theme.of(context).colorScheme;
    final text = Theme.of(context).textTheme;

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(RadiantTokens.radiusMd),
        child: Ink(
          decoration: BoxDecoration(
            color: cs.surface,
            borderRadius: BorderRadius.circular(RadiantTokens.radiusMd),
            border: Border.all(color: brand.border.withValues(alpha: 0.85)),
            boxShadow: [
              BoxShadow(
                color: accent.withValues(alpha: context.isDark ? 0.08 : 0.06),
                blurRadius: 16,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          padding: EdgeInsets.all(compact ? 12 : 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: accent.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(RadiantTokens.radiusXs),
                    ),
                    child: Icon(icon, size: compact ? 16 : 18, color: accent),
                  ),
                  const Spacer(),
                  if (trend != null)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: brand.success.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        trend!,
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w600,
                          color: brand.success,
                        ),
                      ),
                    ),
                ],
              ),
              SizedBox(height: compact ? 10 : 14),
              Text(
                value,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: text.titleMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                  letterSpacing: -0.4,
                  fontSize: compact ? 16 : 18,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                label,
                style: text.labelSmall?.copyWith(
                  color: brand.muted,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
