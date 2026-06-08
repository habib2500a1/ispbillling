import 'package:flutter/material.dart';

import '../radiant_tokens.dart';

class RadiantSkeleton extends StatelessWidget {
  const RadiantSkeleton({
    super.key,
    this.width,
    this.height = 14,
    this.radius = RadiantTokens.radiusXs,
  });

  final double? width;
  final double height;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final base = context.isDark ? RadiantTokens.darkSurface : const Color(0xFFE4E4E7);
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0.35, end: 0.65),
      duration: const Duration(milliseconds: 900),
      curve: Curves.easeInOut,
      builder: (_, value, __) {
        return Container(
          width: width,
          height: height,
          decoration: BoxDecoration(
            color: base.withValues(alpha: value),
            borderRadius: BorderRadius.circular(radius),
          ),
        );
      },
      onEnd: () {},
    );
  }
}

class RadiantSkeletonBlock extends StatelessWidget {
  const RadiantSkeletonBlock({super.key, this.height = 120});

  final double height;

  @override
  Widget build(BuildContext context) {
    return RadiantSkeleton(height: height, radius: RadiantTokens.radiusMd);
  }
}

class RadiantDashboardSkeleton extends StatelessWidget {
  const RadiantDashboardSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        const RadiantSkeleton(width: 160, height: 22),
        const SizedBox(height: 8),
        const RadiantSkeleton(width: 100, height: 14),
        const SizedBox(height: 24),
        const RadiantSkeletonBlock(height: 140),
        const SizedBox(height: 16),
        Row(
          children: const [
            Expanded(child: RadiantSkeletonBlock(height: 96)),
            SizedBox(width: 12),
            Expanded(child: RadiantSkeletonBlock(height: 96)),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: const [
            Expanded(child: RadiantSkeletonBlock(height: 96)),
            SizedBox(width: 12),
            Expanded(child: RadiantSkeletonBlock(height: 96)),
          ],
        ),
        const SizedBox(height: 16),
        const RadiantSkeletonBlock(height: 200),
      ],
    );
  }
}
