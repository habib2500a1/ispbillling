import 'package:flutter/material.dart';

/// Single source of truth for the premium SaaS look (dark + light).
///
/// Screens should prefer semantic colors via `Theme.of(context).colorScheme`
/// and the [BrandColors] theme extension. Raw tokens here exist so the theme
/// files and the legacy `AppTheme` aliases can be built from one place.
class DesignTokens {
  DesignTokens._();

  // ---- Brand (shared across modes) -------------------------------------
  static const Color primary = Color(0xFF8B5CF6); // purple
  static const Color primaryDeep = Color(0xFF7C3AED);
  static const Color primarySoft = Color(0xFFA78BFA);

  // Status (shared)
  static const Color success = Color(0xFF10B981);
  static const Color warning = Color(0xFFF59E0B);
  static const Color danger = Color(0xFFEF4444);
  static const Color info = Color(0xFF38BDF8);

  // Accent palette for charts / category chips
  static const Color teal = Color(0xFF14B8A6);
  static const Color pink = Color(0xFFEC4899);
  static const Color amber = Color(0xFFF59E0B);
  static const Color cyan = Color(0xFF06B6D4);

  // ---- Dark mode (default) ---------------------------------------------
  static const Color darkBg = Color(0xFF0B1120);
  static const Color darkSurface = Color(0xFF111827); // cards
  static const Color darkSurfaceAlt = Color(0xFF1A2234); // elevated / inputs
  static const Color darkBorder = Color(0xFF1F2937);
  static const Color darkText = Color(0xFFE5E7EB);
  static const Color darkTextMuted = Color(0xFF94A3B8);

  // ---- Light mode ------------------------------------------------------
  static const Color lightBg = Color(0xFFF1F5F9);
  static const Color lightSurface = Color(0xFFFFFFFF);
  static const Color lightSurfaceAlt = Color(0xFFF8FAFC);
  static const Color lightBorder = Color(0xFFE2E8F0);
  static const Color lightText = Color(0xFF0F172A);
  static const Color lightTextMuted = Color(0xFF64748B);

  // ---- Shape / motion --------------------------------------------------
  static const double radius = 16;
  static const double radiusSm = 12;
  static const double radiusLg = 24;
  static const Duration motion = Duration(milliseconds: 250);

  static const List<Color> chartPalette = [
    primary,
    info,
    success,
    amber,
    pink,
    teal,
  ];
}

/// Extra semantic colors that ColorScheme doesn't carry, exposed through the
/// theme so widgets adapt automatically to dark/light.
@immutable
class BrandColors extends ThemeExtension<BrandColors> {
  const BrandColors({
    required this.success,
    required this.warning,
    required this.danger,
    required this.info,
    required this.textMuted,
    required this.surfaceAlt,
    required this.border,
    required this.heroGradient,
  });

  final Color success;
  final Color warning;
  final Color danger;
  final Color info;
  final Color textMuted;
  final Color surfaceAlt;
  final Color border;
  final List<Color> heroGradient;

  static const BrandColors dark = BrandColors(
    success: DesignTokens.success,
    warning: DesignTokens.warning,
    danger: DesignTokens.danger,
    info: DesignTokens.info,
    textMuted: DesignTokens.darkTextMuted,
    surfaceAlt: DesignTokens.darkSurfaceAlt,
    border: DesignTokens.darkBorder,
    heroGradient: [Color(0xFF7C3AED), Color(0xFF6366F1), Color(0xFF0EA5E9)],
  );

  static const BrandColors light = BrandColors(
    success: DesignTokens.success,
    warning: DesignTokens.warning,
    danger: DesignTokens.danger,
    info: DesignTokens.info,
    textMuted: DesignTokens.lightTextMuted,
    surfaceAlt: DesignTokens.lightSurfaceAlt,
    border: DesignTokens.lightBorder,
    heroGradient: [Color(0xFF8B5CF6), Color(0xFF6366F1), Color(0xFF22D3EE)],
  );

  /// Convenience accessor: `context.brand`.
  static BrandColors of(BuildContext context) =>
      Theme.of(context).extension<BrandColors>() ?? dark;

  @override
  BrandColors copyWith({
    Color? success,
    Color? warning,
    Color? danger,
    Color? info,
    Color? textMuted,
    Color? surfaceAlt,
    Color? border,
    List<Color>? heroGradient,
  }) {
    return BrandColors(
      success: success ?? this.success,
      warning: warning ?? this.warning,
      danger: danger ?? this.danger,
      info: info ?? this.info,
      textMuted: textMuted ?? this.textMuted,
      surfaceAlt: surfaceAlt ?? this.surfaceAlt,
      border: border ?? this.border,
      heroGradient: heroGradient ?? this.heroGradient,
    );
  }

  @override
  BrandColors lerp(ThemeExtension<BrandColors>? other, double t) {
    if (other is! BrandColors) return this;
    return BrandColors(
      success: Color.lerp(success, other.success, t)!,
      warning: Color.lerp(warning, other.warning, t)!,
      danger: Color.lerp(danger, other.danger, t)!,
      info: Color.lerp(info, other.info, t)!,
      textMuted: Color.lerp(textMuted, other.textMuted, t)!,
      surfaceAlt: Color.lerp(surfaceAlt, other.surfaceAlt, t)!,
      border: Color.lerp(border, other.border, t)!,
      heroGradient: t < 0.5 ? heroGradient : other.heroGradient,
    );
  }
}

/// Ergonomic `context.brand` / `context.cs` accessors.
extension BrandContext on BuildContext {
  BrandColors get brand => BrandColors.of(this);
  ColorScheme get cs => Theme.of(this).colorScheme;
  TextTheme get text => Theme.of(this).textTheme;
}
