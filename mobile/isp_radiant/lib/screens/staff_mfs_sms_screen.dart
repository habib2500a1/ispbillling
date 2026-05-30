import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/remote_config.dart';
import '../services/api_service.dart';
import '../services/mfs_sms_listener.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../utils/mfs_sms_parser.dart';
import '../widgets/isp_ui_kit.dart';
import '../widgets/page_scaffold.dart';

/// MFS SMS verify — admin/staff only (uses staff login token).
class StaffMfsSmsScreen extends StatefulWidget {
  const StaffMfsSmsScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<StaffMfsSmsScreen> createState() => _StaffMfsSmsScreenState();
}

class _StaffMfsSmsScreenState extends State<StaffMfsSmsScreen> {
  final _trxCtrl = TextEditingController();
  final _amountCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _rawCtrl = TextEditingController();
  String _gateway = 'bkash';
  bool _loading = false;
  bool _autoSms = false;

  @override
  void initState() {
    super.initState();
    _initAuto();
    MfsSmsListener.instance.onLogged = (_) {
      if (mounted) setState(() {});
    };
  }

  Future<void> _initAuto() async {
    _autoSms = await MfsSmsListener.instance.isEnabled();
    await MfsSmsListener.instance.start(widget.api);
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    MfsSmsListener.instance.onLogged = null;
    super.dispose();
  }

  Future<void> _toggleAuto(bool v) async {
    if (v && !await MfsSmsListener.instance.ensurePermissions()) {
      if (mounted) showSnack(context, 'SMS permission required', isError: true);
      return;
    }
    await MfsSmsListener.instance.setEnabled(v);
    await MfsSmsListener.instance.start(widget.api);
    setState(() => _autoSms = v);
    if (mounted) {
      showSnack(context, v ? 'Auto SMS verify ON (admin login)' : 'Auto SMS verify OFF');
    }
  }

  Future<void> _pasteSms() async {
    final clip = await Clipboard.getData(Clipboard.kTextPlain);
    final text = clip?.text?.trim() ?? '';
    if (text.isEmpty) return;
    _rawCtrl.text = text;
    _applyParse(text);
  }

  void _applyParse(String text) {
    final p = MfsSmsParser.parse(text);
    setState(() {
      if (p.gateway != null) _gateway = p.gateway!;
      if (p.transactionId != null) _trxCtrl.text = p.transactionId!;
      if (p.amount != null) _amountCtrl.text = p.amount.toString();
      if (p.senderPhone != null) _phoneCtrl.text = p.senderPhone!;
    });
  }

  Future<void> _submit() async {
    final trx = _trxCtrl.text.trim();
    final amount = double.tryParse(_amountCtrl.text.trim());
    if (trx.isEmpty || amount == null || amount <= 0) {
      showSnack(context, 'TrxID and amount required', isError: true);
      return;
    }

    setState(() => _loading = true);
    try {
      final res = await widget.api.staffMfsSmsIngest(
        gateway: _gateway,
        transactionId: trx,
        amount: amount,
        senderPhone: _phoneCtrl.text.trim().isEmpty ? null : _phoneCtrl.text.trim(),
        rawMessage: _rawCtrl.text.trim().isEmpty ? null : _rawCtrl.text.trim(),
        deviceName: 'Radiant staff app',
      );
      if (!mounted) return;
      showSnack(context, 'Saved · ${res['transaction_id']} (${res['status']})');
      _trxCtrl.clear();
      _amountCtrl.clear();
      _phoneCtrl.clear();
      _rawCtrl.clear();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } catch (_) {
      if (mounted) showSnack(context, 'Could not save SMS', isError: true);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final log = MfsSmsListener.instance.recentLog;

    return PageScaffold(
      title: 'MFS SMS verify',
      useGradientBody: true,
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            decoration: IspUiKit.cardDecoration(tint: AppTheme.success.withValues(alpha: 0.12)),
            child: SwitchListTile(
              title: const Text('Auto-read payment SMS', style: TextStyle(fontWeight: FontWeight.bold)),
              subtitle: const Text(
                'Admin login থাকলে bKash/Nagad SMS স্বয়ং verify হবে। ২৪/৭ ফোনে শুধু MFS Verify APK ব্যবহার করুন।',
                style: TextStyle(fontSize: 12),
              ),
              value: _autoSms,
              onChanged: _toggleAuto,
            ),
          ),
          if (RemoteConfig.mfsVerifyApkUrl.isNotEmpty) ...[
            const SizedBox(height: 8),
            ListTile(
              leading: const Icon(Icons.download, color: AppTheme.primary),
              title: const Text('Download MFS Verify APK'),
              subtitle: const Text('Payment SIM ফোনে — device key দিয়ে'),
              trailing: const Icon(Icons.open_in_new),
              onTap: () async {
                final uri = Uri.tryParse(RemoteConfig.mfsVerifyApkUrl);
                if (uri == null) return;
                if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
                  if (mounted) showSnack(context, 'Could not open download link', isError: true);
                }
              },
            ),
          ],
          IspUiKit.formCard(
            title: 'Manual entry',
            subtitle: 'Paste SMS or fill TrxID + amount',
            children: [
              SegmentedButton<String>(
            segments: const [
              ButtonSegment(value: 'bkash', label: Text('bKash')),
              ButtonSegment(value: 'nagad', label: Text('Nagad')),
              ButtonSegment(value: 'rocket', label: Text('Rocket')),
            ],
            selected: {_gateway},
                onSelectionChanged: (s) => setState(() => _gateway = s.first),
              ),
              const SizedBox(height: 12),
              TextField(
            controller: _rawCtrl,
            maxLines: 4,
            decoration: InputDecoration(
              labelText: 'SMS text (paste)',
              suffixIcon: IconButton(icon: const Icon(Icons.content_paste), onPressed: _pasteSms),
            ),
                onChanged: _applyParse,
              ),
              const SizedBox(height: 8),
              TextField(
                controller: _trxCtrl,
                decoration: const InputDecoration(labelText: 'Transaction ID', border: OutlineInputBorder()),
                textCapitalization: TextCapitalization.characters,
              ),
              const SizedBox(height: 8),
              TextField(
                controller: _amountCtrl,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(labelText: 'Amount (BDT)', border: OutlineInputBorder()),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: _phoneCtrl,
                keyboardType: TextInputType.phone,
                decoration: const InputDecoration(labelText: 'Sender phone', border: OutlineInputBorder()),
              ),
              const SizedBox(height: 12),
              IspUiKit.primaryButton(
                label: 'Save to server',
                icon: Icons.cloud_upload,
                color: AppTheme.success,
                loading: _loading,
                onPressed: _submit,
              ),
            ],
          ),
          if (log.isNotEmpty) ...[
            const SizedBox(height: 20),
            const Text('Recent auto-forwarded', style: TextStyle(fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            ...log.take(10).map(
              (e) => ListTile(
                dense: true,
                title: Text('${e['gateway']} · ${e['transaction_id']} · ${e['amount']} BDT'),
                subtitle: Text('${e['status']} · ${e['at']}'),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
