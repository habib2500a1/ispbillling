import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/remote_config.dart';
import '../core/network/api_result.dart';
import '../core/network/connectivity.dart';
import '../core/roles/role_resolver.dart';
import '../core/theme/design_tokens.dart';
import '../design_system/components/radiant_glass_card.dart';
import '../design_system/components/radiant_kpi_tile.dart';
import '../design_system/components/radiant_screen_header.dart';
import '../design_system/components/radiant_section.dart';
import '../design_system/radiant_tokens.dart';
import '../core/widgets/states.dart';
import '../services/api_service.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
import '../widgets/app_shell.dart';
import '../widgets/role_switcher_sheet.dart';
import 'login_hub_screen.dart';
import 'staff_ai_screen.dart';
import '../widgets/barcode_scan_screen.dart';
import 'staff_clients_screen.dart';
import 'staff_global_search_screen.dart';
import 'staff_profile_screen.dart';
import 'staff_tickets_screen.dart';

/// Field operations shell — uses `/technician/*` APIs with staff Sanctum token.
class TechnicianHomeScreen extends ConsumerStatefulWidget {
  const TechnicianHomeScreen({
    super.key,
    required this.api,
    required this.loginPayload,
    this.staffMode = 'technician',
  });

  final ApiService api;
  final Map<String, dynamic> loginPayload;
  final String staffMode;

  @override
  ConsumerState<TechnicianHomeScreen> createState() => _TechnicianHomeScreenState();
}

