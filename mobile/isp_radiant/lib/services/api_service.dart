import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../config/remote_config.dart';
import '../config/server_config.dart';
import 'session_storage.dart';

class ApiService {
  ApiService({SessionStorage? storage, http.Client? client})
      : _storage = storage ?? SessionStorage(),
        _client = client ?? http.Client();

  final SessionStorage _storage;
  final http.Client _client;
  static const _tokenKey = 'auth_token';
  static const _roleKey = 'user_role';
  static const _staffModeKey = 'staff_mode';
  static const _expiresKey = 'token_expires_at';
  static const _timeout = Duration(seconds: 30);
  static const _bootTimeout = Duration(seconds: 8);

  Future<String?> get token => _storage.read(_tokenKey);
  Future<String?> get role => _storage.read(_roleKey);
  Future<String?> get staffMode => _storage.read(_staffModeKey);

  Future<void> saveStaffMode(String mode) => _storage.write(_staffModeKey, mode);

  Future<void> saveSession(String token, String role, {String? expiresAt}) async {
    await _storage.write(_tokenKey, token);
    await _storage.write(_roleKey, role);
    if (expiresAt != null && expiresAt.isNotEmpty) {
      await _storage.write(_expiresKey, expiresAt);
    }
  }

  Future<void> clearSession() async {
    await _storage.delete(_tokenKey);
    await _storage.delete(_roleKey);
    await _storage.delete(_staffModeKey);
    await _storage.delete(_expiresKey);
  }

  Future<bool> hasStoredSession() async {
    final t = await token;
    return t != null && t.isNotEmpty;
  }

  Future<void> loadRemoteConfig({Duration? timeout}) async {
    final limit = timeout ?? _bootTimeout;
    try {
      final res = await _client
          .get(Uri.parse('${ServerConfig.apiBaseUrl}/mobile/config'), headers: {'Accept': 'application/json'})
          .timeout(limit);
      if (res.statusCode == 200) {
        await RemoteConfig.loadFrom(_decode(res));
      }
    } catch (_) {}
  }

  /// [quick] skips token refresh retry — used at cold start so splash never hangs.
  Future<bool> validateSession({bool quick = false}) async {
    final t = await token;
    final r = await role;
    if (t == null || t.isEmpty || r == null || r.isEmpty) return false;

    if (!quick) {
      await maybeRefreshIfExpiring();
    }

    final limit = quick ? _bootTimeout : _timeout;
    try {
      if (r == 'customer') {
        await _get('/customer/me', skipRefresh: quick).timeout(limit);
      } else if (r == 'reseller') {
        await _get('/reseller/me', skipRefresh: quick).timeout(limit);
      } else {
        await _get('/me', skipRefresh: quick).timeout(limit);
      }
      return true;
    } on ApiException catch (e) {
      if (e.statusCode == 401) {
        if (!quick && await refreshToken()) {
          return validateSession(quick: true);
        }
        await clearSession();
        return false;
      }
      return false;
    } catch (_) {
      return false;
    }
  }

  /// Refresh bearer token when within 14 days of expiry (keeps app logged in long-term).
  Future<void> maybeRefreshIfExpiring() async {
    final expRaw = await _storage.read(_expiresKey);
    if (expRaw == null || expRaw.isEmpty) return;
    try {
      final exp = DateTime.parse(expRaw).toLocal();
      if (exp.isAfter(DateTime.now().add(const Duration(days: 14)))) return;
      await refreshToken();
    } catch (_) {}
  }

  Future<Map<String, dynamic>> login({
    required String role,
    required String login,
    required String password,
    String? twoFactorCode,
  }) async {
    return _loginRequest(
      role: role,
      login: login,
      password: password,
      twoFactorCode: twoFactorCode,
    );
  }

  /// Single sign-in — tries staff / customer / reseller until credentials match.
  Future<Map<String, dynamic>> loginUnified({
    required String login,
    required String password,
    String? twoFactorCode,
  }) async {
    try {
      return await _loginRequest(
        role: 'auto',
        login: login,
        password: password,
        twoFactorCode: twoFactorCode,
      );
    } on ApiException catch (e) {
      if (!_isInvalidRoleError(e)) rethrow;
    }

    final roles = <String>[
      if (login.contains('@')) 'staff',
      'customer',
      'reseller',
    ];

    ApiException? lastAuthError;
    for (final role in roles) {
      try {
        return await _loginRequest(
          role: role,
          login: login,
          password: password,
          twoFactorCode: twoFactorCode,
        );
      } on ApiException catch (e) {
        if (e.statusCode == 401) {
          lastAuthError = e;
          continue;
        }
        rethrow;
      }
    }

    throw lastAuthError ?? ApiException('Invalid credentials.');
  }

