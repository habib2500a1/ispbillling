import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Offline cache for customer self-service data (Phase 3).
class OfflineCacheService {
  static const _profileKey = 'cache_customer_profile';
  static const _billsKey = 'cache_customer_bills';
  static const _ticketsKey = 'cache_customer_tickets';
  static const _notificationsKey = 'cache_customer_notifications';
  static const _dashboardKey = 'cache_customer_dashboard';

  Future<void> saveDashboard(Map<String, dynamic> data) async {
    await _write(_dashboardKey, data);
  }

  Future<Map<String, dynamic>?> loadDashboard() async => _readMap(_dashboardKey);

  Future<void> saveBills(List<Map<String, dynamic>> bills) async {
    await _write(_billsKey, {'data': bills, 'saved_at': DateTime.now().toIso8601String()});
  }

  Future<List<Map<String, dynamic>>> loadBills() async {
    final raw = await _readMap(_billsKey);
    if (raw == null) return [];
    return (raw['data'] as List<dynamic>?)
            ?.map((e) => Map<String, dynamic>.from(e as Map))
            .toList() ??
        [];
  }

  Future<void> saveTickets(List<Map<String, dynamic>> tickets) async {
    await _write(_ticketsKey, {'data': tickets, 'saved_at': DateTime.now().toIso8601String()});
  }

  Future<List<Map<String, dynamic>>> loadTickets() async {
    final raw = await _readMap(_ticketsKey);
    if (raw == null) return [];
    return (raw['data'] as List<dynamic>?)
            ?.map((e) => Map<String, dynamic>.from(e as Map))
            .toList() ??
        [];
  }

  Future<void> saveProfile(Map<String, dynamic> profile) async {
    await _write(_profileKey, profile);
  }

  Future<Map<String, dynamic>?> loadProfile() async => _readMap(_profileKey);

  Future<void> saveNotifications(List<Map<String, dynamic>> items) async {
    await _write(_notificationsKey, {'data': items});
  }

  Future<List<Map<String, dynamic>>> loadNotifications() async {
    final raw = await _readMap(_notificationsKey);
    if (raw == null) return [];
    return (raw['data'] as List<dynamic>?)
            ?.map((e) => Map<String, dynamic>.from(e as Map))
            .toList() ??
        [];
  }

  Future<void> clearAll() async {
    final prefs = await SharedPreferences.getInstance();
    for (final k in [_profileKey, _billsKey, _ticketsKey, _notificationsKey, _dashboardKey]) {
      await prefs.remove(k);
    }
  }

  Future<void> _write(String key, Map<String, dynamic> value) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(key, jsonEncode(value));
  }

  Future<Map<String, dynamic>?> _readMap(String key) async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(key);
    if (raw == null || raw.isEmpty) return null;
    try {
      final decoded = jsonDecode(raw);
      if (decoded is Map) return Map<String, dynamic>.from(decoded);
    } catch (_) {}
    return null;
  }
}
