import 'package:flutter/material.dart';

import '../services/api_service.dart';
import '../support/customer_status.dart';
import '../theme/app_theme.dart';
import '../utils/app_nav.dart';
import '../widgets/page_scaffold.dart';

class StaffCustomerEditScreen extends StatefulWidget {
  const StaffCustomerEditScreen({super.key, required this.api, required this.customer});

  final ApiService api;
  final Map<String, dynamic> customer;

  @override
  State<StaffCustomerEditScreen> createState() => _StaffCustomerEditScreenState();
}

class _StaffCustomerEditScreenState extends State<StaffCustomerEditScreen> {
  late final TextEditingController _nameCtrl = TextEditingController(text: widget.customer['name']?.toString());
  late final TextEditingController _phoneCtrl = TextEditingController(text: widget.customer['phone']?.toString());
  late final TextEditingController _addressCtrl = TextEditingController(text: widget.customer['address']?.toString());
  late final TextEditingController _notesCtrl = TextEditingController(text: widget.customer['notes']?.toString());
  late final TextEditingController _customerIdCtrl =
      TextEditingController(text: widget.customer['customer_code']?.toString());
  late final TextEditingController _pppUserCtrl =
      TextEditingController(text: widget.customer['mikrotik_secret_name']?.toString());

  List<Map<String, dynamic>> _packages = [];
  List<Map<String, dynamic>> _areas = [];
  List<Map<String, dynamic>> _zones = [];
  int? _packageId;
  int? _areaId;
  int? _zoneId;
  late int _expireDay = (widget.customer['expire_day'] as num?)?.toInt() ?? 10;
  late String _status = widget.customer['status']?.toString() ?? 'active';
  late String _billingMode = widget.customer['billing_mode']?.toString() ?? 'prepaid';
  bool _loading = true;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _packageId = widget.customer['package_id'] as int?;
    _areaId = widget.customer['area_id'] as int?;
    _zoneId = widget.customer['zone_id'] as int?;
    _load();
  }

  Future<void> _load() async {
    try {
      final body = await widget.api.staffCustomerFormOptions();
      final list = (body['packages'] as List<dynamic>?)?.map((e) => Map<String, dynamic>.from(e as Map)).toList() ?? [];
      if (!mounted) return;
      setState(() {
        _packages = list;
        _areas = (body['areas'] as List<dynamic>?)?.map((e) => Map<String, dynamic>.from(e as Map)).toList() ?? [];
        _zones = (body['zones'] as List<dynamic>?)?.map((e) => Map<String, dynamic>.from(e as Map)).toList() ?? [];
        _loading = false;
        if (_packageId == null) {
          final pkgName = widget.customer['package']?.toString();
          for (final p in list) {
            if (p['name']?.toString() == pkgName) {
              _packageId = (p['id'] as num).toInt();
              break;
            }
          }
        }
        _syncZonesForArea();
      });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
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

  @override
  void dispose() {
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    _addressCtrl.dispose();
    _notesCtrl.dispose();
    _customerIdCtrl.dispose();
    _pppUserCtrl.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_nameCtrl.text.trim().isEmpty || _phoneCtrl.text.trim().isEmpty) {
      showSnack(context, 'Name and phone required', isError: true);
      return;
    }
    if (_addressCtrl.text.trim().isEmpty) {
      showSnack(context, 'Address required', isError: true);
      return;
    }
    final code = _customerIdCtrl.text.trim();
    if (code.isEmpty) {
      showSnack(context, 'Customer ID cannot be empty', isError: true);
      return;
    }
    setState(() => _saving = true);
    try {
      final payload = <String, dynamic>{
        'name': _nameCtrl.text.trim(),
        'phone': _phoneCtrl.text.trim(),
        'address': _addressCtrl.text.trim(),
        'status': _status,
        'customer_code': code,
        'billing_mode': _billingMode,
        'expire_day': _expireDay,
        'mikrotik_secret_name': _pppUserCtrl.text.trim(),
        'notes': _notesCtrl.text.trim(),
      };
      if (_packageId != null) payload['package_id'] = _packageId;
      if (_areaId != null) payload['area_id'] = _areaId;
      if (_zoneId != null) payload['zone_id'] = _zoneId;
      await widget.api.staffUpdateCustomer((widget.customer['id'] as num).toInt(), payload);
      if (mounted) {
        showSnack(context, 'Saved');
        Navigator.pop(context, true);
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
      title: 'Edit customer',
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  TextField(
                    controller: _customerIdCtrl,
                    decoration: const InputDecoration(
                      labelText: 'Customer ID *',
                      prefixIcon: Icon(Icons.badge),
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _nameCtrl,
                    decoration: const InputDecoration(labelText: 'Name *', prefixIcon: Icon(Icons.person), border: OutlineInputBorder()),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _phoneCtrl,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(labelText: 'Phone *', prefixIcon: Icon(Icons.phone), border: OutlineInputBorder()),
                  ),
                  const SizedBox(height: 12),
                  if (_packages.isNotEmpty)
                    DropdownButtonFormField<int>(
                      initialValue: _packages.any((p) => (p['id'] as num).toInt() == _packageId) ? _packageId : null,
                      decoration: const InputDecoration(labelText: 'Package', prefixIcon: Icon(Icons.speed), border: OutlineInputBorder()),
                      items: _packages
                          .map((p) => DropdownMenuItem(value: (p['id'] as num).toInt(), child: Text(p['name']?.toString() ?? '')))
                          .toList(),
                      onChanged: (v) => setState(() => _packageId = v),
                    ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _addressCtrl,
                    maxLines: 2,
                    decoration: const InputDecoration(
                      labelText: 'Address *',
                      prefixIcon: Icon(Icons.location_on),
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 12),
                  if (_areas.isNotEmpty)
                    DropdownButtonFormField<int>(
                      initialValue: _areas.any((a) => (a['id'] as num).toInt() == _areaId) ? _areaId : null,
                      decoration: const InputDecoration(labelText: 'Area *', prefixIcon: Icon(Icons.map), border: OutlineInputBorder()),
                      items: _areas
                          .map((a) => DropdownMenuItem(value: (a['id'] as num).toInt(), child: Text(a['name']?.toString() ?? '')))
                          .toList(),
                      onChanged: (v) => setState(() {
                        _areaId = v;
                        _syncZonesForArea();
                      }),
                    ),
                  if (_areas.isNotEmpty) const SizedBox(height: 12),
                  if (_zonesForArea.isNotEmpty)
                    DropdownButtonFormField<int>(
                      initialValue: _zonesForArea.any((z) => (z['id'] as num).toInt() == _zoneId) ? _zoneId : null,
                      decoration: const InputDecoration(labelText: 'Zone *', prefixIcon: Icon(Icons.place), border: OutlineInputBorder()),
                      items: _zonesForArea
                          .map((z) => DropdownMenuItem(value: (z['id'] as num).toInt(), child: Text(z['name']?.toString() ?? '')))
                          .toList(),
                      onChanged: (v) => setState(() => _zoneId = v),
                    ),
                  if (_zonesForArea.isNotEmpty) const SizedBox(height: 12),
                  DropdownButtonFormField<int>(
                    initialValue: _expireDay,
                    decoration: const InputDecoration(labelText: 'Expire day *', prefixIcon: Icon(Icons.event), border: OutlineInputBorder()),
                    items: List.generate(31, (i) => i + 1)
                        .map((d) => DropdownMenuItem(value: d, child: Text('Day $d')))
                        .toList(),
                    onChanged: (v) => setState(() => _expireDay = v ?? _expireDay),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: _billingMode,
                    decoration: const InputDecoration(labelText: 'Billing mode', prefixIcon: Icon(Icons.payments), border: OutlineInputBorder()),
                    items: const [
                      DropdownMenuItem(value: 'prepaid', child: Text('Prepaid')),
                      DropdownMenuItem(value: 'postpaid', child: Text('Postpaid')),
                      DropdownMenuItem(value: 'advance', child: Text('Advance')),
                    ],
                    onChanged: (v) => setState(() => _billingMode = v ?? _billingMode),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: _status,
                    decoration: const InputDecoration(labelText: 'Status', prefixIcon: Icon(Icons.toggle_on), border: OutlineInputBorder()),
                    items: CustomerStatus.options.entries.map((e) => DropdownMenuItem(value: e.key, child: Text(e.value))).toList(),
                    onChanged: (v) => setState(() => _status = v ?? _status),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _pppUserCtrl,
                    decoration: const InputDecoration(
                      labelText: 'PPPoE username (optional)',
                      prefixIcon: Icon(Icons.router),
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _notesCtrl,
                    maxLines: 2,
                    decoration: const InputDecoration(labelText: 'Notes', border: OutlineInputBorder()),
                  ),
                  const SizedBox(height: 24),
                  FilledButton(
                    onPressed: _saving ? null : _save,
                    style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(48)),
                    child: _saving ? const CircularProgressIndicator(color: Colors.white) : const Text('Save'),
                  ),
                ],
              ),
            ),
    );
  }
}
