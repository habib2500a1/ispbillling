import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../theme/design_tokens.dart';
import '../theme/theme_controller.dart';
import 'cards.dart';

/// A drop-in "Appearance" control (Dark / Light / System) backed by the
/// persisted [themeControllerProvider]. Insert into any settings/profile list.
class ThemeToggleTile extends ConsumerWidget {
  const ThemeToggleTile({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final mode = ref.watch(themeControllerProvider);
    final controller = ref.read(themeControllerProvider.notifier);

    return AppCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(9),
                decoration: BoxDecoration(
                  color: DesignTokens.primary.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(DesignTokens.radiusSm),
                ),
                child: const Icon(Icons.palette_rounded, color: DesignTokens.primary, size: 20),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text('Appearance',
                    style: context.text.bodyLarge?.copyWith(fontWeight: FontWeight.w700)),
              ),
            ],
          ),
          const SizedBox(height: 12),
          SegmentedButton<ThemeMode>(
            segments: const [
              ButtonSegment(value: ThemeMode.light, icon: Icon(Icons.light_mode_rounded), label: Text('Light')),
              ButtonSegment(value: ThemeMode.dark, icon: Icon(Icons.dark_mode_rounded), label: Text('Dark')),
              ButtonSegment(value: ThemeMode.system, icon: Icon(Icons.smartphone_rounded), label: Text('Auto')),
            ],
            selected: {mode},
            showSelectedIcon: false,
            onSelectionChanged: (s) => controller.set(s.first),
          ),
        ],
      ),
    );
  }
}
