import 'package:shared_preferences/shared_preferences.dart';
import 'package:uuid/uuid.dart';

import 'api_service.dart';

/// Registers a stable device id for push when FCM is not configured yet.
/// Replace token with real FCM token after adding firebase_messaging + google-services.json.
class PushService {
  PushService(this._api);

  final ApiService _api;
  static const _prefsKey = 'push_device_token';

  Future<void> registerAfterLogin({
    required String role,
    String? staffMode,
  }) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      var token = prefs.getString(_prefsKey);
      token ??= 'radiant-${const Uuid().v4()}';
      await prefs.setString(_prefsKey, token);
      await _api.registerPushDevice(token, role: role, staffMode: staffMode);
    } catch (_) {}
  }
}
