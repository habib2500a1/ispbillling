import 'package:flutter/material.dart';

import '../theme/design_tokens.dart';

/// Themed pull-to-refresh wrapper used by every data screen.
class AppRefresh extends StatelessWidget {
  const AppRefresh({super.key, required this.onRefresh, required this.child});

  final Future<void> Function() onRefresh;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: onRefresh,
      color: DesignTokens.primary,
      backgroundColor: context.cs.surface,
      displacement: 28,
      child: child,
    );
  }
}
