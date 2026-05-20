import 'package:flutter/material.dart';

import '../config/remote_config.dart';
import '../services/api_service.dart';
import '../services/push_service.dart';
import '../theme/app_theme.dart';
import 'customer_home_screen.dart';
import 'staff_home_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _loginCtrl = TextEditingController();
  final _passCtrl = TextEditingController();
  String _role = 'staff';
  String _staffMode = 'admin';
  bool _loading = false;
  String? _error;

  static const _staffModes = [
    ('admin', 'Admin'),
    ('collector', 'Collector'),
    ('technician', 'Technician'),
    ('noc', 'NOC'),
  ];

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final body = await widget.api.login(
        role: _role,
        login: _loginCtrl.text.trim(),
        password: _passCtrl.text,
      );

      if (!mounted) return;

      await PushService(widget.api).registerAfterLogin(
        role: _role,
        staffMode: _role == 'staff' ? _staffMode : null,
      );

      if (!mounted) return;

      if (_role == 'customer') {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (_) => CustomerHomeScreen(api: widget.api, loginPayload: body),
          ),
        );
      } else {
        await widget.api.saveStaffMode(_staffMode);
        if (!mounted) return;
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (_) => StaffHomeScreen(api: widget.api, loginPayload: body, staffMode: _staffMode),
          ),
        );
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Connection failed. Check internet and API URL.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Widget _brandLogo() {
    final url = RemoteConfig.logoUrl;
    if (url != null && url.isNotEmpty) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(14),
        child: Image.network(
          url,
          width: 72,
          height: 72,
          fit: BoxFit.contain,
          errorBuilder: (_, __, ___) => const Icon(Icons.router, size: 48, color: Colors.white),
        ),
      );
    }
    return const Icon(Icons.router, size: 48, color: Colors.white);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [AppTheme.primary, Color(0xFF4A6FA5), AppTheme.accent],
          ),
        ),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: Column(
                children: [
                  _brandLogo(),
                  const SizedBox(height: 12),
                  Text(
                    RemoteConfig.appName,
                    style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.bold),
                  ),
                  if (RemoteConfig.tagline.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(
                        RemoteConfig.tagline,
                        style: const TextStyle(color: Colors.white70, fontSize: 13),
                        textAlign: TextAlign.center,
                      ),
                    ),
                  const Text(
                    'সব কাজ এই অ্যাপের ভিতরে — ওয়েবসাইট খোলা হয় না',
                    style: TextStyle(color: Colors.white70, fontSize: 13),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Staff Admin · Customer Portal · Collection · Support',
                    style: TextStyle(color: Colors.white54, fontSize: 11),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 28),
                  Card(
                    elevation: 8,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Form(
                        key: _formKey,
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            SegmentedButton<String>(
                              segments: const [
                                ButtonSegment(value: 'staff', label: Text('Staff / Admin')),
                                ButtonSegment(value: 'customer', label: Text('Customer portal')),
                              ],
                              selected: {_role},
                              onSelectionChanged: (s) => setState(() => _role = s.first),
                            ),
                            if (_role == 'staff') ...[
                              const SizedBox(height: 14),
                              DropdownButtonFormField<String>(
                                value: _staffMode,
                                decoration: const InputDecoration(labelText: 'App mode', prefixIcon: Icon(Icons.badge_outlined)),
                                items: _staffModes
                                    .map((e) => DropdownMenuItem(value: e.$1, child: Text(e.$2)))
                                    .toList(),
                                onChanged: (v) => setState(() => _staffMode = v ?? 'admin'),
                              ),
                            ],
                            const SizedBox(height: 20),
                            TextFormField(
                              controller: _loginCtrl,
                              decoration: InputDecoration(
                                labelText: _role == 'staff' ? 'Email' : 'Phone / ID / PPP user',
                                prefixIcon: const Icon(Icons.person_outline),
                              ),
                              validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _passCtrl,
                              obscureText: true,
                              decoration: const InputDecoration(
                                labelText: 'Password',
                                prefixIcon: Icon(Icons.lock_outline),
                              ),
                              validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
                            ),
                            if (_error != null) ...[
                              const SizedBox(height: 12),
                              Text(_error!, style: const TextStyle(color: Colors.red)),
                            ],
                            const SizedBox(height: 20),
                            SizedBox(
                              width: double.infinity,
                              child: FilledButton(
                                onPressed: _loading ? null : _submit,
                                child: _loading
                                    ? const SizedBox(
                                        height: 22,
                                        width: 22,
                                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                      )
                                    : const Text('Sign in'),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Native app · API sync only',
                    style: TextStyle(color: Colors.white38, fontSize: 10),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
