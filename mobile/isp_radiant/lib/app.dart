import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'design_system/radiant_theme.dart';
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
      title: 'Radiant',
      debugShowCheckedModeBanner: false,
      theme: RadiantTheme.light,
      darkTheme: RadiantTheme.dark,
      themeMode: mode,
      home: const SplashGate(),
    );
  }
}
