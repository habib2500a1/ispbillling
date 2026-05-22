import 'package:flutter/material.dart';

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
  final _pppUserCtrl = TextEditingController();
  final _pppPassCtrl = TextEditingController();
  final _addressCtrl = TextEditingController();
  final _notesCtrl = TextEditingController();
  final _customerIdCtrl = TextEditingController();

  List<Map<String, dynamic>> _packages = [];
  List<Map<String, dynamic>> _servers = [];
  List<Map<String, dynamic>> _areas = [];
  List<Map<String, dynamic>> _zones = [];
  int? _packageId;
  int? _serverId;
  int? _areaId;
  int? _zoneId;
  int _expireDay = 10;
  String _billingMode = 'prepaid';
  String _firstBillCycle = 'this_month';
  bool _provisionMikrotik = true;
  bool _autoCustomerId = true;
  String? _nextCustomerIdExample;
  int _step = 0;
  bool _loading = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadOptions();
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    _pppUserCtrl.dispose();
    _pppPassCtrl.dispose();
    _addressCtrl.dispose();
    _notesCtrl.dispose();
    _customerIdCtrl.dispose();
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
      final customerId = body['customer_id'] as Map<String, dynamic>? ?? {};
      if (!mounted) return;
      setState(() {
        _autoCustomerId = customerId['auto_generate'] != false;
        _nextCustomerIdExample = customerId['next_example']?.toString();
        _packages = (body['packages'] as List<dynamic>?)?.map((e) => Map<String, dynamic>.from(e as Map)).toList() ?? [];
        _servers = (body['mikrotik_servers'] as List<dynamic>?)?.map((e) => Map<String, dynamic>.from(e as Map)).toList() ?? [];
        _areas = (body['areas'] as List<dynamic>?)?.map((e) => Map<String, dynamic>.from(e as Map)).toList() ?? [];
        _zones = (body['zones'] as List<dynamic>?)?.map((e) => Map<String, dynamic>.from(e as Map)).toList() ?? [];
        _billingMode = defaults['billing_mode']?.toString() ?? 'prepaid';
        _firstBillCycle = defaults['first_bill_cycle']?.toString() ?? 'this_month';
        _expireDay = (defaults['expire_day'] as num?)?.toInt() ?? 10;
        _provisionMikrotik = defaults['provision_mikrotik'] != false;
        if (_packages.isNotEmpty) {
          _packageId = (_packages.first['id'] as num).toInt();
          _applyServerForPackage(_packageId);
        }
        if (_serverId == null && _servers.isNotEmpty) {
          _serverId = (_servers.first['id'] as num).toInt();
        }
        if (_areas.isNotEmpty) {
          _areaId = (_areas.first['id'] as num).toInt();
          _syncZonesForArea();
        }
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not load form');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _applyServerForPackage(int? packageId) {
    if (packageId == null) return;
    for (final p in _packages) {
      if ((p['id'] as num).toInt() == packageId) {
        final sid = (p['mikrotik_server_id'] as num?)?.toInt();
        if (sid != null) _serverId = sid;
        break;
      }
    }
  }

  List<Map<String, dynamic>> get _zonesForArea {
    if (_areaId == null) return _zones;
    return _zones.where((z) => (z['area_id'] as num?)?.toInt() == _areaId).toList();
  }

  void _syncZonesForArea() {
    final list = _zonesForArea;
    if (list.isEmpty) {
      _zoneId = null;
      return;
    }
    if (_zoneId == null || !list.any((z) => (z['id'] as num).toInt() == _zoneId)) {
      _zoneId = (list.first['id'] as num).toInt();
    }
  }

  bool _validateStep1() {
    if (_nameCtrl.text.trim().isEmpty || _phoneCtrl.text.trim().isEmpty || _packageId == null) {
      showSnack(context, 'Name, phone and package required', isError: true);
      return false;
    }
    if (_addressCtrl.text.trim().isEmpty) {
      showSnack(context, 'Address required', isError: true);
      return false;
    }
    if (_areas.isNotEmpty && _areaId == null) {
      showSnack(context, 'Select area', isError: true);
      return false;
    }
    if (_zonesForArea.isNotEmpty && _zoneId == null) {
      showSnack(context, 'Select zone', isError: true);
      return false;
    }
    if (!_autoCustomerId && _customerIdCtrl.text.trim().isEmpty) {
      showSnack(context, 'Customer ID required', isError: true);
      return false;
    }
    return true;
  }

  void _goNext() {
    if (!_validateStep1()) return;
    setState(() => _step = 1);
  }

  Future<void> _save() async {
    if (!_validateStep1()) {
      setState(() => _step = 0);
      return;
    }
    setState(() => _saving = true);
    try {
      final pppUser = _pppUserCtrl.text.trim();
      final res = await widget.api.createStaffCustomerFull(
        name: _nameCtrl.text.trim(),
        phone: _phoneCtrl.text.trim(),
        packageId: _packageId!,
        address: _addressCtrl.text.trim(),
        areaId: _areaId,
        zoneId: _zoneId,
        notes: _notesCtrl.text.trim(),
        billingDay: 1,
        billingMode: _billingMode,
        firstBillCycle: _firstBillCycle,
        expireDay: _expireDay,
        mikrotikSecretName: pppUser.isNotEmpty ? pppUser : null,
        mikrotikPppPassword: _pppPassCtrl.text.trim().isNotEmpty ? _pppPassCtrl.text.trim() : null,
        mikrotikServerId: _serverId,
        provisionMikrotik: _provisionMikrotik && pppUser.isNotEmpty,
        customerCode: _customerIdCtrl.text.trim().isNotEmpty ? _customerIdCtrl.text.trim() : null,
      );
      final id = (res['customer'] as Map?)?['id'] as num?;
      if (!mounted) return;
      final bill = res['billing'] as Map<String, dynamic>?;
      final inv = bill?['invoice'] as Map<String, dynamic>?;
      var msg = res['message']?.toString() ?? 'Customer created';
      if (inv != null) {
        msg = '${bill?['message'] ?? 'Bill created'} · ${inv['invoice_number']} · ৳${inv['balance_due'] ?? inv['total']}';
      }
      showSnack(context, msg);
      if (id != null) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => StaffCustomerDetailScreen(api: widget.api, customerId: id.toInt())),
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
      useGradientBody: true,
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: ErrorBanner(message: _error!, onRetry: _loadOptions))
              : Column(
                  children: [
                    _stepHeader(),
                    Expanded(
                      child: SingleChildScrollView(
                        padding: const EdgeInsets.all(16),
                        child: _step == 0 ? _buildStep1() : _buildStep2(),
                      ),
                    ),
                    _bottomBar(),
                  ],
                ),
    );
  }

  Widget _stepHeader() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      child: Row(
        children: [
          _stepDot(0, 'Customer'),
          Expanded(child: Container(height: 2, color: _step >= 1 ? AppTheme.primary : Colors.grey.shade300)),
          _stepDot(1, 'PPPoE'),
        ],
      ),
    );
  }

  Widget _stepDot(int index, String label) {
    final active = _step == index;
    final done = _step > index;
    return Column(
      children: [
        CircleAvatar(
          radius: 14,
          backgroundColor: done || active ? AppTheme.primary : Colors.grey.shade300,
          child: Text(
            '${index + 1}',
            style: TextStyle(fontSize: 12, color: done || active ? Colors.white : Colors.grey.shade700),
          ),
        ),
        const SizedBox(height: 4),
        Text(label, style: TextStyle(fontSize: 11, fontWeight: active ? FontWeight.w600 : FontWeight.normal)),
      ],
    );
  }

  Widget _buildStep1() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text(
          'Step 1 — Customer details',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 4),
        const Text('Name, phone, package. PPP login on next page.', style: TextStyle(fontSize: 13, color: Colors.grey)),
        const SizedBox(height: 16),
        _field(_nameCtrl, 'Full name *', Icons.person),
        _field(_phoneCtrl, 'Phone *', Icons.phone, keyboard: TextInputType.phone),
        if (!_autoCustomerId)
          _field(_customerIdCtrl, 'Customer ID *', Icons.badge)
        else if (_nextCustomerIdExample != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Text(
              'Customer ID: automatic (e.g. $_nextCustomerIdExample)',
              style: TextStyle(fontSize: 12, color: Colors.grey.shade700),
            ),
          ),
        if (_packages.isEmpty)
          Card(
            color: Colors.orange.shade50,
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                children: [
                  const Text('No packages — sync from website or tap reload.'),
                  TextButton(onPressed: _loadOptions, child: const Text('Reload')),
                ],
              ),
            ),
          )
        else
          _packageDropdown(),
        _field(_addressCtrl, 'Address *', Icons.location_on, maxLines: 2),
        if (_areas.isNotEmpty) _areaDropdown(),
        if (_zonesForArea.isNotEmpty) _zoneDropdown(),
        _expireDayDropdown(),
        _billingModeChips(),
        if (_billingMode == 'prepaid' || _billingMode == 'advance') _firstBillChips(),
        if (_autoCustomerId) _field(_customerIdCtrl, 'Custom Customer ID (optional)', Icons.badge),
      ],
    );
  }

  Widget _buildStep2() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text(
          'Step 2 — PPPoE login',
          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 4),
        const Text('Username and password together (one place only).', style: TextStyle(fontSize: 13, color: Colors.grey)),
        const SizedBox(height: 16),
        Card(
          elevation: 0,
          color: AppTheme.primary.withValues(alpha: 0.06),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
            side: BorderSide(color: AppTheme.primary.withValues(alpha: 0.25)),
          ),
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              children: [
                _field(_pppUserCtrl, 'PPPoE username (optional)', Icons.person_outline),
                _field(_pppPassCtrl, 'PPPoE password', Icons.lock_outline, obscure: true),
              ],
            ),
          ),
        ),
        if (_servers.length > 1) _serverDropdown(),
        SwitchListTile(
          value: _provisionMikrotik,
          onChanged: (v) => setState(() => _provisionMikrotik = v),
          title: const Text('Activate on MikroTik'),
          subtitle: const Text('Only when PPP username is set'),
          contentPadding: EdgeInsets.zero,
        ),
        const SizedBox(height: 8),
        _field(_notesCtrl, 'Notes (optional)', Icons.notes, maxLines: 2),
      ],
    );
  }

  Widget _bottomBar() {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
        child: Row(
          children: [
            if (_step > 0)
              OutlinedButton(
                onPressed: _saving ? null : () => setState(() => _step = 0),
                child: const Text('Back'),
              ),
            if (_step > 0) const SizedBox(width: 12),
            Expanded(
              child: FilledButton(
                onPressed: _saving
                    ? null
                    : _step == 0
                        ? _goNext
                        : _save,
                style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(52)),
                child: _saving
                    ? const SizedBox(
                        height: 22,
                        width: 22,
                        child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                      )
                    : Text(_step == 0 ? 'Next' : 'Save & open customer'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _field(TextEditingController c, String label, IconData icon,
      {bool obscure = false, int maxLines = 1, TextInputType? keyboard}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextField(
        controller: c,
        obscureText: obscure,
        maxLines: maxLines,
        keyboardType: keyboard,
        decoration: InputDecoration(
          labelText: label,
          prefixIcon: Icon(icon, size: 22),
          border: const OutlineInputBorder(),
        ),
      ),
    );
  }

  Widget _packageDropdown() {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: DropdownButtonFormField<int>(
        initialValue: _packageId,
        decoration: const InputDecoration(labelText: 'Package *', prefixIcon: Icon(Icons.speed), border: OutlineInputBorder()),
        items: _packages
            .map((p) => DropdownMenuItem(
                  value: (p['id'] as num).toInt(),
                  child: Text('${p['name']} · ৳${p['price_monthly']}', overflow: TextOverflow.ellipsis),
                ))
            .toList(),
        onChanged: (v) => setState(() {
          _packageId = v;
          _applyServerForPackage(v);
        }),
      ),
    );
  }

  Widget _areaDropdown() {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: DropdownButtonFormField<int>(
        initialValue: _areaId,
        decoration: const InputDecoration(labelText: 'Area *', prefixIcon: Icon(Icons.map), border: OutlineInputBorder()),
        items: _areas
            .map((a) => DropdownMenuItem(value: (a['id'] as num).toInt(), child: Text(a['name']?.toString() ?? '')))
            .toList(),
        onChanged: (v) => setState(() {
          _areaId = v;
          _syncZonesForArea();
        }),
      ),
    );
  }

  Widget _zoneDropdown() {
    final list = _zonesForArea;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: DropdownButtonFormField<int>(
        initialValue: list.any((z) => (z['id'] as num).toInt() == _zoneId) ? _zoneId : null,
        decoration: const InputDecoration(labelText: 'Zone *', prefixIcon: Icon(Icons.place), border: OutlineInputBorder()),
        items: list
            .map((z) => DropdownMenuItem(value: (z['id'] as num).toInt(), child: Text(z['name']?.toString() ?? '')))
            .toList(),
        onChanged: (v) => setState(() => _zoneId = v),
      ),
    );
  }

  Widget _expireDayDropdown() {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: DropdownButtonFormField<int>(
        initialValue: _expireDay,
        decoration: const InputDecoration(labelText: 'Expire day *', prefixIcon: Icon(Icons.event), border: OutlineInputBorder()),
        items: List.generate(31, (i) => i + 1)
            .map((d) => DropdownMenuItem(value: d, child: Text('Day $d')))
            .toList(),
        onChanged: (v) => setState(() => _expireDay = v ?? _expireDay),
      ),
    );
  }

  Widget _serverDropdown() {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: DropdownButtonFormField<int>(
        initialValue: _serverId,
        decoration: const InputDecoration(labelText: 'Router', prefixIcon: Icon(Icons.dns), border: OutlineInputBorder()),
        items: _servers.map((s) => DropdownMenuItem(value: (s['id'] as num).toInt(), child: Text(s['name']?.toString() ?? ''))).toList(),
        onChanged: (v) => setState(() => _serverId = v),
      ),
    );
  }

  Widget _firstBillChips() {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('First bill (prepaid)', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
          const SizedBox(height: 6),
          Wrap(
            spacing: 8,
            children: [
              _firstBillChip('this_month', 'Bill today'),
              _firstBillChip('next_month', 'Next month'),
            ],
          ),
        ],
      ),
    );
  }

  Widget _firstBillChip(String value, String label) {
    final selected = _firstBillCycle == value;
    return ChoiceChip(
      label: Text(label, style: const TextStyle(fontSize: 12)),
      selected: selected,
      onSelected: (_) => setState(() => _firstBillCycle = value),
      selectedColor: AppTheme.accent.withValues(alpha: 0.4),
    );
  }

  Widget _billingModeChips() {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Billing', style: TextStyle(fontSize: 13, color: Colors.grey)),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            children: [
              _modeChip('postpaid', 'Postpaid'),
              _modeChip('prepaid', 'Prepaid'),
            ],
          ),
        ],
      ),
    );
  }

  Widget _modeChip(String value, String label) {
    final selected = _billingMode == value;
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) => setState(() {
        _billingMode = value;
        if (value == 'prepaid' || value == 'advance') {
          _firstBillCycle = 'this_month';
        }
      }),
      selectedColor: AppTheme.accent.withValues(alpha: 0.4),
    );
  }
}
