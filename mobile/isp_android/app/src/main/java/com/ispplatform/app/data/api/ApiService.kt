package com.ispplatform.app.data.api

import com.google.gson.JsonObject
import com.ispplatform.app.data.model.LoginRequest
import com.ispplatform.app.data.model.LoginResponse
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Path
import retrofit2.http.Query

// Maps spec endpoints to Laravel routes (client → customer, admin → staff/collector).
interface ApiService {

    @POST("login")
    suspend fun login(@Body body: LoginRequest): LoginResponse

    // —— Client ——
    @GET("customer/dashboard")
    suspend fun customerDashboard(): JsonObject

    @GET("customer/bills")
    suspend fun customerBills(): JsonObject

    @POST("customer/bills/{id}/pay")
    suspend fun customerPay(@Path("id") invoiceId: Int, @Body body: Map<String, String> = emptyMap()): JsonObject

    @GET("customer/packages")
    suspend fun customerPackages(): JsonObject

    @POST("customer/packages/change")
    suspend fun customerPackageChange(@Body body: Map<String, @JvmSuppressWildcards Any>): JsonObject

    @POST("customer/profile/password")
    suspend fun customerChangePassword(@Body body: Map<String, String>): JsonObject

    @GET("customer/tickets")
    suspend fun customerTickets(): JsonObject

    @POST("customer/tickets")
    suspend fun customerCreateTicket(@Body body: Map<String, String>): JsonObject

    @GET("customer/usage/live")
    suspend fun customerUsageLive(): JsonObject

    @POST("customer/logout")
    suspend fun customerLogout(): JsonObject

    // —— Admin / Staff ——
    @GET("staff/dashboard")
    suspend fun staffDashboard(): JsonObject

    @GET("staff/customers")
    suspend fun staffCustomers(@Query("q") query: String? = null): JsonObject

    @GET("staff/customers/search")
    suspend fun staffSearchCustomers(@Query("q") q: String): JsonObject

    @GET("staff/customers/{id}")
    suspend fun staffCustomerDetail(@Path("id") id: Int): JsonObject

    @GET("staff/online-clients")
    suspend fun staffOnlineClients(): JsonObject

    @GET("staff/tickets")
    suspend fun staffTickets(): JsonObject

    @GET("staff/tasks")
    suspend fun staffTasks(): JsonObject

    @POST("auth/logout")
    suspend fun staffLogout(): JsonObject

    @POST("collector/collections")
    suspend fun collectorCollection(@Body body: Map<String, @JvmSuppressWildcards Any>): JsonObject

    @GET("collector/wallet")
    suspend fun collectorWallet(): JsonObject

    @POST("collector/expenses")
    suspend fun collectorExpense(@Body body: Map<String, @JvmSuppressWildcards Any>): JsonObject
}
