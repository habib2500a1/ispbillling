# APIKeysSanctumOnlyApi

All URIs are relative to *https://your-domain.example/api/v1*

| Method | HTTP request | Description |
| ------------- | ------------- | ------------- |
| [**resellerApiKeysApiKeyDelete**](APIKeysSanctumOnlyApi.md#resellerApiKeysApiKeyDelete) | **DELETE** reseller/api-keys/{apiKey} | Revoke API key |
| [**resellerApiKeysGet**](APIKeysSanctumOnlyApi.md#resellerApiKeysGet) | **GET** reseller/api-keys | List API keys (metadata only) |
| [**resellerApiKeysPost**](APIKeysSanctumOnlyApi.md#resellerApiKeysPost) | **POST** reseller/api-keys | Create API key |



Revoke API key

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(APIKeysSanctumOnlyApi::class.java)
val apiKey : kotlin.Int = 56 // kotlin.Int | 

launch(Dispatchers.IO) {
    webService.resellerApiKeysApiKeyDelete(apiKey)
}
```

### Parameters
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **apiKey** | **kotlin.Int**|  | |

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


List API keys (metadata only)

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(APIKeysSanctumOnlyApi::class.java)

launch(Dispatchers.IO) {
    val result : ResellerApiKeysGet200Response = webService.resellerApiKeysGet()
}
```

### Parameters
This endpoint does not need any parameter.

### Return type

[**ResellerApiKeysGet200Response**](ResellerApiKeysGet200Response.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: application/json


Create API key

### Example
```kotlin
// Import classes:
//import com.ispplatform.reseller.api.*
//import com.ispplatform.reseller.api.infrastructure.*
//import com.ispplatform.reseller.api.models.*

val apiClient = ApiClient()
apiClient.setBearerToken("TOKEN")
apiClient.setBearerToken("TOKEN")
val webService = apiClient.createWebservice(APIKeysSanctumOnlyApi::class.java)
val apiKeyCreateRequest : ApiKeyCreateRequest =  // ApiKeyCreateRequest | 

launch(Dispatchers.IO) {
    val result : ResellerApiKeysPost201Response = webService.resellerApiKeysPost(apiKeyCreateRequest)
}
```

### Parameters
| Name | Type | Description  | Notes |
| ------------- | ------------- | ------------- | ------------- |
| **apiKeyCreateRequest** | [**ApiKeyCreateRequest**](ApiKeyCreateRequest.md)|  | |

### Return type

[**ResellerApiKeysPost201Response**](ResellerApiKeysPost201Response.md)

### Authorization


Configure apiKeyBearer:
    ApiClient().setBearerToken("TOKEN")
Configure sanctumBearer:
    ApiClient().setBearerToken("TOKEN")

### HTTP request headers

 - **Content-Type**: application/json
 - **Accept**: application/json

