# isp_reseller_api.api.WriteSanctumOnlyApi

## Load the API package
```dart
import 'package:isp_reseller_api/api.dart';
```

All URIs are relative to *https://your-domain.example/api/v1*

Method | HTTP request | Description
------------- | ------------- | -------------
[**resellerCustomersCustomerInvoicePost**](WriteSanctumOnlyApi.md#resellercustomerscustomerinvoicepost) | **POST** /reseller/customers/{customer}/invoice | Generate invoice for subscriber
[**resellerCustomersCustomerPatch**](WriteSanctumOnlyApi.md#resellercustomerscustomerpatch) | **PATCH** /reseller/customers/{customer} | Update subscriber
[**resellerCustomersCustomerPaymentsPost**](WriteSanctumOnlyApi.md#resellercustomerscustomerpaymentspost) | **POST** /reseller/customers/{customer}/payments | Collect payment
[**resellerCustomersCustomerReconnectPost**](WriteSanctumOnlyApi.md#resellercustomerscustomerreconnectpost) | **POST** /reseller/customers/{customer}/reconnect | Reconnect subscriber
[**resellerCustomersCustomerRenewPost**](WriteSanctumOnlyApi.md#resellercustomerscustomerrenewpost) | **POST** /reseller/customers/{customer}/renew | Renew billing cycle
[**resellerCustomersCustomerSuspendPost**](WriteSanctumOnlyApi.md#resellercustomerscustomersuspendpost) | **POST** /reseller/customers/{customer}/suspend | Suspend subscriber
[**resellerCustomersPost**](WriteSanctumOnlyApi.md#resellercustomerspost) | **POST** /reseller/customers | Create subscriber
[**resellerInternalTicketsPost**](WriteSanctumOnlyApi.md#resellerinternalticketspost) | **POST** /reseller/internal-tickets | Open internal ticket
[**resellerSettlementsPost**](WriteSanctumOnlyApi.md#resellersettlementspost) | **POST** /reseller/settlements | Submit settlement request
[**resellerStaffGet**](WriteSanctumOnlyApi.md#resellerstaffget) | **GET** /reseller/staff | List staff accounts
[**resellerStaffPermissionOptionsGet**](WriteSanctumOnlyApi.md#resellerstaffpermissionoptionsget) | **GET** /reseller/staff/permission-options | Staff permission labels (assignable subset)
[**resellerStaffPost**](WriteSanctumOnlyApi.md#resellerstaffpost) | **POST** /reseller/staff | Create staff account
[**resellerStaffStaffMemberDelete**](WriteSanctumOnlyApi.md#resellerstaffstaffmemberdelete) | **DELETE** /reseller/staff/{staffMember} | Deactivate staff account
[**resellerStaffStaffMemberGet**](WriteSanctumOnlyApi.md#resellerstaffstaffmemberget) | **GET** /reseller/staff/{staffMember} | Staff detail
[**resellerStaffStaffMemberPatch**](WriteSanctumOnlyApi.md#resellerstaffstaffmemberpatch) | **PATCH** /reseller/staff/{staffMember} | Update staff account
[**resellerSubResellersPost**](WriteSanctumOnlyApi.md#resellersubresellerspost) | **POST** /reseller/sub-resellers | Create sub-partner
[**resellerTicketsPost**](WriteSanctumOnlyApi.md#resellerticketspost) | **POST** /reseller/tickets | Open ticket
[**resellerWalletRechargePiprapayPost**](WriteSanctumOnlyApi.md#resellerwalletrechargepiprapaypost) | **POST** /reseller/wallet/recharge/piprapay | Start PipraPay wallet checkout
[**resellerWalletRechargePost**](WriteSanctumOnlyApi.md#resellerwalletrechargepost) | **POST** /reseller/wallet/recharge | Submit manual wallet top-up (admin approval)


# **resellerCustomersCustomerInvoicePost**
> resellerCustomersCustomerInvoicePost(customer)

Generate invoice for subscriber

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

final api_instance = WriteSanctumOnlyApi();
final customer = 56; // int | 

try {
    api_instance.resellerCustomersCustomerInvoicePost(customer);
} catch (e) {
    print('Exception when calling WriteSanctumOnlyApi->resellerCustomersCustomerInvoicePost: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **customer** | **int**|  | 

### Return type

void (empty response body)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerCustomersCustomerPatch**
> ResellerCustomersCustomerPatch200Response resellerCustomersCustomerPatch(customer, customerUpdateRequest)

Update subscriber

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

final api_instance = WriteSanctumOnlyApi();
final customer = 56; // int | 
final customerUpdateRequest = CustomerUpdateRequest(); // CustomerUpdateRequest | 

try {
    final result = api_instance.resellerCustomersCustomerPatch(customer, customerUpdateRequest);
    print(result);
} catch (e) {
    print('Exception when calling WriteSanctumOnlyApi->resellerCustomersCustomerPatch: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **customer** | **int**|  | 
 **customerUpdateRequest** | [**CustomerUpdateRequest**](CustomerUpdateRequest.md)|  | 

### Return type

[**ResellerCustomersCustomerPatch200Response**](ResellerCustomersCustomerPatch200Response.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerCustomersCustomerPaymentsPost**
> PaymentCollectResponse resellerCustomersCustomerPaymentsPost(customer, paymentCollectRequest)

Collect payment

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

final api_instance = WriteSanctumOnlyApi();
final customer = 56; // int | 
final paymentCollectRequest = PaymentCollectRequest(); // PaymentCollectRequest | 

try {
    final result = api_instance.resellerCustomersCustomerPaymentsPost(customer, paymentCollectRequest);
    print(result);
} catch (e) {
    print('Exception when calling WriteSanctumOnlyApi->resellerCustomersCustomerPaymentsPost: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **customer** | **int**|  | 
 **paymentCollectRequest** | [**PaymentCollectRequest**](PaymentCollectRequest.md)|  | 

### Return type

[**PaymentCollectResponse**](PaymentCollectResponse.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerCustomersCustomerReconnectPost**
> resellerCustomersCustomerReconnectPost(customer)

Reconnect subscriber

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

final api_instance = WriteSanctumOnlyApi();
final customer = 56; // int | 

try {
    api_instance.resellerCustomersCustomerReconnectPost(customer);
} catch (e) {
    print('Exception when calling WriteSanctumOnlyApi->resellerCustomersCustomerReconnectPost: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **customer** | **int**|  | 

### Return type

void (empty response body)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerCustomersCustomerRenewPost**
> resellerCustomersCustomerRenewPost(customer)

Renew billing cycle

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

final api_instance = WriteSanctumOnlyApi();
final customer = 56; // int | 

try {
    api_instance.resellerCustomersCustomerRenewPost(customer);
} catch (e) {
    print('Exception when calling WriteSanctumOnlyApi->resellerCustomersCustomerRenewPost: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **customer** | **int**|  | 

### Return type

void (empty response body)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerCustomersCustomerSuspendPost**
> resellerCustomersCustomerSuspendPost(customer)

Suspend subscriber

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

final api_instance = WriteSanctumOnlyApi();
final customer = 56; // int | 

try {
    api_instance.resellerCustomersCustomerSuspendPost(customer);
} catch (e) {
    print('Exception when calling WriteSanctumOnlyApi->resellerCustomersCustomerSuspendPost: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **customer** | **int**|  | 

### Return type

void (empty response body)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerCustomersPost**
> Map<String, Object> resellerCustomersPost(customerCreateRequest)

Create subscriber

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

final api_instance = WriteSanctumOnlyApi();
final customerCreateRequest = CustomerCreateRequest(); // CustomerCreateRequest | 

try {
    final result = api_instance.resellerCustomersPost(customerCreateRequest);
    print(result);
} catch (e) {
    print('Exception when calling WriteSanctumOnlyApi->resellerCustomersPost: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **customerCreateRequest** | [**CustomerCreateRequest**](CustomerCreateRequest.md)|  | 

### Return type

[**Map<String, Object>**](Object.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerInternalTicketsPost**
> resellerInternalTicketsPost(resellerInternalTicketsPostRequest)

Open internal ticket

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

final api_instance = WriteSanctumOnlyApi();
final resellerInternalTicketsPostRequest = ResellerInternalTicketsPostRequest(); // ResellerInternalTicketsPostRequest | 

try {
    api_instance.resellerInternalTicketsPost(resellerInternalTicketsPostRequest);
} catch (e) {
    print('Exception when calling WriteSanctumOnlyApi->resellerInternalTicketsPost: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **resellerInternalTicketsPostRequest** | [**ResellerInternalTicketsPostRequest**](ResellerInternalTicketsPostRequest.md)|  | 

### Return type

void (empty response body)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerSettlementsPost**
> SettlementCreateResponse resellerSettlementsPost(settlementCreateRequest)

Submit settlement request

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

final api_instance = WriteSanctumOnlyApi();
final settlementCreateRequest = SettlementCreateRequest(); // SettlementCreateRequest | 

try {
    final result = api_instance.resellerSettlementsPost(settlementCreateRequest);
    print(result);
} catch (e) {
    print('Exception when calling WriteSanctumOnlyApi->resellerSettlementsPost: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **settlementCreateRequest** | [**SettlementCreateRequest**](SettlementCreateRequest.md)|  | 

### Return type

[**SettlementCreateResponse**](SettlementCreateResponse.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerStaffGet**
> ResellerStaffGet200Response resellerStaffGet()

List staff accounts

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

final api_instance = WriteSanctumOnlyApi();

try {
    final result = api_instance.resellerStaffGet();
    print(result);
} catch (e) {
    print('Exception when calling WriteSanctumOnlyApi->resellerStaffGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**ResellerStaffGet200Response**](ResellerStaffGet200Response.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerStaffPermissionOptionsGet**
> ResellerStaffPermissionOptionsGet200Response resellerStaffPermissionOptionsGet()

Staff permission labels (assignable subset)

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

final api_instance = WriteSanctumOnlyApi();

try {
    final result = api_instance.resellerStaffPermissionOptionsGet();
    print(result);
} catch (e) {
    print('Exception when calling WriteSanctumOnlyApi->resellerStaffPermissionOptionsGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**ResellerStaffPermissionOptionsGet200Response**](ResellerStaffPermissionOptionsGet200Response.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerStaffPost**
> StaffMutationResponse resellerStaffPost(staffCreateRequest)

Create staff account

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

final api_instance = WriteSanctumOnlyApi();
final staffCreateRequest = StaffCreateRequest(); // StaffCreateRequest | 

try {
    final result = api_instance.resellerStaffPost(staffCreateRequest);
    print(result);
} catch (e) {
    print('Exception when calling WriteSanctumOnlyApi->resellerStaffPost: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **staffCreateRequest** | [**StaffCreateRequest**](StaffCreateRequest.md)|  | 

### Return type

[**StaffMutationResponse**](StaffMutationResponse.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerStaffStaffMemberDelete**
> ResellerLogoutPost200Response resellerStaffStaffMemberDelete(staffMember)

Deactivate staff account

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

final api_instance = WriteSanctumOnlyApi();
final staffMember = 56; // int | 

try {
    final result = api_instance.resellerStaffStaffMemberDelete(staffMember);
    print(result);
} catch (e) {
    print('Exception when calling WriteSanctumOnlyApi->resellerStaffStaffMemberDelete: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **staffMember** | **int**|  | 

### Return type

[**ResellerLogoutPost200Response**](ResellerLogoutPost200Response.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerStaffStaffMemberGet**
> ResellerStaffStaffMemberGet200Response resellerStaffStaffMemberGet(staffMember)

Staff detail

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

final api_instance = WriteSanctumOnlyApi();
final staffMember = 56; // int | 

try {
    final result = api_instance.resellerStaffStaffMemberGet(staffMember);
    print(result);
} catch (e) {
    print('Exception when calling WriteSanctumOnlyApi->resellerStaffStaffMemberGet: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **staffMember** | **int**|  | 

### Return type

[**ResellerStaffStaffMemberGet200Response**](ResellerStaffStaffMemberGet200Response.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerStaffStaffMemberPatch**
> StaffMutationResponse resellerStaffStaffMemberPatch(staffMember, staffUpdateRequest)

Update staff account

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

final api_instance = WriteSanctumOnlyApi();
final staffMember = 56; // int | 
final staffUpdateRequest = StaffUpdateRequest(); // StaffUpdateRequest | 

try {
    final result = api_instance.resellerStaffStaffMemberPatch(staffMember, staffUpdateRequest);
    print(result);
} catch (e) {
    print('Exception when calling WriteSanctumOnlyApi->resellerStaffStaffMemberPatch: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **staffMember** | **int**|  | 
 **staffUpdateRequest** | [**StaffUpdateRequest**](StaffUpdateRequest.md)|  | 

### Return type

[**StaffMutationResponse**](StaffMutationResponse.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerSubResellersPost**
> resellerSubResellersPost()

Create sub-partner

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

final api_instance = WriteSanctumOnlyApi();

try {
    api_instance.resellerSubResellersPost();
} catch (e) {
    print('Exception when calling WriteSanctumOnlyApi->resellerSubResellersPost: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

void (empty response body)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerTicketsPost**
> resellerTicketsPost()

Open ticket

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

final api_instance = WriteSanctumOnlyApi();

try {
    api_instance.resellerTicketsPost();
} catch (e) {
    print('Exception when calling WriteSanctumOnlyApi->resellerTicketsPost: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

void (empty response body)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerWalletRechargePiprapayPost**
> WalletPipraPayResponse resellerWalletRechargePiprapayPost(walletPipraPayRequest)

Start PipraPay wallet checkout

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

final api_instance = WriteSanctumOnlyApi();
final walletPipraPayRequest = WalletPipraPayRequest(); // WalletPipraPayRequest | 

try {
    final result = api_instance.resellerWalletRechargePiprapayPost(walletPipraPayRequest);
    print(result);
} catch (e) {
    print('Exception when calling WriteSanctumOnlyApi->resellerWalletRechargePiprapayPost: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **walletPipraPayRequest** | [**WalletPipraPayRequest**](WalletPipraPayRequest.md)|  | 

### Return type

[**WalletPipraPayResponse**](WalletPipraPayResponse.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerWalletRechargePost**
> WalletRechargeResponse resellerWalletRechargePost(walletRechargeRequest)

Submit manual wallet top-up (admin approval)

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

final api_instance = WriteSanctumOnlyApi();
final walletRechargeRequest = WalletRechargeRequest(); // WalletRechargeRequest | 

try {
    final result = api_instance.resellerWalletRechargePost(walletRechargeRequest);
    print(result);
} catch (e) {
    print('Exception when calling WriteSanctumOnlyApi->resellerWalletRechargePost: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **walletRechargeRequest** | [**WalletRechargeRequest**](WalletRechargeRequest.md)|  | 

### Return type

[**WalletRechargeResponse**](WalletRechargeResponse.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