  bool _isInvalidRoleError(ApiException e) {
    if (e.statusCode != 422) return false;
    final errors = e.data?['errors'];
    if (errors is Map && errors['role'] != null) return true;
    return e.message.toLowerCase().contains('selected role is invalid');
  }

  Future<Map<String, dynamic>> _loginRequest({
    required String role,
    required String login,
    required String password,
    String? twoFactorCode,
  }) async {
    await loadRemoteConfig();
    final res = await _client
        .post(
          Uri.parse(RemoteConfig.mobileLoginApiUrl),
          headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
          body: jsonEncode({
            'role': role,
            'login': login,
            'password': password,
            if (twoFactorCode != null && twoFactorCode.isNotEmpty) 'two_factor_code': twoFactorCode,
            'device_name': 'isp-radiant-android',
          }),
        )
        .timeout(_timeout);

    final body = _decode(res);
    if (res.statusCode >= 400) {
      throw ApiException(_messageFrom(body), statusCode: res.statusCode, data: body);
    }

    final token = body['token']?.toString();
    if (token == null || token.isEmpty) throw ApiException('No token received');

    final resolvedRole = body['role']?.toString() ?? (role == 'auto' ? 'customer' : role);
    await saveSession(token, resolvedRole, expiresAt: body['expires_at']?.toString());
    return body;
  }

  Future<Map<String, dynamic>> staffDashboard() => _get('/staff/dashboard');

  Future<Map<String, dynamic>> staffInventoryBootstrap() => _get('/staff/inventory/bootstrap');

  Future<List<Map<String, dynamic>>> staffInventoryProducts({
    String? barcode,
    String? query,
    int? warehouseId,
  }) async {
    final parts = <String>[];
    if (barcode != null && barcode.isNotEmpty) {
      parts.add('barcode=${Uri.encodeQueryComponent(barcode)}');
    }
    if (query != null && query.isNotEmpty) {
      parts.add('q=${Uri.encodeQueryComponent(query)}');
    }
    if (warehouseId != null) parts.add('warehouse_id=$warehouseId');
    final qs = parts.isEmpty ? '' : '?${parts.join('&')}';
    final body = await _get('/staff/inventory/products$qs');
    return _listFrom(body['data']);
  }

  Future<Map<String, dynamic>> staffInvoiceHardwareOptions(int invoiceId) =>
      _get('/staff/invoices/$invoiceId/hardware-options');

  Future<Map<String, dynamic>> staffInvoiceHardwareLookup(
    int invoiceId, {
    required String barcode,
    int? warehouseId,
  }) async {
    final parts = ['barcode=${Uri.encodeQueryComponent(barcode)}'];
    if (warehouseId != null) parts.add('warehouse_id=$warehouseId');
    return _get('/staff/invoices/$invoiceId/hardware-product?${parts.join('&')}');
  }

  Future<Map<String, dynamic>> staffInvoiceAddHardwareLine(
    int invoiceId, {
    required int productId,
    int quantity = 1,
    double? unitPrice,
    int? warehouseId,
    bool issueStock = false,
  }) =>
      _post('/staff/invoices/$invoiceId/hardware-line', {
        'product_id': productId,
        'quantity': quantity,
        if (unitPrice != null) 'unit_price': unitPrice,
        if (warehouseId != null) 'warehouse_id': warehouseId,
        'issue_stock': issueStock,
      });

