import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/remote_config.dart';
import '../services/api_service.dart';
import '../theme/app_theme.dart';
import 'login_screen.dart';

/// Pre-login landing — branding, packages & notices (synced from /mobile/config).
class AppWelcomeScreen extends StatefulWidget {
  const AppWelcomeScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<AppWelcomeScreen> createState() => _AppWelcomeScreenState();
}

class _AppWelcomeScreenState extends State<AppWelcomeScreen> {
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    await widget.api.loadRemoteConfig();
    if (mounted) setState(() => _loading = false);
  }

  void _openSignIn() {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => LoginScreen(api: widget.api)),
    );
  }

  Future<void> _openWebsite() async {
    final url = RemoteConfig.websiteUrl;
    if (url == null) return;
    final uri = Uri.tryParse(url);
    if (uri == null) return;
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  Widget _logo() {
    final url = RemoteConfig.logoUrl;
    if (url != null && url.isNotEmpty) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(20),
        child: Image.network(
          url,
          width: 96,
          height: 96,
          fit: BoxFit.contain,
          errorBuilder: (_, __, ___) => _defaultLogo(),
        ),
      );
    }
    return _defaultLogo();
  }

  Widget _defaultLogo() {
    return Container(
      width: 96,
      height: 96,
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Colors.white30),
      ),
      child: const Icon(Icons.wifi_tethering, size: 52, color: Colors.white),
    );
  }

  Widget _featureChip(IconData icon, String label) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 6),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.14),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.white24),
        ),
        child: Column(
          children: [
            Icon(icon, color: Colors.white, size: 22),
            const SizedBox(height: 4),
            Text(
              label,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final packages = RemoteConfig.packages;
    final notices = RemoteConfig.notices;
    final phone = RemoteConfig.supportPhone;

    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [AppTheme.primary, AppTheme.purple, AppTheme.pink],
          ),
        ),
        child: SafeArea(
          child: _loading
              ? const Center(child: CircularProgressIndicator(color: Colors.white))
              : Column(
                  children: [
                    Expanded(
                      child: SingleChildScrollView(
                        padding: const EdgeInsets.fromLTRB(20, 20, 20, 8),
                        child: Column(
                          children: [
                            _logo(),
                            const SizedBox(height: 16),
                            Text(
                              RemoteConfig.appName,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 28,
                                fontWeight: FontWeight.bold,
                                letterSpacing: -0.5,
                              ),
                              textAlign: TextAlign.center,
                            ),
                            if (RemoteConfig.tagline.isNotEmpty) ...[
                              const SizedBox(height: 8),
                              Text(
                                RemoteConfig.tagline,
                                style: const TextStyle(color: Colors.white70, fontSize: 15),
                                textAlign: TextAlign.center,
                              ),
                            ],
                            const SizedBox(height: 14),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                              decoration: BoxDecoration(
                                color: Colors.white.withValues(alpha: 0.2),
                                borderRadius: BorderRadius.circular(24),
                              ),
                              child: const Text(
                                'ক্লায়েন্ট ও অ্যাডমিন — একই অ্যাপ',
                                style: TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w600),
                              ),
                            ),
                            const SizedBox(height: 16),
                            Row(
                              children: [
                                _featureChip(Icons.person, 'Client'),
                                const SizedBox(width: 8),
                                _featureChip(Icons.admin_panel_settings, 'Admin'),
                                const SizedBox(width: 8),
                                _featureChip(Icons.payments, 'Pay'),
                                const SizedBox(width: 8),
                                _featureChip(Icons.support_agent, 'Support'),
                              ],
                            ),
                            if (packages.isNotEmpty) ...[
                              const SizedBox(height: 22),
                              const Align(
                                alignment: Alignment.centerLeft,
                                child: Text(
                                  'Internet packages',
                                  style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16),
                                ),
                              ),
                              const SizedBox(height: 10),
                              SizedBox(
                                height: 118,
                                child: ListView.separated(
                                  scrollDirection: Axis.horizontal,
                                  itemCount: packages.length,
                                  separatorBuilder: (_, __) => const SizedBox(width: 12),
                                  itemBuilder: (context, i) {
                                    final p = packages[i];
                                    final name = p['name']?.toString() ?? 'Package';
                                    final price = p['price'] ?? p['monthly_price'];
                                    final speed = p['speed_label']?.toString() ?? p['bandwidth']?.toString() ?? '';
                                    return Container(
                                      width: 168,
                                      padding: const EdgeInsets.all(14),
                                      decoration: BoxDecoration(
                                        color: Colors.white,
                                        borderRadius: BorderRadius.circular(16),
                                        boxShadow: [
                                          BoxShadow(
                                            color: Colors.black.withValues(alpha: 0.12),
                                            blurRadius: 12,
                                            offset: const Offset(0, 4),
                                          ),
                                        ],
                                      ),
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            name,
                                            maxLines: 2,
                                            overflow: TextOverflow.ellipsis,
                                            style: const TextStyle(
                                              color: AppTheme.primary,
                                              fontWeight: FontWeight.bold,
                                              fontSize: 14,
                                            ),
                                          ),
                                          if (speed.isNotEmpty)
                                            Text(speed, style: TextStyle(color: Colors.grey.shade600, fontSize: 11)),
                                          const Spacer(),
                                          Text(
                                            price != null ? '৳$price/mo' : '—',
                                            style: const TextStyle(
                                              color: AppTheme.success,
                                              fontWeight: FontWeight.bold,
                                              fontSize: 15,
                                            ),
                                          ),
                                        ],
                                      ),
                                    );
                                  },
                                ),
                              ),
                            ],
                            if (notices.isNotEmpty) ...[
                              const SizedBox(height: 18),
                              const Align(
                                alignment: Alignment.centerLeft,
                                child: Text(
                                  'News & notices',
                                  style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16),
                                ),
                              ),
                              const SizedBox(height: 8),
                              ...notices.take(4).map((n) {
                                return Container(
                                  width: double.infinity,
                                  margin: const EdgeInsets.only(bottom: 8),
                                  padding: const EdgeInsets.all(14),
                                  decoration: BoxDecoration(
                                    color: Colors.white.withValues(alpha: 0.12),
                                    borderRadius: BorderRadius.circular(14),
                                    border: Border.all(color: Colors.white24),
                                  ),
                                  child: Row(
                                    children: [
                                      const Icon(Icons.campaign_outlined, color: Colors.white70, size: 20),
                                      const SizedBox(width: 10),
                                      Expanded(
                                        child: Text(
                                          n['title']?.toString() ?? n['body']?.toString() ?? '',
                                          style: const TextStyle(color: Colors.white, fontSize: 13),
                                        ),
                                      ),
                                    ],
                                  ),
                                );
                              }),
                            ],
                            if (phone.isNotEmpty) ...[
                              const SizedBox(height: 10),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  const Icon(Icons.phone, color: Colors.white54, size: 16),
                                  const SizedBox(width: 6),
                                  Text('Support: $phone', style: const TextStyle(color: Colors.white70, fontSize: 13)),
                                ],
                              ),
                            ],
                          ],
                        ),
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
                      child: Column(
                        children: [
                          SizedBox(
                            width: double.infinity,
                            height: 52,
                            child: FilledButton(
                              onPressed: _openSignIn,
                              style: FilledButton.styleFrom(
                                backgroundColor: Colors.white,
                                foregroundColor: AppTheme.primary,
                                elevation: 4,
                              ),
                              child: const Text('Sign in', style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold)),
                            ),
                          ),
                          if (RemoteConfig.websiteUrl != null) ...[
                            const SizedBox(height: 10),
                            TextButton(
                              onPressed: _openWebsite,
                              child: const Text('Visit website', style: TextStyle(color: Colors.white, fontSize: 14)),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
        ),
      ),
    );
  }
}
