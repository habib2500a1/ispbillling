import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Holds the active [ThemeMode] and persists the user's choice.
/// Default is dark (premium SaaS look).
class ThemeController extends StateNotifier<ThemeMode> {
  ThemeController() : super(ThemeMode.dark) {
    _load();
  }

  static const _key = 'app_theme_mode';

  Future<void> _load() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_key);
      state = switch (raw) {
        'light' => ThemeMode.light,
        'system' => ThemeMode.system,
        _ => ThemeMode.dark,
      };
    } catch (_) {
      // keep dark default if storage is unavailable
    }
  }

  Future<void> set(ThemeMode mode) async {
    state = mode;
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_key, mode.name);
    } catch (_) {}
  }

  /// Convenience for a single light/dark switch.
  Future<void> toggle() => set(state == ThemeMode.dark ? ThemeMode.light : ThemeMode.dark);

  bool get isDark => state == ThemeMode.dark;
}

final themeControllerProvider =
    StateNotifierProvider<ThemeController, ThemeMode>((ref) => ThemeController());
