package com.ispplatform.reseller.api.apis

import com.ispplatform.reseller.api.infrastructure.CollectionFormats.*
import retrofit2.http.*
import retrofit2.Response
import okhttp3.RequestBody
import com.squareup.moshi.Json

import com.ispplatform.reseller.api.models.LoginRequest
import com.ispplatform.reseller.api.models.LoginResponse
import com.ispplatform.reseller.api.models.ResellerLogoutPost200Response

interface AuthApi {
    /**
     * POST reseller/login
     * Login (owner or staff)
     * 
     * Responses:
     *  - 200: Bearer token issued
     *  - 422: Validation failed
     *
     * @param loginRequest 
     * @return [LoginResponse]
     */
    @POST("reseller/login")
    suspend fun resellerLoginPost(@Body loginRequest: LoginRequest): Response<LoginResponse>

    /**
     * POST reseller/logout
     * Revoke current Sanctum token
     * 
     * Responses:
     *  - 200: Logged out
     *
     * @return [ResellerLogoutPost200Response]
     */
    @POST("reseller/logout")
    suspend fun resellerLogoutPost(): Response<ResellerLogoutPost200Response>

}
