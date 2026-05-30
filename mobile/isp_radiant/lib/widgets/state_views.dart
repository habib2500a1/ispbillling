import 'package:flutter/material.dart';

import '../core/theme/design_tokens.dart';
import '../core/widgets/skeleton.dart';
import '../theme/app_theme.dart';
import 'isp_ui_kit.dart';

/// Shared premium loading placeholder (skeleton rows) — drop-in replacement for
/// `Center(child: CircularProgressIndicator())` on list screens.
class ListLoading extends StatelessWidget {
  const ListLoading({super.key, this.count = 6, this.rowHeight = 84});
  final int count;
  final double rowHeight;

  @override
  Widget build(BuildContext context) => SkeletonList(count: count, rowHeight: rowHeight);
}

class ErrorBanner extends StatelessWidget {
  const ErrorBanner({super.key, required this.message, this.onRetry});

  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: DesignTokens.danger.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(DesignTokens.radius),
        border: Border.all(color: DesignTokens.danger.withValues(alpha: 0.3)),
      ),
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          const Icon(Icons.error_outline, color: DesignTokens.danger),
          const SizedBox(width: 12),
          Expanded(
            child: Text(message,
                style: TextStyle(color: context.cs.onSurface, fontWeight: FontWeight.w500)),
          ),
          if (onRetry != null)
            TextButton(
              onPressed: onRetry,
              style: TextButton.styleFrom(foregroundColor: DesignTokens.danger),
              child: const Text('Retry'),
            ),
        ],
      ),
    );
  }
}

class EmptyState extends StatelessWidget {
  const EmptyState({
    super.key,
    required this.icon,
    required this.title,
    this.subtitle,
    this.action,
    this.actionLabel,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final VoidCallback? action;
  final String? actionLabel;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Container(
          constraints: const BoxConstraints(maxWidth: 320),
          padding: const EdgeInsets.all(24),
          decoration: IspUiKit.cardDecoration(),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: AppTheme.primary.withValues(alpha: 0.08),
                  shape: BoxShape.circle,
                ),
                child: Icon(icon, size: 40, color: AppTheme.primary),
              ),
              const SizedBox(height: 16),
              Text(
                title,
                textAlign: TextAlign.center,
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 17),
              ),
              if (subtitle != null) ...[
                const SizedBox(height: 8),
                Text(
                  subtitle!,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Color(0xFF64748B), fontSize: 13),
                ),
              ],
              if (action != null && actionLabel != null) ...[
                const SizedBox(height: 20),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton(
                    onPressed: action,
                    style: FilledButton.styleFrom(
                      backgroundColor: AppTheme.primary,
                      minimumSize: const Size.fromHeight(44),
                    ),
                    child: Text(actionLabel!),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class SectionTitle extends StatelessWidget {
  const SectionTitle(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10, top: 4),
      child: Text(
        text,
        style: Theme.of(context).textTheme.titleSmall?.copyWith(
              fontWeight: FontWeight.w800,
              color: AppTheme.primary,
              letterSpacing: 0.3,
            ),
      ),
    );
  }
}

class HeroBalanceCard extends StatelessWidget {
  const HeroBalanceCard({
    super.key,
    required this.title,
    required this.amount,
    required this.subtitle,
    this.trailing,
    this.onPay,
  });

  final String title;
  final String amount;
  final String subtitle;
  final Widget? trailing;
  final VoidCallback? onPay;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: AppTheme.heroGradient,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(color: Colors.white70, fontSize: 13)),
          const SizedBox(height: 6),
          Text(
            amount,
            style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 4),
          Text(subtitle, style: const TextStyle(color: Colors.white70, fontSize: 12)),
          if (trailing != null) ...[const SizedBox(height: 12), trailing!],
          if (onPay != null) ...[
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: onPay,
                style: FilledButton.styleFrom(backgroundColor: Colors.white, foregroundColor: AppTheme.primary),
                child: const Text('Pay now'),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
