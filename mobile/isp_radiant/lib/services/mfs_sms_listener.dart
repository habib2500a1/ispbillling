import 'package:permission_handler/permission_handler.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:telephony/telephony.dart';

import '../utils/mfs_sms_parser.dart';
import 'api_service.dart';

/// Auto-forward MFS SMS when staff/admin is logged in (app open / background).
class MfsSmsListener {
  MfsSmsListener._();
  static final MfsSmsListener instance = MfsSmsListener._();

  static const _prefKey = 'mfs_auto_sms_enabled';

  final Telephony _telephony = Telephony.instance;
  ApiService? _api;
  bool _listening = false;
  final List<Map<String, dynamic>> recentLog = [];
  void Function(Map<String, dynamic>)? onLogged;

  Future<bool> isEnabled() async {
    final p = await SharedPreferences.getInstance();
    return p.getBool(_prefKey) ?? false;
  }

  Future<void> setEnabled(bool value) async {
    final p = await SharedPreferences.getInstance();
    await p.setBool(_prefKey, value);
    if (value && _api != null) {
      await start(_api);
    } else {
      _listening = false;
    }
  }

  Future<bool> ensurePermissions() async {
    final sms = await Permission.sms.request();
    return sms.isGranted;
  }

  Future<void> start(ApiService? api) async {
    _api = api;
    if (api == null) return;

    final role = await api.role;
    if (role != 'staff') return;

    if (!await isEnabled()) return;
    if (!await ensurePermissions()) return;
    if (_listening) return;

    _listening = true;
    _telephony.listenIncomingSms(
      onNewMessage: (SmsMessage message) async {
        await _handleBody(message.body ?? '');
      },
      listenInBackground: true,
    );
  }

  Future<void> stop() async {
    _listening = false;
  }

  Future<void> _handleBody(String body) async {
    final parsed = MfsSmsParser.parse(body);
    if (!parsed.isValid || _api == null) return;

    try {
      final res = await _api!.staffMfsSmsIngest(
        gateway: parsed.gateway!,
        transactionId: parsed.transactionId!,
        amount: parsed.amount!,
        senderPhone: parsed.senderPhone,
        customerReference: parsed.customerReference,
        rawMessage: body,
        deviceName: 'Radiant admin app · SMS auto',
      );
      var status = res['status']?.toString() ?? 'ok';
      if (res['auto_approved'] == true) {
        final code = res['matched_customer_code']?.toString();
        status = code != null && code.isNotEmpty ? 'auto_approved_$code' : 'auto_approved';
      }
      final entry = {
        'at': DateTime.now().toIso8601String(),
        'gateway': parsed.gateway,
        'transaction_id': parsed.transactionId,
        'amount': parsed.amount,
        'status': status,
        'customer_reference': parsed.customerReference,
      };
      recentLog.insert(0, entry);
      if (recentLog.length > 30) recentLog.removeLast();
      onLogged?.call(entry);
    } catch (_) {}
  }
}
