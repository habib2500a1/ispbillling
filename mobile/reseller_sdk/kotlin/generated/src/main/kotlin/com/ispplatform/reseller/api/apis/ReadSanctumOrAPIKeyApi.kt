package com.ispplatform.reseller.api.apis

import com.ispplatform.reseller.api.infrastructure.CollectionFormats.*
import retrofit2.http.*
import retrofit2.Response
import okhttp3.RequestBody
import com.squareup.moshi.Json

import com.ispplatform.reseller.api.models.Customer
import com.ispplatform.reseller.api.models.MeResponse
import com.ispplatform.reseller.api.models.PaginatedCustomers
import com.ispplatform.reseller.api.models.ResellerAnnouncementsGet200Response
import com.ispplatform.reseller.api.models.ResellerDueAccountGet200Response
import com.ispplatform.reseller.api.models.ResellerInternalTicketsGet200Response
import com.ispplatform.reseller.api.models.ResellerSubResellersGet200Response
import com.ispplatform.reseller.api.models.SettlementListResponse

interface ReadSanctumOrAPIKeyApi {
    /**
     * GET reseller/activity
     * Portal activity log
     * 
     * Responses:
     *  - 200: OK
     *
     * @return [kotlin.collections.Map<kotlin.String, kotlin.Any>]
     */
    @GET("reseller/activity")
    suspend fun resellerActivityGet(): Response<kotlin.collections.Map<kotlin.String, kotlin.Any>>

    /**
     * GET reseller/announcements
     * HQ announcements
     * 
     * Responses:
     *  - 200: OK
     *
     * @return [ResellerAnnouncementsGet200Response]
     */
    @GET("reseller/announcements")
    suspend fun resellerAnnouncementsGet(): Response<ResellerAnnouncementsGet200Response>

    /**
     * GET reseller/commissions
     * Commission ledger
     * 
     * Responses:
     *  - 200: OK
     *
     * @return [kotlin.collections.Map<kotlin.String, kotlin.Any>]
     */
    @GET("reseller/commissions")
    suspend fun resellerCommissionsGet(): Response<kotlin.collections.Map<kotlin.String, kotlin.Any>>

    /**
     * GET reseller/customer-transfers
     * Transfer history
     * 
     * Responses:
     *  - 200: OK
     *
     * @return [kotlin.collections.Map<kotlin.String, kotlin.Any>]
     */
    @GET("reseller/customer-transfers")
    suspend fun resellerCustomerTransfersGet(): Response<kotlin.collections.Map<kotlin.String, kotlin.Any>>

    /**
     * GET reseller/customers/{customer}
     * Subscriber detail
     * 
     * Responses:
     *  - 200: Customer with package
     *
     * @param customer 
     * @return [Customer]
     */
    @GET("reseller/customers/{customer}")
    suspend fun resellerCustomersCustomerGet(@Path("customer") customer: kotlin.Int): Response<Customer>

    /**
     * GET reseller/customers
     * List subscribers
     * 
     * Responses:
     *  - 200: Laravel pagination JSON (data, links, meta)
     *
     * @param q Search name, customer code, or phone (optional)
     * @param perPage  (optional, default to 20)
     * @return [PaginatedCustomers]
     */
    @GET("reseller/customers")
    suspend fun resellerCustomersGet(@Query("q") q: kotlin.String? = null, @Query("per_page") perPage: kotlin.Int? = 20): Response<PaginatedCustomers>

    /**
     * GET reseller/dashboard
     * Dashboard metrics
     * 
     * Responses:
     *  - 200: Metrics, charts, recent activity
     *
     * @return [kotlin.collections.Map<kotlin.String, kotlin.Any>]
     */
    @GET("reseller/dashboard")
    suspend fun resellerDashboardGet(): Response<kotlin.collections.Map<kotlin.String, kotlin.Any>>

    /**
     * GET reseller/due-account
     * Reseller billing / due account
     * 
     * Responses:
     *  - 200: OK
     *
     * @return [ResellerDueAccountGet200Response]
     */
    @GET("reseller/due-account")
    suspend fun resellerDueAccountGet(): Response<ResellerDueAccountGet200Response>

    /**
     * GET reseller/internal-tickets
     * Internal HQ tickets
     * 
     * Responses:
     *  - 200: OK
     *
     * @return [ResellerInternalTicketsGet200Response]
     */
    @GET("reseller/internal-tickets")
    suspend fun resellerInternalTicketsGet(): Response<ResellerInternalTicketsGet200Response>

    /**
     * GET reseller/invoices
     * Invoice list
     * 
     * Responses:
     *  - 200: OK
     *
     * @return [kotlin.collections.Map<kotlin.String, kotlin.Any>]
     */
    @GET("reseller/invoices")
    suspend fun resellerInvoicesGet(): Response<kotlin.collections.Map<kotlin.String, kotlin.Any>>

