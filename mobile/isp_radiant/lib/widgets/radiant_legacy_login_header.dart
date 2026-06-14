import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../config/remote_config.dart';

/// Top-left blue arc header matching the legacy Radiant / SOFTIFY login screen.
class RadiantLegacyLoginHeader extends StatelessWidget {
  const RadiantLegacyLoginHeader({super.key});

  static const Color primaryBlue = Color(0xFF4267B2);

  @override
  Widget build(BuildContext context) {
    final company = RemoteConfig.appName.trim();
    final parts = _splitCompanyName(company);

    return SizedBox(
      height: 210,
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
            top: MediaQuery.paddingOf(context).top + 18,
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
                const SizedBox(height: 14),
                _BrandMark(title: parts.$1, subtitle: parts.$2, logoUrl: RemoteConfig.logoUrl),
              ],
            ),
          ),
        ],
      ),
    );
  }

  (String, String) _splitCompanyName(String name) {
    if (name.isEmpty) {
      return ('RADIANT', 'COMMUNICATIONS LTD');
    }

    final upper = name.toUpperCase();
    if (upper.contains('COMMUNICATIONS')) {
      final idx = upper.indexOf('COMMUNICATIONS');
      final title = upper.substring(0, idx).trim();
      final subtitle = upper.substring(idx).trim();
      return (title.isEmpty ? 'RADIANT' : title, subtitle);
    }

    final words = name.split(RegExp(r'\s+'));
    if (words.length <= 1) {
      return (words.first.toUpperCase(), 'COMMUNICATIONS LTD');
    }

    return (words.first.toUpperCase(), words.skip(1).join(' ').toUpperCase());
  }
}

class _BrandMark extends StatelessWidget {
  const _BrandMark({required this.title, required this.subtitle, this.logoUrl});

  final String title;
  final String subtitle;
  final String? logoUrl;

  @override
  Widget build(BuildContext context) {
    if (logoUrl != null && logoUrl!.isNotEmpty) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Image.network(
            logoUrl!,
            height: 54,
            fit: BoxFit.contain,
            alignment: Alignment.centerLeft,
            errorBuilder: (_, __, ___) => _TextLogo(title: title, subtitle: subtitle),
          ),
        ],
      );
    }

    return _TextLogo(title: title, subtitle: subtitle);
  }
}

class _TextLogo extends StatelessWidget {
  const _TextLogo({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        CustomPaint(
          painter: _LogoArcPainter(),
          child: Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Text(
              title,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 34,
                fontWeight: FontWeight.w800,
                letterSpacing: 1.2,
                height: 1,
              ),
            ),
          ),
        ),
        const SizedBox(height: 2),
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
    );
  }
}

class _LogoArcPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = Colors.white
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2.2;

    final path = Path()
      ..moveTo(0, 0)
      ..quadraticBezierTo(size.width * 0.45, -8, size.width * 0.95, 2);

    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
