import 'dart:async';

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../design_system/radiant_tokens.dart';
import '../features/staff_monitoring/data/monitoring_repository.dart';
import '../services/api_service.dart';
import 'staff_client_monitor_detail_screen.dart';

/// Legacy SOFTIFY "Client Monitoring" — stats, router picker, search, filters, client cards.
class StaffMonitoringScreen extends StatefulWidget {
  const StaffMonitoringScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<StaffMonitoringScreen> createState() => _StaffMonitoringScreenState();
}

class _StaffMonitoringScreenState extends State<StaffMonitoringScreen> {
  late final MonitoringRepository _repo = MonitoringRepository(widget.api);
  final _searchCtrl = TextEditingController();

  ClientMonitorStats _stats = const ClientMonitorStats(total: 0, online: 0, offline: 0);
  ClientMonitorFilters _filters = const ClientMonitorFilters(routers: [], zones: [], subzones: []);
  List<ClientMonitorRow> _clients = [];
  bool _loading = true;
  bool _loadingMore = false;
  int _page = 1;
  int _lastPage = 1;
  int _resultTotal = 0;

  int? _routerId;
  int? _zoneId;
  int? _subzoneId;
  String _connection = 'all';
  Timer? _searchDebounce;

  @override
  void initState() {
    super.initState();
    _searchCtrl.addListener(() {
      setState(() {});
      _scheduleSearch();
    });
    _load(page: 1);
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _searchCtrl.dispose();
    super.dispose();
  }

