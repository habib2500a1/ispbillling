import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/theme/design_tokens.dart';
import '../utils/app_nav.dart';

/// Bottom-nav shell for staff — one gradient header per tab, no duplicate app bar.
class AppShell extends StatelessWidget {
  const AppShell({
    super.key,
    required this.tabIndex,
    required this.onTab,
    required this.pages,
    required this.destinations,
    this.floatingActionButton,
  });

  final int tabIndex;
  final ValueChanged<int> onTab;
  final List<Widget> pages;
  final List<NavigationDestination> destinations;
  final Widget? floatingActionButton;

  @override
  Widget build(BuildContext context) {
    final index = tabIndex.clamp(0, pages.length - 1);

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light.copyWith(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.light,
      ),
      child: Scaffold(
        backgroundColor: DesignTokens.lightBg,
        floatingActionButton: floatingActionButton,
        floatingActionButtonLocation: FloatingActionButtonLocation.endFloat,
        body: IndexedStack(
          index: index,
          sizing: StackFit.expand,
          children: pages,
        ),
        bottomNavigationBar: NavigationBar(
          selectedIndex: index,
          onDestinationSelected: (i) => onTabTap(i, onTab),
          destinations: destinations,
          labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
          height: 68,
          backgroundColor: Colors.white,
          indicatorColor: DesignTokens.primary.withValues(alpha: 0.12),
          surfaceTintColor: Colors.transparent,
          shadowColor: Colors.black26,
          elevation: 8,
        ),
      ),
    );
  }
}
