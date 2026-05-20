import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../utils/app_nav.dart';

/// Bottom nav shell — standard AppBar height, safe body, no overlap with system UI.
class AppShell extends StatelessWidget {
  const AppShell({
    super.key,
    required this.tabIndex,
    required this.onTab,
    required this.pages,
    required this.destinations,
    required this.title,
    this.actions,
    this.floatingActionButton,
  });

  final int tabIndex;
  final ValueChanged<int> onTab;
  final List<Widget> pages;
  final List<NavigationDestination> destinations;
  final String title;
  final List<Widget>? actions;
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
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        appBar: AppBar(
          title: Text(title, maxLines: 1, overflow: TextOverflow.ellipsis),
          actions: actions,
          centerTitle: false,
        ),
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
        ),
      ),
    );
  }
}
