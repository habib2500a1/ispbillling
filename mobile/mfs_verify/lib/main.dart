import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'config/app_branding.dart';
import 'services/api_service.dart';
import 'services/sms_listener.dart';
import 'utils/api_url_normalizer.dart';
import 'utils/mfs_sms_parser.dart';
import 'widgets/brand_header.dart';

const _kApiUrl = 'mfs_api_base_url';
const _kDeviceKey = 'mfs_device_key';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const MfsVerifyApp());
}

class MfsVerifyApp extends StatelessWidget {
  const MfsVerifyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'RCL SMS',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF059669)),
        useMaterial3: true,
      ),
      home: const MfsVerifyHome(),
    );
  }
}

class MfsVerifyHome extends StatefulWidget {
  const MfsVerifyHome({super.key});

  @override
  State<MfsVerifyHome> createState() => _MfsVerifyHomeState();
}

class _MfsVerifyHomeState extends State<MfsVerifyHome> with WidgetsBindingObserver {
  final _apiCtrl = TextEditingController();
  final _keyCtrl = TextEditingController();
  final _rawCtrl = TextEditingController();
  bool _configured = false;
  bool _auto = true;
  bool _loading = false;
  MfsApiService? _api;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _load();
    SmsListenerService.instance.onUpdate = () {
      if (mounted) setState(() {});
    };
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _auto && _api != null) {
      unawaited(SmsListenerService.instance.start(_api!));
    }
  }

  Future<void> _load() async {
    final p = await SharedPreferences.getInstance();
    final api = p.getString(_kApiUrl) ?? '';
    final key = p.getString(_kDeviceKey) ?? '';
    _apiCtrl.text = api;
    _keyCtrl.text = key;
    _auto = await SmsListenerService.instance.isAutoEnabled();
    if (api.isNotEmpty) {
      await AppBranding.loadFromApiBase(api);
    }
    if (api.isNotEmpty && key.isNotEmpty) {
      _api = MfsApiService(baseUrl: api, deviceKey: key);
      _configured = true;
      unawaited(SmsListenerService.instance.start(_api!));
    }
    if (mounted) setState(() {});
  }

  Future<void> _saveConfig() async {
    final api = ApiUrlNormalizer.normalize(_apiCtrl.text.trim());
    final key = _keyCtrl.text.trim();
    if (api.isEmpty || key.isEmpty) {
      _snack('API URL and device key required', error: true);
      return;
    }
    if (!ApiUrlNormalizer.looksValid(api)) {
      _snack('Use API base like https://your-domain.com/api/v1', error: true);
      return;
    }
    _apiCtrl.text = api;
    final p = await SharedPreferences.getInstance();
    await p.setString(_kApiUrl, api);
    await p.setString(_kDeviceKey, key);
    _api = MfsApiService(baseUrl: api, deviceKey: key);
    await AppBranding.loadFromApiBase(api);
    setState(() => _configured = true);
    if (_auto) await SmsListenerService.instance.start(_api!);
    _snack('Saved — SMS auto-forward ready');
  }

  Future<void> _toggleAuto(bool v) async {
    if (v && !await SmsListenerService.instance.ensurePermissions()) {
      _snack('SMS permission required', error: true);
      return;
    }
    await SmsListenerService.instance.setAutoEnabled(v);
    setState(() => _auto = v);
    if (v && _api != null) {
      await SmsListenerService.instance.start(_api!);
    }
    _snack(v ? 'Auto SMS ON' : 'Auto SMS OFF');
  }

  Future<void> _pasteAndSend() async {
    final clip = await Clipboard.getData(Clipboard.kTextPlain);
    final text = clip?.text?.trim() ?? '';
    if (text.isEmpty) return;
    _rawCtrl.text = text;
    await _sendManual(text);
  }

  Future<void> _sendManual([String? text]) async {
    final api = _api;
    if (api == null) {
      _snack('Configure API + device key first', error: true);
      return;
    }
    final body = text ?? _rawCtrl.text.trim();
    if (body.isEmpty) {
      _snack('Paste SMS text first', error: true);
      return;
    }
    setState(() => _loading = true);
    try {
      await SmsListenerService.instance.handleRaw(body);
      _snack('Sent to server');
      _rawCtrl.clear();
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _snack(String msg, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(msg),
        backgroundColor: error ? Colors.red.shade700 : null,
      ),
    );
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    SmsListenerService.instance.onUpdate = null;
    _apiCtrl.dispose();
    _keyCtrl.dispose();
    _rawCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final logs = SmsListenerService.instance.log;

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(AppBranding.instance.appName, style: const TextStyle(fontSize: 18)),
            if (AppBranding.instance.companyName.isNotEmpty)
              Text(
                AppBranding.instance.companyName,
                style: const TextStyle(fontSize: 11, fontWeight: FontWeight.normal),
              ),
          ],
        ),
        backgroundColor: const Color(0xFF059669),
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: Icon(_configured ? Icons.settings : Icons.settings_outlined),
            onPressed: () => setState(() => _configured = !_configured),
            tooltip: 'Settings',
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const BrandHeader(),
          const SizedBox(height: 20),
          if (!_configured) ...[
            const Text(
              'Setup (one time)',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _apiCtrl,
              decoration: InputDecoration(
                labelText: 'API base URL (শুধু /api/v1)',
                hintText: 'https://bill.flixbd.xyz/api/v1',
                helperText: _apiCtrl.text.isNotEmpty
                    ? 'POST → ${ApiUrlNormalizer.ingestUrl(_apiCtrl.text)}'
                    : '❌ Staff URL দেবেন না — শুধু device key + এই base',
                border: const OutlineInputBorder(),
              ),
              keyboardType: TextInputType.url,
              onChanged: (v) {
                final base = ApiUrlNormalizer.normalize(v.trim());
                if (ApiUrlNormalizer.looksValid(base)) {
                  unawaited(
                    AppBranding.loadFromApiBase(base).then((_) {
                      if (mounted) setState(() {});
                    }),
                  );
                }
                setState(() {});
              },
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _keyCtrl,
              decoration: const InputDecoration(
                labelText: 'Device key (MFS_SMS_DEVICE_API_KEY)',
                border: OutlineInputBorder(),
              ),
              obscureText: true,
            ),
            const SizedBox(height: 12),
            FilledButton(
              onPressed: _saveConfig,
              style: FilledButton.styleFrom(
                backgroundColor: const Color(0xFF059669),
                minimumSize: const Size.fromHeight(48),
              ),
              child: const Text('Save & start'),
            ),
            const SizedBox(height: 16),
            const Text(
              'Admin → Payments → RCL SMS: copy API base + Generate device key.',
              style: TextStyle(fontSize: 12, color: Colors.grey),
            ),
          ] else ...[
            Card(
              color: const Color(0xFF059669).withValues(alpha: 0.08),
              child: SwitchListTile(
                title: const Text('Auto-read payment SMS', style: TextStyle(fontWeight: FontWeight.bold)),
                subtitle: const Text(
                  'নতুন SMS সাথে সাথে server-এ যাবে। App খোলার সময় শুধু গত ৬ ঘণ্টার inbox একবার scan।',
                  style: TextStyle(fontSize: 12),
                ),
                value: _auto,
                onChanged: _toggleAuto,
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _rawCtrl,
              maxLines: 4,
              decoration: InputDecoration(
                labelText: 'Manual SMS paste',
                border: const OutlineInputBorder(),
                suffixIcon: IconButton(
                  icon: const Icon(Icons.content_paste),
                  onPressed: _pasteAndSend,
                ),
              ),
              onChanged: (t) {
                final p = MfsSmsParser.parse(t);
                if (p.isValid) setState(() {});
              },
            ),
            const SizedBox(height: 8),
            FilledButton.icon(
              onPressed: _loading ? null : () => _sendManual(),
              icon: _loading
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : const Icon(Icons.cloud_upload),
              label: const Text('Send now'),
              style: FilledButton.styleFrom(
                backgroundColor: const Color(0xFF059669),
                minimumSize: const Size.fromHeight(44),
              ),
            ),
            OutlinedButton.icon(
              onPressed: _api == null
                  ? null
                  : () async {
                      await SmsListenerService.instance.scanInboxRecent();
                      if (mounted) setState(() {});
                      _snack('Inbox scan done — check Recent log');
                    },
              icon: const Icon(Icons.refresh),
              label: const Text('Scan last 6h inbox'),
            ),
            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: () async {
                await SmsListenerService.instance.requestBatteryExemption();
                if (mounted) {
                  _snack('Allow “Unrestricted” battery if prompted');
                }
              },
              icon: const Icon(Icons.battery_charging_full, size: 18),
              label: const Text('Allow background (battery)'),
            ),
            const SizedBox(height: 8),
            const Text(
              'Payment SIM: Messages default রাখুন + Unrestricted battery। পুরনো ৬ ঘণ্টার বেশি SMS আর পড়া হয় না — শুধু নতুন payment SMS।',
              style: TextStyle(fontSize: 11, color: Colors.grey),
            ),
            if (logs.isNotEmpty) ...[
              const SizedBox(height: 16),
              const Text('Recent activity', style: TextStyle(fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              ...logs.take(20).map(
                (e) => ListTile(
                  dense: true,
                  leading: Icon(
                    e.status == 'error' || e.status.contains('denied') || e.status.contains('failed')
                        ? Icons.error_outline
                        : e.status.contains('skipped') || e.status == 'skipped_duplicate'
                            ? Icons.info_outline
                            : Icons.check_circle_outline,
                    color: e.status == 'error' ? Colors.red : const Color(0xFF059669),
                    size: 20,
                  ),
                  title: Text(
                    e.gateway == '—'
                        ? e.status
                        : '${e.gateway} · ${e.transactionId} · ${e.amount} BDT',
                    style: const TextStyle(fontSize: 13),
                  ),
                  subtitle: Text(
                    [
                      if (e.preview != null) e.preview!,
                      if (e.error != null) '${e.status}: ${e.error}' else if (e.gateway != '—') e.status,
                    ].join('\n'),
                    maxLines: 3,
                    style: const TextStyle(fontSize: 11),
                  ),
                  trailing: Text(
                    '${e.at.hour.toString().padLeft(2, '0')}:${e.at.minute.toString().padLeft(2, '0')}',
                    style: const TextStyle(fontSize: 11),
                  ),
                ),
              ),
            ],
          ],
        ],
      ),
    );
  }
}
