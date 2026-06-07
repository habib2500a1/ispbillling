import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../config/remote_config.dart';
import '../models/login_role_config.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';
import 'login_screen.dart';
import 'reseller_web_portal_screen.dart';
import 'server_setup_screen.dart';

/// Unified sign-in hub — roles and labels synced from /api/v1/mobile/config (website /login).
class LoginHubScreen extends StatefulWidget {
  const LoginHubScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<LoginHubScreen> createState() => _LoginHubScreenState();
}

class _LoginHubScreenState extends State<LoginHubScreen> {
  bool _loading = true;

  static const _headerStart = Color(0xFF312E81);
  static const _headerEnd = Color(0xFF7C3AED);
  static const _pageBg = Color(0xFFF1F5F9);

  @override
  void initState() {
    super.initState();
    widget.api.loadRemoteConfig().whenComplete(() {
      if (mounted) setState(() => _loading = false);
    });
  }

  void _openRole(LoginRoleConfig role) {
    if (role.isWeb) {
      final url = role.webUrl ?? RemoteConfig.resellerLoginUrl;
      if (url == null || url.isEmpty) return;
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => ResellerWebPortalScreen(initialUrl: url, title: role.label),
        ),
      );
      return;
    }

    if (role.id == 'customer' || role.id == 'staff') {
      Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => LoginScreen(api: widget.api, roleId: role.id),
        ),
      );
    }
  }

  Widget _roleCard(LoginRoleConfig role) {
    Color accent;
    Color iconBg;
    IconData icon;
    String badge;
    switch (role.id) {
      case 'customer':
        accent = const Color(0xFF047857);
        iconBg = const Color(0xFFD1FAE5);
        icon = Icons.person_outline_rounded;
        badge = 'Customer';
        break;
      case 'staff':
        accent = const Color(0xFF4338CA);
        iconBg = const Color(0xFFE0E7FF);
        icon = Icons.shield_outlined;
        badge = 'Staff';
        break;
      case 'reseller':
        accent = const Color(0xFFB45309);
        iconBg = const Color(0xFFFEF3C7);
        icon = Icons.handshake_outlined;
        badge = 'Partner';
        break;
      default:
        accent = _headerStart;
        iconBg = const Color(0xFFE0E7FF);
        icon = Icons.login_rounded;
        badge = 'Sign in';
    }

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: () => _openRole(role),
        borderRadius: BorderRadius.circular(14),
        child: Ink(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: Colors.grey.shade200),
            boxShadow: [
              BoxShadow(
                color: accent.withValues(alpha: 0.08),
                blurRadius: 14,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            child: Row(
              children: [
                Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(color: iconBg, borderRadius: BorderRadius.circular(12)),
                  child: Icon(icon, color: accent, size: 24),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(badge, style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, letterSpacing: 0.6, color: Colors.grey.shade500)),
                      Text(role.label, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                      const SizedBox(height: 2),
                      Text(
                        role.description,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(fontSize: 12, color: Colors.grey.shade600, height: 1.3),
                      ),
                    ],
                  ),
                ),
                Container(
                  width: 28,
                  height: 28,
                  decoration: BoxDecoration(color: accent.withValues(alpha: 0.1), shape: BoxShape.circle),
                  child: Icon(Icons.chevron_right_rounded, color: accent, size: 20),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _brandLogo() {
    final url = RemoteConfig.logoUrl;
    if (url != null && url.isNotEmpty) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: Image.network(
          url,
          width: 72,
          height: 72,
          fit: BoxFit.contain,
          errorBuilder: (_, __, ___) => const Icon(Icons.wifi_tethering, size: 48, color: Colors.white),
        ),
      );
    }
    return const Icon(Icons.wifi_tethering, size: 48, color: Colors.white);
  }

  @override
  Widget build(BuildContext context) {
    final roles = RemoteConfig.loginRoles;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: _pageBg,
        body: SafeArea(
          child: _loading
              ? const Center(child: CircularProgressIndicator())
              : ListView(
                  padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
                  children: [
                    Container(
                      padding: const EdgeInsets.fromLTRB(20, 22, 20, 20),
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [_headerStart, _headerEnd],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        borderRadius: BorderRadius.circular(20),
                        boxShadow: [
                          BoxShadow(
                            color: _headerStart.withValues(alpha: 0.35),
                            blurRadius: 24,
                            offset: const Offset(0, 10),
                          ),
                        ],
                      ),
                      child: Column(
                        children: [
                          _brandLogo(),
                          const SizedBox(height: 12),
                          Text(
                            RemoteConfig.appName,
                            style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w800),
                            textAlign: TextAlign.center,
                          ),
                          const SizedBox(height: 6),
                          Text(
                            'Secure access',
                            style: TextStyle(color: Colors.white.withValues(alpha: 0.75), fontSize: 12, fontWeight: FontWeight.w600, letterSpacing: 0.8),
                          ),
                          const SizedBox(height: 4),
                          const Text(
                            'Choose your portal',
                            style: TextStyle(color: Colors.white70, fontSize: 14),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    ...roles.map((r) => Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: _roleCard(r),
                        )),
                    if (RemoteConfig.payUrl != null) ...[
                      const SizedBox(height: 6),
                      Wrap(
                        alignment: WrapAlignment.center,
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          ActionChip(
                            avatar: const Icon(Icons.payment, size: 18),
                            label: const Text('Pay bill'),
                            onPressed: () {
                              final pay = RemoteConfig.payUrl!;
                              Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder: (_) => ResellerWebPortalScreen(initialUrl: pay, title: 'Pay bill'),
                                ),
                              );
                            },
                          ),
                        ],
                      ),
                    ],
                    const SizedBox(height: 12),
                    Center(
                      child: TextButton.icon(
                        onPressed: () async {
                          await Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => ServerSetupScreen(api: widget.api),
                            ),
                          );
                          if (mounted) setState(() => _loading = true);
                          await widget.api.loadRemoteConfig();
                          if (mounted) setState(() => _loading = false);
                        },
                        icon: const Icon(Icons.dns_outlined, size: 18),
                        label: const Text('Server settings (change domain)'),
                      ),
                    ),
                  ],
                ),
        ),
      ),
    );
  }
}
