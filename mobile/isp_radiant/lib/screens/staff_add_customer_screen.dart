import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:intl/intl.dart';
import 'package:latlong2/latlong.dart';

import '../design_system/radiant_tokens.dart';
import '../services/api_service.dart';
import '../utils/app_nav.dart';
import 'staff_customer_detail_screen.dart';

/// Legacy SOFTIFY multi-step Add Client — personal info, location/map, network (website parity).
class StaffAddCustomerScreen extends StatefulWidget {
  const StaffAddCustomerScreen({super.key, required this.api});

  final ApiService api;

  @override
  State<StaffAddCustomerScreen> createState() => _StaffAddCustomerScreenState();
}

class _StaffAddCustomerScreenState extends State<StaffAddCustomerScreen> {
  final _nameCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _altPhoneCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _nidCtrl = TextEditingController();
  final _occupationCtrl = TextEditingController();
  final _addressCtrl = TextEditingController();
  final _houseCtrl = TextEditingController();
  final _roadCtrl = TextEditingController();
  final _pppUserCtrl = TextEditingController();
  final _pppPassCtrl = TextEditingController();
  final _notesCtrl = TextEditingController();
  final _customerIdCtrl = TextEditingController();
  final _onuMacCtrl = TextEditingController();
  final _eponCtrl = TextEditingController();
  final _boxCtrl = TextEditingController();
  final _installChargeCtrl = TextEditingController(text: '0');
  final _deviceChargeCtrl = TextEditingController(text: '0');
  final _cashCtrl = TextEditingController(text: '0');
  final _discountCtrl = TextEditingController(text: '0');
  final _mapController = MapController();

  List<Map<String, dynamic>> _packages = [];
  List<Map<String, dynamic>> _servers = [];
  List<Map<String, dynamic>> _areas = [];
  List<Map<String, dynamic>> _zones = [];
  List<Map<String, dynamic>> _subzones = [];
  List<Map<String, dynamic>> _districts = [];
  List<Map<String, dynamic>> _upazilas = [];
  List<Map<String, dynamic>> _genders = [];
  List<Map<String, dynamic>> _segments = [];
  List<Map<String, dynamic>> _subscriberTypes = [];
  List<Map<String, dynamic>> _connectionTypes = [];
  List<Map<String, dynamic>> _onuOwnership = [];

  int? _packageId;
  int? _serverId;
  int? _areaId;
  int? _zoneId;
  int? _subzoneId;
  int? _districtId;
  int? _upazilaId;
  int _expireDay = 10;
  int _billingDay = 1;
  String _billingMode = 'prepaid';
  String _firstBillCycle = 'this_month';
  String _status = 'active';
  String? _gender;
  String _segment = 'residential';
  String _subscriberType = 'standard';
  String _connectionType = 'fiber';
  String _onuOwnershipVal = 'company';
  String _lineCashMethod = 'cash';
  bool _provisionMikrotik = true;
  bool _autoCustomerId = true;
  bool _applyLineCharges = false;
  bool _useWallet = true;
  String? _nextCustomerIdExample;
  DateTime? _dob;
  LatLng? _gps;
  int _step = 0;
  bool _loading = true;
  bool _saving = false;
  String? _error;

  static const _steps = ['Personal information', 'Location & package', 'Network'];

  @override
  void initState() {
    super.initState();
    _loadOptions();
  }

