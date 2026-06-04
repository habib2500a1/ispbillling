# isp_reseller_api.api.APIKeysSanctumOnlyApi

## Load the API package
```dart
import 'package:isp_reseller_api/api.dart';
```

All URIs are relative to *https://your-domain.example/api/v1*

Method | HTTP request | Description
------------- | ------------- | -------------
[**resellerApiKeysApiKeyDelete**](APIKeysSanctumOnlyApi.md#resellerapikeysapikeydelete) | **DELETE** /reseller/api-keys/{apiKey} | Revoke API key
[**resellerApiKeysGet**](APIKeysSanctumOnlyApi.md#resellerapikeysget) | **GET** /reseller/api-keys | List API keys (metadata only)
[**resellerApiKeysPost**](APIKeysSanctumOnlyApi.md#resellerapikeyspost) | **POST** /reseller/api-keys | Create API key


# **resellerApiKeysApiKeyDelete**
> resellerApiKeysApiKeyDelete(apiKey)

Revoke API key

### Example
```dart
import 'package:isp_reseller_api/api.dart';
// TODO Configure HTTP Bearer authorization: apiKeyBearer
// Case 1. Use String Token
//defaultApiClient.getAuthentication<HttpBearerAuth>('apiKeyBearer').setAccessToken('YOUR_ACCESS_TOKEN');
// Case 2. Use Function which generate token.
// String yourTokenGeneratorFunction() { ... }
//defaultApiClient.getAuthentication<HttpBearerAuth>('apiKeyBearer').setAccessToken(yourTokenGeneratorFunction);
// TODO Configure HTTP Bearer authorization: sanctumBearer
// Case 1. Use String Token
//defaultApiClient.getAuthentication<HttpBearerAuth>('sanctumBearer').setAccessToken('YOUR_ACCESS_TOKEN');
// Case 2. Use Function which generate token.
// String yourTokenGeneratorFunction() { ... }
//defaultApiClient.getAuthentication<HttpBearerAuth>('sanctumBearer').setAccessToken(yourTokenGeneratorFunction);
// TODO Configure API key authorization: apiKeyHeader
//defaultApiClient.getAuthentication<ApiKeyAuth>('apiKeyHeader').apiKey = 'YOUR_API_KEY';
// uncomment below to setup prefix (e.g. Bearer) for API key, if needed
//defaultApiClient.getAuthentication<ApiKeyAuth>('apiKeyHeader').apiKeyPrefix = 'Bearer';

final api_instance = APIKeysSanctumOnlyApi();
final apiKey = 56; // int | 

try {
    api_instance.resellerApiKeysApiKeyDelete(apiKey);
} catch (e) {
    print('Exception when calling APIKeysSanctumOnlyApi->resellerApiKeysApiKeyDelete: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **apiKey** | **int**|  | 

### Return type

void (empty response body)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: Not defined

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerApiKeysGet**
> ResellerApiKeysGet200Response resellerApiKeysGet()

List API keys (metadata only)

### Example
```dart
import 'package:isp_reseller_api/api.dart';
// TODO Configure HTTP Bearer authorization: apiKeyBearer
// Case 1. Use String Token
//defaultApiClient.getAuthentication<HttpBearerAuth>('apiKeyBearer').setAccessToken('YOUR_ACCESS_TOKEN');
// Case 2. Use Function which generate token.
// String yourTokenGeneratorFunction() { ... }
//defaultApiClient.getAuthentication<HttpBearerAuth>('apiKeyBearer').setAccessToken(yourTokenGeneratorFunction);
// TODO Configure HTTP Bearer authorization: sanctumBearer
// Case 1. Use String Token
//defaultApiClient.getAuthentication<HttpBearerAuth>('sanctumBearer').setAccessToken('YOUR_ACCESS_TOKEN');
// Case 2. Use Function which generate token.
// String yourTokenGeneratorFunction() { ... }
//defaultApiClient.getAuthentication<HttpBearerAuth>('sanctumBearer').setAccessToken(yourTokenGeneratorFunction);
// TODO Configure API key authorization: apiKeyHeader
//defaultApiClient.getAuthentication<ApiKeyAuth>('apiKeyHeader').apiKey = 'YOUR_API_KEY';
// uncomment below to setup prefix (e.g. Bearer) for API key, if needed
//defaultApiClient.getAuthentication<ApiKeyAuth>('apiKeyHeader').apiKeyPrefix = 'Bearer';

final api_instance = APIKeysSanctumOnlyApi();

try {
    final result = api_instance.resellerApiKeysGet();
    print(result);
} catch (e) {
    print('Exception when calling APIKeysSanctumOnlyApi->resellerApiKeysGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**ResellerApiKeysGet200Response**](ResellerApiKeysGet200Response.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerApiKeysPost**
> ResellerApiKeysPost201Response resellerApiKeysPost(apiKeyCreateRequest)

Create API key

### Example
```dart
import 'package:isp_reseller_api/api.dart';
// TODO Configure HTTP Bearer authorization: apiKeyBearer
// Case 1. Use String Token
//defaultApiClient.getAuthentication<HttpBearerAuth>('apiKeyBearer').setAccessToken('YOUR_ACCESS_TOKEN');
// Case 2. Use Function which generate token.
// String yourTokenGeneratorFunction() { ... }
//defaultApiClient.getAuthentication<HttpBearerAuth>('apiKeyBearer').setAccessToken(yourTokenGeneratorFunction);
// TODO Configure HTTP Bearer authorization: sanctumBearer
// Case 1. Use String Token
//defaultApiClient.getAuthentication<HttpBearerAuth>('sanctumBearer').setAccessToken('YOUR_ACCESS_TOKEN');
// Case 2. Use Function which generate token.
// String yourTokenGeneratorFunction() { ... }
//defaultApiClient.getAuthentication<HttpBearerAuth>('sanctumBearer').setAccessToken(yourTokenGeneratorFunction);
// TODO Configure API key authorization: apiKeyHeader
//defaultApiClient.getAuthentication<ApiKeyAuth>('apiKeyHeader').apiKey = 'YOUR_API_KEY';
// uncomment below to setup prefix (e.g. Bearer) for API key, if needed
//defaultApiClient.getAuthentication<ApiKeyAuth>('apiKeyHeader').apiKeyPrefix = 'Bearer';

final api_instance = APIKeysSanctumOnlyApi();
final apiKeyCreateRequest = ApiKeyCreateRequest(); // ApiKeyCreateRequest | 

try {
    final result = api_instance.resellerApiKeysPost(apiKeyCreateRequest);
    print(result);
} catch (e) {
    print('Exception when calling APIKeysSanctumOnlyApi->resellerApiKeysPost: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **apiKeyCreateRequest** | [**ApiKeyCreateRequest**](ApiKeyCreateRequest.md)|  | 

### Return type

[**ResellerApiKeysPost201Response**](ResellerApiKeysPost201Response.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

