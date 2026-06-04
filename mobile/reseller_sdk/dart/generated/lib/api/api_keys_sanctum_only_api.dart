//
// AUTO-GENERATED FILE, DO NOT MODIFY!
//
// @dart=2.18

// ignore_for_file: unused_element, unused_import
// ignore_for_file: always_put_required_named_parameters_first
// ignore_for_file: constant_identifier_names
// ignore_for_file: lines_longer_than_80_chars

part of isp_reseller_api;


class APIKeysSanctumOnlyApi {
  APIKeysSanctumOnlyApi([ApiClient? apiClient]) : apiClient = apiClient ?? defaultApiClient;

  final ApiClient apiClient;

  /// Revoke API key
  ///
  /// Note: This method returns the HTTP [Response].
  ///
  /// Parameters:
  ///
  /// * [int] apiKey (required):
  Future<Response> resellerApiKeysApiKeyDeleteWithHttpInfo(int apiKey,) async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/api-keys/{apiKey}'
      .replaceAll('{apiKey}', apiKey.toString());

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

  /// Revoke API key
  ///
  /// Parameters:
  ///
  /// * [int] apiKey (required):
  Future<void> resellerApiKeysApiKeyDelete(int apiKey,) async {
    final response = await resellerApiKeysApiKeyDeleteWithHttpInfo(apiKey,);
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
  }

  /// List API keys (metadata only)
  ///
  /// Note: This method returns the HTTP [Response].
  Future<Response> resellerApiKeysGetWithHttpInfo() async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/api-keys';

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

  /// List API keys (metadata only)
  Future<ResellerApiKeysGet200Response?> resellerApiKeysGet() async {
    final response = await resellerApiKeysGetWithHttpInfo();
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
    // When a remote server returns no body with a status of 204, we shall not decode it.
    // At the time of writing this, `dart:convert` will throw an "Unexpected end of input"
    // FormatException when trying to decode an empty string.
    if (response.body.isNotEmpty && response.statusCode != HttpStatus.noContent) {
      return await apiClient.deserializeAsync(await _decodeBodyBytes(response), 'ResellerApiKeysGet200Response',) as ResellerApiKeysGet200Response;
    
    }
    return null;
  }

  /// Create API key
  ///
  /// Note: This method returns the HTTP [Response].
  ///
  /// Parameters:
  ///
  /// * [ApiKeyCreateRequest] apiKeyCreateRequest (required):
  Future<Response> resellerApiKeysPostWithHttpInfo(ApiKeyCreateRequest apiKeyCreateRequest,) async {
    // ignore: prefer_const_declarations
    final path = r'/reseller/api-keys';

    // ignore: prefer_final_locals
    Object? postBody = apiKeyCreateRequest;

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

  /// Create API key
  ///
  /// Parameters:
  ///
  /// * [ApiKeyCreateRequest] apiKeyCreateRequest (required):
  Future<ResellerApiKeysPost201Response?> resellerApiKeysPost(ApiKeyCreateRequest apiKeyCreateRequest,) async {
    final response = await resellerApiKeysPostWithHttpInfo(apiKeyCreateRequest,);
    if (response.statusCode >= HttpStatus.badRequest) {
      throw ApiException(response.statusCode, await _decodeBodyBytes(response));
    }
    // When a remote server returns no body with a status of 204, we shall not decode it.
    // At the time of writing this, `dart:convert` will throw an "Unexpected end of input"
    // FormatException when trying to decode an empty string.
    if (response.body.isNotEmpty && response.statusCode != HttpStatus.noContent) {
      return await apiClient.deserializeAsync(await _decodeBodyBytes(response), 'ResellerApiKeysPost201Response',) as ResellerApiKeysPost201Response;
    
    }
    return null;
  }
}
