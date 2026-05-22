import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../config/app_branding.dart';

/// Company logo + RCL SMS title (same branding as Radiant admin app).
class BrandHeader extends StatelessWidget {
  const BrandHeader({super.key, this.compact = false, this.versionLabel});

  final bool compact;
  final String? versionLabel;

  static const _brandGreen = Color(0xFF059669);

  @override
  Widget build(BuildContext context) {
    final b = AppBranding.instance;

    return Column(
      children: [
        _logo(b),
        SizedBox(height: compact ? 8 : 12),
        Text(
          b.appName,
          style: TextStyle(
            fontSize: compact ? 20 : 24,
            fontWeight: FontWeight.bold,
            color: _brandGreen,
            letterSpacing: 0.5,
          ),
        ),
        if (b.companyName.isNotEmpty) ...[
          const SizedBox(height: 4),
          Text(
            b.companyName,
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: compact ? 12 : 13,
              color: Colors.grey.shade700,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
        if (b.tagline.isNotEmpty && !compact) ...[
          const SizedBox(height: 2),
          Text(
            b.tagline,
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
          ),
        ],
        if (versionLabel != null && versionLabel!.isNotEmpty) ...[
          const SizedBox(height: 6),
          Text(
            'v$versionLabel',
            style: TextStyle(fontSize: 11, color: Colors.grey.shade500, fontWeight: FontWeight.w600),
          ),
        ],
      ],
    );
  }

  static Future<String> loadVersionLabel() async {
    final info = await PackageInfo.fromPlatform();
    return '${info.version}+${info.buildNumber}';
  }

  Widget _logo(AppBranding b) {
    final url = b.logoUrl;
    if (url != null && url.isNotEmpty) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(12),
        child: Image.network(
          url,
          height: compact ? 48 : 64,
          fit: BoxFit.contain,
          errorBuilder: (_, __, ___) => _assetLogo(compact),
        ),
      );
    }

    return _assetLogo(compact);
  }

  Widget _assetLogo(bool compact) {
    return Image.asset(
      'assets/branding/logo.png',
      height: compact ? 40 : 56,
      fit: BoxFit.contain,
      errorBuilder: (_, __, ___) => Icon(
        Icons.sms_outlined,
        size: compact ? 40 : 52,
        color: _brandGreen,
      ),
    );
  }
}
