import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Secure storage with timeouts — avoids infinite hang on some Android devices.
class SessionStorage {
  SessionStorage({FlutterSecureStorage? storage})
      : _storage = storage ??
            const FlutterSecureStorage(
              aOptions: AndroidOptions(
                encryptedSharedPreferences: true,
              ),
            );

  final FlutterSecureStorage _storage;
  static const _ioTimeout = Duration(seconds: 4);

  Future<String?> read(String key) async {
    try {
      return await _storage.read(key: key).timeout(_ioTimeout);
    } catch (_) {
      return null;
    }
  }

  Future<void> write(String key, String value) async {
    try {
      await _storage.write(key: key, value: value).timeout(_ioTimeout);
    } catch (_) {}
  }

  Future<void> delete(String key) async {
    try {
      await _storage.delete(key: key).timeout(_ioTimeout);
    } catch (_) {}
  }

  Future<void> deleteAll() async {
    try {
      await _storage.deleteAll().timeout(_ioTimeout);
    } catch (_) {}
  }
}
