class AppConfig {
  /// Baked at build time; users can override in app via Server settings.
  static const String defaultApiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://anetbd.com/api/v1',
  );

  static const String appName = 'Radiant';

  /// Build-time gate for "Server settings" on login hub. Keep false for production single-domain APK.
  static const bool allowServerSetup = bool.fromEnvironment(
    'ALLOW_SERVER_SETUP',
    defaultValue: false,
  );
}
