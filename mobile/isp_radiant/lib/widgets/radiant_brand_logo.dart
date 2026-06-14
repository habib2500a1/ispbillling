import 'package:flutter/material.dart';

import '../config/remote_config.dart';

/// Radiant logo — bundled asset with optional server override.
class RadiantBrandLogo extends StatelessWidget {
  const RadiantBrandLogo({
    super.key,
    this.height = 54,
    this.alignment = Alignment.centerLeft,
    this.color,
  });

  final double height;
  final Alignment alignment;
  final Color? color;

  static const _asset = 'assets/images/radiant_logo.png';

  @override
  Widget build(BuildContext context) {
    final remote = RemoteConfig.logoUrl?.trim();
    if (remote != null && remote.isNotEmpty) {
      return Image.network(
        remote,
        height: height,
        fit: BoxFit.contain,
        alignment: alignment,
        color: color,
        errorBuilder: (_, __, ___) => _assetLogo(),
      );
    }
    return _assetLogo();
  }

  Widget _assetLogo() {
    return Image.asset(
      _asset,
      height: height,
      fit: BoxFit.contain,
      alignment: alignment,
      color: color,
      errorBuilder: (_, __, ___) => const SizedBox.shrink(),
    );
  }
}