  Future<Map<String, dynamic>> staffInventorySale({
    required int warehouseId,
    required String paymentMethod,
    required List<Map<String, dynamic>> lines,
    double discount = 0,
    String? customerName,
    String? customerPhone,
    String? notes,
    String? barcodeScan,
  }) =>
      _post('/staff/inventory/sales', {
        'warehouse_id': warehouseId,
        'payment_method': paymentMethod,
        'discount': discount,
        'lines': lines,
        if (customerName != null) 'customer_name': customerName,
        if (customerPhone != null) 'customer_phone': customerPhone,
        if (notes != null) 'notes': notes,
        if (barcodeScan != null && barcodeScan.isNotEmpty) 'barcode_scan': barcodeScan,
      });
  Future<Map<String, dynamic>> customerDashboard() => _get('/customer/dashboard');
  Future<Map<String, dynamic>> customerUsageLive() => _get('/customer/usage/live');

  Future<List<Map<String, dynamic>>> customerBills() async {
    final body = await _get('/customer/bills');
    return _listFrom(body['data']);
  }

  /// All due invoices + total due + gateways (full payment only).
  Future<Map<String, dynamic>> customerPayables() => _get('/customer/bills/payables');

  Future<List<Map<String, dynamic>>> customerPayments({int page = 1}) async {
    final body = await _get('/customer/payments?page=$page');
    return _listFrom(body['data']);
  }

  Future<Map<String, dynamic>> customerBillDetail(int id) => _get('/customer/bills/$id');

  Future<Map<String, dynamic>> initiateBillPayment(int invoiceId, {required String gateway}) =>
      _post('/customer/bills/$invoiceId/pay', {'gateway': gateway});

  Future<Map<String, dynamic>> initiatePrepayPayment({required int months, required String gateway}) =>
      _post('/customer/bills/prepay', {'months': months, 'gateway': gateway});

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

  Future<Map<String, dynamic>> staffUpdateTicket(
    int id, {
    String? status,
    String? priority,
    int? assignedTo,
    bool clearAssignee = false,
  }) =>
      _patch('/staff/tickets/$id', {
        if (status != null) 'status': status,
        if (priority != null) 'priority': priority,
        if (clearAssignee) 'assigned_to': null,
        if (assignedTo != null) 'assigned_to': assignedTo,
      });

  Future<List<Map<String, dynamic>>> staffTicketAssignees() async {
    final body = await _get('/staff/tickets/assignees');
    return _listFrom(body['data']);
  }

  Future<List<Map<String, dynamic>>> staffTickets({
    String status = 'all',
    bool mine = false,
    bool unassigned = false,
  }) async {
    final q = <String>['status=${Uri.encodeComponent(status)}'];
    if (mine) q.add('mine=1');
    if (unassigned) q.add('unassigned=1');
    final body = await _get('/staff/tickets?${q.join('&')}');
    return _listFrom(body['data']);
  }

  Future<Map<String, dynamic>> staffUpdateTask(int id, String status) =>
      _patch('/staff/tasks/$id', {'status': status});

  Future<List<Map<String, dynamic>>> staffPendingApprovals() async {
    final body = await _get('/staff/approvals/pending');
    return _listFrom(body['data']);
  }

  Future<void> approveExpense(int id) => _post('/staff/approvals/expenses/$id/approve', {});

  Future<void> rejectExpense(int id, {String? reason}) =>
      _post('/staff/approvals/expenses/$id/reject', {if (reason != null) 'reason': reason});

  Future<void> approveStaffExpense(int id) => _post('/staff/approvals/staff-expenses/$id/approve', {});

