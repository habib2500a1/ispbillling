package com.ispplatform.reseller.api.apis

import com.ispplatform.reseller.api.infrastructure.CollectionFormats.*
import retrofit2.http.*
import retrofit2.Response
import okhttp3.RequestBody
import com.squareup.moshi.Json

import com.ispplatform.reseller.api.models.ApiKeyCreateRequest
import com.ispplatform.reseller.api.models.ResellerApiKeysGet200Response
import com.ispplatform.reseller.api.models.ResellerApiKeysPost201Response

interface APIKeysSanctumOnlyApi {
    /**
     * DELETE reseller/api-keys/{apiKey}
     * Revoke API key
     * 
     * Responses:
     *  - 200: Revoked
     *
     * @param apiKey 
     * @return [Unit]
     */
    @DELETE("reseller/api-keys/{apiKey}")
    suspend fun resellerApiKeysApiKeyDelete(@Path("apiKey") apiKey: kotlin.Int): Response<Unit>

    /**
     * GET reseller/api-keys
     * List API keys (metadata only)
     * 
     * Responses:
     *  - 200: OK
     *
     * @return [ResellerApiKeysGet200Response]
     */
    @GET("reseller/api-keys")
    suspend fun resellerApiKeysGet(): Response<ResellerApiKeysGet200Response>

    /**
     * POST reseller/api-keys
     * Create API key
     * 
     * Responses:
     *  - 201: Key created (plain key shown once)
     *
     * @param apiKeyCreateRequest 
     * @return [ResellerApiKeysPost201Response]
     */
    @POST("reseller/api-keys")
    suspend fun resellerApiKeysPost(@Body apiKeyCreateRequest: ApiKeyCreateRequest): Response<ResellerApiKeysPost201Response>

}
