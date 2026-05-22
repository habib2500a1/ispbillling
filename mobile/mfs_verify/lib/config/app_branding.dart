import 'dart:convert';

import 'package:http/http.dart' as http;

import '../utils/api_url_normalizer.dart';

/// Synced with website /api/v1/mobile/config (company logo + name).
class AppBranding {
  AppBranding({
    this.companyName = 'Radiant Communications Ltd',
    this.appName = 'RCL SMS',
    this.tagline = '',
    this.logoUrl,
  });

  final String companyName;
  final String appName;
  final String tagline;
  final String? logoUrl;

  static AppBranding _cached = AppBranding();
  static AppBranding get instance => _cached;

  static Future<AppBranding> loadFromApiBase(String? apiBase) async {
    if (apiBase == null || apiBase.trim().isEmpty) {
      return _cached;
    }

    try {
      final base = ApiUrlNormalizer.normalize(apiBase.trim());
      final root = base.replaceFirst(RegExp(r'/api/v1/?$'), '');
      final uri = Uri.parse('$root/api/v1/mobile/config');
      final res = await http.get(uri).timeout(const Duration(seconds: 12));
      if (res.statusCode < 200 || res.statusCode >= 300) {
        return _cached;
      }

      final json = jsonDecode(res.body) as Map<String, dynamic>;
      final branding = json['branding'] as Map<String, dynamic>? ?? {};

      _cached = AppBranding(
        companyName: branding['company_name']?.toString() ?? json['app_name']?.toString() ?? _cached.companyName,
        appName: 'RCL SMS',
        tagline: branding['tagline']?.toString() ?? '',
        logoUrl: branding['logo_url']?.toString(),
      );
    } catch (_) {
      /* keep previous cache */
    }

    return _cached;
  }
}
