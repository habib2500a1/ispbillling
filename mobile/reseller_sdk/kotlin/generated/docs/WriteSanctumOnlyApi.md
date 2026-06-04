# WriteSanctumOnlyApi

All URIs are relative to *https://your-domain.example/api/v1*

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**resellerCustomersCustomerInvoicePost**](WriteSanctumOnlyApi.md#resellerCustomersCustomerInvoicePost) | **POST** reseller/customers/{customer}/invoice | Generate invoice for subscriber |
| [**resellerCustomersCustomerPatch**](WriteSanctumOnlyApi.md#resellerCustomersCustomerPatch) | **PATCH** reseller/customers/{customer} | Update subscriber |
| [**resellerCustomersCustomerPaymentsPost**](WriteSanctumOnlyApi.md#resellerCustomersCustomerPaymentsPost) | **POST** reseller/customers/{customer}/payments | Collect payment |
| [**resellerCustomersCustomerReconnectPost**](WriteSanctumOnlyApi.md#resellerCustomersCustomerReconnectPost) | **POST** reseller/customers/{customer}/reconnect | Reconnect subscriber |
| [**resellerCustomersCustomerRenewPost**](WriteSanctumOnlyApi.md#resellerCustomersCustomerRenewPost) | **POST** reseller/customers/{customer}/renew | Renew billing cycle |
| [**resellerCustomersCustomerSuspendPost**](WriteSanctumOnlyApi.md#resellerCustomersCustomerSuspendPost) | **POST** reseller/customers/{customer}/suspend | Suspend subscriber |
| [**resellerCustomersPost**](WriteSanctumOnlyApi.md#resellerCustomersPost) | **POST** reseller/customers | Create subscriber |
| [**resellerInternalTicketsPost**](WriteSanctumOnlyApi.md#resellerInternalTicketsPost) | **POST** reseller/internal-tickets | Open internal ticket |
| [**resellerSettlementsPost**](WriteSanctumOnlyApi.md#resellerSettlementsPost) | **POST** reseller/settlements | Submit settlement request |
| [**resellerStaffGet**](WriteSanctumOnlyApi.md#resellerStaffGet) | **GET** reseller/staff | List staff accounts |
| [**resellerStaffPermissionOptionsGet**](WriteSanctumOnlyApi.md#resellerStaffPermissionOptionsGet) | **GET** reseller/staff/permission-options | Staff permission labels (assignable subset) |
| [**resellerStaffPost**](WriteSanctumOnlyApi.md#resellerStaffPost) | **POST** reseller/staff | Create staff account |
| [**resellerStaffStaffMemberDelete**](WriteSanctumOnlyApi.md#resellerStaffStaffMemberDelete) | **DELETE** reseller/staff/{staffMember} | Deactivate staff account |
| [**resellerStaffStaffMemberGet**](WriteSanctumOnlyApi.md#resellerStaffStaffMemberGet) | **GET** reseller/staff/{staffMember} | Staff detail |
| [**resellerStaffStaffMemberPatch**](WriteSanctumOnlyApi.md#resellerStaffStaffMemberPatch) | **PATCH** reseller/staff/{staffMember} | Update staff account |
| [**resellerSubResellersPost**](WriteSanctumOnlyApi.md#resellerSubResellersPost) | **POST** reseller/sub-resellers | Create sub-partner |
| [**resellerTicketsPost**](WriteSanctumOnlyApi.md#resellerTicketsPost) | **POST** reseller/tickets | Open ticket |
| [**resellerWalletRechargePiprapayPost**](WriteSanctumOnlyApi.md#resellerWalletRechargePiprapayPost) | **POST** reseller/wallet/recharge/piprapay | Start PipraPay wallet checkout |
| [**resellerWalletRechargePost**](WriteSanctumOnlyApi.md#resellerWalletRechargePost) | **POST** reseller/wallet/recharge | Submit manual wallet top-up (admin approval) |



Generate invoice for subscriber

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(WriteSanctumOnlyApi::class.java)
val customer : kotlin.Int = 56 // kotlin.Int | 

launch(Dispatchers.IO) {
    webService.resellerCustomersCustomerInvoicePost(customer)
}
```

### Parameters
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **customer** | **kotlin.Int**|  | |

### Return type

null (empty response body)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Update subscriber

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(WriteSanctumOnlyApi::class.java)
val customer : kotlin.Int = 56 // kotlin.Int | 
val customerUpdateRequest : CustomerUpdateRequest =  // CustomerUpdateRequest | 

launch(Dispatchers.IO) {
    val result : ResellerCustomersCustomerPatch200Response = webService.resellerCustomersCustomerPatch(customer, customerUpdateRequest)
}
```

### Parameters
| **customer** | **kotlin.Int**|  | |
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **customerUpdateRequest** | [**CustomerUpdateRequest**](CustomerUpdateRequest.md)|  | |

### Return type

[**ResellerCustomersCustomerPatch200Response**](ResellerCustomersCustomerPatch200Response.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json


Collect payment

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(WriteSanctumOnlyApi::class.java)
val customer : kotlin.Int = 56 // kotlin.Int | 
val paymentCollectRequest : PaymentCollectRequest =  // PaymentCollectRequest | 

launch(Dispatchers.IO) {
    val result : PaymentCollectResponse = webService.resellerCustomersCustomerPaymentsPost(customer, paymentCollectRequest)
}
```

### Parameters
| **customer** | **kotlin.Int**|  | |
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **paymentCollectRequest** | [**PaymentCollectRequest**](PaymentCollectRequest.md)|  | |

### Return type

[**PaymentCollectResponse**](PaymentCollectResponse.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json


Reconnect subscriber

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(WriteSanctumOnlyApi::class.java)
val customer : kotlin.Int = 56 // kotlin.Int | 

launch(Dispatchers.IO) {
    webService.resellerCustomersCustomerReconnectPost(customer)
}
```

### Parameters
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **customer** | **kotlin.Int**|  | |

### Return type

null (empty response body)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Renew billing cycle

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(WriteSanctumOnlyApi::class.java)
val customer : kotlin.Int = 56 // kotlin.Int | 

launch(Dispatchers.IO) {
    webService.resellerCustomersCustomerRenewPost(customer)
}
```

### Parameters
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **customer** | **kotlin.Int**|  | |

### Return type

null (empty response body)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Suspend subscriber

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(WriteSanctumOnlyApi::class.java)
val customer : kotlin.Int = 56 // kotlin.Int | 

launch(Dispatchers.IO) {
    webService.resellerCustomersCustomerSuspendPost(customer)
}
```

### Parameters
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **customer** | **kotlin.Int**|  | |

### Return type

null (empty response body)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Create subscriber

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(WriteSanctumOnlyApi::class.java)
val customerCreateRequest : CustomerCreateRequest =  // CustomerCreateRequest | 

launch(Dispatchers.IO) {
    val result : kotlin.collections.Map<kotlin.String, kotlin.Any> = webService.resellerCustomersPost(customerCreateRequest)
}
```

### Parameters
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **customerCreateRequest** | [**CustomerCreateRequest**](CustomerCreateRequest.md)|  | |

### Return type

[**kotlin.collections.Map&lt;kotlin.String, kotlin.Any&gt;**](kotlin.Any.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json


Open internal ticket

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(WriteSanctumOnlyApi::class.java)
val resellerInternalTicketsPostRequest : ResellerInternalTicketsPostRequest =  // ResellerInternalTicketsPostRequest | 

launch(Dispatchers.IO) {
    webService.resellerInternalTicketsPost(resellerInternalTicketsPostRequest)
}
```

### Parameters
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **resellerInternalTicketsPostRequest** | [**ResellerInternalTicketsPostRequest**](ResellerInternalTicketsPostRequest.md)|  | |

### Return type

null (empty response body)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json


Submit settlement request

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(WriteSanctumOnlyApi::class.java)
val settlementCreateRequest : SettlementCreateRequest =  // SettlementCreateRequest | 

launch(Dispatchers.IO) {
    val result : SettlementCreateResponse = webService.resellerSettlementsPost(settlementCreateRequest)
}
```

### Parameters
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **settlementCreateRequest** | [**SettlementCreateRequest**](SettlementCreateRequest.md)|  | |

### Return type

[**SettlementCreateResponse**](SettlementCreateResponse.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json


List staff accounts

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(WriteSanctumOnlyApi::class.java)

launch(Dispatchers.IO) {
    val result : ResellerStaffGet200Response = webService.resellerStaffGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**ResellerStaffGet200Response**](ResellerStaffGet200Response.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Staff permission labels (assignable subset)

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(WriteSanctumOnlyApi::class.java)

launch(Dispatchers.IO) {
    val result : ResellerStaffPermissionOptionsGet200Response = webService.resellerStaffPermissionOptionsGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**ResellerStaffPermissionOptionsGet200Response**](ResellerStaffPermissionOptionsGet200Response.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Create staff account

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(WriteSanctumOnlyApi::class.java)
val staffCreateRequest : StaffCreateRequest =  // StaffCreateRequest | 

launch(Dispatchers.IO) {
    val result : StaffMutationResponse = webService.resellerStaffPost(staffCreateRequest)
}
```

### Parameters
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **staffCreateRequest** | [**StaffCreateRequest**](StaffCreateRequest.md)|  | |

### Return type

[**StaffMutationResponse**](StaffMutationResponse.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json


Deactivate staff account

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(WriteSanctumOnlyApi::class.java)
val staffMember : kotlin.Int = 56 // kotlin.Int | 

launch(Dispatchers.IO) {
    val result : ResellerLogoutPost200Response = webService.resellerStaffStaffMemberDelete(staffMember)
}
```

### Parameters
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **staffMember** | **kotlin.Int**|  | |

### Return type

[**ResellerLogoutPost200Response**](ResellerLogoutPost200Response.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Staff detail

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(WriteSanctumOnlyApi::class.java)
val staffMember : kotlin.Int = 56 // kotlin.Int | 

launch(Dispatchers.IO) {
    val result : ResellerStaffStaffMemberGet200Response = webService.resellerStaffStaffMemberGet(staffMember)
}
```

### Parameters
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **staffMember** | **kotlin.Int**|  | |

### Return type

[**ResellerStaffStaffMemberGet200Response**](ResellerStaffStaffMemberGet200Response.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Update staff account

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(WriteSanctumOnlyApi::class.java)
val staffMember : kotlin.Int = 56 // kotlin.Int | 
val staffUpdateRequest : StaffUpdateRequest =  // StaffUpdateRequest | 

launch(Dispatchers.IO) {
    val result : StaffMutationResponse = webService.resellerStaffStaffMemberPatch(staffMember, staffUpdateRequest)
}
```

### Parameters
| **staffMember** | **kotlin.Int**|  | |
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **staffUpdateRequest** | [**StaffUpdateRequest**](StaffUpdateRequest.md)|  | |

### Return type

[**StaffMutationResponse**](StaffMutationResponse.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json


Create sub-partner

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(WriteSanctumOnlyApi::class.java)

launch(Dispatchers.IO) {
    webService.resellerSubResellersPost()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

null (empty response body)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Open ticket

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(WriteSanctumOnlyApi::class.java)

launch(Dispatchers.IO) {
    webService.resellerTicketsPost()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

null (empty response body)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Start PipraPay wallet checkout

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(WriteSanctumOnlyApi::class.java)
val walletPipraPayRequest : WalletPipraPayRequest =  // WalletPipraPayRequest | 

launch(Dispatchers.IO) {
    val result : WalletPipraPayResponse = webService.resellerWalletRechargePiprapayPost(walletPipraPayRequest)
}
```

### Parameters
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **walletPipraPayRequest** | [**WalletPipraPayRequest**](WalletPipraPayRequest.md)|  | |

### Return type

[**WalletPipraPayResponse**](WalletPipraPayResponse.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json


Submit manual wallet top-up (admin approval)

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(WriteSanctumOnlyApi::class.java)
val walletRechargeRequest : WalletRechargeRequest =  // WalletRechargeRequest | 

launch(Dispatchers.IO) {
    val result : WalletRechargeResponse = webService.resellerWalletRechargePost(walletRechargeRequest)
}
```

### Parameters
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **walletRechargeRequest** | [**WalletRechargeRequest**](WalletRechargeRequest.md)|  | |

### Return type

[**WalletRechargeResponse**](WalletRechargeResponse.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

