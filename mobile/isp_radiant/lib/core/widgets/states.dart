import 'package:flutter/material.dart';

import '../network/api_result.dart';
import '../theme/design_tokens.dart';

/// Full-screen error state with a retry button. Driven by a typed [Failure]
/// so the icon and tone match the cause (offline vs server vs not-found).
class ErrorStateView extends StatelessWidget {
  const ErrorStateView({super.key, required this.failure, this.onRetry});

  final Failure failure;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    final icon = switch (failure.type) {
      FailureType.network => Icons.wifi_off_rounded,
      FailureType.timeout => Icons.hourglass_disabled_rounded,
      FailureType.unauthorized => Icons.lock_outline_rounded,
      FailureType.notFound => Icons.search_off_rounded,
      FailureType.server => Icons.cloud_off_rounded,
      FailureType.unknown => Icons.error_outline_rounded,
    };
    return _CenteredState(
      icon: icon,
      color: failure.isOffline ? context.brand.warning : context.brand.danger,
      title: failure.isOffline ? 'You are offline' : 'Something went wrong',
      message: failure.message,
      actionLabel: onRetry == null ? null : 'Retry',
      onAction: onRetry,
    );
  }
}

/// Empty-list placeholder.
class EmptyStateView extends StatelessWidget {
  const EmptyStateView({
    super.key,
    this.icon = Icons.inbox_rounded,
    required this.title,
    this.message,
    this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final String title;
  final String? message;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return _CenteredState(
      icon: icon,
      color: context.brand.textMuted,
      title: title,
      message: message,
      actionLabel: actionLabel,
      onAction: onAction,
    );
  }
}

class _CenteredState extends StatelessWidget {
  const _CenteredState({
    required this.icon,
    required this.color,
    required this.title,
    this.message,
    this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final Color color;
  final String title;
  final String? message;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(color: color.withValues(alpha: 0.12), shape: BoxShape.circle),
              child: Icon(icon, size: 40, color: color),
            ),
            const SizedBox(height: 18),
            Text(title,
                style: context.text.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                textAlign: TextAlign.center),
            if (message != null) ...[
              const SizedBox(height: 8),
              Text(message!,
                  style: context.text.bodySmall?.copyWith(color: context.brand.textMuted),
                  textAlign: TextAlign.center),
            ],
            if (actionLabel != null && onAction != null) ...[
              const SizedBox(height: 20),
              FilledButton.icon(
                onPressed: onAction,
                icon: const Icon(Icons.refresh_rounded, size: 18),
                label: Text(actionLabel!),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Slim banner shown at the top of a screen when the device is offline.
class OfflineBanner extends StatelessWidget {
  const OfflineBanner({super.key, this.visible = true});
  final bool visible;

  @override
  Widget build(BuildContext context) {
    return AnimatedSize(
      duration: DesignTokens.motion,
      child: !visible
          ? const SizedBox.shrink()
          : Container(
              width: double.infinity,
              color: context.brand.warning.withValues(alpha: 0.16),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: Row(
                children: [
                  Icon(Icons.wifi_off_rounded, size: 16, color: context.brand.warning),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text('Offline — showing last loaded data',
                        style: context.text.bodySmall?.copyWith(color: context.brand.warning)),
                  ),
                ],
              ),
            ),
    );
  }
}
