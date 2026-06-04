import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/theme/app_themes.dart';
import 'core/theme/theme_controller.dart';
import 'screens/splash_gate.dart';

/// Root app. Theme (dark/light) is driven by [themeControllerProvider] and the
/// user's choice is persisted, so the whole app switches and remembers.
class IspRadiantApp extends ConsumerWidget {
  const IspRadiantApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final mode = ref.watch(themeControllerProvider);
    return MaterialApp(
      title: 'RADIANT ISP',
      debugShowCheckedModeBanner: false,
      theme: AppThemes.light,
      darkTheme: AppThemes.dark,
      themeMode: mode,
      home: const SplashGate(),
    );
  }
}
