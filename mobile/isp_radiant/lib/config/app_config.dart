class AppConfig {
  /// Change to your server URL before building APK.
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://bill.flixbd.xyz/api/v1',
  );

  static const String appName = 'RADIANT ISP';
}
