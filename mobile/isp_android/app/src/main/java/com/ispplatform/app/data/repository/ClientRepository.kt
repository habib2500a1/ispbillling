package com.ispplatform.app.data.repository

import com.google.gson.JsonObject
import com.ispplatform.app.data.api.RetrofitClient
import com.ispplatform.app.util.Resource
import retrofit2.HttpException

class ClientRepository {
    private val api = RetrofitClient.api

    suspend fun dashboard(): Resource<JsonObject> = safe { api.customerDashboard() }
    suspend fun bills(): Resource<JsonObject> = safe { api.customerBills() }
    suspend fun usage(): Resource<JsonObject> = safe { api.customerUsageLive() }
    suspend fun packages(): Resource<JsonObject> = safe { api.customerPackages() }
    suspend fun tickets(): Resource<JsonObject> = safe { api.customerTickets() }

    suspend fun pay(invoiceId: Int): Resource<JsonObject> = safe { api.customerPay(invoiceId) }

    suspend fun changePackage(packageId: Int, note: String?): Resource<JsonObject> = safe {
        api.customerPackageChange(buildMap {
            put("package_id", packageId)
            if (!note.isNullOrBlank()) put("note", note)
        })
    }

    suspend fun changePassword(current: String, newPass: String): Resource<JsonObject> = safe {
        api.customerChangePassword(
            mapOf(
                "current_password" to current,
                "password" to newPass,
                "password_confirmation" to newPass,
            ),
        )
    }

    suspend fun createTicket(subject: String, description: String): Resource<JsonObject> = safe {
        api.customerCreateTicket(
            mapOf(
                "subject" to subject,
                "description" to description,
                "department" to "technical_support",
                "priority" to "medium",
            ),
        )
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
