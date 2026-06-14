import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../config/remote_config.dart';
import '../core/navigation/super_app_navigator.dart';
import '../services/api_service.dart';
import '../widgets/radiant_legacy_login_header.dart';
import 'reseller_web_portal_screen.dart';
import 'server_setup_screen.dart';

/// Radiant login — legacy SOFTIFY-style UI with unified mobile API sign-in.
class LoginHubScreen extends StatefulWidget {
  const LoginHubScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<LoginHubScreen> createState() => _LoginHubScreenState();
}

class _LoginHubScreenState extends State<LoginHubScreen> {
  static const _rememberKey = 'login_remember_me';
  static const _savedLoginKey = 'login_saved_identifier';

  final _formKey = GlobalKey<FormState>();
  final _loginCtrl = TextEditingController();
  final _passCtrl = TextEditingController();
  final _twoFactorCtrl = TextEditingController();

  bool _loading = false;
  bool _bootLoading = true;
  bool _obscure = true;
  bool _needs2fa = false;
  bool _rememberMe = true;
  String? _error;

  static const _fieldBorder = Color(0xFFB0BEC5);
  static const _labelColor = Color(0xFF607D8B);

  @override
  void initState() {
    super.initState();
    _boot();
  }

  Future<void> _boot() async {
    try {
      await widget.api.loadRemoteConfig();
      final prefs = await SharedPreferences.getInstance();
      _rememberMe = prefs.getBool(_rememberKey) ?? RemoteConfig.rememberLoginDefault;
      if (_rememberMe) {
        final saved = prefs.getString(_savedLoginKey);
        if (saved != null && saved.isNotEmpty) {
          _loginCtrl.text = saved;
        }
      }
    } catch (_) {
    } finally {
      if (mounted) setState(() => _bootLoading = false);
    }
  }

  @override
  void dispose() {
    _loginCtrl.dispose();
    _passCtrl.dispose();
    _twoFactorCtrl.dispose();
    super.dispose();
  }