  @override
  void dispose() {
    for (final c in [
      _nameCtrl, _phoneCtrl, _altPhoneCtrl, _emailCtrl, _nidCtrl, _occupationCtrl,
      _addressCtrl, _houseCtrl, _roadCtrl, _pppUserCtrl, _pppPassCtrl, _notesCtrl,
      _customerIdCtrl, _onuMacCtrl, _eponCtrl, _boxCtrl, _installChargeCtrl,
      _deviceChargeCtrl, _cashCtrl, _discountCtrl,
    ]) {
      c.dispose();
    }
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
        _packages = _list(body['packages']);
        _servers = _list(body['mikrotik_servers']);
        _areas = _list(body['areas']);
        _zones = _list(body['zones']);
        _subzones = _list(body['subzones']);
        _districts = _list(body['districts']);
        _upazilas = _list(body['upazilas']);
        _genders = _list(body['gender_options']);
        _segments = _list(body['segments']);
        _subscriberTypes = _list(body['subscriber_types']);
        _connectionTypes = _list(body['connection_types']);
        _onuOwnership = _list(body['onu_ownership_options']);
        _billingMode = defaults['billing_mode']?.toString() ?? 'prepaid';
        _firstBillCycle = defaults['first_bill_cycle']?.toString() ?? 'this_month';
        _expireDay = (defaults['expire_day'] as num?)?.toInt() ?? 10;
        _billingDay = (defaults['billing_day'] as num?)?.toInt() ?? 1;
        _status = defaults['status']?.toString() ?? 'active';
        _provisionMikrotik = defaults['provision_mikrotik'] != false;
        if (_packages.isNotEmpty) {
          _packageId = (_packages.first['id'] as num).toInt();
          _applyServerForPackage(_packageId);
        }
        if (_serverId == null && _servers.isNotEmpty) _serverId = (_servers.first['id'] as num).toInt();
        if (_areas.isNotEmpty) {
          _areaId = (_areas.first['id'] as num).toInt();
          _syncZonesForArea();
        }
        _gps ??= const LatLng(23.8103, 90.4125);
      });
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } catch (_) {
      if (mounted) setState(() => _error = 'Could not load form');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> _list(dynamic raw) =>
      (raw as List<dynamic>? ?? const []).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();

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

  List<Map<String, dynamic>> get _subzonesForZone {
    if (_zoneId == null) return _subzones;
    return _subzones.where((s) => (s['zone_id'] as num?)?.toInt() == _zoneId).toList();
  }

  List<Map<String, dynamic>> get _upazilasForDistrict {
    if (_districtId == null) return _upazilas;
    return _upazilas.where((u) => (u['district_id'] as num?)?.toInt() == _districtId).toList();
  }

  void _syncZonesForArea() {
    final list = _zonesForArea;
    if (list.isEmpty) {
      _zoneId = null;
      _subzoneId = null;
      return;
    }
    if (_zoneId == null || !list.any((z) => (z['id'] as num).toInt() == _zoneId)) {
      _zoneId = (list.first['id'] as num).toInt();
    }
    _syncSubzonesForZone();
  }

  void _syncSubzonesForZone() {
    final list = _subzonesForZone;
    if (list.isEmpty) {
      _subzoneId = null;
      return;
    }
    if (_subzoneId == null || !list.any((s) => (s['id'] as num).toInt() == _subzoneId)) {
      _subzoneId = (list.first['id'] as num).toInt();
    }
  }

  bool _validateStep(int step) {
    if (step == 0) {
      if (_nameCtrl.text.trim().isEmpty || _phoneCtrl.text.trim().isEmpty) {
        showSnack(context, 'Client name and mobile are required', isError: true);
        return false;
      }
      return true;
    }
    if (step == 1) {
      if (_packageId == null) {
        showSnack(context, 'Select a package', isError: true);
        return false;
      }
      if (_addressCtrl.text.trim().isEmpty) {
        showSnack(context, 'Address is required', isError: true);
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
    return true;
  }

  void _next() {
    if (!_validateStep(_step)) return;
    if (_step < _steps.length - 1) setState(() => _step++);
  }

  void _back() {
    if (_step > 0) setState(() => _step--);
  }

  Future<void> _pickDob() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _dob ?? DateTime(1990),
      firstDate: DateTime(1950),
      lastDate: DateTime.now(),
    );
    if (picked != null) setState(() => _dob = picked);
  }

  Future<void> _save() async {
    for (var i = 0; i < _steps.length - 1; i++) {
      if (!_validateStep(i)) {
        setState(() => _step = i);
        return;
      }
    }
    setState(() => _saving = true);
    try {
      final meta = <String, dynamic>{
        if (_gender != null) 'gender': _gender,
        if (_dob != null) 'date_of_birth': DateFormat('yyyy-MM-dd').format(_dob!),
        if (_occupationCtrl.text.trim().isNotEmpty) 'occupation': _occupationCtrl.text.trim(),
        if (_houseCtrl.text.trim().isNotEmpty) 'house_no': _houseCtrl.text.trim(),
        if (_roadCtrl.text.trim().isNotEmpty) 'road_no': _roadCtrl.text.trim(),
        'connection_type': _connectionType,
        'onu_ownership': _onuOwnershipVal,
        if (_boxCtrl.text.trim().isNotEmpty) 'box_name': _boxCtrl.text.trim(),
        if (_eponCtrl.text.trim().isNotEmpty) 'epon_port': _eponCtrl.text.trim(),
        if (_onuMacCtrl.text.trim().isNotEmpty) 'onu_mac': _onuMacCtrl.text.trim(),
        if (_gps != null) ...{
          'gps_lat': _gps!.latitude.toStringAsFixed(7),
          'gps_lng': _gps!.longitude.toStringAsFixed(7),
        },
        'monthly_discount_bdt': double.tryParse(_discountCtrl.text.trim()) ?? 0,
      };

      final pppUser = _pppUserCtrl.text.trim();
      final res = await widget.api.createStaffCustomerFull(
        name: _nameCtrl.text.trim(),
        phone: _phoneCtrl.text.trim(),
        packageId: _packageId!,
        email: _emailCtrl.text.trim(),
        alternatePhone: _altPhoneCtrl.text.trim(),
        nidNumber: _nidCtrl.text.trim(),
        gender: _gender,
        dateOfBirth: _dob != null ? DateFormat('yyyy-MM-dd').format(_dob!) : null,
        occupation: _occupationCtrl.text.trim(),
        segment: _segment,
        subscriberType: _subscriberType,
        address: _addressCtrl.text.trim(),
        areaId: _areaId,
        zoneId: _zoneId,
        subzoneId: _subzoneId,
        districtId: _districtId,
        upazilaId: _upazilaId,
        notes: _notesCtrl.text.trim(),
        billingDay: _billingDay,
        billingMode: _billingMode,
        firstBillCycle: _firstBillCycle,
        expireDay: _expireDay,
        status: _status,
        mikrotikSecretName: pppUser.isNotEmpty ? pppUser : null,
        mikrotikPppPassword: _pppPassCtrl.text.trim().isNotEmpty ? _pppPassCtrl.text.trim() : null,
        mikrotikServerId: _serverId,
        provisionMikrotik: _provisionMikrotik && pppUser.isNotEmpty,
        customerCode: _customerIdCtrl.text.trim().isNotEmpty ? _customerIdCtrl.text.trim() : null,
        applyLineCharges: _applyLineCharges,
        installationCharge: double.tryParse(_installChargeCtrl.text.trim()),
        lineDeviceCharge: double.tryParse(_deviceChargeCtrl.text.trim()),
        lineCashAmount: double.tryParse(_cashCtrl.text.trim()),
        lineCashMethod: _lineCashMethod,
        useWalletOnRegister: _useWallet,
        meta: meta,
      );
      final id = (res['customer'] as Map?)?['id'] as num?;
      if (!mounted) return;
      showSnack(context, res['message']?.toString() ?? 'Client added');
      if (id != null) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => StaffCustomerDetailScreen(api: widget.api, customerId: id.toInt())),
        );
      } else {
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
    return Scaffold(
      backgroundColor: RadiantTokens.brand,
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: Colors.white))
          : _error != null
              ? Center(child: Text(_error!, style: const TextStyle(color: Colors.white)))
              : Column(
                  children: [
                    SafeArea(
                      bottom: false,
                      child: Row(
                        children: [
                          IconButton(onPressed: () => Navigator.maybePop(context), icon: const Icon(Icons.arrow_back, color: Colors.white)),
                          const Expanded(
                            child: Text('Add Client', textAlign: TextAlign.center, style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w600)),
                          ),
                          const SizedBox(width: 48),
                        ],
                      ),
                    ),
                    Expanded(
                      child: Container(
                        width: double.infinity,
                        decoration: const BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
                        ),
                        child: Column(
                          children: [
                            _stepper(),
                            Expanded(
                              child: SingleChildScrollView(
                                padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                                child: switch (_step) {
                                  0 => _stepPersonal(),
                                  1 => _stepLocation(),
                                  _ => _stepNetwork(),
                                },
                              ),
                            ),
                            _bottomActions(),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
    );
  }

  Widget _stepper() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
      child: Row(
        children: [
          for (var i = 0; i < _steps.length; i++) ...[
            if (i > 0) Expanded(child: Container(height: 2, color: i <= _step ? RadiantTokens.brand : Colors.grey.shade300)),
            _stepBubble(i),
          ],
        ],
      ),
    );
  }

  Widget _stepBubble(int index) {
    final active = _step == index;
    final done = _step > index;
    return Column(
      children: [
        CircleAvatar(
          radius: 14,
          backgroundColor: done || active ? RadiantTokens.brand : Colors.grey.shade300,
          child: Text('${index + 1}', style: TextStyle(fontSize: 12, color: done || active ? Colors.white : Colors.grey.shade700)),
        ),
        const SizedBox(height: 4),
        SizedBox(
          width: 88,
          child: Text(
            _steps[index],
            textAlign: TextAlign.center,
            maxLines: 2,
            style: TextStyle(fontSize: 10, fontWeight: active ? FontWeight.w700 : FontWeight.normal, color: active ? RadiantTokens.brand : Colors.grey.shade600),
          ),
        ),
      ],
    );
  }

  Widget _stepPersonal() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Center(
          child: Stack(
            children: [
              CircleAvatar(
                radius: 48,
                backgroundColor: Colors.grey.shade300,
                child: Icon(Icons.person, size: 56, color: Colors.grey.shade500),
              ),
              Positioned(
                right: 0,
                bottom: 0,
                child: CircleAvatar(
                  radius: 16,
                  backgroundColor: Colors.white,
                  child: Icon(Icons.edit, size: 16, color: RadiantTokens.brand),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 20),
        _legacyField(_nameCtrl, 'Client name', required: true),
        _legacyField(_phoneCtrl, 'Mobile Number', required: true, keyboard: TextInputType.phone),
        _legacyDobField(),
        _legacyField(_emailCtrl, 'E-mail', keyboard: TextInputType.emailAddress),
        _legacyDropdown('Gender', _gender, _genders, (v) => setState(() => _gender = v), valueKey: 'value', labelKey: 'label'),
        _legacyField(_occupationCtrl, 'Occupation'),
        _legacyField(_altPhoneCtrl, 'Alternate phone', keyboard: TextInputType.phone),
        _legacyField(_nidCtrl, 'NID number'),
        if (_segments.isNotEmpty)
          _legacyDropdown('Client type', _segment, _segments, (v) => setState(() => _segment = v ?? _segment), valueKey: 'value'),
        if (_subscriberTypes.isNotEmpty)
          _legacyDropdown('Billing category', _subscriberType, _subscriberTypes, (v) => setState(() => _subscriberType = v ?? _subscriberType), valueKey: 'value'),
      ],
    );
  }

  Widget _stepLocation() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text('Location GPS', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
        const SizedBox(height: 8),
        ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: SizedBox(
            height: 180,
            child: FlutterMap(
              mapController: _mapController,
              options: MapOptions(
                initialCenter: _gps ?? const LatLng(23.8103, 90.4125),
                initialZoom: 14,
                onTap: (_, point) => setState(() => _gps = point),
              ),
              children: [
                TileLayer(urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
                if (_gps != null)
                  MarkerLayer(
                    markers: [
                      Marker(
                        point: _gps!,
                        width: 36,
                        height: 36,
                        child: const Icon(Icons.location_on, color: Colors.red, size: 36),
                      ),
                    ],
                  ),
              ],
            ),
          ),
        ),
        if (_gps != null)
          Padding(
            padding: const EdgeInsets.only(top: 6, bottom: 12),
            child: Text('${_gps!.latitude.toStringAsFixed(6)}, ${_gps!.longitude.toStringAsFixed(6)}', style: TextStyle(fontSize: 11, color: Colors.grey.shade600)),
          ),
        _legacyField(_addressCtrl, 'Address', required: true, maxLines: 2),
        if (_districts.isNotEmpty)
          _legacyDropdown('District', _districtId?.toString(), _districts, (v) => setState(() {
                _districtId = v != null ? int.tryParse(v) : null;
                _upazilaId = null;
              }), valueKey: 'id'),
        if (_upazilasForDistrict.isNotEmpty)
          _legacyDropdown('Thana / upazila', _upazilaId?.toString(), _upazilasForDistrict, (v) => setState(() => _upazilaId = v != null ? int.tryParse(v) : null), valueKey: 'id'),
        _legacyField(_houseCtrl, 'House no'),
        _legacyField(_roadCtrl, 'Road / street'),
        if (_areas.isNotEmpty)
          _legacyDropdown('Area', _areaId?.toString(), _areas, (v) => setState(() {
                _areaId = v != null ? int.tryParse(v) : null;
                _syncZonesForArea();
              }), valueKey: 'id'),
        if (_zonesForArea.isNotEmpty)
          _legacyDropdown('Zone', _zoneId?.toString(), _zonesForArea, (v) => setState(() {
                _zoneId = v != null ? int.tryParse(v) : null;
                _syncSubzonesForZone();
              }), valueKey: 'id'),
        if (_subzonesForZone.isNotEmpty)
          _legacyDropdown('Sub zone', _subzoneId?.toString(), _subzonesForZone, (v) => setState(() => _subzoneId = v != null ? int.tryParse(v) : null), valueKey: 'id'),
        if (_packages.isEmpty)
          const Text('No packages — add from website first.')
        else
          _legacyDropdown('Package', _packageId?.toString(), _packages, (v) => setState(() {
                _packageId = v != null ? int.tryParse(v) : null;
                _applyServerForPackage(_packageId);
              }), valueKey: 'id', labelKey: 'name'),
        _legacyDropdown('Expire day', '$_expireDay', List.generate(31, (i) => {'id': '${i + 1}', 'name': 'Day ${i + 1}'}), (v) => setState(() => _expireDay = int.tryParse(v ?? '') ?? _expireDay), valueKey: 'id'),
        _legacyDropdown('Bill day', '$_billingDay', List.generate(28, (i) => {'id': '${i + 1}', 'name': 'Day ${i + 1}'}), (v) => setState(() => _billingDay = int.tryParse(v ?? '') ?? _billingDay), valueKey: 'id'),
        _billingModeRow(),
        if (_billingMode == 'prepaid' || _billingMode == 'advance') _firstBillRow(),
        if (_autoCustomerId && _nextCustomerIdExample != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Text('Customer ID: auto (e.g. $_nextCustomerIdExample)', style: TextStyle(fontSize: 12, color: Colors.grey.shade700)),
          ),
        _legacyField(_customerIdCtrl, _autoCustomerId ? 'Custom Customer ID (optional)' : 'Customer ID', required: !_autoCustomerId),
        _legacyField(_discountCtrl, 'Monthly discount (BDT)', keyboard: const TextInputType.numberWithOptions(decimal: true)),
        _legacyField(_notesCtrl, 'Remarks / notes', maxLines: 3),
      ],
    );
  }

  Widget _stepNetwork() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Text('PPPoE login', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
        const SizedBox(height: 10),
        if (_servers.length > 1)
          _legacyDropdown('Router', _serverId?.toString(), _servers, (v) => setState(() => _serverId = v != null ? int.tryParse(v) : null), valueKey: 'id'),
        _legacyField(_pppUserCtrl, 'PPPoE username'),
        _legacyField(_pppPassCtrl, 'PPPoE password', obscure: true),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          value: _provisionMikrotik,
          onChanged: (v) => setState(() => _provisionMikrotik = v),
          title: const Text('Activate on MikroTik'),
        ),
        const Divider(),
        const Text('Connection & ONU', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
        const SizedBox(height: 8),
        if (_connectionTypes.isNotEmpty)
          _legacyDropdown('Connection type', _connectionType, _connectionTypes, (v) => setState(() => _connectionType = v ?? _connectionType), valueKey: 'value'),
        if (_onuOwnership.isNotEmpty)
          _legacyDropdown('ONU ownership', _onuOwnershipVal, _onuOwnership, (v) => setState(() => _onuOwnershipVal = v ?? _onuOwnershipVal), valueKey: 'value'),
        _legacyField(_boxCtrl, 'TJ box / port'),
        _legacyField(_eponCtrl, 'EPON port'),
        _legacyField(_onuMacCtrl, 'ONU MAC'),
        const Divider(),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          value: _applyLineCharges,
          onChanged: (v) => setState(() => _applyLineCharges = v),
          title: const Text('Apply line & device charges now'),
        ),
        if (_applyLineCharges) ...[
          _legacyField(_installChargeCtrl, 'Line / installation charge (BDT)', keyboard: const TextInputType.numberWithOptions(decimal: true)),
          _legacyField(_deviceChargeCtrl, 'Device charge (BDT)', keyboard: const TextInputType.numberWithOptions(decimal: true)),
          _legacyField(_cashCtrl, 'Cash collected (BDT)', keyboard: const TextInputType.numberWithOptions(decimal: true)),
          _legacyDropdown('Cash method', _lineCashMethod, const [
            {'value': 'cash', 'label': 'Cash'},
            {'value': 'bkash', 'label': 'bKash'},
            {'value': 'nagad', 'label': 'Nagad'},
            {'value': 'bank', 'label': 'Bank'},
          ], (v) => setState(() => _lineCashMethod = v ?? _lineCashMethod), valueKey: 'value', labelKey: 'label'),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            value: _useWallet,
            onChanged: (v) => setState(() => _useWallet = v),
            title: const Text('Use wallet balance'),
          ),
        ],
      ],
    );
  }

  Widget _legacyField(
    TextEditingController ctrl,
    String label, {
    bool required = false,
    bool obscure = false,
    int maxLines = 1,
    TextInputType? keyboard,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          RichText(
            text: TextSpan(
              style: TextStyle(fontSize: 13, color: Colors.grey.shade800),
              children: [
                TextSpan(text: label),
                if (required) const TextSpan(text: ' *', style: TextStyle(color: Colors.red)),
              ],
            ),
          ),
          const SizedBox(height: 6),
          TextField(
            controller: ctrl,
            obscureText: obscure,
            maxLines: maxLines,
            keyboardType: keyboard,
            decoration: InputDecoration(
              filled: true,
              fillColor: Colors.white,
              contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide(color: Colors.grey.shade300)),
              enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide(color: Colors.grey.shade300)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _legacyDobField() {
    final text = _dob != null ? DateFormat('dd/MM/yyyy').format(_dob!) : '';
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Date of Birth', style: TextStyle(fontSize: 13, color: Colors.grey.shade800)),
          const SizedBox(height: 6),
          InkWell(
            onTap: _pickDob,
            borderRadius: BorderRadius.circular(10),
            child: InputDecorator(
              decoration: InputDecoration(
                filled: true,
                fillColor: Colors.white,
                hintText: 'dd/mm/yyyy',
                contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide(color: Colors.grey.shade300)),
                enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide(color: Colors.grey.shade300)),
                suffixIcon: const Icon(Icons.calendar_today, size: 18, color: Colors.grey),
              ),
              child: Text(text, style: TextStyle(color: text.isEmpty ? Colors.grey : Colors.black87)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _legacyDropdown(
    String label,
    String? value,
    List<Map<String, dynamic>> options,
    ValueChanged<String?> onChanged, {
    String valueKey = 'id',
    String labelKey = 'label',
    bool required = false,
  }) {
    final items = options
        .map((o) => DropdownMenuItem<String>(
              value: o[valueKey]?.toString(),
              child: Text(o[labelKey]?.toString() ?? o['name']?.toString() ?? '—'),
            ))
        .toList();
    final selected = items.any((i) => i.value == value) ? value : null;

    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          RichText(
            text: TextSpan(
              style: TextStyle(fontSize: 13, color: Colors.grey.shade800),
              children: [
                TextSpan(text: label),
                if (required) const TextSpan(text: ' *', style: TextStyle(color: Colors.red)),
              ],
            ),
          ),
          const SizedBox(height: 6),
          DropdownButtonFormField<String>(
            initialValue: selected,
            decoration: InputDecoration(
              filled: true,
              fillColor: Colors.white,
              contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide(color: Colors.grey.shade300)),
              enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide(color: Colors.grey.shade300)),
            ),
            hint: const Text('Select'),
            items: items,
            onChanged: onChanged,
          ),
        ],
      ),
    );
  }

  Widget _billingModeRow() {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Wrap(
        spacing: 8,
        children: [
          for (final mode in ['postpaid', 'prepaid', 'advance'])
            ChoiceChip(
              label: Text(mode[0].toUpperCase() + mode.substring(1)),
              selected: _billingMode == mode,
              onSelected: (_) => setState(() => _billingMode = mode),
            ),
        ],
      ),
    );
  }

  Widget _firstBillRow() {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Wrap(
        spacing: 8,
        children: [
          ChoiceChip(label: const Text('Bill today'), selected: _firstBillCycle == 'this_month', onSelected: (_) => setState(() => _firstBillCycle = 'this_month')),
          ChoiceChip(label: const Text('Next month'), selected: _firstBillCycle == 'next_month', onSelected: (_) => setState(() => _firstBillCycle = 'next_month')),
        ],
      ),
    );
  }

  Widget _bottomActions() {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
        child: Row(
          children: [
            if (_step > 0)
              TextButton(onPressed: _saving ? null : _back, child: const Text('BACK')),
            const Spacer(),
            if (_saving)
              const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2))
            else
              TextButton(
                onPressed: _step < _steps.length - 1 ? _next : _save,
                child: Text(
                  _step < _steps.length - 1 ? 'NEXT >' : 'SUBMIT',
                  style: const TextStyle(color: RadiantTokens.brand, fontWeight: FontWeight.w700, fontSize: 15),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
