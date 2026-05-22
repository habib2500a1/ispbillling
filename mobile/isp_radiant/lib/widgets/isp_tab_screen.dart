import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../theme/app_theme.dart';
import '../utils/layout.dart';
import 'isp_ui_kit.dart';
import 'state_views.dart';

/// Standard tab body: gradient header + loading/error/content (matches billing/support SS).
class IspTabScreen extends StatelessWidget {
  const IspTabScreen({
    super.key,
    required this.title,
    this.subtitle,
    this.trailing,
    this.headerChild,
    required this.child,
    this.loading = false,
    this.error,
    this.onRetry,
    this.onRefresh,
    this.empty,
  });

  final String title;
  final String? subtitle;
  final List<Widget>? trailing;
  final Widget? headerChild;
  final Widget child;
  final bool loading;
  final String? error;
  final VoidCallback? onRetry;
  final Future<void> Function()? onRefresh;
  final Widget? empty;

  @override
  Widget build(BuildContext context) {
    return IspSafeHeader(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          IspUiKit.gradientHeader(
            title: title,
            subtitle: subtitle,
            trailing: trailing,
            child: headerChild,
          ),
          Expanded(child: _body(context)),
        ],
      ),
    );
  }

  Widget _body(BuildContext context) {
    if (loading) {
      return const Center(child: CircularProgressIndicator(color: AppTheme.primary));
    }
    if (error != null) {
      return Center(
        child: Padding(
          padding: pagePadding(context),
          child: ErrorBanner(message: error!, onRetry: onRetry),
        ),
      );
    }
    if (empty != null) {
      return empty!;
    }
    if (onRefresh != null) {
      return RefreshIndicator(
        onRefresh: onRefresh!,
        color: AppTheme.primary,
        child: child,
      );
    }
    return child;
  }
}