class _TechnicianHomeScreenState extends ConsumerState<TechnicianHomeScreen> {
  int _tab = 0;
  List<Map<String, dynamic>> _visits = [];
  RoleCapabilities? _caps;
  Map<String, dynamic>? _user;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _boot();
  }

  Future<void> _boot() async {
    try {
      final me = await widget.api.staffMe();
      _user = me;
      _caps = RoleCapabilities.fromMe(me, savedMode: widget.staffMode);
    } catch (_) {
      _caps = RoleCapabilities.fromMe(const {}, savedMode: widget.staffMode);
    }
    await _loadVisits();
  }

  Future<void> _loadVisits() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await widget.api.technicianFieldVisits(todayOnly: _tab == 1);
      if (mounted) {
        setState(() {
          _visits = res;
          _loading = false;
        });
      }
    } on ApiException catch (e) {
      if (mounted) setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (_) {
      if (mounted) setState(() {
        _error = 'Could not load visits';
        _loading = false;
      });
    }
  }

  Future<void> _logout() async {
    await widget.api.logout();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => LoginHubScreen(api: widget.api)),
      (_) => false,
    );
  }

  void _openSwitcher() {
    final caps = _caps;
    if (caps == null || !caps.hasMultipleInterfaces) return;
    showRoleSwitcherSheet(
      context,
      api: widget.api,
      capabilities: caps,
      currentMode: widget.staffMode,
      loginPayload: widget.loginPayload,
    );
  }

  Future<Position?> _currentPosition() async {
    if (!await Geolocator.isLocationServiceEnabled()) {
      return null;
    }
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied || permission == LocationPermission.deniedForever) {
      return null;
    }
    try {
      return await Geolocator.getCurrentPosition(locationSettings: const LocationSettings(accuracy: LocationAccuracy.high));
    } catch (_) {
      return null;
    }
  }

  Future<void> _pingGps() async {
    final pos = await _currentPosition();
    if (pos == null) return;
    try {
      await widget.api.technicianPingLocation(
        latitude: pos.latitude,
        longitude: pos.longitude,
        accuracyMeters: pos.accuracy.round(),
        headingDeg: pos.heading >= 0 ? pos.heading : null,
        speedKmh: pos.speed >= 0 ? pos.speed * 3.6 : null,
      );
    } catch (_) {}
  }

  Future<void> _openMaps(Map<String, dynamic> visit) async {
    final visitId = (visit['id'] as num?)?.toInt();
    final pos = await _currentPosition();
    try {
      final route = await widget.api.technicianNavigate(
        visitId: visitId,
        fromLat: pos?.latitude,
        fromLng: pos?.longitude,
      );
      final mapsUrl = route['maps_url']?.toString();
      if (mapsUrl != null && mapsUrl.isNotEmpty) {
        await launchUrl(Uri.parse(mapsUrl), mode: LaunchMode.externalApplication);
        return;
      }
    } catch (_) {
      /* fallback below */
    }

    final lat = visit['latitude'] as num?;
    final lng = visit['longitude'] as num?;
    if (lat == null || lng == null) {
      final customer = (visit['ticket'] as Map?)?['customer'] as Map?;
      final address = customer?['address']?.toString();
      if (address != null && address.isNotEmpty) {
        final uri = Uri.parse('https://www.google.com/maps/search/?api=1&query=${Uri.encodeComponent(address)}');
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      } else if (mounted) {
        showSnack(context, 'No location for this visit', isError: true);
      }
      return;
    }
    final uri = Uri.parse('https://www.google.com/maps/dir/?api=1&destination=$lat,$lng');
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }

  Future<void> _updateVisitStatus(Map<String, dynamic> visit, String status) async {
    final id = (visit['id'] as num).toInt();
    try {
      Position? pos;
      if (status == 'in_progress') {
        pos = await _currentPosition();
        await _pingGps();
      }
      await widget.api.technicianUpdateFieldVisit(
        id,
        status: status,
        latitude: pos?.latitude,
        longitude: pos?.longitude,
      );
      await _loadVisits();
      if (mounted) showSnack(context, 'Visit updated');
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final online = ref.watch(isOnlineProvider);
    final name = _user?['name']?.toString() ?? widget.loginPayload['user']?['name']?.toString() ?? 'Technician';

    return AppShell(
      tabIndex: _tab,
      onTab: (i) {
        setState(() => _tab = i);
        if (i == 0 || i == 1) _loadVisits();
      },
      destinations: const [
        NavigationDestination(icon: Icon(Icons.home_work_outlined), label: 'Home'),
        NavigationDestination(icon: Icon(Icons.event_note_outlined), label: 'Visits'),
        NavigationDestination(icon: Icon(Icons.search_rounded), label: 'Search'),
        NavigationDestination(icon: Icon(Icons.person_outline_rounded), label: 'Profile'),
      ],
      pages: [
        _buildHomeTab(online, name),
        _buildVisitsTab(),
        StaffGlobalSearchScreen(api: widget.api, embedded: true, technicianMode: true),
        StaffProfileScreen(
          api: widget.api,
          user: _user ?? widget.loginPayload['user'] as Map<String, dynamic>?,
          staffMode: widget.staffMode,
          roleCapabilities: _caps,
          loginPayload: widget.loginPayload,
        ),
      ],
    );
  }

  Widget _buildHomeTab(bool online, String name) {
    final pending = _visits.where((v) => v['status'] != 'completed' && v['status'] != 'cancelled').length;
    final today = _visits.where((v) {
      final s = v['scheduled_at']?.toString();
      if (s == null) return false;
      return DateTime.tryParse(s)?.toLocal().day == DateTime.now().day;
    }).length;

    return RefreshIndicator(
      onRefresh: _loadVisits,
      color: RadiantTokens.brand,
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          if (!online) const OfflineBanner(),
          RadiantScreenHeader(
            title: 'Field Ops',
            subtitle: '$name · ${RemoteConfig.appName}',
            trailing: [
              if (_caps?.hasMultipleInterfaces == true)
                RadiantHeaderIcon(icon: Icons.swap_horiz_rounded, onPressed: _openSwitcher, tooltip: 'Switch role'),
              RadiantHeaderIcon(
                icon: Icons.smart_toy_outlined,
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => StaffAiScreen(api: widget.api, technicianMode: true)),
                ),
                tooltip: 'AI',
              ),
              RadiantHeaderIcon(icon: Icons.logout_rounded, onPressed: _logout, tooltip: 'Logout'),
            ],
            child: Row(
              children: [
                Expanded(
                  child: RadiantKpiTile(
                    compact: true,
                    label: 'Assigned',
                    value: '$pending',
                    icon: Icons.assignment_outlined,
                    color: RadiantTokens.brand,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: RadiantKpiTile(
                    compact: true,
                    label: 'Today',
                    value: '$today',
                    icon: Icons.today_outlined,
                    color: RadiantTokens.accent,
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: pagePadding(context),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: RadiantQuickChip(
                        icon: Icons.confirmation_number_outlined,
                        label: 'My tickets',
                        onTap: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => StaffTicketsScreen(
                                api: widget.api,
                                staffUserId: _user?['id'] as int?,
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                    Expanded(
                      child: RadiantQuickChip(
                        icon: Icons.qr_code_scanner,
                        label: 'Scan ONU/MAC',
                        onTap: () async {
                          final code = await Navigator.push<String>(
                            context,
                            MaterialPageRoute(builder: (_) => const BarcodeScanScreen()),
                          );
                          if (code == null || code.isEmpty || !mounted) return;
                          try {
                            final hits = await widget.api.searchCustomers(code);
                            if (!mounted) return;
                            if (hits.isEmpty) {
                              showSnack(context, 'No customer for: $code', isError: true);
                              return;
                            }
                            Navigator.push(
                              context,
                              MaterialPageRoute(builder: (_) => StaffClientsScreen(api: widget.api)),
                            );
                          } on ApiException catch (e) {
                            if (mounted) showSnack(context, e.message, isError: true);
                          }
                        },
                      ),
                    ),
                    Expanded(
                      child: RadiantQuickChip(
                        icon: Icons.auto_awesome,
                        label: 'AI assistant',
                        onTap: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(builder: (_) => StaffAiScreen(api: widget.api, technicianMode: true)),
                          );
                        },
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                const RadiantSectionHeader(title: 'Upcoming visits'),
                if (_loading && _visits.isEmpty)
                  const Center(child: Padding(padding: EdgeInsets.all(24), child: CircularProgressIndicator(color: RadiantTokens.brand)))
                else if (_error != null && _visits.isEmpty)
                  ErrorStateView(
                    failure: Failure(_error!, type: FailureType.server),
                    onRetry: _loadVisits,
                  )
                else if (_visits.isEmpty)
                  RadiantGlassCard(
                    child: Text(
                      'No field visits assigned.',
                      textAlign: TextAlign.center,
                      style: context.text.bodyMedium?.copyWith(color: context.radiant.muted),
                    ),
                  )
                else
                  ..._visits.take(5).map(_visitCard),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildVisitsTab() {
    if (_loading && _visits.isEmpty) {
      return const Center(child: CircularProgressIndicator(color: RadiantTokens.brand));
    }
    return RefreshIndicator(
      onRefresh: _loadVisits,
      color: RadiantTokens.brand,
      child: ListView(
        padding: pagePadding(context),
        children: [
          const SizedBox(height: 8),
          ..._visits.map(_visitCard),
          if (_visits.isEmpty)
            RadiantGlassCard(
              child: Text(
                'No visits',
                textAlign: TextAlign.center,
                style: context.text.bodyMedium?.copyWith(color: context.radiant.muted),
              ),
            ),
        ],
      ),
    );
  }

  Widget _visitCard(Map<String, dynamic> visit) {
    final ticket = visit['ticket'] as Map<String, dynamic>?;
    final customer = ticket?['customer'] as Map<String, dynamic>?;
    final fmt = DateFormat('MMM d · HH:mm');
    final scheduled = visit['scheduled_at']?.toString();
    final when = scheduled != null ? fmt.format(DateTime.tryParse(scheduled)?.toLocal() ?? DateTime.now()) : '—';
    final status = visit['status']?.toString() ?? 'pending';
    final statusColor = _statusColor(status);
    final brand = context.radiant;

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: RadiantGlassCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: statusColor.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(RadiantTokens.radiusSm),
                  ),
                  child: Icon(Icons.home_repair_service_outlined, color: statusColor, size: 20),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        ticket?['subject']?.toString() ?? 'Visit #${visit['id']}',
                        style: context.text.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 4),
                      Text(when, style: context.text.bodySmall?.copyWith(color: brand.muted)),
                    ],
                  ),
                ),
                RadiantStatusChip(label: status.replaceAll('_', ' '), color: statusColor),
              ],
            ),
            if (customer != null) ...[
              const SizedBox(height: 10),
              Text(
                '${customer['name'] ?? ''} · ${customer['phone'] ?? ''}',
                style: context.text.bodyMedium?.copyWith(fontWeight: FontWeight.w500),
              ),
            ],
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                OutlinedButton.icon(
                  onPressed: () => _openMaps(visit),
                  icon: const Icon(Icons.navigation_outlined, size: 18),
                  label: const Text('Navigate'),
                ),
                if (status == 'pending' || status == 'scheduled')
                  FilledButton(
                    onPressed: () => _updateVisitStatus(visit, 'in_progress'),
                    style: FilledButton.styleFrom(backgroundColor: RadiantTokens.brand),
                    child: const Text('Start'),
                  ),
                if (status == 'in_progress')
                  FilledButton(
                    onPressed: () => _updateVisitStatus(visit, 'completed'),
                    style: FilledButton.styleFrom(backgroundColor: RadiantTokens.success),
                    child: const Text('Complete'),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'completed':
        return RadiantTokens.success;
      case 'in_progress':
        return RadiantTokens.brand;
      case 'cancelled':
        return RadiantTokens.danger;
      default:
        return RadiantTokens.warning;
    }
  }
}
