import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:telephony/telephony.dart';

import '../utils/mfs_sms_parser.dart';
import 'api_service.dart';
import 'background_service.dart';

const _kApiUrl = 'mfs_api_base_url';
const _kDeviceKey = 'mfs_device_key';
const _prefAuto = 'mfs_auto_enabled';
const _prefSeenTrx = 'mfs_seen_trx_ids';
const _prefLastInboxScanMs = 'mfs_last_inbox_scan_ms';

/// Only ingest payment SMS from this window (older inbox rows are ignored).
const Duration inboxMaxAge = Duration(hours: 6);

class SmsLogEntry {
  SmsLogEntry({
    required this.at,
    required this.gateway,
    required this.transactionId,
    required this.amount,
    required this.status,
    this.error,
    this.preview,
  });

  final DateTime at;
  final String gateway;
  final String transactionId;
  final double amount;
  final String status;
  final String? error;
  final String? preview;
}

/// Background SMS handler (required when listenInBackground: true).
@pragma('vm:entry-point')
Future<void> mfsSmsBackgroundHandler(SmsMessage message) async {
  WidgetsFlutterBinding.ensureInitialized();
  await SmsListenerService.instance.handleIncomingMessage(message, fromBackground: true);
}

class SmsListenerService {
  SmsListenerService._();
  static final SmsListenerService instance = SmsListenerService._();

  final Telephony _telephony = Telephony.instance;
  MfsApiService? _api;
  bool _listening = false;
  bool _scanBusy = false;
  bool _sixHourInboxScanDone = false;
  final List<SmsLogEntry> log = [];
  void Function()? onUpdate;

  /// Same TrxID can arrive from SMS broadcast + inbox scan at once — block parallel POSTs.
  final Set<String> _inFlight = {};

  Future<bool> isAutoEnabled() async {
    final p = await SharedPreferences.getInstance();
    return p.getBool(_prefAuto) ?? true;
  }

  Future<void> setAutoEnabled(bool value) async {
    final p = await SharedPreferences.getInstance();
    await p.setBool(_prefAuto, value);
    if (value) {
      // Keep reading SMS while locked/closed via the persistent foreground service.
      await startBackgroundService();
    } else {
      _listening = false;
      stopBackgroundService();
    }
  }

  Future<void> requestBatteryExemption() async {
    await Permission.ignoreBatteryOptimizations.request();
  }

  Future<bool> ensurePermissions() async {
    var sms = await Permission.sms.status;
    if (!sms.isGranted) {
      sms = await Permission.sms.request();
    }
    // Android 13+ needs this for the foreground-service notification to show;
    // no-op (auto-granted) on older versions.
    if (!await Permission.notification.status.isGranted) {
      await Permission.notification.request();
    }
    return sms.isGranted;
  }

  Future<void> start(MfsApiService api) async {
    _api = api;
    if (!await isAutoEnabled()) return;
    if (!await ensurePermissions()) {
      _addSkip('permission_denied', 'SMS permission not granted', '');
      return;
    }

    if (!_listening) {
      _listening = true;
      _telephony.listenIncomingSms(
        onNewMessage: (m) => handleIncomingMessage(m),
        onBackgroundMessage: mfsSmsBackgroundHandler,
        listenInBackground: true,
      );
    }

    if (!_sixHourInboxScanDone) {
      await scanInboxRecent();
      _sixHourInboxScanDone = true;
    }
  }

  Future<MfsApiService?> _apiFromPrefs() async {
    if (_api != null) return _api;
    final p = await SharedPreferences.getInstance();
    final base = p.getString(_kApiUrl) ?? '';
    final key = p.getString(_kDeviceKey) ?? '';
    if (base.isEmpty || key.isEmpty) return null;
    return MfsApiService(baseUrl: base, deviceKey: key);
  }

  Future<void> handleIncomingMessage(SmsMessage message, {bool fromBackground = false}) async {
    final body = message.body ?? '';
    final address = message.address ?? '';
    await handleRaw(body, sender: address, fromBackground: fromBackground);
  }

  /// App start or manual button — one-time read of last 6 hours only.
  Future<void> scanInboxRecent() async {
    await _scanInbox(
      maxMessages: 50,
      maxAge: inboxMaxAge,
      allowRematchIngest: false,
    );
    _sixHourInboxScanDone = true;
  }

