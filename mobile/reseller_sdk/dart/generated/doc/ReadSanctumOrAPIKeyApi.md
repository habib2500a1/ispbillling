# isp_reseller_api.api.ReadSanctumOrAPIKeyApi

## Load the API package
```dart
import 'package:isp_reseller_api/api.dart';
```

All URIs are relative to *https://your-domain.example/api/v1*

Method | HTTP request | Description
------------- | ------------- | -------------
[**resellerActivityGet**](ReadSanctumOrAPIKeyApi.md#reselleractivityget) | **GET** /reseller/activity | Portal activity log
[**resellerAnnouncementsGet**](ReadSanctumOrAPIKeyApi.md#resellerannouncementsget) | **GET** /reseller/announcements | HQ announcements
[**resellerCommissionsGet**](ReadSanctumOrAPIKeyApi.md#resellercommissionsget) | **GET** /reseller/commissions | Commission ledger
[**resellerCustomerTransfersGet**](ReadSanctumOrAPIKeyApi.md#resellercustomertransfersget) | **GET** /reseller/customer-transfers | Transfer history
[**resellerCustomersCustomerGet**](ReadSanctumOrAPIKeyApi.md#resellercustomerscustomerget) | **GET** /reseller/customers/{customer} | Subscriber detail
[**resellerCustomersGet**](ReadSanctumOrAPIKeyApi.md#resellercustomersget) | **GET** /reseller/customers | List subscribers
[**resellerDashboardGet**](ReadSanctumOrAPIKeyApi.md#resellerdashboardget) | **GET** /reseller/dashboard | Dashboard metrics
[**resellerDueAccountGet**](ReadSanctumOrAPIKeyApi.md#resellerdueaccountget) | **GET** /reseller/due-account | Reseller billing / due account
[**resellerInternalTicketsGet**](ReadSanctumOrAPIKeyApi.md#resellerinternalticketsget) | **GET** /reseller/internal-tickets | Internal HQ tickets
[**resellerInvoicesGet**](ReadSanctumOrAPIKeyApi.md#resellerinvoicesget) | **GET** /reseller/invoices | Invoice list
[**resellerMeGet**](ReadSanctumOrAPIKeyApi.md#resellermeget) | **GET** /reseller/me | Current actor and permissions
[**resellerNetworkGet**](ReadSanctumOrAPIKeyApi.md#resellernetworkget) | **GET** /reseller/network | Network / online subscribers
[**resellerNotificationsGet**](ReadSanctumOrAPIKeyApi.md#resellernotificationsget) | **GET** /reseller/notifications | Notifications
[**resellerOnuGet**](ReadSanctumOrAPIKeyApi.md#reselleronuget) | **GET** /reseller/onu | ONU / GPON status list
[**resellerPartnerPathGet**](ReadSanctumOrAPIKeyApi.md#resellerpartnerpathget) | **GET** /reseller/partner/{path} | Legacy alias (same as /reseller/{path})
[**resellerReportsEnterpriseGet**](ReadSanctumOrAPIKeyApi.md#resellerreportsenterpriseget) | **GET** /reseller/reports/enterprise | Enterprise report pack
[**resellerReportsSummaryGet**](ReadSanctumOrAPIKeyApi.md#resellerreportssummaryget) | **GET** /reseller/reports/summary | Reports summary
[**resellerSettlementsGet**](ReadSanctumOrAPIKeyApi.md#resellersettlementsget) | **GET** /reseller/settlements | Settlement requests
[**resellerSubResellersChildGet**](ReadSanctumOrAPIKeyApi.md#resellersubresellerschildget) | **GET** /reseller/sub-resellers/{child} | Sub-partner detail
[**resellerSubResellersGet**](ReadSanctumOrAPIKeyApi.md#resellersubresellersget) | **GET** /reseller/sub-resellers | Sub-partners
[**resellerTicketsGet**](ReadSanctumOrAPIKeyApi.md#resellerticketsget) | **GET** /reseller/tickets | Support tickets
[**resellerWalletGet**](ReadSanctumOrAPIKeyApi.md#resellerwalletget) | **GET** /reseller/wallet | Wallet statement
[**resellerWalletOverviewGet**](ReadSanctumOrAPIKeyApi.md#resellerwalletoverviewget) | **GET** /reseller/wallet/overview | Wallet overview (quota, frozen, recent tx)


# **resellerActivityGet**
> Map<String, Object> resellerActivityGet()

Portal activity log

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

final api_instance = ReadSanctumOrAPIKeyApi();

try {
    final result = api_instance.resellerActivityGet();
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerActivityGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**Map<String, Object>**](Object.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerAnnouncementsGet**
> ResellerAnnouncementsGet200Response resellerAnnouncementsGet()

HQ announcements

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

final api_instance = ReadSanctumOrAPIKeyApi();

try {
    final result = api_instance.resellerAnnouncementsGet();
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerAnnouncementsGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**ResellerAnnouncementsGet200Response**](ResellerAnnouncementsGet200Response.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerCommissionsGet**
> Map<String, Object> resellerCommissionsGet()

Commission ledger

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

final api_instance = ReadSanctumOrAPIKeyApi();

try {
    final result = api_instance.resellerCommissionsGet();
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerCommissionsGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**Map<String, Object>**](Object.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerCustomerTransfersGet**
> Map<String, Object> resellerCustomerTransfersGet()

Transfer history

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

final api_instance = ReadSanctumOrAPIKeyApi();

try {
    final result = api_instance.resellerCustomerTransfersGet();
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerCustomerTransfersGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**Map<String, Object>**](Object.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerCustomersCustomerGet**
> Customer resellerCustomersCustomerGet(customer)

Subscriber detail

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

final api_instance = ReadSanctumOrAPIKeyApi();
final customer = 56; // int | 

try {
    final result = api_instance.resellerCustomersCustomerGet(customer);
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerCustomersCustomerGet: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **customer** | **int**|  | 

### Return type

[**Customer**](Customer.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerCustomersGet**
> PaginatedCustomers resellerCustomersGet(q, perPage)

List subscribers

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

final api_instance = ReadSanctumOrAPIKeyApi();
final q = q_example; // String | Search name, customer code, or phone
final perPage = 56; // int | 

try {
    final result = api_instance.resellerCustomersGet(q, perPage);
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerCustomersGet: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **q** | **String**| Search name, customer code, or phone | [optional] 
 **perPage** | **int**|  | [optional] [default to 20]

### Return type

[**PaginatedCustomers**](PaginatedCustomers.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerDashboardGet**
> Map<String, Object> resellerDashboardGet()

Dashboard metrics

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

final api_instance = ReadSanctumOrAPIKeyApi();

try {
    final result = api_instance.resellerDashboardGet();
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerDashboardGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**Map<String, Object>**](Object.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerDueAccountGet**
> ResellerDueAccountGet200Response resellerDueAccountGet()

Reseller billing / due account

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

final api_instance = ReadSanctumOrAPIKeyApi();

try {
    final result = api_instance.resellerDueAccountGet();
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerDueAccountGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**ResellerDueAccountGet200Response**](ResellerDueAccountGet200Response.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerInternalTicketsGet**
> ResellerInternalTicketsGet200Response resellerInternalTicketsGet()

Internal HQ tickets

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

final api_instance = ReadSanctumOrAPIKeyApi();

try {
    final result = api_instance.resellerInternalTicketsGet();
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerInternalTicketsGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**ResellerInternalTicketsGet200Response**](ResellerInternalTicketsGet200Response.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerInvoicesGet**
> Map<String, Object> resellerInvoicesGet()

Invoice list

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

final api_instance = ReadSanctumOrAPIKeyApi();

try {
    final result = api_instance.resellerInvoicesGet();
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerInvoicesGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**Map<String, Object>**](Object.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerMeGet**
> MeResponse resellerMeGet()

Current actor and permissions

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

final api_instance = ReadSanctumOrAPIKeyApi();

try {
    final result = api_instance.resellerMeGet();
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerMeGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**MeResponse**](MeResponse.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerNetworkGet**
> Map<String, Object> resellerNetworkGet()

Network / online subscribers

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

final api_instance = ReadSanctumOrAPIKeyApi();

try {
    final result = api_instance.resellerNetworkGet();
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerNetworkGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**Map<String, Object>**](Object.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerNotificationsGet**
> Map<String, Object> resellerNotificationsGet()

Notifications

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

final api_instance = ReadSanctumOrAPIKeyApi();

try {
    final result = api_instance.resellerNotificationsGet();
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerNotificationsGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**Map<String, Object>**](Object.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerOnuGet**
> Map<String, Object> resellerOnuGet()

ONU / GPON status list

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

final api_instance = ReadSanctumOrAPIKeyApi();

try {
    final result = api_instance.resellerOnuGet();
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerOnuGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**Map<String, Object>**](Object.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerPartnerPathGet**
> resellerPartnerPathGet(path)

Legacy alias (same as /reseller/{path})

Deprecated prefix. Prefer `/reseller/dashboard`, `/reseller/customers`, etc. Supported paths mirror shared read routes (dashboard, wallet, customers, …). 

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

final api_instance = ReadSanctumOrAPIKeyApi();
final path = dashboard; // String | 

try {
    api_instance.resellerPartnerPathGet(path);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerPartnerPathGet: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **path** | **String**|  | 

### Return type

void (empty response body)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: Not defined

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerReportsEnterpriseGet**
> Map<String, Object> resellerReportsEnterpriseGet()

Enterprise report pack

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

final api_instance = ReadSanctumOrAPIKeyApi();

try {
    final result = api_instance.resellerReportsEnterpriseGet();
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerReportsEnterpriseGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**Map<String, Object>**](Object.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerReportsSummaryGet**
> Map<String, Object> resellerReportsSummaryGet()

Reports summary

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

final api_instance = ReadSanctumOrAPIKeyApi();

try {
    final result = api_instance.resellerReportsSummaryGet();
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerReportsSummaryGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**Map<String, Object>**](Object.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerSettlementsGet**
> SettlementListResponse resellerSettlementsGet()

Settlement requests

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

final api_instance = ReadSanctumOrAPIKeyApi();

try {
    final result = api_instance.resellerSettlementsGet();
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerSettlementsGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**SettlementListResponse**](SettlementListResponse.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerSubResellersChildGet**
> Map<String, Object> resellerSubResellersChildGet(child)

Sub-partner detail

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

final api_instance = ReadSanctumOrAPIKeyApi();
final child = 56; // int | 

try {
    final result = api_instance.resellerSubResellersChildGet(child);
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerSubResellersChildGet: $e\n');
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **child** | **int**|  | 

### Return type

[**Map<String, Object>**](Object.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerSubResellersGet**
> ResellerSubResellersGet200Response resellerSubResellersGet()

Sub-partners

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

final api_instance = ReadSanctumOrAPIKeyApi();

try {
    final result = api_instance.resellerSubResellersGet();
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerSubResellersGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**ResellerSubResellersGet200Response**](ResellerSubResellersGet200Response.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerTicketsGet**
> Map<String, Object> resellerTicketsGet()

Support tickets

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

final api_instance = ReadSanctumOrAPIKeyApi();

try {
    final result = api_instance.resellerTicketsGet();
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerTicketsGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**Map<String, Object>**](Object.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerWalletGet**
> Map<String, Object> resellerWalletGet()

Wallet statement

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

final api_instance = ReadSanctumOrAPIKeyApi();

try {
    final result = api_instance.resellerWalletGet();
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerWalletGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**Map<String, Object>**](Object.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

# **resellerWalletOverviewGet**
> Map<String, Object> resellerWalletOverviewGet()

Wallet overview (quota, frozen, recent tx)

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

final api_instance = ReadSanctumOrAPIKeyApi();

try {
    final result = api_instance.resellerWalletOverviewGet();
    print(result);
} catch (e) {
    print('Exception when calling ReadSanctumOrAPIKeyApi->resellerWalletOverviewGet: $e\n');
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**Map<String, Object>**](Object.md)

### Authorization

[apiKeyBearer](../README.md#apiKeyBearer), [sanctumBearer](../README.md#sanctumBearer), [apiKeyHeader](../README.md#apiKeyHeader)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

[[Back to top]](#) [[Back to API list]](../README.md#documentation-for-api-endpoints) [[Back to Model list]](../README.md#documentation-for-models) [[Back to README]](../README.md)

