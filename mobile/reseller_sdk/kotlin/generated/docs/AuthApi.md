# AuthApi

All URIs are relative to *https://your-domain.example/api/v1*

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**resellerLoginPost**](AuthApi.md#resellerLoginPost) | **POST** reseller/login | Login (owner or staff) |
| [**resellerLogoutPost**](AuthApi.md#resellerLogoutPost) | **POST** reseller/logout | Revoke current Sanctum token |



Login (owner or staff)

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
val webService = apiClient.createWebservice(AuthApi::class.java)
val loginRequest : LoginRequest =  // LoginRequest | 

launch(Dispatchers.IO) {
    val result : LoginResponse = webService.resellerLoginPost(loginRequest)
}
```

### Parameters
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **loginRequest** | [**LoginRequest**](LoginRequest.md)|  | |

### Return type

[**LoginResponse**](LoginResponse.md)

### Authorization

No authorization required

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json


Revoke current Sanctum token

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(AuthApi::class.java)

launch(Dispatchers.IO) {
    val result : ResellerLogoutPost200Response = webService.resellerLogoutPost()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**ResellerLogoutPost200Response**](ResellerLogoutPost200Response.md)

### Authorization


Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json

