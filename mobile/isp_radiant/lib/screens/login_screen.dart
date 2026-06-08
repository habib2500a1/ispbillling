import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../config/remote_config.dart';
import '../services/api_service.dart';
import '../services/push_service.dart';
import '../theme/app_theme.dart';
import '../core/navigation/super_app_navigator.dart';
import 'customer_home_screen.dart';

/// Native sign-in for customer or staff — role chosen on [LoginHubScreen].
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.api, required this.roleId});

  final ApiService api;
  /// `customer`, `staff`, or `reseller` (from server login.roles)
  final String roleId;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _loginCtrl = TextEditingController();
  final _passCtrl = TextEditingController();

  bool _loading = false;
  String? _error;
  bool _obscure = true;

  static const _headerBlue = Color(0xFF1565C0);
  static const _pageBg = Color(0xFFE8EEF5);

  bool get _isStaff => widget.roleId == 'staff';
  bool get _isReseller => widget.roleId == 'reseller';

  String get _apiRole => _isStaff ? 'staff' : (_isReseller ? 'reseller' : 'customer');

  String get _title {
    if (_isStaff) return 'Admin / staff';
    if (_isReseller) return 'Reseller / partner';
    return 'Customer portal';
  }

  @override
  void dispose() {
    _loginCtrl.dispose();
    _passCtrl.dispose();
    super.dispose();
  }

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

      if (_apiRole == 'customer') {
        await PushService(widget.api).registerAfterLogin(role: _apiRole);
        if (!mounted) return;
        await SuperAppNavigator.goCustomerHome(context, widget.api, loginPayload: body);
      } else if (_apiRole == 'reseller') {
        await PushService(widget.api).registerAfterLogin(role: _apiRole);
        if (!mounted) return;
        await SuperAppNavigator.goResellerHome(context, widget.api, loginPayload: body);
      } else {
        await SuperAppNavigator.applyStaffLogin(context, widget.api, loginBody: body);
        if (!mounted) return;
        final mode = await widget.api.staffMode ?? 'admin';
        await PushService(widget.api).registerAfterLogin(role: _apiRole, staffMode: mode);
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
          width: 64,
          height: 64,
          fit: BoxFit.contain,
          errorBuilder: (_, __, ___) => const Icon(Icons.wifi_tethering, size: 44, color: Colors.white),
        ),
      );
    }
    return const Icon(Icons.wifi_tethering, size: 44, color: Colors.white);
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: _pageBg,
        body: Column(
          children: [
            Container(
              width: double.infinity,
              padding: EdgeInsets.fromLTRB(12, MediaQuery.paddingOf(context).top + 8, 20, 22),
              decoration: const BoxDecoration(
                color: _headerBlue,
                borderRadius: BorderRadius.only(
                  bottomLeft: Radius.circular(24),
                  bottomRight: Radius.circular(24),
                ),
              ),
              child: Column(
                children: [
                  Row(
                    children: [
                      IconButton(
                        onPressed: _loading ? null : () => Navigator.of(context).pop(),
                        icon: const Icon(Icons.arrow_back, color: Colors.white),
                      ),
                      const Expanded(
                        child: Text(
                          'Sign in',
                          style: TextStyle(color: Colors.white70, fontSize: 14),
                          textAlign: TextAlign.center,
                        ),
                      ),
                      const SizedBox(width: 48),
                    ],
                  ),
                  _brandLogo(),
                  const SizedBox(height: 10),
                  Text(
                    _title,
                    style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 4),
                  Text(
                    RemoteConfig.appName,
                    style: const TextStyle(color: Colors.white60, fontSize: 13),
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
                          TextFormField(
                            controller: _loginCtrl,
                            keyboardType: _isStaff ? TextInputType.emailAddress : TextInputType.text,
                            textInputAction: TextInputAction.next,
                            autofocus: true,
                            decoration: InputDecoration(
                              labelText: _isStaff ? 'Staff email' : 'Phone / ID / Username',
                              prefixIcon: Icon(
                                _isStaff ? Icons.email_outlined : Icons.badge_outlined,
                                color: _headerBlue,
                              ),
                              filled: true,
                              fillColor: const Color(0xFFF8FAFC),
                              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
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
                                  : Text(
                                      _isStaff ? 'Staff sign in' : 'Customer sign in',
                                      style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                                    ),
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