  InputDecoration _outlineField(String label, {Widget? suffix}) {
    return InputDecoration(
      labelText: label,
      labelStyle: const TextStyle(color: _labelColor, fontSize: 14),
      floatingLabelStyle: const TextStyle(color: _labelColor, fontSize: 13),
      suffixIcon: suffix,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(4),
        borderSide: const BorderSide(color: _fieldBorder, width: 1.2),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(4),
        borderSide: const BorderSide(color: RadiantLegacyLoginHeader.primaryBlue, width: 1.4),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(4),
        borderSide: const BorderSide(color: Colors.redAccent, width: 1.2),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(4),
        borderSide: const BorderSide(color: Colors.redAccent, width: 1.4),
      ),
    );
  }

  Future<void> _persistRememberMe() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_rememberKey, _rememberMe);
    if (_rememberMe) {
      await prefs.setString(_savedLoginKey, _loginCtrl.text.trim());
    } else {
      await prefs.remove(_savedLoginKey);
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final body = await widget.api.loginUnified(
        login: _loginCtrl.text.trim(),
        password: _passCtrl.text,
        twoFactorCode: _needs2fa ? _twoFactorCtrl.text.trim() : null,
      );

      await _persistRememberMe();

      if (!mounted) return;
      await SuperAppNavigator.routeAfterLogin(context, widget.api, body);
    } on ApiException catch (e) {
      final data = e.data;
      if (e.statusCode == 422 && data?['requires_2fa'] == true) {
        setState(() {
          _needs2fa = true;
          _error = 'Enter your two-factor code to continue.';
        });
      } else {
        setState(() => _error = e.message);
      }
    } catch (_) {
      setState(() => _error = 'Connection failed. Check internet and try again.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _openWeb(String url, String title) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ResellerWebPortalScreen(initialUrl: url, title: title),
      ),
    );
  }

  String get _companyShort {
    final name = RemoteConfig.appName.trim();
    if (name.isEmpty) return 'Radiant';
    return name.split(RegExp(r'\s+')).first;
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent),
      child: Scaffold(
        backgroundColor: Colors.white,
        body: _bootLoading
            ? const Center(child: CircularProgressIndicator(color: RadiantLegacyLoginHeader.primaryBlue))
            : SafeArea(
                top: false,
                child: Column(
                  children: [
                    const RadiantLegacyLoginHeader(),
                    Expanded(
                      child: ListView(
                        padding: const EdgeInsets.fromLTRB(28, 8, 28, 16),
                        children: [
                          Form(
                            key: _formKey,
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                TextFormField(
                                  controller: _loginCtrl,
                                  keyboardType: TextInputType.text,
                                  textInputAction: TextInputAction.next,
                                  autofocus: _loginCtrl.text.isEmpty,
                                  decoration: _outlineField('Client Code/User Name'),
                                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                                ),
                                const SizedBox(height: 22),
                                TextFormField(
                                  controller: _passCtrl,
                                  obscureText: _obscure,
                                  textInputAction: TextInputAction.done,
                                  onFieldSubmitted: (_) => _submit(),
                                  decoration: _outlineField(
                                    'Password',
                                    suffix: IconButton(
                                      icon: Icon(
                                        _obscure ? Icons.visibility_off_outlined : Icons.visibility_outlined,
                                        color: _labelColor,
                                      ),
                                      onPressed: () => setState(() => _obscure = !_obscure),
                                    ),
                                  ),
                                  validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
                                ),
                                if (_needs2fa) ...[
                                  const SizedBox(height: 22),
                                  TextFormField(
                                    controller: _twoFactorCtrl,
                                    keyboardType: TextInputType.number,
                                    decoration: _outlineField('Two-factor code'),
                                    validator: (v) => (_needs2fa && (v == null || v.trim().isEmpty)) ? 'Required' : null,
                                  ),
                                ],
                                const SizedBox(height: 14),
                                Row(
                                  children: [
                                    SizedBox(
                                      width: 24,
                                      height: 24,
                                      child: Checkbox(
                                        value: _rememberMe,
                                        activeColor: RadiantLegacyLoginHeader.primaryBlue,
                                        side: const BorderSide(color: _fieldBorder, width: 1.4),
                                        onChanged: (v) => setState(() => _rememberMe = v ?? false),
                                      ),
                                    ),
                                    const SizedBox(width: 8),
                                    GestureDetector(
                                      onTap: () => setState(() => _rememberMe = !_rememberMe),
                                      child: const Text(
                                        'Remember me?',
                                        style: TextStyle(color: Color(0xFF455A64), fontSize: 14),
                                      ),
                                    ),
                                    const Spacer(),
                                    TextButton(
                                      onPressed: () => _openWeb(RemoteConfig.forgotPasswordUrl, 'Forgot password'),
                                      style: TextButton.styleFrom(
                                        padding: EdgeInsets.zero,
                                        minimumSize: Size.zero,
                                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                                      ),
                                      child: const Text(
                                        'Forgot Password?',
                                        style: TextStyle(
                                          color: Color(0xFF37474F),
                                          fontSize: 14,
                                          decoration: TextDecoration.underline,
                                          decorationColor: Color(0xFF37474F),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                if (_error != null) ...[
                                  const SizedBox(height: 14),
                                  Text(
                                    _error!,
                                    style: const TextStyle(color: Colors.redAccent, fontSize: 13),
                                    textAlign: TextAlign.center,
                                  ),
                                ],
                                const SizedBox(height: 28),
                                SizedBox(
                                  height: 48,
                                  child: ElevatedButton(
                                    onPressed: _loading ? null : _submit,
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: RadiantLegacyLoginHeader.primaryBlue,
                                      foregroundColor: Colors.white,
                                      elevation: 0,
                                      shape: const StadiumBorder(),
                                      textStyle: const TextStyle(fontSize: 17, fontWeight: FontWeight.w600),
                                    ),
                                    child: _loading
                                        ? const SizedBox(
                                            width: 24,
                                            height: 24,
                                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                          )
                                        : const Text('Log In'),
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 36),
                          Text(
                            'Are you a customer of $_companyShort?',
                            textAlign: TextAlign.center,
                            style: const TextStyle(color: Color(0xFF455A64), fontSize: 15),
                          ),
                          const SizedBox(height: 6),
                          Center(
                            child: GestureDetector(
                              onTap: () {
                                final signup = RemoteConfig.portalSignupUrl;
                                if (signup != null) {
                                  _openWeb(signup, 'Register account');
                                }
                              },
                              child: RichText(
                                text: TextSpan(
                                  style: const TextStyle(color: Color(0xFF455A64), fontSize: 15),
                                  children: [
                                    const TextSpan(text: 'Ensure your account is '),
                                    TextSpan(
                                      text: 'Registered',
                                      style: TextStyle(
                                        decoration: TextDecoration.underline,
                                        color: RadiantLegacyLoginHeader.primaryBlue.withValues(alpha: 0.95),
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                          if (RemoteConfig.payUrl != null) ...[
                            const SizedBox(height: 18),
                            Center(
                              child: TextButton(
                                onPressed: () => _openWeb(RemoteConfig.payUrl!, 'Pay bill'),
                                child: const Text(
                                  'Pay bill without login',
                                  style: TextStyle(
                                    decoration: TextDecoration.underline,
                                    color: Color(0xFF546E7A),
                                  ),
                                ),
                              ),
                            ),
                          ],
                          if (RemoteConfig.canChangeServer) ...[
                            const SizedBox(height: 4),
                            Center(
                              child: TextButton.icon(
                                onPressed: () async {
                                  await Navigator.of(context).push(
                                    MaterialPageRoute(page: ServerSetupScreen(api: widget.api)),
                                  );
                                  if (!mounted) return;
                                  setState(() => _bootLoading = true);
                                  await _boot();
                                },
                                icon: const Icon(Icons.dns_outlined, size: 16),
                                label: const Text('Server settings'),
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                    const Padding(
                      padding: EdgeInsets.fromLTRB(16, 8, 16, 14),
                      child: _DevelopedByFooter(),
                    ),
                  ],
                ),
              ),
      ),
    );
  }
}

class _DevelopedByFooter extends StatelessWidget {
  const _DevelopedByFooter();

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        const Text(
          'Developed By',
          style: TextStyle(color: Color(0xFF78909C), fontSize: 13),
        ),
        const SizedBox(width: 8),
        Text(
          'SOFTIFY',
          style: TextStyle(
            color: const Color(0xFF26A69A),
            fontSize: 22,
            fontWeight: FontWeight.w800,
            letterSpacing: 0.5,
            shadows: [
              Shadow(
                color: const Color(0xFF26A69A).withValues(alpha: 0.35),
                blurRadius: 1,
                offset: const Offset(1, 1),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
