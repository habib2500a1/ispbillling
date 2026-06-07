class AppConfig {
  /// Baked at build time; users can override in app via Server settings.
  static const String defaultApiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://bill.flixbd.xyz/api/v1',
  );

  static const String appName = 'RADIANT ISP';
}
