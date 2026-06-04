package com.ispplatform.reseller.api.apis

import com.ispplatform.reseller.api.infrastructure.CollectionFormats.*
import retrofit2.http.*
import retrofit2.Response
import okhttp3.RequestBody
import com.squareup.moshi.Json

import com.ispplatform.reseller.api.models.CustomerCreateRequest
import com.ispplatform.reseller.api.models.CustomerUpdateRequest
import com.ispplatform.reseller.api.models.InlineObject
import com.ispplatform.reseller.api.models.PaymentCollectRequest
import com.ispplatform.reseller.api.models.PaymentCollectResponse
import com.ispplatform.reseller.api.models.ResellerCustomersCustomerPatch200Response
import com.ispplatform.reseller.api.models.ResellerInternalTicketsPostRequest
import com.ispplatform.reseller.api.models.ResellerLogoutPost200Response
import com.ispplatform.reseller.api.models.ResellerStaffGet200Response
import com.ispplatform.reseller.api.models.ResellerStaffPermissionOptionsGet200Response
import com.ispplatform.reseller.api.models.ResellerStaffStaffMemberGet200Response
import com.ispplatform.reseller.api.models.SettlementCreateRequest
import com.ispplatform.reseller.api.models.SettlementCreateResponse
import com.ispplatform.reseller.api.models.StaffCreateRequest
import com.ispplatform.reseller.api.models.StaffMutationResponse
import com.ispplatform.reseller.api.models.StaffUpdateRequest
import com.ispplatform.reseller.api.models.WalletPipraPayRequest
import com.ispplatform.reseller.api.models.WalletPipraPayResponse
import com.ispplatform.reseller.api.models.WalletRechargeRequest
import com.ispplatform.reseller.api.models.WalletRechargeResponse

interface WriteSanctumOnlyApi {
    /**
     * POST reseller/customers/{customer}/invoice
     * Generate invoice for subscriber
     * 
     * Responses:
     *  - 201: Invoice created
     *  - 405: API keys cannot mutate resources
     *
     * @param customer 
     * @return [Unit]
     */
    @POST("reseller/customers/{customer}/invoice")
    suspend fun resellerCustomersCustomerInvoicePost(@Path("customer") customer: kotlin.Int): Response<Unit>

    /**
     * PATCH reseller/customers/{customer}
     * Update subscriber
     * 
     * Responses:
     *  - 200: OK
     *  - 422: Validation failed
     *  - 405: API keys cannot mutate resources
     *
     * @param customer 
     * @param customerUpdateRequest 
     * @return [ResellerCustomersCustomerPatch200Response]
     */
    @PATCH("reseller/customers/{customer}")
    suspend fun resellerCustomersCustomerPatch(@Path("customer") customer: kotlin.Int, @Body customerUpdateRequest: CustomerUpdateRequest): Response<ResellerCustomersCustomerPatch200Response>

    /**
     * POST reseller/customers/{customer}/payments
     * Collect payment
     * 
     * Responses:
     *  - 201: Payment recorded
     *  - 422: Validation failed
     *  - 405: API keys cannot mutate resources
     *
     * @param customer 
     * @param paymentCollectRequest 
     * @return [PaymentCollectResponse]
     */
    @POST("reseller/customers/{customer}/payments")
    suspend fun resellerCustomersCustomerPaymentsPost(@Path("customer") customer: kotlin.Int, @Body paymentCollectRequest: PaymentCollectRequest): Response<PaymentCollectResponse>

    /**
     * POST reseller/customers/{customer}/reconnect
     * Reconnect subscriber
     * 
     * Responses:
     *  - 200: Reconnected
     *  - 405: API keys cannot mutate resources
     *
     * @param customer 
     * @return [Unit]
     */
    @POST("reseller/customers/{customer}/reconnect")
    suspend fun resellerCustomersCustomerReconnectPost(@Path("customer") customer: kotlin.Int): Response<Unit>

    /**
     * POST reseller/customers/{customer}/renew
     * Renew billing cycle
     * 
     * Responses:
     *  - 200: Renewed
     *  - 405: API keys cannot mutate resources
     *
     * @param customer 
     * @return [Unit]
     */
    @POST("reseller/customers/{customer}/renew")
    suspend fun resellerCustomersCustomerRenewPost(@Path("customer") customer: kotlin.Int): Response<Unit>

    /**
     * POST reseller/customers/{customer}/suspend
     * Suspend subscriber
     * 
     * Responses:
     *  - 200: Suspended
     *  - 405: API keys cannot mutate resources
     *
     * @param customer 
     * @return [Unit]
     */
    @POST("reseller/customers/{customer}/suspend")
    suspend fun resellerCustomersCustomerSuspendPost(@Path("customer") customer: kotlin.Int): Response<Unit>

    /**
     * POST reseller/customers
     * Create subscriber
     * 
     * Responses:
     *  - 201: Created (customer, optional bill/payment)
     *  - 422: Validation failed
     *  - 405: API keys cannot mutate resources
     *
     * @param customerCreateRequest 
     * @return [kotlin.collections.Map<kotlin.String, kotlin.Any>]
     */
    @POST("reseller/customers")
    suspend fun resellerCustomersPost(@Body customerCreateRequest: CustomerCreateRequest): Response<kotlin.collections.Map<kotlin.String, kotlin.Any>>

