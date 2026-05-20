import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../services/api_service.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../widgets/page_scaffold.dart';
import '../widgets/state_views.dart';
import 'staff_customer_detail_screen.dart';

class StaffAddCustomerScreen extends StatefulWidget {
  const StaffAddCustomerScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<StaffAddCustomerScreen> createState() => _StaffAddCustomerScreenState();
}

class _StaffAddCustomerScreenState extends State<StaffAddCustomerScreen> {
  final _nameCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _addressCtrl = TextEditingController();
  final _codeCtrl = TextEditingController();
  final _pppUserCtrl = TextEditingController();
  final _pppPassCtrl = TextEditingController();
  final _radiusCtrl = TextEditingController();
  final _portalPassCtrl = TextEditingController();
  final _notesCtrl = TextEditingController();
  final _billingDayCtrl = TextEditingController();

  Map<String, dynamic>? _options;
  List<Map<String, dynamic>> _packages = [];
  List<Map<String, dynamic>> _areas = [];
  List<Map<String, dynamic>> _zones = [];
  List<Map<String, dynamic>> _servers = [];
  List<Map<String, dynamic>> _statusOpts = [];

  int? _packageId;
  int? _areaId;
  int? _zoneId;
  int? _serverId;
  String _status = 'active';
  String _billingMode = 'postpaid';
  DateTime? _joinedAt;
  DateTime? _expiresAt;
  bool _provisionMikrotik = true;
  bool _loading = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadOptions();
    _phoneCtrl.addListener(_autoPppUser);
  }

  void _autoPppUser() {
    if (_pppUserCtrl.text.trim().isEmpty) {
      final digits = _phoneCtrl.text.replaceAll(RegExp(r'\D'), '');
      if (digits.isNotEmpty) _pppUserCtrl.text = digits;
    }
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    _emailCtrl.dispose();
    _addressCtrl.dispose();
    _codeCtrl.dispose();
    _pppUserCtrl.dispose();
    _pppPassCtrl.dispose();
    _radiusCtrl.dispose();
    _portalPassCtrl.dispose();
    _notesCtrl.dispose();
    _billingDayCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadOptions() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final body = await widget.api.staffCustomerFormOptions();
      final defaults = body['defaults'] as Map<String, dynamic>? ?? {};
      if (!mounted) return;
      setState(() {
        _options = body;
        _packages = (body['packages'] as List<dynamic>?)?.map((e) => Map<String, dynamic>.from(e as Map)).toList() ?? [];
        _areas = (body['areas'] as List<dynamic>?)?.map((e) => Map<String, dynamic>.from(e as Map)).toList() ?? [];
        _zones = (body['zones'] as List<dynamic>?)?.map((e) => Map<String, dynamic>.from(e as Map)).toList() ?? [];
        _servers = (body['mikrotik_servers'] as List<dynamic>?)?.map((e) => Map<String, dynamic>.from(e as Map)).toList() ?? [];
        _statusOpts = (body['status_options'] as List<dynamic>?)?.map((e) => Map<String, dynamic>.from(e as Map)).toList() ?? [];
        _status = defaults['status']?.toString() ?? 'active';
        _billingMode = defaults['billing_mode']?.toString() ?? 'postpaid';
        _provisionMikrotik = defaults['provision_mikrotik'] != false;
        _billingDayCtrl.text = '${defaults['billing_day'] ?? DateTime.now().day}';
        _joinedAt = DateTime.tryParse(defaults['joined_at']?.toString() ?? '') ?? DateTime.now();
        _expiresAt = DateTime.tryParse(defaults['service_expires_at']?.toString() ?? '');
        if (_packages.isNotEmpty) _packageId = (_packages.first['id'] as num).toInt();
        if (_servers.isNotEmpty) _serverId = (_servers.first['id'] as num).toInt();
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not load form');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _save() async {
    if (_nameCtrl.text.trim().isEmpty || _phoneCtrl.text.trim().isEmpty || _packageId == null) {
      showSnack(context, 'Name, phone and package required', isError: true);
      return;
    }
    setState(() => _saving = true);
    try {
      final res = await widget.api.createStaffCustomerFull(
        name: _nameCtrl.text.trim(),
        phone: _phoneCtrl.text.trim(),
        packageId: _packageId!,
        email: _emailCtrl.text.trim(),
        address: _addressCtrl.text.trim(),
        customerCode: _codeCtrl.text.trim(),
        status: _status,
        mikrotikSecretName: _pppUserCtrl.text.trim(),
        mikrotikPppPassword: _pppPassCtrl.text.trim(),
        radiusUsername: _radiusCtrl.text.trim(),
        portalPassword: _portalPassCtrl.text.trim(),
        notes: _notesCtrl.text.trim(),
        billingDay: int.tryParse(_billingDayCtrl.text.trim()),
        billingMode: _billingMode,
        areaId: _areaId,
        zoneId: _zoneId,
        mikrotikServerId: _serverId,
        joinedAt: _joinedAt != null ? DateFormat('yyyy-MM-dd').format(_joinedAt!) : null,
        serviceExpiresAt: _expiresAt != null ? DateFormat('yyyy-MM-dd').format(_expiresAt!) : null,
        provisionMikrotik: _provisionMikrotik,
      );
      final customer = res['customer'] as Map<String, dynamic>?;
      final network = res['network'] as Map<String, dynamic>?;
      final id = (customer?['id'] as num?)?.toInt();
      if (!mounted) return;
      showSnack(context, '${network?['message'] ?? res['message'] ?? 'Created'}');
      if (id != null) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => StaffCustomerDetailScreen(api: widget.api, customerId: id)),
        );
      } else {
        Navigator.pop(context);
      }
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message, isError: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return PageScaffold(
      title: 'New customer',
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: ErrorBanner(message: _error!, onRetry: _loadOptions))
              : SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      _sectionTitle('Account'),
                      _field(_nameCtrl, 'Full name *'),
                      _field(_phoneCtrl, 'Phone *', keyboard: TextInputType.phone),
                      _field(_emailCtrl, 'Email'),
                      _field(_addressCtrl, 'Address', maxLines: 2),
                      _field(_codeCtrl, 'Customer ID (auto if empty)'),
                      const SizedBox(height: 16),
                      _sectionTitle('Package & billing'),
                      _packageDropdown(),
                      _statusDropdown(),
                      _field(_billingDayCtrl, 'Bill day (1-28)', keyboard: TextInputType.number),
                      _billingModeDropdown(),
                      _dateRow('Activation', _joinedAt, (d) => setState(() => _joinedAt = d)),
                      _dateRow('Expire date', _expiresAt, (d) => setState(() => _expiresAt = d)),
                      const SizedBox(height: 16),
                      _sectionTitle('PPPoE / MikroTik'),
                      SwitchListTile(
                        value: _provisionMikrotik,
                        onChanged: (v) => setState(() => _provisionMikrotik = v),
                        title: const Text('Provision MikroTik on save'),
                        subtitle: const Text('Creates PPP secret & activates line (same as website)'),
                      ),
                      _serverDropdown(),
                      _field(_pppUserCtrl, 'PPPoE username *'),
                      _field(_pppPassCtrl, 'PPPoE password', obscure: true),
                      _field(_radiusCtrl, 'RADIUS username (optional)'),
                      const SizedBox(height: 16),
                      _sectionTitle('Customer portal'),
                      _field(_portalPassCtrl, 'Portal login password', obscure: true),
                      const SizedBox(height: 16),
                      _sectionTitle('Location'),
                      _areaDropdown(),
                      _zoneDropdown(),
                      _field(_notesCtrl, 'Notes', maxLines: 2),
                      const SizedBox(height: 24),
                      FilledButton(
                        onPressed: _saving ? null : _save,
                        child: _saving
                            ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2))
                            : const Text('Create & activate'),
                      ),
                    ],
                  ),
                ),
    );
  }

  Widget _sectionTitle(String t) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Text(t, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.primary)),
      );

  Widget _field(TextEditingController c, String label, {bool obscure = false, int maxLines = 1, TextInputType? keyboard}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: TextField(
        controller: c,
        obscureText: obscure,
        maxLines: maxLines,
        keyboardType: keyboard,
        decoration: InputDecoration(labelText: label, border: const OutlineInputBorder()),
      ),
    );
  }

  Widget _packageDropdown() {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: DropdownButtonFormField<int>(
        value: _packageId,
        decoration: const InputDecoration(labelText: 'Package *', border: OutlineInputBorder()),
        items: _packages
            .map((p) => DropdownMenuItem(
                  value: (p['id'] as num).toInt(),
                  child: Text('${p['name']} · ${p['download_mbps']}Mbps · ৳${p['price_monthly']}'),
                ))
            .toList(),
        onChanged: (v) => setState(() => _packageId = v),
      ),
    );
  }

  Widget _statusDropdown() {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: DropdownButtonFormField<String>(
        value: _status,
        decoration: const InputDecoration(labelText: 'Status', border: OutlineInputBorder()),
        items: _statusOpts
            .map((s) => DropdownMenuItem(value: s['value']?.toString(), child: Text(s['label']?.toString() ?? '')))
            .toList(),
        onChanged: (v) => setState(() => _status = v ?? _status),
      ),
    );
  }

  Widget _billingModeDropdown() {
    final modes = (_options?['billing_modes'] as List<dynamic>?) ?? [];
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: DropdownButtonFormField<String>(
        value: _billingMode,
        decoration: const InputDecoration(labelText: 'Billing mode', border: OutlineInputBorder()),
        items: modes
            .map((m) => DropdownMenuItem(
                  value: (m as Map)['value']?.toString(),
                  child: Text((m)['label']?.toString() ?? ''),
                ))
            .toList(),
        onChanged: (v) => setState(() => _billingMode = v ?? _billingMode),
      ),
    );
  }

  Widget _serverDropdown() {
    if (_servers.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: DropdownButtonFormField<int>(
        value: _serverId,
        decoration: const InputDecoration(labelText: 'MikroTik router', border: OutlineInputBorder()),
        items: _servers
            .map((s) => DropdownMenuItem(value: (s['id'] as num).toInt(), child: Text('${s['name']} (${s['host'] ?? ''})')))
            .toList(),
        onChanged: (v) => setState(() => _serverId = v),
      ),
    );
  }

  Widget _areaDropdown() {
    if (_areas.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: DropdownButtonFormField<int>(
        value: _areaId,
        decoration: const InputDecoration(labelText: 'Area', border: OutlineInputBorder()),
        items: [
          const DropdownMenuItem(value: null, child: Text('—')),
          ..._areas.map((a) => DropdownMenuItem(value: (a['id'] as num).toInt(), child: Text(a['name']?.toString() ?? ''))),
        ],
        onChanged: (v) => setState(() => _areaId = v),
      ),
    );
  }

  Widget _zoneDropdown() {
    final filtered = _areaId == null ? _zones : _zones.where((z) => z['area_id'] == _areaId).toList();
    if (filtered.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: DropdownButtonFormField<int>(
        value: _zoneId,
        decoration: const InputDecoration(labelText: 'Zone', border: OutlineInputBorder()),
        items: [
          const DropdownMenuItem(value: null, child: Text('—')),
          ...filtered.map((z) => DropdownMenuItem(value: (z['id'] as num).toInt(), child: Text(z['name']?.toString() ?? ''))),
        ],
        onChanged: (v) => setState(() => _zoneId = v),
      ),
    );
  }

  Widget _dateRow(String label, DateTime? value, ValueChanged<DateTime?> onPick) {
    final fmt = DateFormat('dd MMM yyyy');
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(label),
      subtitle: Text(value != null ? fmt.format(value) : 'Not set'),
      trailing: const Icon(Icons.calendar_today),
      onTap: () async {
        final d = await showDatePicker(
          context: context,
          initialDate: value ?? DateTime.now(),
          firstDate: DateTime(2020),
          lastDate: DateTime(2035),
        );
        if (d != null) onPick(d);
      },
    );
  }
}
