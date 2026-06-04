# isp_reseller_api.api.AuthApi

## Load the API package
```dart
import 'package:isp_reseller_api/api.dart';
```

All URIs are relative to *https://your-domain.example/api/v1*

Method | HTTP request | Description
------------- | ------------- | -------------
[**resellerLoginPost**](AuthApi.md#resellerloginpost) | **POST** /reseller/login | Login (owner or staff)
[**resellerLogoutPost**](AuthApi.md#resellerlogoutpost) | **POST** /reseller/logout | Revoke current Sanctum token


# **resellerLoginPost**
> LoginResponse resellerLoginPost(loginRequest)

Login (owner or staff)

### Example
```dart
import 'package:isp_reseller_api/api.dart';

final api_instance = AuthApi();
final loginRequest = LoginRequest(); // LoginRequest | 

try {
    final result = api_instance.resellerLoginPost(loginRequest);
    print(result);
} catch (e) {
    print('Exception when calling AuthApi->resellerLoginPost: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **loginRequest** | [**LoginRequest**](LoginRequest.md)|  | 

### Return type

[**LoginResponse**](LoginResponse.md)

### Authorization

No authorization required

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerLogoutPost**
> ResellerLogoutPost200Response resellerLogoutPost()

Revoke current Sanctum token

### Example
```dart
import 'package:isp_reseller_api/api.dart';
// TODO Configure HTTP Bearer authorization: sanctumBearer
// Case 1. Use String Token
//defaultApiClient.getAuthentication<HttpBearerAuth>('sanctumBearer').setAccessToken('YOUR_ACCESS_TOKEN');
// Case 2. Use Function which generate token.
// String yourTokenGeneratorFunction() { ... }
//defaultApiClient.getAuthentication<HttpBearerAuth>('sanctumBearer').setAccessToken(yourTokenGeneratorFunction);

final api_instance = AuthApi();

try {
    final result = api_instance.resellerLogoutPost();
    print(result);
} catch (e) {
    print('Exception when calling AuthApi->resellerLogoutPost: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**ResellerLogoutPost200Response**](ResellerLogoutPost200Response.md)

### Authorization

[sanctumBearer](../README.md#sanctumBearer)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

