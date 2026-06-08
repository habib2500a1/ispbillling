import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/remote_config.dart';
import '../core/network/api_result.dart';
import '../core/network/connectivity.dart';
import '../core/roles/role_resolver.dart';
import '../core/theme/design_tokens.dart';
import '../core/widgets/states.dart';
import '../services/api_service.dart';
import '../utils/app_nav.dart';
import '../utils/layout.dart';
import '../widgets/app_shell.dart';
import '../widgets/isp_ui_kit.dart';
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

  Future<void> _openMaps(Map<String, dynamic> visit) async {
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
      await widget.api.technicianUpdateFieldVisit(id, status: status);
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
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          if (!online) const OfflineBanner(),
          IspUiKit.gradientHeader(
            title: 'Field Ops',
            subtitle: '$name · ${RemoteConfig.appName}',
            trailing: [
              if (_caps?.hasMultipleInterfaces == true)
                IconButton(icon: const Icon(Icons.swap_horiz_rounded, color: Colors.white), onPressed: _openSwitcher),
              IconButton(
                icon: const Icon(Icons.smart_toy_outlined, color: Colors.white),
                onPressed: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => StaffAiScreen(api: widget.api, technicianMode: true)),
                ),
              ),
              IconButton(icon: const Icon(Icons.logout, color: Colors.white), onPressed: _logout),
            ],
            child: Row(
              children: [
                Expanded(child: _stat('Assigned', '$pending', Icons.assignment_outlined)),
                const SizedBox(width: 8),
                Expanded(child: _stat('Today', '$today', Icons.today_outlined)),
              ],
            ),
          ),
          Padding(
            padding: pagePadding(context),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _actionChip('My tickets', Icons.confirmation_number_outlined, () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => StaffTicketsScreen(
                            api: widget.api,
                            staffUserId: _user?['id'] as int?,
                          ),
                        ),
                      );
                    }),
                    _actionChip('Scan ONU/MAC', Icons.qr_code_scanner, () async {
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
                    }),
                    _actionChip('AI assistant', Icons.auto_awesome, () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(builder: (_) => StaffAiScreen(api: widget.api, technicianMode: true)),
                      );
                    }),
                  ],
                ),
                const SizedBox(height: 16),
                const Text('Upcoming visits', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
                const SizedBox(height: 8),
                if (_loading && _visits.isEmpty)
                  const Center(child: Padding(padding: EdgeInsets.all(24), child: CircularProgressIndicator()))
                else if (_error != null && _visits.isEmpty)
                  ErrorStateView(
                    failure: Failure(_error!, type: FailureType.server),
                    onRetry: _loadVisits,
                  )
                else if (_visits.isEmpty)
                  const Padding(
                    padding: EdgeInsets.all(24),
                    child: Text('No field visits assigned.', textAlign: TextAlign.center),
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
      return const Center(child: CircularProgressIndicator());
    }
    return RefreshIndicator(
      onRefresh: _loadVisits,
      child: ListView(
        padding: pagePadding(context),
        children: [
          const SizedBox(height: 8),
          ..._visits.map(_visitCard),
          if (_visits.isEmpty)
            const Padding(padding: EdgeInsets.all(32), child: Text('No visits', textAlign: TextAlign.center)),
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

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    ticket?['subject']?.toString() ?? 'Visit #${visit['id']}',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ),
                Chip(label: Text(status), visualDensity: VisualDensity.compact),
              ],
            ),
            Text(when, style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
            if (customer != null) ...[
              const SizedBox(height: 6),
              Text('${customer['name'] ?? ''} · ${customer['phone'] ?? ''}', style: const TextStyle(fontSize: 13)),
            ],
            const SizedBox(height: 10),
            Row(
              children: [
                TextButton.icon(onPressed: () => _openMaps(visit), icon: const Icon(Icons.navigation_outlined), label: const Text('Navigate')),
                if (status == 'pending' || status == 'scheduled')
                  TextButton(onPressed: () => _updateVisitStatus(visit, 'in_progress'), child: const Text('Start')),
                if (status == 'in_progress')
                  FilledButton(onPressed: () => _updateVisitStatus(visit, 'completed'), child: const Text('Complete')),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _stat(String label, String value, IconData icon) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(icon, color: Colors.white, size: 22),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: const TextStyle(color: Colors.white70, fontSize: 10)),
                Text(value, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _actionChip(String label, IconData icon, VoidCallback onTap) {
    return ActionChip(
      avatar: Icon(icon, size: 18, color: DesignTokens.primary),
      label: Text(label),
      onPressed: onTap,
    );
  }
}
