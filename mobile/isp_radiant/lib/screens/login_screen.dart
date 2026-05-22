import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../config/remote_config.dart';
import '../services/api_service.dart';
import '../services/push_service.dart';
import '../theme/app_theme.dart';
import 'customer_home_screen.dart';
import 'staff_home_screen.dart';

/// Reference ISP app login — Admin / Client only, English, no extra hints.
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

  /// UI role: admin → staff API, client → customer API
  String _uiRole = 'admin';
  bool _loading = false;
  bool _configLoading = true;
  String? _error;
  bool _obscure = true;

  static const _headerBlue = Color(0xFF1565C0);
  static const _pageBg = Color(0xFFE8EEF5);

  @override
  void initState() {
    super.initState();
    widget.api.loadRemoteConfig().whenComplete(() {
      if (mounted) setState(() => _configLoading = false);
    });
  }

  @override
  void dispose() {
    _loginCtrl.dispose();
    _passCtrl.dispose();
    super.dispose();
  }

  String get _apiRole => _uiRole == 'client' ? 'customer' : 'staff';

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final body = await widget.api.login(
        role: _apiRole,
        login: _loginCtrl.text.trim(),
        password: _passCtrl.text,
      );

      if (!mounted) return;

      await PushService(widget.api).registerAfterLogin(
        role: _apiRole,
        staffMode: _apiRole == 'staff' ? 'admin' : null,
      );

      if (!mounted) return;

      if (_apiRole == 'customer') {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (_) => CustomerHomeScreen(api: widget.api, loginPayload: body),
          ),
        );
      } else {
        await widget.api.saveStaffMode('admin');
        if (!mounted) return;
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (_) => StaffHomeScreen(api: widget.api, loginPayload: body, staffMode: 'admin'),
          ),
        );
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'Connection failed. Check internet and server.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Widget _brandLogo() {
    final url = RemoteConfig.logoUrl;
    if (url != null && url.isNotEmpty) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: Image.network(
          url,
          width: 80,
          height: 80,
          fit: BoxFit.contain,
          errorBuilder: (_, __, ___) => const Icon(Icons.wifi_tethering, size: 56, color: Colors.white),
        ),
      );
    }
    return const Icon(Icons.wifi_tethering, size: 56, color: Colors.white);
  }

  Widget _roleChip(String value, String label, IconData icon) {
    final selected = _uiRole == value;
    return Expanded(
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: _loading ? null : () => setState(() => _uiRole = value),
          borderRadius: BorderRadius.circular(12),
          child: Container(
            padding: const EdgeInsets.symmetric(vertical: 14),
            decoration: BoxDecoration(
              color: selected ? _headerBlue : Colors.white,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: selected ? _headerBlue : Colors.grey.shade300, width: 1.5),
            ),
            child: Column(
              children: [
                Icon(icon, color: selected ? Colors.white : _headerBlue, size: 26),
                const SizedBox(height: 6),
                Text(
                  label,
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                    color: selected ? Colors.white : _headerBlue,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isAdmin = _uiRole == 'admin';

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: _pageBg,
        body: Column(
          children: [
            Container(
              width: double.infinity,
              padding: EdgeInsets.fromLTRB(20, MediaQuery.paddingOf(context).top + 24, 20, 28),
              decoration: const BoxDecoration(
                color: _headerBlue,
                borderRadius: BorderRadius.only(
                  bottomLeft: Radius.circular(24),
                  bottomRight: Radius.circular(24),
                ),
              ),
              child: Column(
                children: [
                  if (_configLoading)
                    const Padding(
                      padding: EdgeInsets.only(bottom: 12),
                      child: SizedBox(
                        width: 28,
                        height: 28,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                      ),
                    ),
                  _brandLogo(),
                  const SizedBox(height: 14),
                  Text(
                    RemoteConfig.appName,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 22,
                      fontWeight: FontWeight.bold,
                    ),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Sign in',
                    style: TextStyle(color: Colors.white70, fontSize: 15),
                  ),
                ],
              ),
            ),
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(20, 24, 20, 24),
                child: Card(
                  elevation: 2,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  color: Colors.white,
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Form(
                      key: _formKey,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const Text(
                            'Login as',
                            style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14, color: Color(0xFF64748B)),
                          ),
                          const SizedBox(height: 12),
                          Row(
                            children: [
                              _roleChip('admin', 'Admin', Icons.admin_panel_settings_outlined),
                              const SizedBox(width: 12),
                              _roleChip('client', 'Client', Icons.person_outline),
                            ],
                          ),
                          const SizedBox(height: 24),
                          TextFormField(
                            controller: _loginCtrl,
                            keyboardType: isAdmin ? TextInputType.emailAddress : TextInputType.text,
                            textInputAction: TextInputAction.next,
                            decoration: InputDecoration(
                              labelText: isAdmin ? 'Email' : 'Phone / ID / Username',
                              prefixIcon: Icon(isAdmin ? Icons.email_outlined : Icons.badge_outlined, color: _headerBlue),
                              filled: true,
                              fillColor: const Color(0xFFF8FAFC),
                              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                              enabledBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(12),
                                borderSide: BorderSide(color: Colors.grey.shade300),
                              ),
                              focusedBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(12),
                                borderSide: const BorderSide(color: _headerBlue, width: 2),
                              ),
                            ),
                            validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                          ),
                          const SizedBox(height: 16),
                          TextFormField(
                            controller: _passCtrl,
                            obscureText: _obscure,
                            textInputAction: TextInputAction.done,
                            onFieldSubmitted: (_) => _submit(),
                            decoration: InputDecoration(
                              labelText: 'Password',
                              prefixIcon: const Icon(Icons.lock_outline, color: _headerBlue),
                              suffixIcon: IconButton(
                                icon: Icon(_obscure ? Icons.visibility_off : Icons.visibility, color: Colors.grey),
                                onPressed: () => setState(() => _obscure = !_obscure),
                              ),
                              filled: true,
                              fillColor: const Color(0xFFF8FAFC),
                              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                              enabledBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(12),
                                borderSide: BorderSide(color: Colors.grey.shade300),
                              ),
                              focusedBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(12),
                                borderSide: const BorderSide(color: _headerBlue, width: 2),
                              ),
                            ),
                            validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
                          ),
                          if (_error != null) ...[
                            const SizedBox(height: 14),
                            Container(
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: AppTheme.danger.withValues(alpha: 0.08),
                                borderRadius: BorderRadius.circular(10),
                                border: Border.all(color: AppTheme.danger.withValues(alpha: 0.3)),
                              ),
                              child: Text(_error!, style: const TextStyle(color: AppTheme.danger, fontSize: 13)),
                            ),
                          ],
                          const SizedBox(height: 24),
                          SizedBox(
                            height: 50,
                            child: FilledButton(
                              onPressed: _loading ? null : _submit,
                              style: FilledButton.styleFrom(
                                backgroundColor: _headerBlue,
                                foregroundColor: Colors.white,
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                              ),
                              child: _loading
                                  ? const SizedBox(
                                      width: 24,
                                      height: 24,
                                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                    )
                                  : Text(isAdmin ? 'Admin sign in' : 'Client sign in', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
