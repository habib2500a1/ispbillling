import 'dart:async';

import 'package:flutter/material.dart';

import '../config/remote_config.dart';
import '../core/theme/design_tokens.dart';
import '../services/api_service.dart';
import 'login_screen.dart';
import 'customer_home_screen.dart';
import 'staff_home_screen.dart';

/// Boots the app: loads remote config, validates the stored session, and
/// redirects to the right home by role (staff / customer) or to login.
class SplashGate extends StatefulWidget {
  const SplashGate({super.key});

  @override
  State<SplashGate> createState() => _SplashGateState();
}

class _SplashGateState extends State<SplashGate> {
  final _api = ApiService();
  String _status = 'Loading…';

  @override
  void initState() {
    super.initState();
    _boot();
  }

  void _goLogin() {
    if (!mounted) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => LoginScreen(api: _api)),
    );
  }

  Future<void> _boot() async {
    try {
      if (mounted) setState(() => _status = 'Connecting…');
      await _api.loadRemoteConfig().timeout(const Duration(seconds: 10));
    } catch (_) {}

    final token = await _api.token;
    if (token == null || token.isEmpty) {
      _goLogin();
      return;
    }

    try {
      if (mounted) setState(() => _status = 'Checking session…');
      final valid = await _api.validateSession(quick: true).timeout(const Duration(seconds: 12));
      if (!mounted) return;

      if (!valid) {
        await _api.clearSession();
        _goLogin();
        return;
      }

      final role = await _api.role;
      if (!mounted) return;

      if (role == 'staff') {
        final mode = await _api.staffMode ?? 'admin';
        if (!mounted) return;
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (_) => StaffHomeScreen(api: _api, loginPayload: const {}, staffMode: mode),
          ),
        );
        return;
      }

      if (role == 'customer') {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => CustomerHomeScreen(api: _api, loginPayload: const {})),
        );
        return;
      }

      await _api.clearSession();
      _goLogin();
    } catch (_) {
      await _api.clearSession();
      _goLogin();
    }
  }

  @override
  Widget build(BuildContext context) {
    final gradient = context.brand.heroGradient;
    return Scaffold(
      body: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: gradient,
          ),
        ),
        child: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                padding: const EdgeInsets.all(22),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.14),
                  shape: BoxShape.circle,
                  border: Border.all(color: Colors.white.withValues(alpha: 0.25)),
                ),
                child: const Icon(Icons.wifi_tethering_rounded, size: 56, color: Colors.white),
              ),
              const SizedBox(height: 22),
              Text(
                RemoteConfig.appName,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 24,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 0.5,
                ),
              ),
              const SizedBox(height: 28),
              const SizedBox(
                width: 26,
                height: 26,
                child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5),
              ),
              const SizedBox(height: 14),
              Text(_status, style: TextStyle(color: Colors.white.withValues(alpha: 0.85))),
              const SizedBox(height: 24),
              TextButton(
                onPressed: _goLogin,
                style: TextButton.styleFrom(foregroundColor: Colors.white),
                child: const Text('Sign in'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
