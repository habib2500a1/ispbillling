import '../models/login_role_config.dart';
import 'app_config.dart';
import 'server_config.dart';

/// Cached server config from /mobile/config — synced with website branding.
class RemoteConfig {
  RemoteConfig._();

  static Map<String, dynamic>? _raw;

  static Future<void> loadFrom(Map<String, dynamic> json) async {
    _raw = json;
  }

  static String get appName {
    final branding = _raw?['branding'] as Map?;
    return branding?['company_name']?.toString() ?? _raw?['app_name']?.toString() ?? AppConfig.appName;
  }

  static String? get logoUrl {
    final branding = _raw?['branding'] as Map?;
    return branding?['logo_url']?.toString();
  }

  static String get tagline {
    final branding = _raw?['branding'] as Map?;
    return branding?['tagline']?.toString() ?? '';
  }

  static String get supportPhone {
    final branding = _raw?['branding'] as Map?;
    return branding?['phone']?.toString() ?? '';
  }

  static String? get websiteUrl {
    final links = _raw?['links'] as Map?;
    final landing = links?['landing']?.toString();
    if (landing != null && landing.isNotEmpty) return landing;
    final branding = _raw?['branding'] as Map?;
    final site = branding?['website']?.toString();
    return (site != null && site.isNotEmpty) ? site : null;
  }

  static List<Map<String, dynamic>> get packages {
    final list = _raw?['packages'] as List<dynamic>? ?? [];
    return list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  static List<Map<String, dynamic>> get notices {
    final list = _raw?['notices'] as List<dynamic>? ?? [];
    return list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  static bool get bkashEnabled => (_raw?['features'] as Map?)?['bkash'] == true;

  static String get ticketDepartmentDefault {
    final defaults = (_raw?['ticket'] as Map?)?['defaults'] as Map?;
    return defaults?['department']?.toString() ?? 'technical_support';
  }

  static String get ticketPriorityDefault {
    final defaults = (_raw?['ticket'] as Map?)?['defaults'] as Map?;
    return defaults?['priority']?.toString() ?? 'medium';
  }

  static bool get offlineSync => (_raw?['features'] as Map?)?['offline_sync'] == true;
  static bool get realtimeWs => (_raw?['features'] as Map?)?['realtime_ws'] == true;
  static bool get aiAssistant => (_raw?['features'] as Map?)?['ai_assistant'] == true;
  static bool get networkControl => (_raw?['features'] as Map?)?['network_control'] == true;
  static bool get speedTestEnabled => (_raw?['features'] as Map?)?['speed_test'] != false;

  static Map<String, dynamic> get speedTest {
    final fromServer = _raw?['speed_test'] as Map?;
    if (fromServer != null && fromServer.isNotEmpty) {
      return Map<String, dynamic>.from(fromServer);
    }
    return const {
      'enabled': true,
      'ping_url': 'https://www.speedtest.sg/speedtest/ping.php',
      'download_url': 'https://www.speedtest.sg/speedtest/download.php',
      'upload_url': 'https://www.speedtest.sg/speedtest/upload.php',
    };
  }

  static String get speedTestPingUrl => speedTest['ping_url']?.toString() ?? '';
  static String get speedTestDownloadUrl => speedTest['download_url']?.toString() ?? '';
  static String get speedTestUploadUrl => speedTest['upload_url']?.toString() ?? '';

  static bool get mfsSmsStaff => (_raw?['features'] as Map?)?['mfs_sms_staff'] == true;
  static bool get pushFcm => (_raw?['features'] as Map?)?['push_fcm'] == true;
  static bool get biometricLogin => (_raw?['features'] as Map?)?['biometric_login'] == true;

  static String get mfsVerifyApkUrl {
    final links = _raw?['links'] as Map?;
    return links?['apk_mfs_verify']?.toString() ?? '';
  }

  static Map<String, dynamic> get phases => Map<String, dynamic>.from((_raw?['phases'] as Map?) ?? {});
  static Map<String, dynamic> get branding => Map<String, dynamic>.from((_raw?['branding'] as Map?) ?? {});

  static Map<String, dynamic> get login => Map<String, dynamic>.from((_raw?['login'] as Map?) ?? {});

  static String get loginHubUrl {
    final login = _raw?['login'] as Map?;
    final fromLogin = login?['hub_url']?.toString();
    if (fromLogin != null && fromLogin.isNotEmpty) return fromLogin;
    final links = _raw?['links'] as Map?;
    final fromLinks = links?['login_hub']?.toString();
    if (fromLinks != null && fromLinks.isNotEmpty) return fromLinks;
    return '${ServerConfig.siteRootFromApiBase()}/login';
  }

  static String get mobileLoginApiUrl {
    final login = _raw?['login'] as Map?;
    final url = login?['api_url']?.toString();
    if (url != null && url.isNotEmpty) return url;
    return '${ServerConfig.apiBaseUrl}/mobile/login';
  }

  static String? get payUrl {
    final links = _raw?['links'] as Map?;
    return links?['pay']?.toString();
  }

  static String? get resellerLoginUrl {
    final links = _raw?['links'] as Map?;
    final fromLinks = links?['reseller_login']?.toString();
    if (fromLinks != null && fromLinks.isNotEmpty) return fromLinks;
    for (final role in loginRoles) {
      if (role.id == 'reseller' && role.webUrl != null && role.webUrl!.isNotEmpty) {
        return role.webUrl;
      }
    }
    return null;
  }

  static List<LoginRoleConfig> get loginRoles {
    final roles = login['roles'] as List<dynamic>?;
    if (roles == null || roles.isEmpty) return LoginRoleConfig.defaults();
    return roles
        .map((e) => LoginRoleConfig.fromJson(Map<String, dynamic>.from(e as Map)))
        .where((r) => r.enabled && r.id.isNotEmpty)
        .toList();
  }
}
