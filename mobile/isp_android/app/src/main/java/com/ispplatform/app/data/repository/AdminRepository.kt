package com.ispplatform.app.data.repository

import com.google.gson.JsonObject
import com.ispplatform.app.data.api.RetrofitClient
import com.ispplatform.app.util.Resource
import retrofit2.HttpException

class AdminRepository {
    private val api = RetrofitClient.api

    suspend fun dashboard(): Resource<JsonObject> = safe { api.staffDashboard() }
    suspend fun customers(q: String? = null): Resource<JsonObject> = safe { api.staffCustomers(q) }
    suspend fun searchCustomers(q: String): Resource<JsonObject> = safe { api.staffSearchCustomers(q) }
    suspend fun customerDetail(id: Int): Resource<JsonObject> = safe { api.staffCustomerDetail(id) }
    suspend fun onlineClients(): Resource<JsonObject> = safe { api.staffOnlineClients() }
    suspend fun tickets(): Resource<JsonObject> = safe { api.staffTickets() }
    suspend fun tasks(): Resource<JsonObject> = safe { api.staffTasks() }
    suspend fun wallet(): Resource<JsonObject> = safe { api.collectorWallet() }

    suspend fun receivePayment(
        customerId: Int,
        amount: Double,
        invoiceId: Int?,
        method: String,
    ): Resource<JsonObject> = safe {
        api.collectorCollection(
            buildMap {
                put("customer_id", customerId)
                put("amount", amount)
                put("method", method)
                invoiceId?.let { put("invoice_id", it) }
            },
        )
    }

    suspend fun addExpense(amount: Double, note: String): Resource<JsonObject> = safe {
        api.collectorExpense(mapOf("amount" to amount, "notes" to note))
    }

    private suspend fun safe(block: suspend () -> JsonObject): Resource<JsonObject> {
        return try {
            Resource.Success(block())
        } catch (e: HttpException) {
            Resource.Error(e.message ?: "Error ${e.code()}", e.code())
        } catch (e: Exception) {
            Resource.Error(e.message ?: "Network error")
        }
    }
}
