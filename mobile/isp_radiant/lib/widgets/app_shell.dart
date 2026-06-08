import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../design_system/navigation/radiant_super_shell.dart';
import '../design_system/radiant_tokens.dart';
import '../utils/app_nav.dart';

/// Staff / technician bottom shell — delegates to Radiant 3.0 navigation.
class AppShell extends StatelessWidget {
  const AppShell({
    super.key,
    required this.tabIndex,
    required this.onTab,
    required this.pages,
    required this.destinations,
    this.floatingActionButton,
    this.centerAction,
    this.centerActionIcon = Icons.add_rounded,
    this.centerActionLabel,
    this.drawer,
  });

  final int tabIndex;
  final ValueChanged<int> onTab;
  final List<Widget> pages;
  final List<NavigationDestination> destinations;
  final Widget? floatingActionButton;
  final VoidCallback? centerAction;
  final IconData centerActionIcon;
  final String? centerActionLabel;
  final Widget? drawer;

  @override
  Widget build(BuildContext context) {
    final radiantDestinations = destinations
        .map(
          (d) => RadiantNavDestination(
            icon: d.icon,
            selectedIcon: d.selectedIcon ?? d.icon,
            label: d.label,
          ),
        )
        .toList();

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: context.isDark
          ? SystemUiOverlayStyle.light.copyWith(statusBarColor: Colors.transparent)
          : SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent),
      child: RadiantSuperShell(
        tabIndex: tabIndex,
        onTab: (i) => onTabTap(i, onTab),
        pages: pages,
        destinations: radiantDestinations,
        floatingActionButton: floatingActionButton,
        centerAction: centerAction,
        centerActionIcon: centerActionIcon,
        centerActionLabel: centerActionLabel,
        drawer: drawer,
      ),
    );
  }
}
