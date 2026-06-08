import 'package:flutter/material.dart';

import '../radiant_tokens.dart';

/// Embedded tab header — mesh background + actions (replaces IspUiKit.gradientHeader).
class RadiantScreenHeader extends StatelessWidget {
  const RadiantScreenHeader({
    super.key,
    required this.title,
    this.subtitle,
    this.trailing = const [],
    this.child,
    this.compact = false,
  });

  final String title;
  final String? subtitle;
  final List<Widget> trailing;
  final Widget? child;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final pad = MediaQuery.paddingOf(context).top;
    final brand = context.radiant;
    final text = Theme.of(context).textTheme;

    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: brand.meshGradient,
        ),
        borderRadius: const BorderRadius.vertical(bottom: Radius.circular(RadiantTokens.radiusXl)),
      ),
      child: Stack(
        children: [
          Positioned(
            right: -30,
            top: 0,
            child: Container(
              width: 140,
              height: 140,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: RadiantTokens.brand.withValues(alpha: 0.15),
              ),
            ),
          ),
          Padding(
            padding: EdgeInsets.fromLTRB(16, pad + (compact ? 8 : 12), 8, compact ? 14 : 20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            title,
                            style: text.titleLarge?.copyWith(
                              fontWeight: FontWeight.w800,
                              letterSpacing: -0.4,
                            ),
                          ),
                          if (subtitle != null && subtitle!.isNotEmpty)
                            Padding(
                              padding: const EdgeInsets.only(top: 4),
                              child: Text(
                                subtitle!,
                                style: text.bodySmall?.copyWith(color: brand.muted),
                              ),
                            ),
                        ],
                      ),
                    ),
                    ...trailing,
                  ],
                ),
                if (child != null) ...[const SizedBox(height: 14), child!],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class RadiantHeaderIcon extends StatelessWidget {
  const RadiantHeaderIcon({super.key, required this.icon, required this.onPressed, this.tooltip});

  final IconData icon;
  final VoidCallback onPressed;
  final String? tooltip;

  @override
  Widget build(BuildContext context) {
    return IconButton(
      icon: Icon(icon, color: context.isDark ? Colors.white : RadiantTokens.brandDeep),
      tooltip: tooltip,
      onPressed: onPressed,
    );
  }
}

class RadiantSearchField extends StatelessWidget {
  const RadiantSearchField({
    super.key,
    required this.controller,
    this.hint = 'Search…',
    this.loading = false,
    this.onSearch,
    this.onClear,
  });

  final TextEditingController controller;
  final String hint;
  final bool loading;
  final VoidCallback? onSearch;
  final VoidCallback? onClear;

  @override
  Widget build(BuildContext context) {
    final brand = context.radiant;
    return TextField(
      controller: controller,
      textInputAction: TextInputAction.search,
      onSubmitted: (_) => onSearch?.call(),
      decoration: InputDecoration(
        hintText: hint,
        prefixIcon: loading
            ? const Padding(
                padding: EdgeInsets.all(12),
                child: SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)),
              )
            : Icon(Icons.search_rounded, color: brand.muted),
        suffixIcon: controller.text.isNotEmpty
            ? IconButton(icon: const Icon(Icons.close_rounded), onPressed: onClear ?? () => controller.clear())
            : (onSearch != null ? IconButton(icon: const Icon(Icons.arrow_forward_rounded), onPressed: onSearch) : null),
        filled: true,
        fillColor: context.isDark ? RadiantTokens.darkSurface : Colors.white,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
          borderSide: BorderSide(color: brand.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
          borderSide: BorderSide(color: brand.border),
        ),
      ),
    );
  }
}

class RadiantFormSection extends StatelessWidget {
  const RadiantFormSection({super.key, required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(title, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
        const SizedBox(height: 10),
        child,
      ],
    );
  }
}