    /**
     * POST reseller/internal-tickets
     * Open internal ticket
     * 
     * Responses:
     *  - 201: Created
     *  - 405: API keys cannot mutate resources
     *
     * @param resellerInternalTicketsPostRequest 
     * @return [Unit]
     */
    @POST("reseller/internal-tickets")
    suspend fun resellerInternalTicketsPost(@Body resellerInternalTicketsPostRequest: ResellerInternalTicketsPostRequest): Response<Unit>

    /**
     * POST reseller/settlements
     * Submit settlement request
     * 
     * Responses:
     *  - 201: Settlement submitted
     *  - 422: Validation failed
     *  - 405: API keys cannot mutate resources
     *
     * @param settlementCreateRequest 
     * @return [SettlementCreateResponse]
     */
    @POST("reseller/settlements")
    suspend fun resellerSettlementsPost(@Body settlementCreateRequest: SettlementCreateRequest): Response<SettlementCreateResponse>

    /**
     * GET reseller/staff
     * List staff accounts
     * 
     * Responses:
     *  - 200: Staff list
     *
     * @return [ResellerStaffGet200Response]
     */
    @GET("reseller/staff")
    suspend fun resellerStaffGet(): Response<ResellerStaffGet200Response>

    /**
     * GET reseller/staff/permission-options
     * Staff permission labels (assignable subset)
     * 
     * Responses:
     *  - 200: Permission options map
     *
     * @return [ResellerStaffPermissionOptionsGet200Response]
     */
    @GET("reseller/staff/permission-options")
    suspend fun resellerStaffPermissionOptionsGet(): Response<ResellerStaffPermissionOptionsGet200Response>

    /**
     * POST reseller/staff
     * Create staff account
     * 
     * Responses:
     *  - 201: Staff created
     *  - 422: Validation failed
     *  - 405: API keys cannot mutate resources
     *
     * @param staffCreateRequest 
     * @return [StaffMutationResponse]
     */
    @POST("reseller/staff")
    suspend fun resellerStaffPost(@Body staffCreateRequest: StaffCreateRequest): Response<StaffMutationResponse>

    /**
     * DELETE reseller/staff/{staffMember}
     * Deactivate staff account
     * 
     * Responses:
     *  - 200: Deactivated
     *
     * @param staffMember 
     * @return [ResellerLogoutPost200Response]
     */
    @DELETE("reseller/staff/{staffMember}")
    suspend fun resellerStaffStaffMemberDelete(@Path("staffMember") staffMember: kotlin.Int): Response<ResellerLogoutPost200Response>

    /**
     * GET reseller/staff/{staffMember}
     * Staff detail
     * 
     * Responses:
     *  - 200: Staff member
     *
     * @param staffMember 
     * @return [ResellerStaffStaffMemberGet200Response]
     */
    @GET("reseller/staff/{staffMember}")
    suspend fun resellerStaffStaffMemberGet(@Path("staffMember") staffMember: kotlin.Int): Response<ResellerStaffStaffMemberGet200Response>

    /**
     * PATCH reseller/staff/{staffMember}
     * Update staff account
     * 
     * Responses:
     *  - 200: Updated
     *  - 422: Validation failed
     *  - 405: API keys cannot mutate resources
     *
     * @param staffMember 
     * @param staffUpdateRequest 
     * @return [StaffMutationResponse]
     */
    @PATCH("reseller/staff/{staffMember}")
    suspend fun resellerStaffStaffMemberPatch(@Path("staffMember") staffMember: kotlin.Int, @Body staffUpdateRequest: StaffUpdateRequest): Response<StaffMutationResponse>

    /**
     * POST reseller/sub-resellers
     * Create sub-partner
     * 
     * Responses:
     *  - 201: Created
     *  - 405: API keys cannot mutate resources
     *
     * @return [Unit]
     */
    @POST("reseller/sub-resellers")
    suspend fun resellerSubResellersPost(): Response<Unit>

    /**
     * POST reseller/tickets
     * Open ticket
     * 
     * Responses:
     *  - 201: Created
     *  - 405: API keys cannot mutate resources
     *
     * @return [Unit]
     */
    @POST("reseller/tickets")
    suspend fun resellerTicketsPost(): Response<Unit>

    /**
     * POST reseller/wallet/recharge/piprapay
     * Start PipraPay wallet checkout
     * 
     * Responses:
     *  - 200: Checkout URL
     *  - 422: Validation failed
     *  - 405: API keys cannot mutate resources
     *
     * @param walletPipraPayRequest 
     * @return [WalletPipraPayResponse]
     */
    @POST("reseller/wallet/recharge/piprapay")
    suspend fun resellerWalletRechargePiprapayPost(@Body walletPipraPayRequest: WalletPipraPayRequest): Response<WalletPipraPayResponse>

    /**
     * POST reseller/wallet/recharge
     * Submit manual wallet top-up (admin approval)
     * 
     * Responses:
     *  - 201: Recharge request submitted
     *  - 422: Validation failed
     *  - 405: API keys cannot mutate resources
     *
     * @param walletRechargeRequest 
     * @return [WalletRechargeResponse]
     */
    @POST("reseller/wallet/recharge")
    suspend fun resellerWalletRechargePost(@Body walletRechargeRequest: WalletRechargeRequest): Response<WalletRechargeResponse>

}
