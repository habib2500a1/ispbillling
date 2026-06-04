# ReadSanctumOrAPIKeyApi

All URIs are relative to *https://your-domain.example/api/v1*

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**resellerActivityGet**](ReadSanctumOrAPIKeyApi.md#resellerActivityGet) | **GET** reseller/activity | Portal activity log |
| [**resellerAnnouncementsGet**](ReadSanctumOrAPIKeyApi.md#resellerAnnouncementsGet) | **GET** reseller/announcements | HQ announcements |
| [**resellerCommissionsGet**](ReadSanctumOrAPIKeyApi.md#resellerCommissionsGet) | **GET** reseller/commissions | Commission ledger |
| [**resellerCustomerTransfersGet**](ReadSanctumOrAPIKeyApi.md#resellerCustomerTransfersGet) | **GET** reseller/customer-transfers | Transfer history |
| [**resellerCustomersCustomerGet**](ReadSanctumOrAPIKeyApi.md#resellerCustomersCustomerGet) | **GET** reseller/customers/{customer} | Subscriber detail |
| [**resellerCustomersGet**](ReadSanctumOrAPIKeyApi.md#resellerCustomersGet) | **GET** reseller/customers | List subscribers |
| [**resellerDashboardGet**](ReadSanctumOrAPIKeyApi.md#resellerDashboardGet) | **GET** reseller/dashboard | Dashboard metrics |
| [**resellerDueAccountGet**](ReadSanctumOrAPIKeyApi.md#resellerDueAccountGet) | **GET** reseller/due-account | Reseller billing / due account |
| [**resellerInternalTicketsGet**](ReadSanctumOrAPIKeyApi.md#resellerInternalTicketsGet) | **GET** reseller/internal-tickets | Internal HQ tickets |
| [**resellerInvoicesGet**](ReadSanctumOrAPIKeyApi.md#resellerInvoicesGet) | **GET** reseller/invoices | Invoice list |
| [**resellerMeGet**](ReadSanctumOrAPIKeyApi.md#resellerMeGet) | **GET** reseller/me | Current actor and permissions |
| [**resellerNetworkGet**](ReadSanctumOrAPIKeyApi.md#resellerNetworkGet) | **GET** reseller/network | Network / online subscribers |
| [**resellerNotificationsGet**](ReadSanctumOrAPIKeyApi.md#resellerNotificationsGet) | **GET** reseller/notifications | Notifications |
| [**resellerOnuGet**](ReadSanctumOrAPIKeyApi.md#resellerOnuGet) | **GET** reseller/onu | ONU / GPON status list |
| [**resellerPartnerPathGet**](ReadSanctumOrAPIKeyApi.md#resellerPartnerPathGet) | **GET** reseller/partner/{path} | Legacy alias (same as /reseller/{path}) |
| [**resellerReportsEnterpriseGet**](ReadSanctumOrAPIKeyApi.md#resellerReportsEnterpriseGet) | **GET** reseller/reports/enterprise | Enterprise report pack |
| [**resellerReportsSummaryGet**](ReadSanctumOrAPIKeyApi.md#resellerReportsSummaryGet) | **GET** reseller/reports/summary | Reports summary |
| [**resellerSettlementsGet**](ReadSanctumOrAPIKeyApi.md#resellerSettlementsGet) | **GET** reseller/settlements | Settlement requests |
| [**resellerSubResellersChildGet**](ReadSanctumOrAPIKeyApi.md#resellerSubResellersChildGet) | **GET** reseller/sub-resellers/{child} | Sub-partner detail |
| [**resellerSubResellersGet**](ReadSanctumOrAPIKeyApi.md#resellerSubResellersGet) | **GET** reseller/sub-resellers | Sub-partners |
| [**resellerTicketsGet**](ReadSanctumOrAPIKeyApi.md#resellerTicketsGet) | **GET** reseller/tickets | Support tickets |
| [**resellerWalletGet**](ReadSanctumOrAPIKeyApi.md#resellerWalletGet) | **GET** reseller/wallet | Wallet statement |
| [**resellerWalletOverviewGet**](ReadSanctumOrAPIKeyApi.md#resellerWalletOverviewGet) | **GET** reseller/wallet/overview | Wallet overview (quota, frozen, recent tx) |



Portal activity log

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)

launch(Dispatchers.IO) {
    val result : kotlin.collections.Map<kotlin.String, kotlin.Any> = webService.resellerActivityGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**kotlin.collections.Map&lt;kotlin.String, kotlin.Any&gt;**](kotlin.Any.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


HQ announcements

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)

launch(Dispatchers.IO) {
    val result : ResellerAnnouncementsGet200Response = webService.resellerAnnouncementsGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**ResellerAnnouncementsGet200Response**](ResellerAnnouncementsGet200Response.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Commission ledger

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)

launch(Dispatchers.IO) {
    val result : kotlin.collections.Map<kotlin.String, kotlin.Any> = webService.resellerCommissionsGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**kotlin.collections.Map&lt;kotlin.String, kotlin.Any&gt;**](kotlin.Any.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Transfer history

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)

launch(Dispatchers.IO) {
    val result : kotlin.collections.Map<kotlin.String, kotlin.Any> = webService.resellerCustomerTransfersGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**kotlin.collections.Map&lt;kotlin.String, kotlin.Any&gt;**](kotlin.Any.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Subscriber detail

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)
val customer : kotlin.Int = 56 // kotlin.Int | 

launch(Dispatchers.IO) {
    val result : Customer = webService.resellerCustomersCustomerGet(customer)
}
```

### Parameters
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **customer** | **kotlin.Int**|  | |

### Return type

[**Customer**](Customer.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


List subscribers

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)
val q : kotlin.String = q_example // kotlin.String | Search name, customer code, or phone
val perPage : kotlin.Int = 56 // kotlin.Int | 

launch(Dispatchers.IO) {
    val result : PaginatedCustomers = webService.resellerCustomersGet(q, perPage)
}
```

### Parameters
| **q** | **kotlin.String**| Search name, customer code, or phone | [optional] |
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **perPage** | **kotlin.Int**|  | [optional] [default to 20] |

### Return type

[**PaginatedCustomers**](PaginatedCustomers.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Dashboard metrics

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)

launch(Dispatchers.IO) {
    val result : kotlin.collections.Map<kotlin.String, kotlin.Any> = webService.resellerDashboardGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**kotlin.collections.Map&lt;kotlin.String, kotlin.Any&gt;**](kotlin.Any.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Reseller billing / due account

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)

launch(Dispatchers.IO) {
    val result : ResellerDueAccountGet200Response = webService.resellerDueAccountGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**ResellerDueAccountGet200Response**](ResellerDueAccountGet200Response.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Internal HQ tickets

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)

launch(Dispatchers.IO) {
    val result : ResellerInternalTicketsGet200Response = webService.resellerInternalTicketsGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**ResellerInternalTicketsGet200Response**](ResellerInternalTicketsGet200Response.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Invoice list

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)

launch(Dispatchers.IO) {
    val result : kotlin.collections.Map<kotlin.String, kotlin.Any> = webService.resellerInvoicesGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**kotlin.collections.Map&lt;kotlin.String, kotlin.Any&gt;**](kotlin.Any.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Current actor and permissions

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)

launch(Dispatchers.IO) {
    val result : MeResponse = webService.resellerMeGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**MeResponse**](MeResponse.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Network / online subscribers

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)

launch(Dispatchers.IO) {
    val result : kotlin.collections.Map<kotlin.String, kotlin.Any> = webService.resellerNetworkGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**kotlin.collections.Map&lt;kotlin.String, kotlin.Any&gt;**](kotlin.Any.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Notifications

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)

launch(Dispatchers.IO) {
    val result : kotlin.collections.Map<kotlin.String, kotlin.Any> = webService.resellerNotificationsGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**kotlin.collections.Map&lt;kotlin.String, kotlin.Any&gt;**](kotlin.Any.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


ONU / GPON status list

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)

launch(Dispatchers.IO) {
    val result : kotlin.collections.Map<kotlin.String, kotlin.Any> = webService.resellerOnuGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**kotlin.collections.Map&lt;kotlin.String, kotlin.Any&gt;**](kotlin.Any.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Legacy alias (same as /reseller/{path})

Deprecated prefix. Prefer &#x60;/reseller/dashboard&#x60;, &#x60;/reseller/customers&#x60;, etc. Supported paths mirror shared read routes (dashboard, wallet, customers, …). 

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)
val path : kotlin.String = dashboard // kotlin.String | 

launch(Dispatchers.IO) {
    webService.resellerPartnerPathGet(path)
}
```

### Parameters
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **path** | **kotlin.String**|  | |

### Return type

null (empty response body)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: Not defined


Enterprise report pack

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)

launch(Dispatchers.IO) {
    val result : kotlin.collections.Map<kotlin.String, kotlin.Any> = webService.resellerReportsEnterpriseGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**kotlin.collections.Map&lt;kotlin.String, kotlin.Any&gt;**](kotlin.Any.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Reports summary

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)

launch(Dispatchers.IO) {
    val result : kotlin.collections.Map<kotlin.String, kotlin.Any> = webService.resellerReportsSummaryGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**kotlin.collections.Map&lt;kotlin.String, kotlin.Any&gt;**](kotlin.Any.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Settlement requests

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)

launch(Dispatchers.IO) {
    val result : SettlementListResponse = webService.resellerSettlementsGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**SettlementListResponse**](SettlementListResponse.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Sub-partner detail

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)
val child : kotlin.Int = 56 // kotlin.Int | 

launch(Dispatchers.IO) {
    val result : kotlin.collections.Map<kotlin.String, kotlin.Any> = webService.resellerSubResellersChildGet(child)
}
```

### Parameters
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **child** | **kotlin.Int**|  | |

### Return type

[**kotlin.collections.Map&lt;kotlin.String, kotlin.Any&gt;**](kotlin.Any.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Sub-partners

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)

launch(Dispatchers.IO) {
    val result : ResellerSubResellersGet200Response = webService.resellerSubResellersGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**ResellerSubResellersGet200Response**](ResellerSubResellersGet200Response.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Support tickets

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)

launch(Dispatchers.IO) {
    val result : kotlin.collections.Map<kotlin.String, kotlin.Any> = webService.resellerTicketsGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**kotlin.collections.Map&lt;kotlin.String, kotlin.Any&gt;**](kotlin.Any.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Wallet statement

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)

launch(Dispatchers.IO) {
    val result : kotlin.collections.Map<kotlin.String, kotlin.Any> = webService.resellerWalletGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**kotlin.collections.Map&lt;kotlin.String, kotlin.Any&gt;**](kotlin.Any.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Wallet overview (quota, frozen, recent tx)

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(ReadSanctumOrAPIKeyApi::class.java)

launch(Dispatchers.IO) {
    val result : kotlin.collections.Map<kotlin.String, kotlin.Any> = webService.resellerWalletOverviewGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**kotlin.collections.Map&lt;kotlin.String, kotlin.Any&gt;**](kotlin.Any.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

