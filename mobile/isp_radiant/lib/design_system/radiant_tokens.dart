import 'package:flutter/material.dart';

/// Radiant 3.0 — enterprise SaaS design tokens (Stripe × Linear × Revolut).
/// Replaces legacy ISP blue (#1565C0) visual language entirely.
class RadiantTokens {
  RadiantTokens._();

  // Brand — indigo/violet spectrum (NOT legacy ISP blue)
  static const Color brand = Color(0xFF6366F1);
  static const Color brandDeep = Color(0xFF4F46E5);
  static const Color brandSoft = Color(0xFFA5B4FC);
  static const Color accent = Color(0xFF8B5CF6);
  static const Color accentCyan = Color(0xFF06B6D4);

  static const Color success = Color(0xFF22C55E);
  static const Color warning = Color(0xFFF59E0B);
  static const Color danger = Color(0xFFEF4444);
  static const Color info = Color(0xFF38BDF8);

  // Light — premium SaaS
  static const Color lightBg = Color(0xFFFAFAFA);
  static const Color lightSurface = Color(0xFFFFFFFF);
  static const Color lightSurfaceGlass = Color(0xCCFFFFFF);
  static const Color lightBorder = Color(0xFFE4E4E7);
  static const Color lightText = Color(0xFF09090B);
  static const Color lightMuted = Color(0xFF71717A);

  // Dark — OLED friendly
  static const Color darkBg = Color(0xFF09090B);
  static const Color darkSurface = Color(0xFF18181B);
  static const Color darkSurfaceGlass = Color(0x9918181B);
  static const Color darkBorder = Color(0xFF27272A);
  static const Color darkText = Color(0xFFFAFAFA);
  static const Color darkMuted = Color(0xFFA1A1AA);

  static const double radiusXs = 8;
  static const double radiusSm = 12;
  static const double radiusMd = 16;
  static const double radiusLg = 22;
  static const double radiusXl = 28;

  static const Duration fast = Duration(milliseconds: 180);
  static const Duration normal = Duration(milliseconds: 280);
  static const Duration slow = Duration(milliseconds: 420);

  static const List<Color> meshLight = [
    Color(0xFFEEF2FF),
    Color(0xFFF5F3FF),
    Color(0xFFECFEFF),
  ];

  static const List<Color> meshDark = [
    Color(0xFF1E1B4B),
    Color(0xFF18181B),
    Color(0xFF0C4A6E),
  ];

  static const List<Color> chartPalette = [
    brand,
    accentCyan,
    success,
    warning,
    accent,
    info,
  ];
}

@immutable
class RadiantBrand extends ThemeExtension<RadiantBrand> {
  const RadiantBrand({
    required this.muted,
    required this.border,
    required this.surfaceAlt,
    required this.glass,
    required this.meshGradient,
    required this.glow,
    required this.success,
    required this.warning,
    required this.danger,
    required this.info,
  });

  final Color muted;
  final Color border;
  final Color surfaceAlt;
  final Color glass;
  final List<Color> meshGradient;
  final Color glow;
  final Color success;
  final Color warning;
  final Color danger;
  final Color info;

  static const light = RadiantBrand(
    muted: RadiantTokens.lightMuted,
    border: RadiantTokens.lightBorder,
    surfaceAlt: Color(0xFFF4F4F5),
    glass: RadiantTokens.lightSurfaceGlass,
    meshGradient: RadiantTokens.meshLight,
    glow: Color(0x336366F1),
    success: RadiantTokens.success,
    warning: RadiantTokens.warning,
    danger: RadiantTokens.danger,
    info: RadiantTokens.info,
  );

  static const dark = RadiantBrand(
    muted: RadiantTokens.darkMuted,
    border: RadiantTokens.darkBorder,
    surfaceAlt: Color(0xFF27272A),
    glass: RadiantTokens.darkSurfaceGlass,
    meshGradient: RadiantTokens.meshDark,
    glow: Color(0x446366F1),
    success: RadiantTokens.success,
    warning: RadiantTokens.warning,
    danger: RadiantTokens.danger,
    info: RadiantTokens.info,
  );

  static RadiantBrand of(BuildContext context) =>
      Theme.of(context).extension<RadiantBrand>() ?? dark;

  @override
  RadiantBrand copyWith({
    Color? muted,
    Color? border,
    Color? surfaceAlt,
    Color? glass,
    List<Color>? meshGradient,
    Color? glow,
    Color? success,
    Color? warning,
    Color? danger,
    Color? info,
  }) {
    return RadiantBrand(
      muted: muted ?? this.muted,
      border: border ?? this.border,
      surfaceAlt: surfaceAlt ?? this.surfaceAlt,
      glass: glass ?? this.glass,
      meshGradient: meshGradient ?? this.meshGradient,
      glow: glow ?? this.glow,
      success: success ?? this.success,
      warning: warning ?? this.warning,
      danger: danger ?? this.danger,
      info: info ?? this.info,
    );
  }

  @override
  RadiantBrand lerp(covariant ThemeExtension<RadiantBrand>? other, double t) {
    if (other is! RadiantBrand) return this;
    return RadiantBrand(
      muted: Color.lerp(muted, other.muted, t)!,
      border: Color.lerp(border, other.border, t)!,
      surfaceAlt: Color.lerp(surfaceAlt, other.surfaceAlt, t)!,
      glass: Color.lerp(glass, other.glass, t)!,
      meshGradient: t < 0.5 ? meshGradient : other.meshGradient,
      glow: Color.lerp(glow, other.glow, t)!,
      success: Color.lerp(success, other.success, t)!,
      warning: Color.lerp(warning, other.warning, t)!,
      danger: Color.lerp(danger, other.danger, t)!,
      info: Color.lerp(info, other.info, t)!,
    );
  }
}

extension RadiantContext on BuildContext {
  RadiantBrand get radiant => RadiantBrand.of(this);
  bool get isDark => Theme.of(this).brightness == Brightness.dark;
}
