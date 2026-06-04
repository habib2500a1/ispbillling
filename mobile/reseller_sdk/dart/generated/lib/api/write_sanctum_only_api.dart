//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;


class WriteSanctumOnlyApi {
  WriteSanctumOnlyApi([ApiClient? apiClient]) : apiClient = apiClient ?? defaultApiClient;

  final ApiClient apiClient;

  /// Generate invoice for subscriber
  ///
  /// Note: This method returns the HTTP [Response].
  ///
  /// Parameters:
  ///
  /// * [int] customer (required):
  Future<Response> resellerCustomersCustomerInvoicePostWithHttpInfo(int customer,) async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/customers/{customer}/invoice'
      .replaceAll('{customer}', customer.toString());

    // ignore: prefer_final_locals
    Object? postBody;

    final queryParams = <QueryParam>[];
    final headerParams = <String, String>{};
    final formParams = <String, String>{};

    const contentTypes = <String>[];


    return apiClient.invokeAPI(
      path,
      'POST',
      queryParams,
      postBody,
      headerParams,
      formParams,
      contentTypes.isEmpty ? null : contentTypes.first,
    );
  }

  /// Generate invoice for subscriber
  ///
  /// Parameters:
  ///
  /// * [int] customer (required):
  Future<void> resellerCustomersCustomerInvoicePost(int customer,) async {
    final response = await resellerCustomersCustomerInvoicePostWithHttpInfo(customer,);
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
  }

  /// Update subscriber
  ///
  /// Note: This method returns the HTTP [Response].
  ///
  /// Parameters:
  ///
  /// * [int] customer (required):
  ///
  /// * [CustomerUpdateRequest] customerUpdateRequest (required):
  Future<Response> resellerCustomersCustomerPatchWithHttpInfo(int customer, CustomerUpdateRequest customerUpdateRequest,) async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/customers/{customer}'
      .replaceAll('{customer}', customer.toString());

    // ignore: prefer_final_locals
    Object? postBody = customerUpdateRequest;

    final queryParams = <QueryParam>[];
    final headerParams = <String, String>{};
    final formParams = <String, String>{};

    const contentTypes = <String>['application/json'];


    return apiClient.invokeAPI(
      path,
      'PATCH',
      queryParams,
      postBody,
      headerParams,
      formParams,
      contentTypes.isEmpty ? null : contentTypes.first,
    );
  }

  /// Update subscriber
  ///
  /// Parameters:
  ///
  /// * [int] customer (required):
  ///
  /// * [CustomerUpdateRequest] customerUpdateRequest (required):
  Future<ResellerCustomersCustomerPatch200Response?> resellerCustomersCustomerPatch(int customer, CustomerUpdateRequest customerUpdateRequest,) async {
    final response = await resellerCustomersCustomerPatchWithHttpInfo(customer, customerUpdateRequest,);
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
    // When a remote server returns no body with a status of 204, we shall not decode it.
    // At the time of writing this, `dart:convert` will throw an "Unexpected end of input"
    // FormatException when trying to decode an empty string.
    if (response.body.isNotEmpty && response.statusCode != HttpStatus.noContent) {
      return await apiClient.deserializeAsync(await _decodeBodyBytes(response), 'ResellerCustomersCustomerPatch200Response',) as ResellerCustomersCustomerPatch200Response;
    
    }
    return null;
  }

  /// Collect payment
  ///
  /// Note: This method returns the HTTP [Response].
  ///
  /// Parameters:
  ///
  /// * [int] customer (required):
  ///
  /// * [PaymentCollectRequest] paymentCollectRequest (required):
  Future<Response> resellerCustomersCustomerPaymentsPostWithHttpInfo(int customer, PaymentCollectRequest paymentCollectRequest,) async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/customers/{customer}/payments'
      .replaceAll('{customer}', customer.toString());

    // ignore: prefer_final_locals
    Object? postBody = paymentCollectRequest;

    final queryParams = <QueryParam>[];
    final headerParams = <String, String>{};
    final formParams = <String, String>{};

    const contentTypes = <String>['application/json'];


    return apiClient.invokeAPI(
      path,
      'POST',
      queryParams,
      postBody,
      headerParams,
      formParams,
      contentTypes.isEmpty ? null : contentTypes.first,
    );
  }

  /// Collect payment
  ///
  /// Parameters:
  ///
  /// * [int] customer (required):
  ///
  /// * [PaymentCollectRequest] paymentCollectRequest (required):
  Future<PaymentCollectResponse?> resellerCustomersCustomerPaymentsPost(int customer, PaymentCollectRequest paymentCollectRequest,) async {
    final response = await resellerCustomersCustomerPaymentsPostWithHttpInfo(customer, paymentCollectRequest,);
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
    // When a remote server returns no body with a status of 204, we shall not decode it.
    // At the time of writing this, `dart:convert` will throw an "Unexpected end of input"
    // FormatException when trying to decode an empty string.
    if (response.body.isNotEmpty && response.statusCode != HttpStatus.noContent) {
      return await apiClient.deserializeAsync(await _decodeBodyBytes(response), 'PaymentCollectResponse',) as PaymentCollectResponse;
    
    }
    return null;
  }

  /// Reconnect subscriber
  ///
  /// Note: This method returns the HTTP [Response].
  ///
  /// Parameters:
  ///
  /// * [int] customer (required):
  Future<Response> resellerCustomersCustomerReconnectPostWithHttpInfo(int customer,) async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/customers/{customer}/reconnect'
      .replaceAll('{customer}', customer.toString());

    // ignore: prefer_final_locals
    Object? postBody;

    final queryParams = <QueryParam>[];
    final headerParams = <String, String>{};
    final formParams = <String, String>{};

    const contentTypes = <String>[];


    return apiClient.invokeAPI(
      path,
      'POST',
      queryParams,
      postBody,
      headerParams,
      formParams,
      contentTypes.isEmpty ? null : contentTypes.first,
    );
  }

  /// Reconnect subscriber
  ///
  /// Parameters:
  ///
  /// * [int] customer (required):
  Future<void> resellerCustomersCustomerReconnectPost(int customer,) async {
    final response = await resellerCustomersCustomerReconnectPostWithHttpInfo(customer,);
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
  }

  /// Renew billing cycle
  ///
  /// Note: This method returns the HTTP [Response].
  ///
  /// Parameters:
  ///
  /// * [int] customer (required):
  Future<Response> resellerCustomersCustomerRenewPostWithHttpInfo(int customer,) async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/customers/{customer}/renew'
      .replaceAll('{customer}', customer.toString());

    // ignore: prefer_final_locals
    Object? postBody;

    final queryParams = <QueryParam>[];
    final headerParams = <String, String>{};
    final formParams = <String, String>{};

    const contentTypes = <String>[];


    return apiClient.invokeAPI(
      path,
      'POST',
      queryParams,
      postBody,
      headerParams,
      formParams,
      contentTypes.isEmpty ? null : contentTypes.first,
    );
  }

  /// Renew billing cycle
  ///
  /// Parameters:
  ///
  /// * [int] customer (required):
  Future<void> resellerCustomersCustomerRenewPost(int customer,) async {
    final response = await resellerCustomersCustomerRenewPostWithHttpInfo(customer,);
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
  }

  /// Suspend subscriber
  ///
  /// Note: This method returns the HTTP [Response].
  ///
  /// Parameters:
  ///
  /// * [int] customer (required):
  Future<Response> resellerCustomersCustomerSuspendPostWithHttpInfo(int customer,) async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/customers/{customer}/suspend'
      .replaceAll('{customer}', customer.toString());

    // ignore: prefer_final_locals
    Object? postBody;

    final queryParams = <QueryParam>[];
    final headerParams = <String, String>{};
    final formParams = <String, String>{};

    const contentTypes = <String>[];


    return apiClient.invokeAPI(
      path,
      'POST',
      queryParams,
      postBody,
      headerParams,
      formParams,
      contentTypes.isEmpty ? null : contentTypes.first,
    );
  }

  /// Suspend subscriber
  ///
  /// Parameters:
  ///
  /// * [int] customer (required):
  Future<void> resellerCustomersCustomerSuspendPost(int customer,) async {
    final response = await resellerCustomersCustomerSuspendPostWithHttpInfo(customer,);
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
  }

  /// Create subscriber
  ///
  /// Note: This method returns the HTTP [Response].
  ///
  /// Parameters:
  ///
  /// * [CustomerCreateRequest] customerCreateRequest (required):
  Future<Response> resellerCustomersPostWithHttpInfo(CustomerCreateRequest customerCreateRequest,) async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/customers';

    // ignore: prefer_final_locals
    Object? postBody = customerCreateRequest;

    final queryParams = <QueryParam>[];
    final headerParams = <String, String>{};
    final formParams = <String, String>{};

    const contentTypes = <String>['application/json'];


    return apiClient.invokeAPI(
      path,
      'POST',
      queryParams,
      postBody,
      headerParams,
      formParams,
      contentTypes.isEmpty ? null : contentTypes.first,
    );
  }

  /// Create subscriber
  ///
  /// Parameters:
  ///
  /// * [CustomerCreateRequest] customerCreateRequest (required):
  Future<Map<String, Object>?> resellerCustomersPost(CustomerCreateRequest customerCreateRequest,) async {
    final response = await resellerCustomersPostWithHttpInfo(customerCreateRequest,);
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
    // When a remote server returns no body with a status of 204, we shall not decode it.
    // At the time of writing this, `dart:convert` will throw an "Unexpected end of input"
    // FormatException when trying to decode an empty string.
    if (response.body.isNotEmpty && response.statusCode != HttpStatus.noContent) {
      return Map<String, Object>.from(await apiClient.deserializeAsync(await _decodeBodyBytes(response), 'Map<String, Object>'),);

    }
    return null;
  }

  /// Open internal ticket
  ///
  /// Note: This method returns the HTTP [Response].
  ///
  /// Parameters:
  ///
  /// * [ResellerInternalTicketsPostRequest] resellerInternalTicketsPostRequest (required):
  Future<Response> resellerInternalTicketsPostWithHttpInfo(ResellerInternalTicketsPostRequest resellerInternalTicketsPostRequest,) async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/internal-tickets';

    // ignore: prefer_final_locals
    Object? postBody = resellerInternalTicketsPostRequest;

    final queryParams = <QueryParam>[];
    final headerParams = <String, String>{};
    final formParams = <String, String>{};

    const contentTypes = <String>['application/json'];


    return apiClient.invokeAPI(
      path,
      'POST',
      queryParams,
      postBody,
      headerParams,
      formParams,
      contentTypes.isEmpty ? null : contentTypes.first,
    );
  }

  /// Open internal ticket
  ///
  /// Parameters:
  ///
  /// * [ResellerInternalTicketsPostRequest] resellerInternalTicketsPostRequest (required):
  Future<void> resellerInternalTicketsPost(ResellerInternalTicketsPostRequest resellerInternalTicketsPostRequest,) async {
    final response = await resellerInternalTicketsPostWithHttpInfo(resellerInternalTicketsPostRequest,);
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
  }

  /// Submit settlement request
  ///
  /// Note: This method returns the HTTP [Response].
  ///
  /// Parameters:
  ///
  /// * [SettlementCreateRequest] settlementCreateRequest (required):
  Future<Response> resellerSettlementsPostWithHttpInfo(SettlementCreateRequest settlementCreateRequest,) async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/settlements';

    // ignore: prefer_final_locals
    Object? postBody = settlementCreateRequest;

    final queryParams = <QueryParam>[];
    final headerParams = <String, String>{};
    final formParams = <String, String>{};

    const contentTypes = <String>['application/json'];


    return apiClient.invokeAPI(
      path,
      'POST',
      queryParams,
      postBody,
      headerParams,
      formParams,
      contentTypes.isEmpty ? null : contentTypes.first,
    );
  }

  /// Submit settlement request
  ///
  /// Parameters:
  ///
  /// * [SettlementCreateRequest] settlementCreateRequest (required):
  Future<SettlementCreateResponse?> resellerSettlementsPost(SettlementCreateRequest settlementCreateRequest,) async {
    final response = await resellerSettlementsPostWithHttpInfo(settlementCreateRequest,);
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
    // When a remote server returns no body with a status of 204, we shall not decode it.
    // At the time of writing this, `dart:convert` will throw an "Unexpected end of input"
    // FormatException when trying to decode an empty string.
    if (response.body.isNotEmpty && response.statusCode != HttpStatus.noContent) {
      return await apiClient.deserializeAsync(await _decodeBodyBytes(response), 'SettlementCreateResponse',) as SettlementCreateResponse;
    
    }
    return null;
  }

  /// List staff accounts
  ///
  /// Note: This method returns the HTTP [Response].
  Future<Response> resellerStaffGetWithHttpInfo() async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/staff';

    // ignore: prefer_final_locals
    Object? postBody;

    final queryParams = <QueryParam>[];
    final headerParams = <String, String>{};
    final formParams = <String, String>{};

    const contentTypes = <String>[];


    return apiClient.invokeAPI(
      path,
      'GET',
      queryParams,
      postBody,
      headerParams,
      formParams,
      contentTypes.isEmpty ? null : contentTypes.first,
    );
  }

  /// List staff accounts
  Future<ResellerStaffGet200Response?> resellerStaffGet() async {
    final response = await resellerStaffGetWithHttpInfo();
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
    // When a remote server returns no body with a status of 204, we shall not decode it.
    // At the time of writing this, `dart:convert` will throw an "Unexpected end of input"
    // FormatException when trying to decode an empty string.
    if (response.body.isNotEmpty && response.statusCode != HttpStatus.noContent) {
      return await apiClient.deserializeAsync(await _decodeBodyBytes(response), 'ResellerStaffGet200Response',) as ResellerStaffGet200Response;
    
    }
    return null;
  }

  /// Staff permission labels (assignable subset)
  ///
  /// Note: This method returns the HTTP [Response].
  Future<Response> resellerStaffPermissionOptionsGetWithHttpInfo() async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/staff/permission-options';

    // ignore: prefer_final_locals
    Object? postBody;

    final queryParams = <QueryParam>[];
    final headerParams = <String, String>{};
    final formParams = <String, String>{};

    const contentTypes = <String>[];


    return apiClient.invokeAPI(
      path,
      'GET',
      queryParams,
      postBody,
      headerParams,
      formParams,
      contentTypes.isEmpty ? null : contentTypes.first,
    );
  }

  /// Staff permission labels (assignable subset)
  Future<ResellerStaffPermissionOptionsGet200Response?> resellerStaffPermissionOptionsGet() async {
    final response = await resellerStaffPermissionOptionsGetWithHttpInfo();
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
    // When a remote server returns no body with a status of 204, we shall not decode it.
    // At the time of writing this, `dart:convert` will throw an "Unexpected end of input"
    // FormatException when trying to decode an empty string.
    if (response.body.isNotEmpty && response.statusCode != HttpStatus.noContent) {
      return await apiClient.deserializeAsync(await _decodeBodyBytes(response), 'ResellerStaffPermissionOptionsGet200Response',) as ResellerStaffPermissionOptionsGet200Response;
    
    }
    return null;
  }

  /// Create staff account
  ///
  /// Note: This method returns the HTTP [Response].
  ///
  /// Parameters:
  ///
  /// * [StaffCreateRequest] staffCreateRequest (required):
  Future<Response> resellerStaffPostWithHttpInfo(StaffCreateRequest staffCreateRequest,) async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/staff';

    // ignore: prefer_final_locals
    Object? postBody = staffCreateRequest;

    final queryParams = <QueryParam>[];
    final headerParams = <String, String>{};
    final formParams = <String, String>{};

    const contentTypes = <String>['application/json'];


    return apiClient.invokeAPI(
      path,
      'POST',
      queryParams,
      postBody,
      headerParams,
      formParams,
      contentTypes.isEmpty ? null : contentTypes.first,
    );
  }

  /// Create staff account
  ///
  /// Parameters:
  ///
  /// * [StaffCreateRequest] staffCreateRequest (required):
  Future<StaffMutationResponse?> resellerStaffPost(StaffCreateRequest staffCreateRequest,) async {
    final response = await resellerStaffPostWithHttpInfo(staffCreateRequest,);
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
    // When a remote server returns no body with a status of 204, we shall not decode it.
    // At the time of writing this, `dart:convert` will throw an "Unexpected end of input"
    // FormatException when trying to decode an empty string.
    if (response.body.isNotEmpty && response.statusCode != HttpStatus.noContent) {
      return await apiClient.deserializeAsync(await _decodeBodyBytes(response), 'StaffMutationResponse',) as StaffMutationResponse;
    
    }
    return null;
  }

  /// Deactivate staff account
  ///
  /// Note: This method returns the HTTP [Response].
  ///
  /// Parameters:
  ///
  /// * [int] staffMember (required):
  Future<Response> resellerStaffStaffMemberDeleteWithHttpInfo(int staffMember,) async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/staff/{staffMember}'
      .replaceAll('{staffMember}', staffMember.toString());

    // ignore: prefer_final_locals
    Object? postBody;

    final queryParams = <QueryParam>[];
    final headerParams = <String, String>{};
    final formParams = <String, String>{};

    const contentTypes = <String>[];


    return apiClient.invokeAPI(
      path,
      'DELETE',
      queryParams,
      postBody,
      headerParams,
      formParams,
      contentTypes.isEmpty ? null : contentTypes.first,
    );
  }

  /// Deactivate staff account
  ///
  /// Parameters:
  ///
  /// * [int] staffMember (required):
  Future<ResellerLogoutPost200Response?> resellerStaffStaffMemberDelete(int staffMember,) async {
    final response = await resellerStaffStaffMemberDeleteWithHttpInfo(staffMember,);
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
    // When a remote server returns no body with a status of 204, we shall not decode it.
    // At the time of writing this, `dart:convert` will throw an "Unexpected end of input"
    // FormatException when trying to decode an empty string.
    if (response.body.isNotEmpty && response.statusCode != HttpStatus.noContent) {
      return await apiClient.deserializeAsync(await _decodeBodyBytes(response), 'ResellerLogoutPost200Response',) as ResellerLogoutPost200Response;
    
    }
    return null;
  }

  /// Staff detail
  ///
  /// Note: This method returns the HTTP [Response].
  ///
  /// Parameters:
  ///
  /// * [int] staffMember (required):
  Future<Response> resellerStaffStaffMemberGetWithHttpInfo(int staffMember,) async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/staff/{staffMember}'
      .replaceAll('{staffMember}', staffMember.toString());

    // ignore: prefer_final_locals
    Object? postBody;

    final queryParams = <QueryParam>[];
    final headerParams = <String, String>{};
    final formParams = <String, String>{};

    const contentTypes = <String>[];


    return apiClient.invokeAPI(
      path,
      'GET',
      queryParams,
      postBody,
      headerParams,
      formParams,
      contentTypes.isEmpty ? null : contentTypes.first,
    );
  }

  /// Staff detail
  ///
  /// Parameters:
  ///
  /// * [int] staffMember (required):
  Future<ResellerStaffStaffMemberGet200Response?> resellerStaffStaffMemberGet(int staffMember,) async {
    final response = await resellerStaffStaffMemberGetWithHttpInfo(staffMember,);
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
    // When a remote server returns no body with a status of 204, we shall not decode it.
    // At the time of writing this, `dart:convert` will throw an "Unexpected end of input"
    // FormatException when trying to decode an empty string.
    if (response.body.isNotEmpty && response.statusCode != HttpStatus.noContent) {
      return await apiClient.deserializeAsync(await _decodeBodyBytes(response), 'ResellerStaffStaffMemberGet200Response',) as ResellerStaffStaffMemberGet200Response;
    
    }
    return null;
  }

  /// Update staff account
  ///
  /// Note: This method returns the HTTP [Response].
  ///
  /// Parameters:
  ///
  /// * [int] staffMember (required):
  ///
  /// * [StaffUpdateRequest] staffUpdateRequest (required):
  Future<Response> resellerStaffStaffMemberPatchWithHttpInfo(int staffMember, StaffUpdateRequest staffUpdateRequest,) async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/staff/{staffMember}'
      .replaceAll('{staffMember}', staffMember.toString());

    // ignore: prefer_final_locals
    Object? postBody = staffUpdateRequest;

    final queryParams = <QueryParam>[];
    final headerParams = <String, String>{};
    final formParams = <String, String>{};

    const contentTypes = <String>['application/json'];


    return apiClient.invokeAPI(
      path,
      'PATCH',
      queryParams,
      postBody,
      headerParams,
      formParams,
      contentTypes.isEmpty ? null : contentTypes.first,
    );
  }

  /// Update staff account
  ///
  /// Parameters:
  ///
  /// * [int] staffMember (required):
  ///
  /// * [StaffUpdateRequest] staffUpdateRequest (required):
  Future<StaffMutationResponse?> resellerStaffStaffMemberPatch(int staffMember, StaffUpdateRequest staffUpdateRequest,) async {
    final response = await resellerStaffStaffMemberPatchWithHttpInfo(staffMember, staffUpdateRequest,);
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
    // When a remote server returns no body with a status of 204, we shall not decode it.
    // At the time of writing this, `dart:convert` will throw an "Unexpected end of input"
    // FormatException when trying to decode an empty string.
    if (response.body.isNotEmpty && response.statusCode != HttpStatus.noContent) {
      return await apiClient.deserializeAsync(await _decodeBodyBytes(response), 'StaffMutationResponse',) as StaffMutationResponse;
    
    }
    return null;
  }

  /// Create sub-partner
  ///
  /// Note: This method returns the HTTP [Response].
  Future<Response> resellerSubResellersPostWithHttpInfo() async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/sub-resellers';

    // ignore: prefer_final_locals
    Object? postBody;

    final queryParams = <QueryParam>[];
    final headerParams = <String, String>{};
    final formParams = <String, String>{};

    const contentTypes = <String>[];


    return apiClient.invokeAPI(
      path,
      'POST',
      queryParams,
      postBody,
      headerParams,
      formParams,
      contentTypes.isEmpty ? null : contentTypes.first,
    );
  }

  /// Create sub-partner
  Future<void> resellerSubResellersPost() async {
    final response = await resellerSubResellersPostWithHttpInfo();
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
  }

  /// Open ticket
  ///
  /// Note: This method returns the HTTP [Response].
  Future<Response> resellerTicketsPostWithHttpInfo() async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/tickets';

    // ignore: prefer_final_locals
    Object? postBody;

    final queryParams = <QueryParam>[];
    final headerParams = <String, String>{};
    final formParams = <String, String>{};

    const contentTypes = <String>[];


    return apiClient.invokeAPI(
      path,
      'POST',
      queryParams,
      postBody,
      headerParams,
      formParams,
      contentTypes.isEmpty ? null : contentTypes.first,
    );
  }

  /// Open ticket
  Future<void> resellerTicketsPost() async {
    final response = await resellerTicketsPostWithHttpInfo();
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
  }

  /// Start PipraPay wallet checkout
  ///
  /// Note: This method returns the HTTP [Response].
  ///
  /// Parameters:
  ///
  /// * [WalletPipraPayRequest] walletPipraPayRequest (required):
  Future<Response> resellerWalletRechargePiprapayPostWithHttpInfo(WalletPipraPayRequest walletPipraPayRequest,) async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/wallet/recharge/piprapay';

    // ignore: prefer_final_locals
    Object? postBody = walletPipraPayRequest;

    final queryParams = <QueryParam>[];
    final headerParams = <String, String>{};
    final formParams = <String, String>{};

    const contentTypes = <String>['application/json'];


    return apiClient.invokeAPI(
      path,
      'POST',
      queryParams,
      postBody,
      headerParams,
      formParams,
      contentTypes.isEmpty ? null : contentTypes.first,
    );
  }

  /// Start PipraPay wallet checkout
  ///
  /// Parameters:
  ///
  /// * [WalletPipraPayRequest] walletPipraPayRequest (required):
  Future<WalletPipraPayResponse?> resellerWalletRechargePiprapayPost(WalletPipraPayRequest walletPipraPayRequest,) async {
    final response = await resellerWalletRechargePiprapayPostWithHttpInfo(walletPipraPayRequest,);
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
    // When a remote server returns no body with a status of 204, we shall not decode it.
    // At the time of writing this, `dart:convert` will throw an "Unexpected end of input"
    // FormatException when trying to decode an empty string.
    if (response.body.isNotEmpty && response.statusCode != HttpStatus.noContent) {
      return await apiClient.deserializeAsync(await _decodeBodyBytes(response), 'WalletPipraPayResponse',) as WalletPipraPayResponse;
    
    }
    return null;
  }

  /// Submit manual wallet top-up (admin approval)
  ///
  /// Note: This method returns the HTTP [Response].
  ///
  /// Parameters:
  ///
  /// * [WalletRechargeRequest] walletRechargeRequest (required):
  Future<Response> resellerWalletRechargePostWithHttpInfo(WalletRechargeRequest walletRechargeRequest,) async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/wallet/recharge';

    // ignore: prefer_final_locals
    Object? postBody = walletRechargeRequest;

    final queryParams = <QueryParam>[];
    final headerParams = <String, String>{};
    final formParams = <String, String>{};

    const contentTypes = <String>['application/json'];


    return apiClient.invokeAPI(
      path,
      'POST',
      queryParams,
      postBody,
      headerParams,
      formParams,
      contentTypes.isEmpty ? null : contentTypes.first,
    );
  }

  /// Submit manual wallet top-up (admin approval)
  ///
  /// Parameters:
  ///
  /// * [WalletRechargeRequest] walletRechargeRequest (required):
  Future<WalletRechargeResponse?> resellerWalletRechargePost(WalletRechargeRequest walletRechargeRequest,) async {
    final response = await resellerWalletRechargePostWithHttpInfo(walletRechargeRequest,);
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
    // When a remote server returns no body with a status of 204, we shall not decode it.
    // At the time of writing this, `dart:convert` will throw an "Unexpected end of input"
    // FormatException when trying to decode an empty string.
    if (response.body.isNotEmpty && response.statusCode != HttpStatus.noContent) {
      return await apiClient.deserializeAsync(await _decodeBodyBytes(response), 'WalletRechargeResponse',) as WalletRechargeResponse;
    
    }
    return null;
  }
}
