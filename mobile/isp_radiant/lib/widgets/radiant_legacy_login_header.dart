import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../config/remote_config.dart';
import 'radiant_brand_logo.dart';

/// Top-left blue arc header matching the legacy Radiant / SOFTIFY login screen.
class RadiantLegacyLoginHeader extends StatelessWidget {
  const RadiantLegacyLoginHeader({super.key});

  static const Color primaryBlue = Color(0xFF4267B2);

  @override
  Widget build(BuildContext context) {
    final subtitle = _companySubtitle();

    return SizedBox(
      height: 200,
      width: double.infinity,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned(
            top: -130,
            left: -110,
            child: Container(
              width: 340,
              height: 340,
              decoration: const BoxDecoration(
                color: primaryBlue,
                shape: BoxShape.circle,
              ),
            ),
          ),
          Positioned(
            top: MediaQuery.paddingOf(context).top + 16,
            left: 28,
            right: 24,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Welcome,',
                  style: GoogleFonts.pacifico(
                    color: Colors.white,
                    fontSize: 34,
                    height: 1.1,
                  ),
                ),
                if (subtitle.isNotEmpty) ...[
                  const SizedBox(height: 10),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      letterSpacing: 0.8,
                    ),
                  ),
                ],
              ],
            ),
          ),
          Positioned(
            left: 24,
            right: 24,
            bottom: 0,
            child: const RadiantBrandLogo(height: 56),
          ),
        ],
      ),
    );
  }

  String _companySubtitle() {
    final branding = RemoteConfig.branding;
    final company = branding['company_name']?.toString().trim() ?? '';
    if (company.isEmpty) return 'COMMUNICATIONS LTD';

    final upper = company.toUpperCase();
    if (upper.contains('COMMUNICATIONS')) {
      final idx = upper.indexOf('COMMUNICATIONS');
      return upper.substring(idx).trim();
    }
    return company;
  }
}