    /**
     * GET reseller/me
     * Current actor and permissions
     * 
     * Responses:
     *  - 200: Profile
     *
     * @return [MeResponse]
     */
    @GET("reseller/me")
    suspend fun resellerMeGet(): Response<MeResponse>

    /**
     * GET reseller/network
     * Network / online subscribers
     * 
     * Responses:
     *  - 200: OK
     *
     * @return [kotlin.collections.Map<kotlin.String, kotlin.Any>]
     */
    @GET("reseller/network")
    suspend fun resellerNetworkGet(): Response<kotlin.collections.Map<kotlin.String, kotlin.Any>>

    /**
     * GET reseller/notifications
     * Notifications
     * 
     * Responses:
     *  - 200: OK
     *
     * @return [kotlin.collections.Map<kotlin.String, kotlin.Any>]
     */
    @GET("reseller/notifications")
    suspend fun resellerNotificationsGet(): Response<kotlin.collections.Map<kotlin.String, kotlin.Any>>

    /**
     * GET reseller/onu
     * ONU / GPON status list
     * 
     * Responses:
     *  - 200: OK
     *
     * @return [kotlin.collections.Map<kotlin.String, kotlin.Any>]
     */
    @GET("reseller/onu")
    suspend fun resellerOnuGet(): Response<kotlin.collections.Map<kotlin.String, kotlin.Any>>

    /**
     * GET reseller/partner/{path}
     * Legacy alias (same as /reseller/{path})
     * Deprecated prefix. Prefer &#x60;/reseller/dashboard&#x60;, &#x60;/reseller/customers&#x60;, etc. Supported paths mirror shared read routes (dashboard, wallet, customers, …). 
     * Responses:
     *  - 200: Same response as canonical `/reseller/_*` GET
     *
     * @param path 
     * @return [Unit]
     */
    @GET("reseller/partner/{path}")
    suspend fun resellerPartnerPathGet(@Path("path") path: kotlin.String): Response<Unit>

    /**
     * GET reseller/reports/enterprise
     * Enterprise report pack
     * 
     * Responses:
     *  - 200: OK
     *
     * @return [kotlin.collections.Map<kotlin.String, kotlin.Any>]
     */
    @GET("reseller/reports/enterprise")
    suspend fun resellerReportsEnterpriseGet(): Response<kotlin.collections.Map<kotlin.String, kotlin.Any>>

    /**
     * GET reseller/reports/summary
     * Reports summary
     * 
     * Responses:
     *  - 200: OK
     *
     * @return [kotlin.collections.Map<kotlin.String, kotlin.Any>]
     */
    @GET("reseller/reports/summary")
    suspend fun resellerReportsSummaryGet(): Response<kotlin.collections.Map<kotlin.String, kotlin.Any>>

    /**
     * GET reseller/settlements
     * Settlement requests
     * 
     * Responses:
     *  - 200: Paginated settlements
     *
     * @return [SettlementListResponse]
     */
    @GET("reseller/settlements")
    suspend fun resellerSettlementsGet(): Response<SettlementListResponse>

    /**
     * GET reseller/sub-resellers/{child}
     * Sub-partner detail
     * 
     * Responses:
     *  - 200: OK
     *
     * @param child 
     * @return [kotlin.collections.Map<kotlin.String, kotlin.Any>]
     */
    @GET("reseller/sub-resellers/{child}")
    suspend fun resellerSubResellersChildGet(@Path("child") child: kotlin.Int): Response<kotlin.collections.Map<kotlin.String, kotlin.Any>>

    /**
     * GET reseller/sub-resellers
     * Sub-partners
     * 
     * Responses:
     *  - 200: OK
     *
     * @return [ResellerSubResellersGet200Response]
     */
    @GET("reseller/sub-resellers")
    suspend fun resellerSubResellersGet(): Response<ResellerSubResellersGet200Response>

    /**
     * GET reseller/tickets
     * Support tickets
     * 
     * Responses:
     *  - 200: OK
     *
     * @return [kotlin.collections.Map<kotlin.String, kotlin.Any>]
     */
    @GET("reseller/tickets")
    suspend fun resellerTicketsGet(): Response<kotlin.collections.Map<kotlin.String, kotlin.Any>>

    /**
     * GET reseller/wallet
     * Wallet statement
     * 
     * Responses:
     *  - 200: OK
     *
     * @return [kotlin.collections.Map<kotlin.String, kotlin.Any>]
     */
    @GET("reseller/wallet")
    suspend fun resellerWalletGet(): Response<kotlin.collections.Map<kotlin.String, kotlin.Any>>

    /**
     * GET reseller/wallet/overview
     * Wallet overview (quota, frozen, recent tx)
     * 
     * Responses:
     *  - 200: OK
     *
     * @return [kotlin.collections.Map<kotlin.String, kotlin.Any>]
     */
    @GET("reseller/wallet/overview")
    suspend fun resellerWalletOverviewGet(): Response<kotlin.collections.Map<kotlin.String, kotlin.Any>>

}