  void _scheduleSearch() {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 400), () => _load(page: 1));
  }

  Future<void> _load({required int page}) async {
    if (page == 1) {
      setState(() => _loading = true);
    } else {
      setState(() => _loadingMore = true);
    }

    final res = await _repo.clientMonitoring(
      q: _searchCtrl.text.trim(),
      mikrotikServerId: _routerId,
      zoneId: _zoneId,
      subzoneId: _subzoneId,
      connection: _connection,
      page: page,
    );

    if (!mounted) return;
    res.when(
      ok: (data) => setState(() {
        _stats = data.stats;
        _filters = data.filters;
        _page = data.currentPage;
        _lastPage = data.lastPage;
        _resultTotal = data.total;
        if (page == 1) {
          _clients = data.clients;
        } else {
          _clients = [..._clients, ...data.clients];
        }
        _loading = false;
        _loadingMore = false;
      }),
      err: (_) => setState(() {
        _loading = false;
        _loadingMore = false;
      }),
    );
  }

  Future<void> _pickConnection() async {
    final options = const [
      {'value': 'all', 'name': 'All'},
      {'value': 'online', 'name': 'Online'},
      {'value': 'offline', 'name': 'Offline'},
    ];
    final picked = await showModalBottomSheet<String>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const ListTile(title: Text('Connection', style: TextStyle(fontWeight: FontWeight.w700))),
            for (final o in options)
              ListTile(
                title: Text(o['name']!),
                trailing: _connection == o['value'] ? const Icon(Icons.check, color: RadiantTokens.brand) : null,
                onTap: () => Navigator.pop(ctx, o['value']),
              ),
          ],
        ),
      ),
    );
    if (!mounted || picked == null || picked == _connection) return;
    setState(() => _connection = picked);
    _load(page: 1);
  }

  Future<void> _pickFilter({
    required String title,
    required List<Map<String, dynamic>> options,
    required int? currentId,
    required ValueChanged<int?> onPick,
  }) async {
    final picked = await showModalBottomSheet<int?>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(title: Text(title, style: const TextStyle(fontWeight: FontWeight.w700))),
            ListTile(
              title: const Text('All'),
              trailing: currentId == null ? const Icon(Icons.check, color: RadiantTokens.brand) : null,
              onTap: () => Navigator.pop(ctx, null),
            ),
            for (final o in options)
              ListTile(
                title: Text(o['name']?.toString() ?? o['label']?.toString() ?? '—'),
                trailing: currentId == (o['id'] as num?)?.toInt()
                    ? const Icon(Icons.check, color: RadiantTokens.brand)
                    : null,
                onTap: () => Navigator.pop(ctx, (o['id'] as num).toInt()),
              ),
          ],
        ),
      ),
    );
    if (!mounted || picked == currentId) return;
    onPick(picked);
    if (title == 'Zone') _subzoneId = null;
    _load(page: 1);
  }

  Future<void> _callPhone(String phone) async {
    final uri = Uri.parse('tel:$phone');
    if (await canLaunchUrl(uri)) await launchUrl(uri);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF0F4F8),
      body: Column(
        children: [
          _header(),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator(color: RadiantTokens.brand))
                : RefreshIndicator(
                    onRefresh: () => _load(page: 1),
                    color: RadiantTokens.brand,
                    child: ListView(
                      padding: const EdgeInsets.fromLTRB(12, 0, 12, 16),
                      children: [
                        _searchBar(),
                        const SizedBox(height: 10),
                        _filterRow(),
                        const SizedBox(height: 8),
                        Text(
                          'Showing Result: $_resultTotal of ${_stats.total}',
                          style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                        ),
                        const SizedBox(height: 10),
                        ..._clients.map(_clientCard),
                        if (_page < _lastPage)
                          Padding(
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            child: Center(
                              child: _loadingMore
                                  ? const CircularProgressIndicator()
                                  : OutlinedButton(
                                      onPressed: () => _load(page: _page + 1),
                                      child: const Text('Load more'),
                                    ),
                            ),
                          ),
                      ],
                    ),
                  ),
          ),
        ],
      ),
    );
  }

  Widget _header() {
    final routerLabel = _filters.routers
        .where((r) => (r['id'] as num?)?.toInt() == _routerId)
        .map((r) => r['name']?.toString() ?? r['label']?.toString())
        .firstOrNull;

    return Container(
      color: RadiantTokens.brand,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(8, 4, 12, 14),
          child: Column(
            children: [
              Row(
                children: [
                  IconButton(
                    onPressed: () => Navigator.maybePop(context),
                    icon: const Icon(Icons.arrow_back, color: Colors.white),
                  ),
                  const Expanded(
                    child: Text(
                      'Client Monitoring',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w600),
                    ),
                  ),
                  const SizedBox(width: 48),
                ],
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  _statTile(Icons.people_outline, 'Client', '${_stats.total}'),
                  _statTile(Icons.wifi, 'Online', '${_stats.online}', color: const Color(0xFF66BB6A)),
                  _statTile(Icons.wifi_off, 'Offline', '${_stats.offline}', color: const Color(0xFFFF7043)),
                ],
              ),
              const SizedBox(height: 12),
              Material(
                color: Colors.white,
                borderRadius: BorderRadius.circular(28),
                child: InkWell(
                  borderRadius: BorderRadius.circular(28),
                  onTap: () => _pickFilter(
                    title: 'Router',
                    options: _filters.routers,
                    currentId: _routerId,
                    onPick: (v) => setState(() => _routerId = v),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
                    child: Row(
                      children: [
                        Expanded(
                          child: Text(
                            routerLabel ?? 'All routers',
                            style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                          ),
                        ),
                        const Icon(Icons.keyboard_arrow_down_rounded),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _statTile(IconData icon, String label, String value, {Color? color}) {
    return Expanded(
      child: Column(
        children: [
          Icon(icon, color: color ?? Colors.white, size: 22),
          const SizedBox(height: 4),
          Text(label, style: const TextStyle(color: Colors.white70, fontSize: 11)),
          Text(value, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 16)),
        ],
      ),
    );
  }

  Widget _searchBar() {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(8),
      elevation: 0.5,
      child: TextField(
        controller: _searchCtrl,
        decoration: InputDecoration(
          hintText: 'Search name, code, phone, username…',
          border: InputBorder.none,
          contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          suffixIcon: _searchCtrl.text.isNotEmpty
              ? IconButton(
                  icon: const Icon(Icons.close, color: Colors.red),
                  onPressed: () {
                    _searchCtrl.clear();
                    _load(page: 1);
                  },
                )
              : null,
        ),
      ),
    );
  }

  Widget _filterRow() {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          _filterChip('Status', _connection == 'all' ? null : _connectionLabel(), _pickConnection),
          _filterChip('Zone', _zoneName(), () => _pickFilter(
                title: 'Zone',
                options: _filters.zones,
                currentId: _zoneId,
                onPick: (v) => setState(() => _zoneId = v),
              )),
          _filterChip('Subzone', _subzoneName(), () => _pickFilter(
                title: 'Subzone',
                options: _subzonesForZone(),
                currentId: _subzoneId,
                onPick: (v) => setState(() => _subzoneId = v),
              )),
          _filterChip('Box', null, () {}),
          _filterChip('Conn.', _connection == 'all' ? null : _connectionLabel(), _pickConnection),
        ],
      ),
    );
  }

  String? _zoneName() => _filters.zones
      .where((z) => (z['id'] as num?)?.toInt() == _zoneId)
      .map((z) => z['name']?.toString())
      .firstOrNull;

  String? _subzoneName() => _subzonesForZone()
      .where((z) => (z['id'] as num?)?.toInt() == _subzoneId)
      .map((z) => z['name']?.toString())
      .firstOrNull;

  List<Map<String, dynamic>> _subzonesForZone() {
    if (_zoneId == null) return _filters.subzones;
    return _filters.subzones.where((s) => (s['zone_id'] as num?)?.toInt() == _zoneId).toList();
  }

  String? _connectionLabel() {
    if (_connection == 'online') return 'Online';
    if (_connection == 'offline') return 'Offline';
    return null;
  }

  Widget _filterChip(String label, String? value, VoidCallback onTap) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ActionChip(
        label: Text(value == null ? label : '$label: $value', style: const TextStyle(fontSize: 12)),
        onPressed: onTap,
        backgroundColor: Colors.white,
        side: BorderSide(color: Colors.grey.shade300),
      ),
    );
  }

  Widget _clientCard(ClientMonitorRow c) {
    final connected = c.isOnline;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          borderRadius: BorderRadius.circular(10),
          onTap: () => Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => StaffClientMonitorDetailScreen(api: widget.api, customerId: c.id, preview: c),
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 10, 12, 0),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(child: _cardField('Name', c.name)),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: connected ? const Color(0xFFE8F5E9) : Colors.grey.shade200,
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Text(
                        c.connectionStatus,
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: connected ? const Color(0xFF2E7D32) : Colors.grey.shade700,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          _cardField('Client Code', c.customerCode),
                          _cardField('User ID/IP', c.username),
                          _cardField('Zone', c.zone, valueColor: const Color(0xFFFF7043)),
                          _cardField('Subzone', c.subzone, valueColor: const Color(0xFFFF7043)),
                          _cardField('Box', c.box, valueColor: const Color(0xFFFF7043)),
                          _cardField('Profile', c.profile),
                          _cardField('Ip Address', c.framedIp),
                        ],
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Icon(
                        connected ? Icons.router : Icons.router_outlined,
                        color: connected ? const Color(0xFF66BB6A) : Colors.grey,
                        size: 28,
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                margin: const EdgeInsets.only(top: 8),
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                decoration: BoxDecoration(
                  color: const Color(0xFFE8EEF4),
                  borderRadius: const BorderRadius.vertical(bottom: Radius.circular(10)),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        'Logout: ${c.lastLogout.isNotEmpty ? c.lastLogout : '—'}',
                        style: TextStyle(fontSize: 11, color: Colors.grey.shade700),
                      ),
                    ),
                    if (c.phone.isNotEmpty)
                      IconButton(
                        visualDensity: VisualDensity.compact,
                        padding: EdgeInsets.zero,
                        constraints: const BoxConstraints(),
                        icon: const Icon(Icons.phone, size: 18, color: RadiantTokens.brand),
                        onPressed: () => _callPhone(c.phone),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _cardField(String label, String value, {Color? valueColor}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: RichText(
        text: TextSpan(
          style: TextStyle(fontSize: 12, color: Colors.grey.shade700),
          children: [
            TextSpan(text: '$label: ', style: const TextStyle(fontWeight: FontWeight.w500)),
            TextSpan(
              text: value.isNotEmpty ? value : '—',
              style: TextStyle(color: valueColor ?? Colors.black87, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
    );
  }
}

extension _FirstOrNull<T> on Iterable<T> {
  T? get firstOrNull {
    final it = iterator;
    if (!it.moveNext()) return null;
    return it.current;
  }
}
