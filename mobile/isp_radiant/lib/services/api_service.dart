import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

import '../config/app_config.dart';
import '../config/remote_config.dart';

class ApiService {
  ApiService({FlutterSecureStorage? storage, http.Client? client})
      : _storage = storage ?? const FlutterSecureStorage(),
        _client = client ?? http.Client();

  final FlutterSecureStorage _storage;
  final http.Client _client;
  static const _tokenKey = 'auth_token';
  static const _roleKey = 'user_role';
  static const _staffModeKey = 'staff_mode';
  static const _timeout = Duration(seconds: 30);

  Future<String?> get token => _storage.read(key: _tokenKey);
  Future<String?> get role => _storage.read(key: _roleKey);
  Future<String?> get staffMode => _storage.read(key: _staffModeKey);

  Future<void> saveStaffMode(String mode) => _storage.write(key: _staffModeKey, value: mode);

  Future<void> saveSession(String token, String role) async {
    await _storage.write(key: _tokenKey, value: token);
    await _storage.write(key: _roleKey, value: role);
  }

  Future<void> clearSession() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _roleKey);
    await _storage.delete(key: _staffModeKey);
  }

  Future<void> loadRemoteConfig() async {
    try {
      final res = await _client
          .get(Uri.parse('${AppConfig.apiBaseUrl}/mobile/config'), headers: {'Accept': 'application/json'})
          .timeout(_timeout);
      if (res.statusCode == 200) {
        await RemoteConfig.loadFrom(_decode(res));
      }
    } catch (_) {}
  }

  Future<bool> validateSession() async {
    final t = await token;
    final r = await role;
    if (t == null || r == null) return false;
    try {
      if (r == 'customer') {
        await _get('/customer/me');
      } else {
        await _get('/me');
      }
      return true;
    } on ApiException catch (e) {
      if (e.statusCode == 401) await clearSession();
      return false;
    } catch (_) {
      return false;
    }
  }

  Future<Map<String, dynamic>> login({
    required String role,
    required String login,
    required String password,
  }) async {
    await loadRemoteConfig();
    final res = await _client
        .post(
          Uri.parse('${AppConfig.apiBaseUrl}/mobile/login'),
          headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
          body: jsonEncode({
            'role': role,
            'login': login,
            'password': password,
            'device_name': 'isp-radiant-android',
          }),
        )
        .timeout(_timeout);

    final body = _decode(res);
    if (res.statusCode >= 400) {
      throw ApiException(_messageFrom(body), statusCode: res.statusCode);
    }

    final token = body['token']?.toString();
    if (token == null || token.isEmpty) throw ApiException('No token received');

    await saveSession(token, role);
    return body;
  }

  Future<Map<String, dynamic>> staffDashboard() => _get('/staff/dashboard');
  Future<Map<String, dynamic>> customerDashboard() => _get('/customer/dashboard');
  Future<Map<String, dynamic>> customerUsageLive() => _get('/customer/usage/live');

  Future<List<Map<String, dynamic>>> customerBills() async {
    final body = await _get('/customer/bills');
    return _listFrom(body['data']);
  }

  Future<Map<String, dynamic>> customerBillDetail(int id) => _get('/customer/bills/$id');

  Future<Map<String, dynamic>> initiateBillPayment(int invoiceId) =>
      _post('/customer/bills/$invoiceId/pay', {});

  Future<List<Map<String, dynamic>>> customerTickets() async {
    final body = await _get('/customer/tickets');
    return _listFrom(body['data']);
  }

  Future<Map<String, dynamic>> customerTicketDetail(int id) => _get('/customer/tickets/$id');

  Future<Map<String, dynamic>> createTicket({
    required String subject,
    required String description,
  }) async {
    return _post('/customer/tickets', {
      'subject': subject,
      'description': description,
      'department': RemoteConfig.ticketDepartmentDefault,
      'priority': RemoteConfig.ticketPriorityDefault,
    });
  }

  Future<Map<String, dynamic>> replyTicket(int id, String body) =>
      _post('/customer/tickets/$id/reply', {'body': body});

  Future<Map<String, dynamic>> staffTicketDetail(int id) => _get('/staff/tickets/$id');

  Future<Map<String, dynamic>> staffReplyTicket(int id, String body, {bool internal = false}) =>
      _post('/staff/tickets/$id/reply', {'body': body, 'is_internal': internal});

  Future<Map<String, dynamic>> staffUpdateTicket(int id, {String? status, String? priority}) =>
      _patch('/staff/tickets/$id', {
        if (status != null) 'status': status,
        if (priority != null) 'priority': priority,
      });

  Future<Map<String, dynamic>> staffUpdateTask(int id, String status) =>
      _patch('/staff/tasks/$id', {'status': status});

  Future<List<Map<String, dynamic>>> staffPendingApprovals() async {
    final body = await _get('/staff/approvals/pending');
    return _listFrom(body['data']);
  }

  Future<void> approveExpense(int id) => _post('/staff/approvals/expenses/$id/approve', {});

  Future<void> rejectExpense(int id, {String? reason}) =>
      _post('/staff/approvals/expenses/$id/reject', {if (reason != null) 'reason': reason});

  Future<Map<String, dynamic>> staffCustomerFormOptions() => _get('/staff/customers/form-options');

  Future<List<Map<String, dynamic>>> staffCustomerPackages() async {
    final body = await _get('/staff/customers/form-options');
    return _listFrom(body['packages']);
  }

  Future<Map<String, dynamic>> createStaffCustomerFull({
    required String name,
    required String phone,
    required int packageId,
    String? email,
    String? address,
    String? customerCode,
    String? status,
    String? mikrotikSecretName,
    String? mikrotikPppPassword,
    String? radiusUsername,
    String? portalPassword,
    String? notes,
    int? billingDay,
    String? billingMode,
    int? areaId,
    int? zoneId,
    int? mikrotikServerId,
    String? joinedAt,
    String? serviceExpiresAt,
    bool provisionMikrotik = true,
    String? firstBillCycle,
    int? expireDay,
  }) =>
      _post('/staff/customers/create', {
        'name': name,
        'phone': phone,
        'package_id': packageId,
        if (email != null && email.isNotEmpty) 'email': email,
        if (address != null && address.isNotEmpty) 'address': address,
        if (customerCode != null && customerCode.isNotEmpty) 'customer_code': customerCode,
        if (status != null) 'status': status,
        if (mikrotikSecretName != null && mikrotikSecretName.isNotEmpty) 'mikrotik_secret_name': mikrotikSecretName,
        if (mikrotikPppPassword != null && mikrotikPppPassword.isNotEmpty) 'mikrotik_ppp_password': mikrotikPppPassword,
        if (radiusUsername != null && radiusUsername.isNotEmpty) 'radius_username': radiusUsername,
        if (portalPassword != null && portalPassword.isNotEmpty) 'portal_password': portalPassword,
        if (notes != null && notes.isNotEmpty) 'notes': notes,
        if (billingDay != null) 'billing_day': billingDay,
        if (billingMode != null) 'billing_mode': billingMode,
        if (areaId != null) 'area_id': areaId,
        if (zoneId != null) 'zone_id': zoneId,
        if (mikrotikServerId != null) 'mikrotik_server_id': mikrotikServerId,
        if (joinedAt != null) 'joined_at': joinedAt,
        if (serviceExpiresAt != null) 'service_expires_at': serviceExpiresAt,
        'provision_mikrotik': provisionMikrotik,
        'network_access_state': 'active',
        if (firstBillCycle != null) 'first_bill_cycle': firstBillCycle,
        if (expireDay != null) 'expire_day': expireDay,
      });

  Future<Map<String, dynamic>> createStaffCustomer({
    required String name,
    required String phone,
    required int packageId,
    String? email,
    String? address,
    String? notes,
    String? portalPassword,
  }) =>
      _post('/staff/customers/create', {
        'name': name,
        'phone': phone,
        'package_id': packageId,
        if (email != null) 'email': email,
        if (address != null) 'address': address,
        if (notes != null) 'notes': notes,
        if (portalPassword != null) 'portal_password': portalPassword,
      });

  Future<List<Map<String, dynamic>>> collectorExpenseCategories() async {
    final body = await _get('/staff/expense-categories');
    return _listFrom(body['data']);
  }

  Future<List<Map<String, dynamic>>> staffExpenses() async {
    final body = await _get('/staff/expenses');
    return _listFrom(body['data']);
  }

  Future<Map<String, dynamic>> submitCollectorExpense({
    required double amount,
    required int categoryId,
    String? description,
    String? expenseDate,
  }) =>
      _post('/staff/expenses', {
        'amount': amount,
        'category_id': categoryId,
        if (description != null) 'description': description,
        if (expenseDate != null) 'expense_date': expenseDate,
      });

  Future<void> registerPushDevice(String token, {required String role, String? staffMode}) async {
    if (role == 'customer') {
      await _post('/customer/devices', {'token': token, 'platform': 'android'});
      return;
    }
    await _post('/staff/devices', {
      'token': token,
      'platform': 'android',
      'app': staffMode ?? 'staff',
    });
  }

  Future<void> updatePassword({required String current, required String password}) async {
    await _post('/customer/profile/password', {
      'current_password': current,
      'password': password,
      'password_confirmation': password,
    });
  }

  Future<List<Map<String, dynamic>>> customerPackages() async {
    final body = await _get('/customer/packages');
    return _listFrom(body['data']);
  }

  Future<Map<String, dynamic>> requestPackageChange(int packageId, {String? note}) async {
    return _post('/customer/packages/change', {
      'package_id': packageId,
      if (note != null) 'note': note,
    });
  }

  Future<List<Map<String, dynamic>>> searchCustomers(String q) async {
    if (q.trim().length < 2) return [];
    final encoded = Uri.encodeQueryComponent(q.trim());
    final body = await _get('/staff/customers/search?q=$encoded');
    return _listFrom(body['data']);
  }

  Future<Map<String, dynamic>> staffCustomers({
    String q = '',
    int page = 1,
    int perPage = 50,
    String? status,
    int? packageId,
    bool dueOnly = false,
    int? expiringDays,
  }) async {
    final params = <String, String>{
      'page': '$page',
      'per_page': '$perPage',
      if (q.isNotEmpty) 'q': q,
      if (status != null && status.isNotEmpty) 'status': status,
      if (packageId != null) 'package_id': '$packageId',
      if (dueOnly) 'due_only': '1',
      if (expiringDays != null) 'expiring_days': '$expiringDays',
    };
    final query = params.entries.map((e) => '${e.key}=${Uri.encodeQueryComponent(e.value)}').join('&');
    return _get('/staff/customers?$query');
  }

  Future<Map<String, dynamic>> staffCustomerDetail(int id) async {
    final body = await _get('/staff/customers/$id');
    return body['customer'] as Map<String, dynamic>? ?? body;
  }

  Future<Map<String, dynamic>> staffCustomerUsageLive(int customerId) async {
    final body = await _get('/staff/customers/$customerId/usage/live');
    return body['usage'] as Map<String, dynamic>? ?? body;
  }

  Future<Map<String, dynamic>> collectorWallet() async {
    final body = await _get('/collector/wallet');
    return body['data'] as Map<String, dynamic>? ?? body;
  }

  Future<List<Map<String, dynamic>>> staffPaymentMethods() async {
    final body = await _get('/staff/payment-methods');
    return _listFrom(body['data']);
  }

  Future<Map<String, dynamic>> staffRecordPayment({
    required int customerId,
    required double amount,
    required String method,
    int? invoiceId,
    String? reference,
    String? notes,
  }) =>
      _post('/staff/payments', {
        'customer_id': customerId,
        'amount': amount,
        'method': method,
        if (invoiceId != null) 'invoice_id': invoiceId,
        if (reference != null) 'reference': reference,
        if (notes != null) 'notes': notes,
      });

  Future<List<Map<String, dynamic>>> staffPackagesList() async {
    final body = await _get('/staff/packages');
    return _listFrom(body['data']);
  }

  Future<Map<String, dynamic>> staffCreatePackage({
    required String name,
    required double downloadMbps,
    required double priceMonthly,
    double? uploadMbps,
  }) =>
      _post('/staff/packages', {
        'name': name,
        'download_mbps': downloadMbps,
        'price_monthly': priceMonthly,
        if (uploadMbps != null) 'upload_mbps': uploadMbps,
      });

  Future<List<Map<String, dynamic>>> staffExpiringReport({int days = 7}) async {
    final body = await _get('/staff/reports/expiring?days=$days');
    return _listFrom(body['data']);
  }

  Future<Map<String, dynamic>> staffCollectionsReport() => _get('/staff/reports/collections');

  Future<void> staffSmsReminder(int customerId) => _post('/staff/customers/$customerId/sms-reminder', {});

  Future<Map<String, dynamic>> staffSmsBulkDue({String? message}) =>
      _post('/staff/sms/bulk-due', {if (message != null) 'message': message});

  Future<Map<String, dynamic>> staffBroadcastNotice(String message, {String target = 'active'}) =>
      _post('/staff/notices/broadcast', {'message': message, 'target': target});

  Future<void> staffUpdatePassword({required String current, required String password}) async {
    await _post('/staff/profile/password', {
      'current_password': current,
      'password': password,
      'password_confirmation': password,
    });
  }

  Future<Map<String, dynamic>> staffCustomerOnu(int customerId) => _get('/staff/customers/$customerId/onu');

  Future<Map<String, dynamic>> staffUpdateCustomerOnu(int customerId, {String? onuMac, String? macBinding}) =>
      _patch('/staff/customers/$customerId/onu', {
        if (onuMac != null) 'onu_mac': onuMac,
        if (macBinding != null) 'mac_binding': macBinding,
      });

  Future<Map<String, dynamic>> staffCollectionOptions() async {
    final body = await _get('/staff/billing/collection-options');
    return Map<String, dynamic>.from(body['data'] as Map? ?? {});
  }

  Future<Map<String, dynamic>> staffTeamDiscounts() async {
    final body = await _get('/staff/team/discounts');
    return Map<String, dynamic>.from(body);
  }

  Future<void> updateStaffTeamDiscount(
    int userId, {
    required bool enabled,
    double? maxBdt,
    double? maxPercent,
  }) async {
    await _patch('/staff/team/$userId/discount', {
      'enabled': enabled,
      if (maxBdt != null) 'max_discount_bdt': maxBdt,
      if (maxPercent != null) 'max_discount_percent_of_due': maxPercent,
    });
  }

  Future<Map<String, dynamic>> recordCollection({
    required int customerId,
    required double amount,
    int? invoiceId,
    String method = 'cash',
    String? reference,
    String? notes,
    String discountPreset = 'none',
    double? discountCustom,
  }) async {
    return _post('/collector/collections', {
      'customer_id': customerId,
      'amount': amount,
      if (invoiceId != null) 'invoice_id': invoiceId,
      'method': method,
      if (reference != null) 'reference': reference,
      if (notes != null) 'notes': notes,
      'discount_preset': discountPreset,
      if (discountCustom != null && discountCustom > 0) 'discount_custom': discountCustom,
    });
  }

  Future<List<Map<String, dynamic>>> staffTickets({String status = 'all'}) async {
    final encoded = Uri.encodeQueryComponent(status);
    final body = await _get('/staff/tickets?status=$encoded');
    return _listFrom(body['data']);
  }

  Future<List<Map<String, dynamic>>> staffTasks() async {
    final body = await _get('/staff/tasks');
    return _listFrom(body['data']);
  }

  Future<Map<String, dynamic>> staffOnlineClients() => _get('/staff/monitoring/online');

  Future<Map<String, dynamic>> staffMonitoringLive() => _get('/staff/monitoring/live');

  Future<Map<String, dynamic>> staffBillingSummary() => _get('/staff/billing/summary');

  Future<Map<String, dynamic>> staffBillingDue({int page = 1}) async {
    final body = await _get('/staff/billing/due?page=$page');
    return body;
  }

  Future<Map<String, dynamic>> staffBillingInvoices({String status = 'all', int page = 1}) async {
    final encoded = Uri.encodeQueryComponent(status);
    return _get('/staff/billing/invoices?status=$encoded&page=$page');
  }

  Future<Map<String, dynamic>> staffBillingCollections({int page = 1}) async {
    return _get('/staff/billing/collections?page=$page');
  }

  Future<Map<String, dynamic>> staffUpdateCustomer(int id, Map<String, dynamic> fields) =>
      _patch('/staff/customers/$id', fields);

  Future<Map<String, dynamic>> staffCreateTicket({
    required int customerId,
    required String subject,
    required String description,
    String department = 'technical_support',
    String priority = 'medium',
  }) =>
      _post('/staff/tickets', {
        'customer_id': customerId,
        'subject': subject,
        'description': description,
        'department': department,
        'priority': priority,
      });

  Future<List<Map<String, dynamic>>> collectorExpenses() async {
    final body = await _get('/collector/expenses');
    return _listFrom(body['data']);
  }

  Future<Map<String, dynamic>> nocDashboard() => _get('/staff/noc/dashboard');

  Future<Map<String, dynamic>> realtimeConfig() => _get('/mobile/realtime');

  Future<Map<String, dynamic>> mobileSync({
    required String deviceUuid,
    required List<Map<String, dynamic>> items,
  }) =>
      _post('/mobile/sync', {'device_uuid': deviceUuid, 'items': items});

  Future<Map<String, dynamic>> suspendCustomer(int customerId, {String? reason}) =>
      _post('/staff/network/suspend', {
        'customer_id': customerId,
        if (reason != null) 'reason': reason,
      });

  Future<Map<String, dynamic>> reconnectCustomer(int customerId) =>
      _post('/staff/network/reconnect', {'customer_id': customerId});

  Future<Map<String, dynamic>> customerOnuStatus() => _get('/customer/onu/status');

  Future<Map<String, dynamic>> customerOnuReboot() => _post('/customer/onu/reboot', {});

  Future<Map<String, dynamic>> customerAiAsk(String question) =>
      _post('/customer/ai/ask', {'question': question});

  Future<bool> refreshToken() async {
    final r = await role;
    final path = r == 'customer' ? '/customer/auth/refresh' : '/auth/refresh';
    try {
      final body = await _post(path, {});
      final token = body['token']?.toString();
      if (token != null && token.isNotEmpty && r != null) {
        await saveSession(token, r);
        return true;
      }
    } catch (_) {}
    return false;
  }

  Future<void> logout() async {
    final t = await token;
    final r = await role;
    if (t != null) {
      final path = r == 'customer' ? '/customer/logout' : '/auth/logout';
      try {
        await _client.post(Uri.parse('${AppConfig.apiBaseUrl}$path'), headers: await _headers()).timeout(_timeout);
      } catch (_) {}
    }
    await clearSession();
  }

  Future<Map<String, dynamic>> _get(String path, {bool retried = false}) async {
    final res = await _client.get(Uri.parse('${AppConfig.apiBaseUrl}$path'), headers: await _headers()).timeout(_timeout);
    if (res.statusCode == 401 && !retried && await refreshToken()) {
      return _get(path, retried: true);
    }
    return _handle(res);
  }

  Future<Map<String, dynamic>> _patch(String path, Map<String, dynamic> payload, {bool retried = false}) async {
    final res = await _client
        .patch(
          Uri.parse('${AppConfig.apiBaseUrl}$path'),
          headers: await _headers(),
          body: jsonEncode(payload),
        )
        .timeout(_timeout);
    if (res.statusCode == 401 && !retried && await refreshToken()) {
      return _patch(path, payload, retried: true);
    }
    return _handle(res);
  }

  Future<Map<String, dynamic>> _post(String path, Map<String, dynamic> payload, {bool retried = false}) async {
    final res = await _client
        .post(
          Uri.parse('${AppConfig.apiBaseUrl}$path'),
          headers: await _headers(),
          body: jsonEncode(payload),
        )
        .timeout(_timeout);
    if (res.statusCode == 401 && !retried && await refreshToken()) {
      return _post(path, payload, retried: true);
    }
    return _handle(res);
  }

  Map<String, dynamic> _handle(http.Response res) {
    final body = _decode(res);
    if (res.statusCode == 401) {
      clearSession();
      throw ApiException('Session expired. Please sign in again.', statusCode: 401);
    }
    if (res.statusCode >= 400) {
      throw ApiException(_messageFrom(body), statusCode: res.statusCode);
    }
    return body;
  }

  String _messageFrom(Map<String, dynamic> body) {
    final msg = body['message']?.toString();
    if (msg != null && msg.isNotEmpty) return msg;
    final errors = body['errors'];
    if (errors is Map) {
      final first = errors.values.first;
      if (first is List && first.isNotEmpty) return first.first.toString();
    }
    return 'Request failed';
  }

  List<Map<String, dynamic>> _listFrom(dynamic raw) {
    if (raw is! List) return [];
    return raw.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  Future<Map<String, String>> _headers() async {
    final t = await token;
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (t != null) 'Authorization': 'Bearer $t',
    };
  }

  Map<String, dynamic> _decode(http.Response res) {
    if (res.body.isEmpty) return {};
    try {
      final decoded = jsonDecode(res.body);
      if (decoded is Map<String, dynamic>) return decoded;
      return {'data': decoded};
    } catch (_) {
      throw ApiException('Invalid server response (${res.statusCode})', statusCode: res.statusCode);
    }
  }
}

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode});
  final String message;
  final int? statusCode;
  @override
  String toString() => message;
}
