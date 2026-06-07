import 'package:shared_preferences/shared_preferences.dart';

import 'app_config.dart';

/// Runtime server URL — users can change domain without reinstalling APK.
class ServerConfig {
  ServerConfig._();

  static const _prefKey = 'api_base_url';
  static String _apiBaseUrl = AppConfig.defaultApiBaseUrl;
  static bool _initialized = false;

  static String get apiBaseUrl => _apiBaseUrl;

  static Future<void> init() async {
    if (_initialized) return;
    _initialized = true;
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString(_prefKey);
    if (saved != null && saved.trim().isNotEmpty) {
      _apiBaseUrl = normalizeApiBaseUrl(saved.trim());
    }
  }

  static Future<void> saveApiBaseUrl(String input) async {
    final normalized = normalizeApiBaseUrl(input);
    _apiBaseUrl = normalized;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_prefKey, normalized);
  }

  static Future<void> clearSaved() async {
    _apiBaseUrl = AppConfig.defaultApiBaseUrl;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_prefKey);
  }

  static String normalizeApiBaseUrl(String input) {
    var url = input.trim();
    if (url.isEmpty) return AppConfig.defaultApiBaseUrl;
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
      url = 'https://$url';
    }
    url = url.replaceAll(RegExp(r'/+$'), '');
    if (url.endsWith('/api/v1')) {
      return url;
    }
    if (url.endsWith('/api')) {
      return '$url/v1';
    }
    return '$url/api/v1';
  }

  static String siteRootFromApiBase() {
    return _apiBaseUrl.replaceFirst(RegExp(r'/api/v1/?$'), '');
  }
}
