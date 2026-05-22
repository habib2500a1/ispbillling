import 'app_config.dart';

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

  static bool get mfsSmsStaff => (_raw?['features'] as Map?)?['mfs_sms_staff'] == true;

  static String get mfsVerifyApkUrl {
    final links = _raw?['links'] as Map?;
    return links?['apk_mfs_verify']?.toString() ?? '';
  }

  static Map<String, dynamic> get phases => Map<String, dynamic>.from((_raw?['phases'] as Map?) ?? {});
  static Map<String, dynamic> get branding => Map<String, dynamic>.from((_raw?['branding'] as Map?) ?? {});
}
