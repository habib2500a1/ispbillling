import 'dart:io';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:uuid/uuid.dart';

import '../config/remote_config.dart';
import 'api_service.dart';

/// Push device registration (Phase 2) — stable device id; ready for FCM token swap.
class PushService {
  PushService(this._api);

  final ApiService _api;
  static const _prefsKey = 'push_device_token';

  Future<void> registerAfterLogin({
    required String role,
    String? staffMode,
  }) async {
    try {
      if (Platform.isAndroid) {
        await Permission.notification.request();
      }
      final token = await _resolveToken();
      await _api.registerPushDevice(token, role: role, staffMode: staffMode);
    } catch (_) {}
  }

  Future<String> _resolveToken() async {
    final prefs = await SharedPreferences.getInstance();
    final existing = prefs.getString(_prefsKey);
    if (existing != null && existing.isNotEmpty) return existing;

    var token = 'radiant-${const Uuid().v4()}';
    if (RemoteConfig.pushFcm) {
      token = 'fcm-pending-$token';
    }

    try {
      final info = DeviceInfoPlugin();
      if (Platform.isAndroid) {
        final android = await info.androidInfo;
        token = 'and-${android.id}-$token'.substring(0, 120);
      } else if (Platform.isIOS) {
        final ios = await info.iosInfo;
        final id = ios.identifierForVendor ?? token;
        token = 'ios-$id'.substring(0, 120);
      }
    } catch (_) {}

    await prefs.setString(_prefsKey, token);
    return token;
  }
}
