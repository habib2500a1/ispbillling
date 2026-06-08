import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../config/remote_config.dart';
import '../core/theme/design_tokens.dart';
import '../design_system/components/radiant_glass_card.dart';
import '../design_system/components/radiant_section.dart';
import '../design_system/components/radiant_skeleton.dart';
import '../design_system/navigation/radiant_super_shell.dart';
import '../design_system/radiant_tokens.dart';
import '../models/login_role_config.dart';
import '../services/api_service.dart';
import 'login_screen.dart';
import 'reseller_web_portal_screen.dart';
import 'server_setup_screen.dart';

/// Radiant 3.0 sign-in hub — roles from /api/v1/mobile/config (logic unchanged).
class LoginHubScreen extends StatefulWidget {
  const LoginHubScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<LoginHubScreen> createState() => _LoginHubScreenState();
}

class _LoginHubScreenState extends State<LoginHubScreen> {
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    widget.api.loadRemoteConfig().whenComplete(() {
      if (mounted) setState(() => _loading = false);
    });
  }

  void _openRole(LoginRoleConfig role) {
    if (role.isWeb && role.id != 'reseller') {
      final url = role.webUrl ?? RemoteConfig.resellerLoginUrl;
      if (url == null || url.isEmpty) return;
      Navigator.of(context).push(
        RadiantPageRoute(
          page: ResellerWebPortalScreen(initialUrl: url, title: role.label),
        ),
      );
      return;
    }

    if (role.id == 'customer' || role.id == 'staff' || role.id == 'reseller') {
      Navigator.of(context).push(
        RadiantPageRoute(page: LoginScreen(api: widget.api, roleId: role.id)),
      );
    }
  }

  (Color, IconData, String) _roleMeta(String id) {
    return switch (id) {
      'customer' => (RadiantTokens.success, Icons.person_rounded, 'Customer'),
      'staff' => (RadiantTokens.brand, Icons.badge_outlined, 'Staff'),
      'reseller' => (RadiantTokens.warning, Icons.handshake_rounded, 'Partner'),
      _ => (RadiantTokens.accent, Icons.login_rounded, 'Sign in'),
    };
  }

  Widget _roleCard(LoginRoleConfig role) {
    final (accent, icon, badge) = _roleMeta(role.id);
    return RadiantGlassCard(
      onTap: () => _openRole(role),
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [accent.withValues(alpha: 0.25), accent.withValues(alpha: 0.08)],
              ),
              borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
              border: Border.all(color: accent.withValues(alpha: 0.25)),
            ),
            child: Icon(icon, color: accent, size: 26),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  badge.toUpperCase(),
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.8,
                    color: context.radiant.muted,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  role.label,
                  style: context.text.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 4),
                Text(
                  role.description,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: context.text.bodySmall?.copyWith(color: context.radiant.muted, height: 1.35),
                ),
              ],
            ),
          ),
          Icon(Icons.arrow_forward_ios_rounded, size: 16, color: accent),
        ],
      ),
    );
  }

  Widget _brandLogo() {
    final url = RemoteConfig.logoUrl;
    if (url != null && url.isNotEmpty) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(RadiantTokens.radiusMd),
        child: Image.network(
          url,
          width: 64,
          height: 64,
          fit: BoxFit.contain,
          errorBuilder: (_, __, ___) => _defaultLogo(),
        ),
      );
    }
    return _defaultLogo();
  }

  Widget _defaultLogo() {
    return Container(
      width: 64,
      height: 64,
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [RadiantTokens.brand, RadiantTokens.accent],
        ),
        borderRadius: BorderRadius.circular(RadiantTokens.radiusMd),
      ),
      child: const Icon(Icons.hub_rounded, color: Colors.white, size: 32),
    );
  }

  @override
  Widget build(BuildContext context) {
    final roles = RemoteConfig.loginRoles;
    final isDark = context.isDark;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: isDark ? SystemUiOverlayStyle.light : SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: isDark ? RadiantTokens.darkBg : RadiantTokens.lightBg,
        body: SafeArea(
          child: _loading
              ? const Padding(
                  padding: EdgeInsets.all(24),
                  child: RadiantDashboardSkeleton(),
                )
              : ListView(
                  padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
                  children: [
                    RadiantMeshBackground(
                      bottomRadius: RadiantTokens.radiusXl,
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(24, 32, 24, 32),
                        child: Column(
                          children: [
                            _brandLogo(),
                            const SizedBox(height: 16),
                            Text(
                              RemoteConfig.appName,
                              style: context.text.headlineSmall?.copyWith(
                                fontWeight: FontWeight.w800,
                                letterSpacing: -0.5,
                              ),
                              textAlign: TextAlign.center,
                            ),
                            const SizedBox(height: 8),
                            Text(
                              'Enterprise ISP Super App',
                              style: context.text.bodyMedium?.copyWith(color: context.radiant.muted),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'Select your workspace to continue',
                              style: context.text.bodySmall?.copyWith(color: context.radiant.muted),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 28),
                    const RadiantSectionHeader(title: 'Sign in as'),
                    ...roles.map((r) => Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: _roleCard(r),
                        )),
                    if (RemoteConfig.payUrl != null) ...[
                      const SizedBox(height: 8),
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
                            Icon(Icons.payment_rounded, color: RadiantTokens.brand),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Text(
                                'Quick pay without login',
                                style: context.text.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                              ),
                            ),
                            Icon(Icons.chevron_right_rounded, color: context.radiant.muted),
                          ],
                        ),
                      ),
                    ],
                    const SizedBox(height: 20),
                    Center(
                      child: TextButton.icon(
                        onPressed: () async {
                          await Navigator.of(context).push(
                            RadiantPageRoute(page: ServerSetupScreen(api: widget.api)),
                          );
                          if (mounted) setState(() => _loading = true);
                          await widget.api.loadRemoteConfig();
                          if (mounted) setState(() => _loading = false);
                        },
                        icon: const Icon(Icons.dns_outlined, size: 18),
                        label: const Text('Server settings'),
                      ),
                    ),
                  ],
                ),
        ),
      ),
    );
  }
}
