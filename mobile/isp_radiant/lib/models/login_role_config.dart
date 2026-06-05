/// Sign-in role from GET /api/v1/mobile/config → login.roles
class LoginRoleConfig {
  const LoginRoleConfig({
    required this.id,
    required this.label,
    required this.description,
    required this.enabled,
    required this.mode,
    this.webUrl,
  });

  final String id;
  final String label;
  final String description;
  final bool enabled;
  /// native = app form + POST /mobile/login; web = in-app browser to web_url
  final String mode;
  final String? webUrl;

  bool get isNative => mode == 'native';
  bool get isWeb => mode == 'web';

  static LoginRoleConfig fromJson(Map<String, dynamic> json) {
    return LoginRoleConfig(
      id: json['id']?.toString() ?? '',
      label: json['label']?.toString() ?? '',
      description: json['description']?.toString() ?? '',
      enabled: json['enabled'] == true,
      mode: json['mode']?.toString() ?? 'native',
      webUrl: json['web_url']?.toString(),
    );
  }

  static List<LoginRoleConfig> defaults() => const [
        LoginRoleConfig(
          id: 'customer',
          label: 'Customer portal',
          description: 'Bills, usage, speed test, tickets',
          enabled: true,
          mode: 'native',
        ),
        LoginRoleConfig(
          id: 'staff',
          label: 'Admin / staff',
          description: 'Billing desk, subscribers, network',
          enabled: true,
          mode: 'native',
        ),
        LoginRoleConfig(
          id: 'reseller',
          label: 'Reseller / partner',
          description: 'Collections and partner dashboard',
          enabled: true,
          mode: 'web',
          webUrl: null,
        ),
      ];
}
