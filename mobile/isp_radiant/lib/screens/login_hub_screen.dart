import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../config/remote_config.dart';
import '../core/navigation/super_app_navigator.dart';
import '../core/theme/design_tokens.dart';
import '../design_system/components/radiant_glass_card.dart';
import '../design_system/components/radiant_section.dart';
import '../design_system/navigation/radiant_super_shell.dart';
import '../design_system/radiant_tokens.dart';
import '../services/api_service.dart';
import 'reseller_web_portal_screen.dart';
import 'server_setup_screen.dart';

/// Radiant 3.0 — single sign-in. Server detects customer / staff / reseller.
class LoginHubScreen extends StatefulWidget {
  const LoginHubScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<LoginHubScreen> createState() => _LoginHubScreenState();
}

class _LoginHubScreenState extends State<LoginHubScreen> {
  final _formKey = GlobalKey<FormState>();
  final _loginCtrl = TextEditingController();
  final _passCtrl = TextEditingController();
  final _twoFactorCtrl = TextEditingController();

  bool _loading = false;
  bool _bootLoading = true;
  bool _obscure = true;
  bool _needs2fa = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    widget.api.loadRemoteConfig().whenComplete(() {
      if (mounted) setState(() => _bootLoading = false);
    });
  }

  @override
  void dispose() {
    _loginCtrl.dispose();
    _passCtrl.dispose();
    _twoFactorCtrl.dispose();
    super.dispose();
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

  Widget _brandLogo() {
    final url = RemoteConfig.logoUrl;
    if (url != null && url.isNotEmpty) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
        child: Image.network(
          url,
          width: 56,
          height: 56,
          fit: BoxFit.contain,
          errorBuilder: (_, __, ___) => _fallbackLogo(),
        ),
      );
    }
    return _fallbackLogo();
  }

  Widget _fallbackLogo() {
    return Container(
      width: 56,
      height: 56,
      decoration: BoxDecoration(
        color: RadiantTokens.brand.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
      ),
      child: const Icon(Icons.wifi_tethering_rounded, color: RadiantTokens.brand, size: 30),
    );
  }

  InputDecoration _field(String label, {Widget? prefix, Widget? suffix}) {
    return InputDecoration(
      labelText: label,
      prefixIcon: prefix,
      suffixIcon: suffix,
      filled: true,
      fillColor: context.isDark ? RadiantTokens.darkSurface : RadiantTokens.lightSurface,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(RadiantTokens.radiusSm)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
        borderSide: BorderSide(color: context.radiant.border),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final brand = context.radiant;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: context.isDark ? SystemUiOverlayStyle.light : SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: context.isDark ? RadiantTokens.darkBg : RadiantTokens.lightBg,
        body: _bootLoading
            ? const Center(child: CircularProgressIndicator(color: RadiantTokens.brand))
            : Stack(
                children: [
                  Positioned.fill(
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topCenter,
                          end: Alignment.bottomCenter,
                          colors: context.isDark
                              ? [const Color(0xFF1E1B4B), RadiantTokens.darkBg]
                              : [const Color(0xFFEEF2FF), RadiantTokens.lightBg],
                        ),
                      ),
                    ),
                  ),
                  SafeArea(
                    child: ListView(
                      padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
                      children: [
                    RadiantMeshBackground(
                      bottomRadius: RadiantTokens.radiusXl,
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(20, 28, 20, 28),
                        child: Column(
                          children: [
                            _brandLogo(),
                            const SizedBox(height: 16),
                            Text(
                              RemoteConfig.appName,
                              textAlign: TextAlign.center,
                              style: context.text.titleLarge?.copyWith(
                                fontWeight: FontWeight.w800,
                                letterSpacing: -0.4,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              'Enterprise ISP Super App',
                              style: context.text.bodySmall?.copyWith(color: brand.muted),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              'Sign in with your account — we’ll open the right workspace.',
                              textAlign: TextAlign.center,
                              style: context.text.bodySmall?.copyWith(color: brand.muted, height: 1.4),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),
                    RadiantGlassCard(
                      child: Form(
                        key: _formKey,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            TextFormField(
                              controller: _loginCtrl,
                              keyboardType: TextInputType.text,
                              textInputAction: TextInputAction.next,
                              autofocus: true,
                              decoration: _field(
                                'Email, phone, or username',
                                prefix: const Icon(Icons.person_outline_rounded, color: RadiantTokens.brand),
                              ),
                              validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                            ),
                            const SizedBox(height: 14),
                            TextFormField(
                              controller: _passCtrl,
                              obscureText: _obscure,
                              textInputAction: TextInputAction.done,
                              onFieldSubmitted: (_) => _submit(),
                              decoration: _field(
                                'Password',
                                prefix: const Icon(Icons.lock_outline_rounded, color: RadiantTokens.brand),
                                suffix: IconButton(
                                  icon: Icon(_obscure ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                                  onPressed: () => setState(() => _obscure = !_obscure),
                                ),
                              ),
                              validator: (v) => (v == null || v.isEmpty) ? 'Required' : null,
                            ),
                            if (_needs2fa) ...[
                              const SizedBox(height: 14),
                              TextFormField(
                                controller: _twoFactorCtrl,
                                keyboardType: TextInputType.number,
                                decoration: _field(
                                  'Two-factor code',
                                  prefix: const Icon(Icons.shield_outlined, color: RadiantTokens.brand),
                                ),
                                validator: (v) => (_needs2fa && (v == null || v.trim().isEmpty)) ? 'Required' : null,
                              ),
                            ],
                            if (_error != null) ...[
                              const SizedBox(height: 14),
                              Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: RadiantTokens.danger.withValues(alpha: 0.08),
                                  borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
                                  border: Border.all(color: RadiantTokens.danger.withValues(alpha: 0.25)),
                                ),
                                child: Text(_error!, style: const TextStyle(color: RadiantTokens.danger, fontSize: 13)),
                              ),
                            ],
                            const SizedBox(height: 20),
                            FilledButton(
                              onPressed: _loading ? null : _submit,
                              style: FilledButton.styleFrom(
                                minimumSize: const Size.fromHeight(52),
                                backgroundColor: RadiantTokens.brand,
                              ),
                              child: _loading
                                  ? const SizedBox(
                                      width: 24,
                                      height: 24,
                                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                    )
                                  : const Text('Sign in', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                            ),
                          ],
                        ),
                      ),
                    ),
                    if (RemoteConfig.payUrl != null) ...[
                      const SizedBox(height: 16),
                      RadiantGlassCard(
                        onTap: () {
                          final pay = RemoteConfig.payUrl!;
                          Navigator.of(context).push(
                            RadiantPageRoute(
                              page: ResellerWebPortalScreen(initialUrl: pay, title: 'Pay bill'),
                            ),
                          );
                        },
                        child: Row(
                          children: [
                            const Icon(Icons.payment_rounded, color: RadiantTokens.brand),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Text(
                                'Quick pay without login',
                                style: context.text.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                              ),
                            ),
                            Icon(Icons.chevron_right_rounded, color: brand.muted),
                          ],
                        ),
                      ),
                    ],
                    if (RemoteConfig.canChangeServer) ...[
                      const SizedBox(height: 16),
                      Center(
                        child: TextButton.icon(
                          onPressed: () async {
                            await Navigator.of(context).push(
                              RadiantPageRoute(page: ServerSetupScreen(api: widget.api)),
                            );
                            if (mounted) setState(() => _bootLoading = true);
                            await widget.api.loadRemoteConfig();
                            if (mounted) setState(() => _bootLoading = false);
                          },
                          icon: const Icon(Icons.dns_outlined, size: 18),
                          label: const Text('Server settings'),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
      ),
    );
  }
}
