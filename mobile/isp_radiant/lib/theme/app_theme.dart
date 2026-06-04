import 'package:flutter/material.dart';

import '../core/theme/app_themes.dart';
import '../core/theme/design_tokens.dart';

/// COMPATIBILITY SHIM.
///
/// The design system now lives in `core/theme/` (DesignTokens + AppThemes +
/// ThemeController). This class is kept only so screens not yet migrated to the
/// clean-architecture layer keep compiling and inherit the new premium palette.
/// New code should use `Theme.of(context).colorScheme` and `context.brand`.
/// Each constant below maps to a [DesignTokens] value.
@Deprecated('Use Theme.of(context).colorScheme / context.brand / DesignTokens')
class AppTheme {
  static const Color primary = DesignTokens.primary;
  static const Color primaryDark = DesignTokens.primaryDeep;
  static const Color accent = DesignTokens.info;
  static const Color accentSoft = DesignTokens.darkSurfaceAlt;
  static const Color background = DesignTokens.darkBg;
  static const Color card = DesignTokens.darkSurface;
  static const Color success = DesignTokens.success;
  static const Color danger = DesignTokens.danger;
  static const Color warning = DesignTokens.warning;
  static const Color info = DesignTokens.info;
  static const Color purple = DesignTokens.primary;
  static const Color pink = DesignTokens.pink;
  static const Color teal = DesignTokens.teal;

  /// Kept for any legacy reference; root app now uses AppThemes.dark/light.
  static ThemeData get light => AppThemes.light;
  static ThemeData get dark => AppThemes.dark;

  static BoxDecoration get heroGradient => BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [DesignTokens.primaryDeep, Color(0xFF6366F1), Color(0xFF0EA5E9)],
        ),
        borderRadius: BorderRadius.circular(DesignTokens.radius),
      );

  static List<Color> get navBarColors =>
      [DesignTokens.primary, DesignTokens.success, DesignTokens.teal, DesignTokens.info, DesignTokens.pink];

  static BoxDecoration tinted(Color c) => BoxDecoration(
        color: c.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(DesignTokens.radiusSm),
        border: Border.all(color: c.withValues(alpha: 0.30)),
      );
}
