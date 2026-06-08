import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';

import '../core/theme/design_tokens.dart';
import 'radiant_tokens.dart';

/// Builds Material 3 themes using Radiant 3.0 tokens (replaces legacy palette).
class RadiantTheme {
  RadiantTheme._();

  static ThemeData get light => _build(Brightness.light);
  static ThemeData get dark => _build(Brightness.dark);

  static ThemeData _build(Brightness brightness) {
    final isDark = brightness == Brightness.dark;
    final bg = isDark ? RadiantTokens.darkBg : RadiantTokens.lightBg;
    final surface = isDark ? RadiantTokens.darkSurface : RadiantTokens.lightSurface;
    final onSurface = isDark ? RadiantTokens.darkText : RadiantTokens.lightText;
    final muted = isDark ? RadiantTokens.darkMuted : RadiantTokens.lightMuted;
    final border = isDark ? RadiantTokens.darkBorder : RadiantTokens.lightBorder;

    final scheme = ColorScheme(
      brightness: brightness,
      primary: RadiantTokens.brand,
      onPrimary: Colors.white,
      primaryContainer: RadiantTokens.brandDeep,
      onPrimaryContainer: Colors.white,
      secondary: RadiantTokens.accent,
      onSecondary: Colors.white,
      tertiary: RadiantTokens.accentCyan,
      surface: surface,
      onSurface: onSurface,
      surfaceContainerHighest: isDark ? RadiantTokens.darkSurface : const Color(0xFFF4F4F5),
      error: RadiantTokens.danger,
      onError: Colors.white,
      outline: border,
    );

    final base = ThemeData(useMaterial3: true, brightness: brightness, colorScheme: scheme);
    final brandExt = isDark ? RadiantBrand.dark : RadiantBrand.light;

    // Keep BrandColors shim for unmigrated screens during phased rebuild.
    final legacyBrand = isDark ? BrandColors.dark : BrandColors.light;

    return base.copyWith(
      scaffoldBackgroundColor: bg,
      canvasColor: bg,
      extensions: [brandExt, legacyBrand],
      textTheme: GoogleFonts.interTextTheme(base.textTheme).apply(
        bodyColor: onSurface,
        displayColor: onSurface,
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: Colors.transparent,
        foregroundColor: onSurface,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleTextStyle: GoogleFonts.inter(
          color: onSurface,
          fontSize: 18,
          fontWeight: FontWeight.w600,
          letterSpacing: -0.3,
        ),
        systemOverlayStyle: isDark
            ? SystemUiOverlayStyle.light.copyWith(statusBarColor: Colors.transparent)
            : SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: Colors.transparent,
        indicatorColor: RadiantTokens.brand.withValues(alpha: 0.14),
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        height: 64,
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return TextStyle(
            fontSize: 10,
            fontWeight: selected ? FontWeight.w600 : FontWeight.w500,
            color: selected ? RadiantTokens.brand : muted,
            letterSpacing: 0.1,
          );
        }),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return IconThemeData(
            color: selected ? RadiantTokens.brand : muted,
            size: 22,
          );
        }),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        color: surface,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(RadiantTokens.radiusMd),
          side: BorderSide(color: border.withValues(alpha: 0.8)),
        ),
        margin: EdgeInsets.zero,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: RadiantTokens.brand,
          foregroundColor: Colors.white,
          elevation: 0,
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: isDark ? RadiantTokens.darkSurface : const Color(0xFFF4F4F5),
        hintStyle: TextStyle(color: muted),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
          borderSide: BorderSide(color: border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
          borderSide: BorderSide(color: border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
          borderSide: const BorderSide(color: RadiantTokens.brand, width: 1.5),
        ),
      ),
      dividerTheme: DividerThemeData(color: border, thickness: 1),
      progressIndicatorTheme: const ProgressIndicatorThemeData(color: RadiantTokens.brand),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: surface,
        contentTextStyle: TextStyle(color: onSurface),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
          side: BorderSide(color: border),
        ),
      ),
    );
  }
}
