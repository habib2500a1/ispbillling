import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';
import 'package:uuid/uuid.dart';

import 'api_service.dart';

/// Queues collector actions when offline; flushes via POST /mobile/sync.
class OfflineSyncService {
  OfflineSyncService(this._api);

  final ApiService _api;
  static const _queueKey = 'offline_sync_queue';
  static const _deviceKey = 'device_uuid';
  final _uuid = const Uuid();

  Future<String> deviceUuid() async {
    final prefs = await SharedPreferences.getInstance();
    var id = prefs.getString(_deviceKey);
    if (id == null || id.isEmpty) {
      id = _uuid.v4();
      await prefs.setString(_deviceKey, id);
    }
    return id;
  }

  Future<int> pendingCount() async {
    final items = await _loadQueue();
    return items.length;
  }

  Future<void> enqueueCollection({
    required int customerId,
    required double amount,
    int? invoiceId,
    String method = 'cash',
    String? reference,
    String? notes,
  }) async {
    final items = await _loadQueue();
    items.add({
      'action': 'collector.collection',
      'idempotency_key': _uuid.v4(),
      'payload': {
        'customer_id': customerId,
        'amount': amount,
        if (invoiceId != null) 'invoice_id': invoiceId,
        'method': method,
        if (reference != null) 'reference': reference,
        if (notes != null) 'notes': notes,
      },
    });
    await _saveQueue(items);
  }

  Future<Map<String, dynamic>?> flush() async {
    final items = await _loadQueue();
    if (items.isEmpty) return null;

    final device = await deviceUuid();
    final result = await _api.mobileSync(deviceUuid: device, items: items);
    final failed = (result['failed'] as num?)?.toInt() ?? 0;
    if (failed == 0) {
      await _saveQueue([]);
    } else {
      final results = (result['results'] as List<dynamic>?) ?? [];
      final failedKeys = results
          .where((r) => (r as Map)['status'] == 'failed')
          .map((r) => (r as Map)['idempotency_key']?.toString())
          .whereType<String>()
          .toSet();
      final remaining = items.where((item) {
        final key = item['idempotency_key']?.toString();
        return key != null && failedKeys.contains(key);
      }).toList();
      await _saveQueue(remaining);
    }
    return result;
  }

  Future<List<Map<String, dynamic>>> _loadQueue() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_queueKey);
    if (raw == null || raw.isEmpty) return [];
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) return [];
      return decoded.map((e) => Map<String, dynamic>.from(e as Map)).toList();
    } catch (_) {
      return [];
    }
  }

  Future<void> _saveQueue(List<Map<String, dynamic>> items) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_queueKey, jsonEncode(items));
  }
}
