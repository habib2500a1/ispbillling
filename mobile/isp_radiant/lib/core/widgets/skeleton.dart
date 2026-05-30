import 'package:flutter/material.dart';

import '../theme/design_tokens.dart';

/// A shimmering placeholder block. Compose these to build skeleton screens
/// while data loads — no external shimmer package needed.
class Skeleton extends StatefulWidget {
  const Skeleton({
    super.key,
    this.width,
    this.height = 16,
    this.radius = DesignTokens.radiusSm,
    this.shape = BoxShape.rectangle,
  });

  const Skeleton.circle({super.key, double size = 44})
      : width = size,
        height = size,
        radius = 0,
        shape = BoxShape.circle;

  final double? width;
  final double height;
  final double radius;
  final BoxShape shape;

  @override
  State<Skeleton> createState() => _SkeletonState();
}

class _SkeletonState extends State<Skeleton> with SingleTickerProviderStateMixin {
  late final AnimationController _c =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 1200))..repeat();

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final base = context.brand.surfaceAlt;
    final highlight = Color.lerp(base, context.cs.onSurface, 0.08)!;
    return AnimatedBuilder(
      animation: _c,
      builder: (context, _) {
        return Container(
          width: widget.width,
          height: widget.height,
          decoration: BoxDecoration(
            shape: widget.shape,
            borderRadius:
                widget.shape == BoxShape.circle ? null : BorderRadius.circular(widget.radius),
            gradient: LinearGradient(
              begin: Alignment(-1 - _c.value * 2, 0),
              end: Alignment(1 - _c.value * 2, 0),
              colors: [base, highlight, base],
              stops: const [0.35, 0.5, 0.65],
            ),
          ),
        );
      },
    );
  }
}

/// A scrollable column of list-row skeletons (for history/ticket/package lists).
class SkeletonList extends StatelessWidget {
  const SkeletonList({super.key, this.count = 6, this.rowHeight = 72});
  final int count;
  final double rowHeight;

  @override
  Widget build(BuildContext context) {
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: count,
      separatorBuilder: (_, _) => const SizedBox(height: 10),
      itemBuilder: (_, _) => Container(
        height: rowHeight,
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: context.cs.surface,
          borderRadius: BorderRadius.circular(DesignTokens.radius),
          border: Border.all(color: context.brand.border),
        ),
        child: Row(
          children: const [
            Skeleton.circle(size: 40),
            SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Skeleton(width: 160, height: 14),
                  SizedBox(height: 8),
                  Skeleton(width: 100, height: 11),
                ],
              ),
            ),
            SizedBox(width: 12),
            Skeleton(width: 56, height: 16),
          ],
        ),
      ),
    );
  }
}

/// A card-shaped skeleton used to stand in for a stat/list card.
class SkeletonCard extends StatelessWidget {
  const SkeletonCard({super.key, this.height = 96});
  final double height;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: height,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: context.cs.surface,
        borderRadius: BorderRadius.circular(DesignTokens.radius),
        border: Border.all(color: context.brand.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: const [
          Skeleton(width: 40, height: 40, radius: 10),
          Skeleton(width: 80, height: 14),
          Skeleton(width: 120, height: 12),
        ],
      ),
    );
  }
}