  /// Foreground-service safety net: light, frequent scan of just-arrived SMS.
  /// Runs in the service isolate (no cached [_api]) so it resolves config from
  /// prefs via [handleRaw]'s `fromBackground` path. Dedup keeps it idempotent
  /// against the real-time listener.
  Future<void> pollInboxForService() async {
    await _scanInbox(
      maxMessages: 15,
      maxAge: const Duration(minutes: 15),
      allowRematchIngest: false,
      fromBackground: true,
    );
  }

  Future<void> _scanInbox({
    required int maxMessages,
    required Duration maxAge,
    bool allowRematchIngest = false,
    bool fromBackground = false,
  }) async {
    if (_scanBusy) return;
    if (!await ensurePermissions()) return;

    _scanBusy = true;
    final p = await SharedPreferences.getInstance();
    final lastScanMs = p.getInt(_prefLastInboxScanMs) ?? 0;
    var newestMs = lastScanMs;
    try {
      final messages = await _telephony.getInboxSms(
        columns: [SmsColumn.ADDRESS, SmsColumn.BODY, SmsColumn.DATE],
        sortOrder: [OrderBy(SmsColumn.DATE, sort: Sort.DESC)],
      );

      final cutoff = DateTime.now().subtract(maxAge);
      var scanned = 0;
      for (final m in messages) {
        if (scanned >= maxMessages) break;
        final dateMs = m.date;
        if (dateMs != null) {
          final dt = DateTime.fromMillisecondsSinceEpoch(dateMs);
          if (dt.isBefore(cutoff)) break;
          if (!allowRematchIngest && dateMs <= lastScanMs) {
            continue;
          }
          if (dateMs > newestMs) newestMs = dateMs;
        }
        final address = m.address ?? '';
        final body = m.body ?? '';
        if (body.isEmpty) continue;
        if (!MfsSmsParser.looksLikeMfsSender(address) && !MfsSmsParser.parse(body).isValid) {
          continue;
        }
        scanned++;
        await handleRaw(
          body,
          sender: address,
          fromBackground: fromBackground,
          silentDuplicate: true,
          allowRematchIngest: allowRematchIngest,
        );
      }
    } catch (e) {
      if (maxMessages >= 20) {
        _addSkip('inbox_scan', e.toString(), '');
      }
    } finally {
      if (newestMs > lastScanMs) {
        await p.setInt(_prefLastInboxScanMs, newestMs);
      }
      _scanBusy = false;
    }
  }

