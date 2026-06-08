import 'package:flutter/material.dart';

import '../core/theme/design_tokens.dart';
import '../design_system/components/radiant_glass_card.dart';
import '../design_system/components/radiant_kpi_tile.dart';
import '../design_system/components/radiant_screen_header.dart';
import '../design_system/components/radiant_section.dart';
import '../design_system/radiant_tokens.dart';
import '../services/api_service.dart';
import '../utils/layout.dart';
import '../widgets/state_views.dart';

class StaffNocScreen extends StatefulWidget {
  const StaffNocScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<StaffNocScreen> createState() => _StaffNocScreenState();
}

class _StaffNocScreenState extends State<StaffNocScreen> {
  Map<String, dynamic>? _data;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await widget.api.nocDashboard();
      if (mounted) setState(() => _data = data);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Failed to load NOC dashboard');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final alerts = (_data?['alerts'] as List<dynamic>?) ?? [];
    final brand = context.radiant;

    return Scaffold(
      backgroundColor: context.isDark ? RadiantTokens.darkBg : RadiantTokens.lightBg,
      body: _loading
          ? const ListLoading()
          : RefreshIndicator(
              onRefresh: _load,
              color: RadiantTokens.brand,
              child: ListView(
                padding: EdgeInsets.zero,
                children: [
                  RadiantScreenHeader(
                    title: 'NOC Dashboard',
                    subtitle: 'Network health overview',
                    trailing: [
                      RadiantHeaderIcon(icon: Icons.refresh_rounded, onPressed: _load, tooltip: 'Refresh'),
                    ],
                  ),
                  Padding(
                    padding: pagePadding(context, top: 8),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        if (_error != null)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: ErrorBanner(message: _error!, onRetry: _load),
                          ),
                        GridView.count(
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          crossAxisCount: 2,
                          mainAxisSpacing: 10,
                          crossAxisSpacing: 10,
                          childAspectRatio: 1.35,
                          children: [
                            RadiantKpiTile(
                              compact: true,
                              label: 'OLT',
                              value: '${_data?['olt_count'] ?? '—'}',
                              icon: Icons.dns_rounded,
                              color: RadiantTokens.brand,
                            ),
                            RadiantKpiTile(
                              compact: true,
                              label: 'ONU',
                              value: '${_data?['onu_count'] ?? '—'}',
                              icon: Icons.router_rounded,
                              color: RadiantTokens.accent,
                            ),
                            RadiantKpiTile(
                              compact: true,
                              label: 'Online',
                              value: '${_data?['customers_online'] ?? '—'}',
                              icon: Icons.wifi_rounded,
                              color: RadiantTokens.success,
                            ),
                            RadiantKpiTile(
                              compact: true,
                              label: 'Weak signal',
                              value: '${_data?['onu_weak_count'] ?? '—'}',
                              icon: Icons.signal_cellular_alt_1_bar_rounded,
                              color: RadiantTokens.warning,
                            ),
                          ],
                        ),
                        const SizedBox(height: 20),
                        const RadiantSectionHeader(title: 'Alerts'),
                        if (alerts.isEmpty)
                          RadiantGlassCard(
                            child: Row(
                              children: [
                                const Icon(Icons.check_circle_rounded, color: RadiantTokens.success),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Text(
                                    'No active alerts',
                                    style: context.text.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                                  ),
                                ),
                              ],
                            ),
                          )
                        else
                          ...alerts.map((a) {
                            final m = Map<String, dynamic>.from(a as Map);
                            final critical = m['severity'] == 'critical';
                            final color = critical ? RadiantTokens.danger : RadiantTokens.warning;
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 10),
                              child: RadiantGlassCard(
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Container(
                                      padding: const EdgeInsets.all(8),
                                      decoration: BoxDecoration(
                                        color: color.withValues(alpha: 0.12),
                                        borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
                                      ),
                                      child: Icon(
                                        critical ? Icons.error_rounded : Icons.warning_amber_rounded,
                                        color: color,
                                        size: 20,
                                      ),
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            m['title']?.toString() ?? 'Alert',
                                            style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                                          ),
                                          const SizedBox(height: 4),
                                          Text(
                                            m['message']?.toString() ?? '',
                                            style: context.text.bodySmall?.copyWith(color: brand.muted),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            );
                          }),
                      ],
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}
