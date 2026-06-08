import 'package:flutter/material.dart';

import '../radiant_tokens.dart';

class RadiantNavDestination {
  const RadiantNavDestination({
    required this.icon,
    required this.selectedIcon,
    required this.label,
  });

  final Widget icon;
  final Widget selectedIcon;
  final String label;
}

/// Modern bottom navigation — floating pill bar with optional center action.
class RadiantSuperShell extends StatelessWidget {
  const RadiantSuperShell({
    super.key,
    required this.tabIndex,
    required this.onTab,
    required this.pages,
    required this.destinations,
    this.centerAction,
    this.centerActionIcon = Icons.add_rounded,
    this.centerActionLabel,
    this.drawer,
    this.floatingActionButton,
  });

  final int tabIndex;
  final ValueChanged<int> onTab;
  final List<Widget> pages;
  final List<RadiantNavDestination> destinations;
  final VoidCallback? centerAction;
  final IconData centerActionIcon;
  final String? centerActionLabel;
  final Widget? drawer;
  final Widget? floatingActionButton;

  @override
  Widget build(BuildContext context) {
    final index = tabIndex.clamp(0, pages.length - 1);
    final isDark = context.isDark;
    final brand = context.radiant;

    return Scaffold(
      backgroundColor: isDark ? RadiantTokens.darkBg : RadiantTokens.lightBg,
      drawer: drawer,
      body: IndexedStack(index: index, children: pages),
      floatingActionButton: floatingActionButton,
      extendBody: true,
      bottomNavigationBar: Padding(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
        child: SafeArea(
          top: false,
          child: Container(
            height: 68,
            decoration: BoxDecoration(
              color: isDark
                  ? RadiantTokens.darkSurface.withValues(alpha: 0.94)
                  : Colors.white.withValues(alpha: 0.94),
              borderRadius: BorderRadius.circular(RadiantTokens.radiusXl),
              border: Border.all(color: brand.border.withValues(alpha: 0.85)),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: isDark ? 0.35 : 0.08),
                  blurRadius: 24,
                  offset: const Offset(0, 8),
                ),
              ],
            ),
            child: Row(
              children: [
                for (var i = 0; i < destinations.length; i++) ...[
                  if (centerAction != null && i == (destinations.length / 2).floor())
                    _CenterAction(
                      icon: centerActionIcon,
                      label: centerActionLabel,
                      onTap: centerAction!,
                    ),
                  Expanded(
                    child: _NavItem(
                      dest: destinations[i],
                      selected: index == i,
                      onTap: () => onTab(i),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _NavItem extends StatelessWidget {
  const _NavItem({
    required this.dest,
    required this.selected,
    required this.onTap,
  });

  final RadiantNavDestination dest;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected ? RadiantTokens.brand : context.radiant.muted;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(RadiantTokens.radiusLg),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            AnimatedScale(
              scale: selected ? 1.05 : 1,
              duration: RadiantTokens.fast,
              child: IconTheme.merge(
                data: IconThemeData(color: color, size: 22),
                child: selected ? dest.selectedIcon : dest.icon,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              dest.label,
              style: TextStyle(
                fontSize: 10,
                fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                color: color,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CenterAction extends StatelessWidget {
  const _CenterAction({
    required this.icon,
    required this.onTap,
    this.label,
  });

  final IconData icon;
  final VoidCallback onTap;
  final String? label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Material(
            elevation: 0,
            color: Colors.transparent,
            child: InkWell(
              onTap: onTap,
              customBorder: const CircleBorder(),
              child: Ink(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: const LinearGradient(
                    colors: [RadiantTokens.brand, RadiantTokens.accent],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: RadiantTokens.brand.withValues(alpha: 0.45),
                      blurRadius: 16,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Icon(icon, color: Colors.white, size: 24),
              ),
            ),
          ),
          if (label != null) ...[
            const SizedBox(height: 2),
            Text(
              label!,
              style: TextStyle(
                fontSize: 9,
                fontWeight: FontWeight.w600,
                color: RadiantTokens.brand,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// Custom page route with fade + slide transition (60fps target).
class RadiantPageRoute<T> extends PageRouteBuilder<T> {
  RadiantPageRoute({required Widget page})
      : super(
          pageBuilder: (_, __, ___) => page,
          transitionDuration: RadiantTokens.normal,
          reverseTransitionDuration: RadiantTokens.fast,
          transitionsBuilder: (_, animation, __, child) {
            final curved = CurvedAnimation(parent: animation, curve: Curves.easeOutCubic);
            return FadeTransition(
              opacity: curved,
              child: SlideTransition(
                position: Tween<Offset>(
                  begin: const Offset(0, 0.04),
                  end: Offset.zero,
                ).animate(curved),
                child: child,
              ),
            );
          },
        );
}