  Future<void> rejectStaffExpense(int id, {String? reason}) =>
      _post('/staff/approvals/staff-expenses/$id/reject', {if (reason != null) 'reason': reason});

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
    String? alternatePhone,
    String? nidNumber,
    String? gender,
    String? dateOfBirth,
    String? occupation,
    String? segment,
    String? subscriberType,
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
    int? subzoneId,
    int? districtId,
    int? upazilaId,
    int? mikrotikServerId,
    String? joinedAt,
    String? serviceExpiresAt,
    bool provisionMikrotik = true,
    String? firstBillCycle,
    int? expireDay,
    String? networkAccessState,
    bool applyLineCharges = false,
    double? accountBalance,
    double? installationCharge,
    double? lineDeviceCharge,
    double? lineCashAmount,
    String? lineCashMethod,
    bool useWalletOnRegister = true,
    Map<String, dynamic>? meta,
  }) =>
      _post('/staff/customers/create', {
        'name': name,
        'phone': phone,
        'package_id': packageId,
        if (email != null && email.isNotEmpty) 'email': email,
        if (alternatePhone != null && alternatePhone.isNotEmpty) 'alternate_phone': alternatePhone,
        if (nidNumber != null && nidNumber.isNotEmpty) 'nid_number': nidNumber,
        if (gender != null && gender.isNotEmpty) 'gender': gender,
        if (dateOfBirth != null && dateOfBirth.isNotEmpty) 'date_of_birth': dateOfBirth,
        if (occupation != null && occupation.isNotEmpty) 'occupation': occupation,
        if (segment != null && segment.isNotEmpty) 'segment': segment,
        if (subscriberType != null && subscriberType.isNotEmpty) 'subscriber_type': subscriberType,
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
        if (subzoneId != null) 'subzone_id': subzoneId,
        if (districtId != null) 'district_id': districtId,
        if (upazilaId != null) 'upazila_id': upazilaId,
        if (mikrotikServerId != null) 'mikrotik_server_id': mikrotikServerId,
        if (joinedAt != null) 'joined_at': joinedAt,
        if (serviceExpiresAt != null) 'service_expires_at': serviceExpiresAt,
        'provision_mikrotik': provisionMikrotik,
        'network_access_state': networkAccessState ?? 'active',
        if (firstBillCycle != null) 'first_bill_cycle': firstBillCycle,
        if (expireDay != null) 'expire_day': expireDay,
        'apply_line_charges': applyLineCharges,
        if (accountBalance != null) 'account_balance': accountBalance,
        if (installationCharge != null) 'installation_charge': installationCharge,
        if (lineDeviceCharge != null) 'line_device_charge': lineDeviceCharge,
        if (lineCashAmount != null) 'line_cash_amount': lineCashAmount,
        if (lineCashMethod != null) 'line_cash_method': lineCashMethod,
        'use_wallet_on_register': useWalletOnRegister,
        if (meta != null && meta.isNotEmpty) 'meta': meta,
      });

  Future<Map<String, dynamic>> createStaffCustomer({
    required String name,
    required String phone,
    required int packageId,
    String? email,
    String? address,
    int? areaId,
    int? zoneId,
    String? notes,
    String? portalPassword,
  }) =>
      _post('/staff/customers/create', {
        'name': name,
        'phone': phone,
        'package_id': packageId,
        if (email != null) 'email': email,
        'address': address?.trim().isNotEmpty == true ? address!.trim() : 'Created from mobile app',
        if (areaId != null) 'area_id': areaId,
        if (zoneId != null) 'zone_id': zoneId,
        if (notes != null) 'notes': notes,
        if (portalPassword != null) 'portal_password': portalPassword,
      });

  Future<List<Map<String, dynamic>>> collectorExpenseCategories() async {
    final body = await _get('/collector/expense-categories');
    return _listFrom(body['data']);
  }

  Future<List<Map<String, dynamic>>> staffExpenses() async {
    final body = await _get('/staff/expenses');
    return _listFrom(body['data']);
  }

  Future<Map<String, dynamic>> submitCollectorExpense({
    required double amount,
    required int categoryId,
    String expenseSource = 'office',
    String? description,
    String? expenseDate,
  }) =>
      _post('/collector/expenses', {
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
    bool? networkSuspended,
    int? expiringDays,
  }) async {
    final params = <String, String>{
      'page': '$page',
      'per_page': '$perPage',
      if (q.isNotEmpty) 'q': q,
      if (status != null && status.isNotEmpty) 'status': status,
      if (packageId != null) 'package_id': '$packageId',
      if (dueOnly) 'due_only': '1',
      if (networkSuspended == true) 'network_suspended': '1',
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
    int? collectorUserId,
    String discountPreset = 'none',
    double? discountCustom,
  }) =>
      _post('/staff/payments', {
        'customer_id': customerId,
        'amount': amount,
        'method': method,
        if (invoiceId != null) 'invoice_id': invoiceId,
        if (reference != null) 'reference': reference,
        if (notes != null) 'notes': notes,
        if (collectorUserId != null) 'collector_user_id': collectorUserId,
        'discount_preset': discountPreset,
        if (discountCustom != null && discountCustom > 0) 'discount_custom': discountCustom,
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

  Future<Map<String, dynamic>> staffReportsDue({int page = 1}) =>
      _get('/staff/reports/due?page=$page');

  Future<Map<String, dynamic>> staffMfsSmsIngest({
    required String gateway,
    required String transactionId,
    required double amount,
    String? senderPhone,
    String? customerReference,
    String? rawMessage,
    String? deviceName,
  }) =>
      _post('/staff/mfs/sms/ingest', {
        'gateway': gateway,
        'transaction_id': transactionId,
        'amount': amount,
        if (senderPhone != null && senderPhone.isNotEmpty) 'sender_phone': senderPhone,
        if (customerReference != null && customerReference.isNotEmpty)
          'customer_reference': customerReference,
        if (rawMessage != null && rawMessage.isNotEmpty) 'raw_message': rawMessage,
        if (deviceName != null && deviceName.isNotEmpty) 'device_name': deviceName,
      });

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
    int? collectorUserId,
    String discountPreset = 'none',
    double? discountCustom,
  }) async {
    return staffRecordPayment(
      customerId: customerId,
      amount: amount,
      method: method,
      invoiceId: invoiceId,
      reference: reference,
      notes: notes,
      collectorUserId: collectorUserId,
      discountPreset: discountPreset,
      discountCustom: discountCustom,
    );
  }

  Future<List<Map<String, dynamic>>> staffTasks() async {
    final body = await _get('/staff/tasks');
    return _listFrom(body['data']);
  }

  Future<Map<String, dynamic>> staffOnlineClients() => _get('/staff/monitoring/online');

  Future<Map<String, dynamic>> staffMonitoringLive() => _get('/staff/monitoring/live');

  Future<Map<String, dynamic>> staffMonitoringClients({
    String q = '',
    int? mikrotikServerId,
    int? zoneId,
    int? subzoneId,
    int? areaId,
    String connection = 'all',
    int page = 1,
    int perPage = 25,
  }) async {
    final params = <String, String>{
      'page': '$page',
      'per_page': '$perPage',
      if (q.isNotEmpty) 'q': q,
      if (mikrotikServerId != null) 'mikrotik_server_id': '$mikrotikServerId',
      if (zoneId != null) 'zone_id': '$zoneId',
      if (subzoneId != null) 'subzone_id': '$subzoneId',
      if (areaId != null) 'area_id': '$areaId',
      if (connection.isNotEmpty && connection != 'all') 'connection': connection,
    };
    final query = params.entries.map((e) => '${Uri.encodeQueryComponent(e.key)}=${Uri.encodeQueryComponent(e.value)}').join('&');
    try {
      return await _get('/staff/monitoring/clients?$query');
    } on ApiException catch (e) {
      if (e.statusCode != 404) rethrow;
      final fallback = await _get('/staff/monitoring/online');
      final clients = _listFrom(fallback['data'])
          .map((row) => {
                ...row,
                'username': row['username'] ?? row['radius_username'] ?? row['user_id'] ?? '',
                'profile': row['profile'] ?? row['package'] ?? '',
                'is_online': true,
                'connection_status': 'Connected',
                'last_logout': '',
              })
          .toList();
      final online = (fallback['total_online'] as num?)?.toInt() ?? clients.length;
      return {
        'data': clients,
        'stats': {'total': online, 'online': online, 'offline': 0},
        'filters': {'routers': const [], 'zones': const [], 'subzones': const [], 'areas': const []},
        'meta': {'current_page': 1, 'last_page': 1, 'total': clients.length},
      };
    }
  }

  Future<Map<String, dynamic>> staffBillingSummary() => _get('/staff/billing/summary');

  Future<Map<String, dynamic>> staffBillingDue({int page = 1, String q = ''}) async {
    final params = <String>['page=$page', 'per_page=50'];
    if (q.trim().isNotEmpty) params.add('q=${Uri.encodeQueryComponent(q.trim())}');
    return _get('/staff/billing/due?${params.join('&')}');
  }

  Future<Map<String, dynamic>> staffBillingInvoices({String status = 'all', int page = 1}) async {
    final encoded = Uri.encodeQueryComponent(status);
    return _get('/staff/billing/invoices?status=$encoded&page=$page');
  }

  Future<Map<String, dynamic>> staffBillingCollections({int page = 1}) async {
    return _get('/staff/billing/collections?page=$page');
  }

  Future<Map<String, dynamic>> staffPaymentReceiptDetail(int paymentId) =>
      _get('/staff/payments/$paymentId/receipt');

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

  Future<Map<String, dynamic>> staffExtendService(int customerId, {int days = 30}) =>
      _post('/staff/customers/$customerId/extend-service', {'days': days});

  Future<Map<String, dynamic>> staffToggleNetwork(int customerId) =>
      _post('/staff/customers/$customerId/toggle-network', {});

  Future<Map<String, dynamic>> customerOnuStatus() => _get('/customer/onu/status');

  Future<Map<String, dynamic>> customerOnuReboot() => _post('/customer/onu/reboot', {});

  Future<Map<String, dynamic>> customerAiAsk(String question) =>
      _post('/customer/ai/ask', {'question': question});

  Future<Map<String, dynamic>> staffMe() => _get('/me');

  Future<Map<String, dynamic>> staffAiAsk(String query, {List<Map<String, dynamic>>? session}) =>
      _post('/staff/ai/ask', {
        'query': query,
        if (session != null) 'session': session,
      });

  Future<List<Map<String, dynamic>>> staffGisSearch(String q) async {
    if (q.trim().length < 2) return [];
    final encoded = Uri.encodeQueryComponent(q.trim());
    final body = await _get('/staff/gis/search?q=$encoded');
    return _listFrom(body['results']);
  }

  Future<Map<String, dynamic>> staffGisMap({int? customerId}) async {
    final suffix = customerId != null ? '?customer=$customerId' : '';
    return _get('/staff/gis/map$suffix');
  }

  Future<List<Map<String, dynamic>>> technicianFieldVisits({bool todayOnly = false, String? status}) async {
    final params = <String>[];
    if (todayOnly) params.add('today_only=1');
    if (status != null && status.isNotEmpty) params.add('status=${Uri.encodeQueryComponent(status)}');
    final q = params.isEmpty ? '' : '?${params.join('&')}';
    final body = await _get('/technician/field-visits$q');
    return _listFrom(body['data']);
  }

  Future<Map<String, dynamic>> technicianUpdateFieldVisit(
    int id, {
    String? status,
    double? latitude,
    double? longitude,
    String? locationText,
    String? report,
  }) =>
      _patch('/technician/field-visits/$id', {
        if (status != null) 'status': status,
        if (latitude != null) 'latitude': latitude,
        if (longitude != null) 'longitude': longitude,
        if (locationText != null) 'location_text': locationText,
        if (report != null) 'report': report,
      });

  Future<void> technicianPingLocation({
    required double latitude,
    required double longitude,
    int? accuracyMeters,
    double? headingDeg,
    double? speedKmh,
  }) async {
    await _post('/technician/location', {
      'latitude': latitude,
      'longitude': longitude,
      if (accuracyMeters != null) 'accuracy_meters': accuracyMeters,
      if (headingDeg != null) 'heading_deg': headingDeg,
      if (speedKmh != null) 'speed_kmh': speedKmh,
    });
  }

  Future<Map<String, dynamic>> technicianNavigate({
    int? visitId,
    int? customerId,
    double? destinationLat,
    double? destinationLng,
    double? fromLat,
    double? fromLng,
  }) async {
    final params = <String, String>{};
    if (visitId != null) params['visit_id'] = '$visitId';
    if (customerId != null) params['customer_id'] = '$customerId';
    if (destinationLat != null) params['destination_lat'] = '$destinationLat';
    if (destinationLng != null) params['destination_lng'] = '$destinationLng';
    if (fromLat != null) params['from_lat'] = '$fromLat';
    if (fromLng != null) params['from_lng'] = '$fromLng';
    final q = params.isEmpty ? '' : '?${params.entries.map((e) => '${e.key}=${Uri.encodeQueryComponent(e.value)}').join('&')}';
    return _get('/technician/navigate$q');
  }

  Future<Map<String, dynamic>> resellerDashboard() => _get('/reseller/dashboard');

  Future<Map<String, dynamic>> resellerDueAccount() => _get('/reseller/due-account');

  Future<List<Map<String, dynamic>>> resellerCustomers({String? q}) async {
    final qs = q != null && q.isNotEmpty ? '?q=${Uri.encodeQueryComponent(q)}' : '';
    final body = await _get('/reseller/customers$qs');
    return _listFrom(body['data']);
  }

  Future<List<Map<String, dynamic>>> resellerCommissions() async {
    final body = await _get('/reseller/commissions');
    return _listFrom(body['data']);
  }

  Future<bool> refreshToken() async {
    final r = await role;
    final path = switch (r) {
      'customer' => '/customer/auth/refresh',
      'reseller' => '/reseller/auth/refresh',
      _ => '/auth/refresh',
    };
    try {
      final body = await _post(path, {}, skipRefreshOn401: true);
      final token = body['token']?.toString();
      if (token != null && token.isNotEmpty && r != null) {
        await saveSession(token, r, expiresAt: body['expires_at']?.toString());
        return true;
      }
    } catch (_) {}
    return false;
  }

  Future<void> logout() async {
    final t = await token;
    final r = await role;
    if (t != null) {
      final path = switch (r) {
        'customer' => '/customer/logout',
        'reseller' => '/reseller/logout',
        _ => '/auth/logout',
      };
      try {
        await _client.post(Uri.parse('${ServerConfig.apiBaseUrl}$path'), headers: await _headers()).timeout(_timeout);
      } catch (_) {}
    }
    await clearSession();
    await _clearDeviceScopedCaches();
  }

  Future<void> _clearDeviceScopedCaches() async {
    final prefs = await SharedPreferences.getInstance();
    final keys = <String>[
      'cache_customer_profile',
      'cache_customer_bills',
      'cache_customer_tickets',
      'cache_customer_notifications',
      'cache_customer_dashboard',
      'offline_sync_queue',
      'device_uuid',
      'push_device_token',
    ];
    for (final key in keys) {
      await prefs.remove(key);
    }
  }

  Future<Map<String, dynamic>> _get(String path, {bool retried = false, bool skipRefresh = false}) async {
    final res = await _client.get(Uri.parse('${ServerConfig.apiBaseUrl}$path'), headers: await _headers()).timeout(_timeout);
    if (res.statusCode == 401 && !retried && !skipRefresh && await refreshToken()) {
      return _get(path, retried: true, skipRefresh: skipRefresh);
    }
    return _handle(res);
  }

  Future<Map<String, dynamic>> _patch(String path, Map<String, dynamic> payload, {bool retried = false}) async {
    final res = await _client
        .patch(
          Uri.parse('${ServerConfig.apiBaseUrl}$path'),
          headers: await _headers(),
          body: jsonEncode(payload),
        )
        .timeout(_timeout);
    if (res.statusCode == 401 && !retried && await refreshToken()) {
      return _patch(path, payload, retried: true);
    }
    return _handle(res);
  }

  Future<Map<String, dynamic>> _post(String path, Map<String, dynamic> payload, {bool retried = false, bool skipRefreshOn401 = false}) async {
    final res = await _client
        .post(
          Uri.parse('${ServerConfig.apiBaseUrl}$path'),
          headers: await _headers(),
          body: jsonEncode(payload),
        )
        .timeout(_timeout);
    if (res.statusCode == 401 && !retried && !skipRefreshOn401 && await refreshToken()) {
      return _post(path, payload, retried: true, skipRefreshOn401: skipRefreshOn401);
    }
    return _handle(res, skipClearOn401: skipRefreshOn401);
  }

  Map<String, dynamic> _handle(http.Response res, {bool skipClearOn401 = false}) {
    final body = _decode(res);
    if (res.statusCode == 401) {
      if (!skipClearOn401) {
        clearSession();
      }
      throw ApiException('Session expired. Please sign in again.', statusCode: 401);
    }
    if (res.statusCode >= 400) {
      throw ApiException(_messageFrom(body), statusCode: res.statusCode);
    }
    return body;
  }

  String _messageFrom(Map<String, dynamic> body) {
    final msg = body['message']?.toString();
    if (msg != null && msg.isNotEmpty) {
      if (msg.contains('No query results for model')) {
        if (msg.contains('Customer')) return 'Customer not found. Search again and select.';
        if (msg.contains('SupportTicket')) return 'Ticket not found.';
        return 'Record not found.';
      }
      return msg;
    }
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
  ApiException(this.message, {this.statusCode, this.data});
  final String message;
  final int? statusCode;
  final Map<String, dynamic>? data;
  @override
  String toString() => message;
}