  Future<void> handleRaw(
    String body, {
    String sender = '',
    bool fromBackground = false,
    bool silentDuplicate = false,
    bool allowRematchIngest = false,
  }) async {
    final api = fromBackground ? await _apiFromPrefs() : _api;
    if (api == null) {
      _addSkip('no_config', 'API URL / device key missing', body);
      return;
    }
    if (body.trim().isEmpty) return;

    final parsed = MfsSmsParser.parse(body);
    if (!parsed.isValid) {
      if (!silentDuplicate && (MfsSmsParser.looksLikeMfsSender(sender) || parsed.gateway != null)) {
        _addSkip(parsed.skipReason ?? 'parse_failed', _preview(body), body);
      }
      return;
    }

    final dedupeKey = '${parsed.gateway}:${parsed.transactionId}';
    if (!allowRematchIngest && (await _alreadySent(dedupeKey) || _inFlight.contains(dedupeKey))) {
      if (!silentDuplicate) {
        _addLog(
          gateway: parsed.gateway!,
          transactionId: parsed.transactionId!,
          amount: parsed.amount!,
          status: 'skipped_duplicate',
          preview: _preview(body),
        );
      }
      return;
    }

    _inFlight.add(dedupeKey);
    try {
      final res = await api.ingest(
        gateway: parsed.gateway!,
        transactionId: parsed.transactionId!,
        amount: parsed.amount!,
        senderPhone: parsed.senderPhone,
        customerReference: parsed.customerReference,
        rawMessage: body,
        deviceName: fromBackground ? 'RCL SMS (bg)' : 'RCL SMS',
      );
      if (!allowRematchIngest) {
        await _markSent(dedupeKey);
      }
      final matchedPending = (res['matched_pending'] is int) ? res['matched_pending'] as int : int.tryParse('${res['matched_pending']}') ?? 0;
      final autoApproved = res['auto_approved'] == true || matchedPending > 0;
      final refMatch = res['reference_match']?.toString();
      final customerCode = res['matched_customer_code']?.toString();
      final matchedBy = res['matched_by']?.toString();
      final billState = res['bill_payment_state']?.toString();
      final billLabel = res['bill_payment_label']?.toString();
      final refToken = res['reference_token']?.toString() ?? parsed.customerReference;

      var logStatus = billLabel ?? billState ?? (res['duplicate'] == true ? 'duplicate_ok' : (res['status']?.toString() ?? 'ok'));
      if (autoApproved && customerCode != null && customerCode.isNotEmpty) {
        logStatus = 'bill_linked_$customerCode';
      } else if (billState == 'pending_match' && refToken != null && refToken.isNotEmpty) {
        logStatus = matchedBy == 'sms_reference' ? 'ref_$refToken·pending' : 'pending_match';
      } else if (billState == 'duplicate_trx') {
        logStatus = 'duplicate_trx';
      } else if (refMatch == 'needs_assignment' && refToken != null) {
        logStatus = 'ref_$refToken·assign_id';
      } else if (refMatch == 'ambiguous_or_none' && parsed.customerReference != null) {
        logStatus = 'ref_no_match';
      }
      _addLog(
        gateway: parsed.gateway!,
        transactionId: parsed.transactionId!,
        amount: parsed.amount!,
        status: logStatus,
        preview: _preview(body),
      );
    } on MfsApiException catch (e) {
      if (await _alreadySent(dedupeKey)) {
        _logDuplicateOk(parsed, body, silentDuplicate: silentDuplicate);
        return;
      }
      _addLog(
        gateway: parsed.gateway!,
        transactionId: parsed.transactionId!,
        amount: parsed.amount!,
        status: 'error',
        error: e.message,
        preview: _preview(body),
      );
    } catch (e) {
      if (await _alreadySent(dedupeKey)) {
        _logDuplicateOk(parsed, body, silentDuplicate: silentDuplicate);
        return;
      }
      _addLog(
        gateway: parsed.gateway!,
        transactionId: parsed.transactionId!,
        amount: parsed.amount!,
        status: 'error',
        error: _friendlyError(e),
        preview: _preview(body),
      );
    } finally {
      _inFlight.remove(dedupeKey);
    }
  }

  void _logDuplicateOk(MfsSmsParseResult parsed, String body, {required bool silentDuplicate}) {
    if (silentDuplicate) return;
    _addLog(
      gateway: parsed.gateway!,
      transactionId: parsed.transactionId!,
      amount: parsed.amount!,
      status: 'duplicate_ok',
      preview: _preview(body),
    );
  }

  String _friendlyError(Object e) {
    final s = e.toString();
    if (s.contains('ClientException') || s.contains('SocketException')) {
      return 'Network glitch — check signal; SMS may still reach server';
    }
    return s.length > 120 ? '${s.substring(0, 120)}…' : s;
  }

  Future<bool> _alreadySent(String key) async {
    final p = await SharedPreferences.getInstance();
    final list = p.getStringList(_prefSeenTrx) ?? [];
    return list.contains(key);
  }

  Future<void> _markSent(String key) async {
    final p = await SharedPreferences.getInstance();
    final list = p.getStringList(_prefSeenTrx) ?? [];
    list.insert(0, key);
    while (list.length > 200) {
      list.removeLast();
    }
    await p.setStringList(_prefSeenTrx, list);
  }

  String _preview(String body) {
    final t = body.replaceAll(RegExp(r'\s+'), ' ').trim();
    return t.length > 60 ? '${t.substring(0, 60)}…' : t;
  }

  void _addSkip(String status, String error, String body) {
    _addLog(
      gateway: '—',
      transactionId: '—',
      amount: 0,
      status: status,
      error: error,
      preview: body.isNotEmpty ? _preview(body) : null,
    );
  }

  void _addLog({
    required String gateway,
    required String transactionId,
    required double amount,
    required String status,
    String? error,
    String? preview,
  }) {
    log.insert(
      0,
      SmsLogEntry(
        at: DateTime.now(),
        gateway: gateway,
        transactionId: transactionId,
        amount: amount,
        status: status,
        error: error,
        preview: preview,
      ),
    );
    if (log.length > 80) log.removeLast();
    onUpdate?.call();
  }
}
