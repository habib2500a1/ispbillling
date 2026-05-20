package com.ispplatform.app.data.repository

import com.google.gson.Gson
import com.ispplatform.app.data.api.RetrofitClient
import com.ispplatform.app.data.local.SessionManager
import com.ispplatform.app.data.model.LoginRequest
import com.ispplatform.app.util.Resource
import retrofit2.HttpException

class AuthRepository {
    private val api = RetrofitClient.api
    private val session = RetrofitClient.sessionManager()
    private val gson = Gson()

    suspend fun login(identifier: String, password: String): Resource<String> {
        return try {
            val res = api.login(LoginRequest(login = identifier.trim(), password = password))
            val token = res.token
            val type = res.user_type
            if (token.isNullOrBlank() || type.isNullOrBlank()) {
                return Resource.Error(res.message ?: "Login failed")
            }
            session.token = token
            session.userType = when (type) {
                "customer", "client" -> SessionManager.TYPE_CLIENT
                "staff", "admin" -> SessionManager.TYPE_ADMIN
                else -> type
            }
            session.userJson = res.user?.let { gson.toJson(it) }
            Resource.Success(session.userType!!)
        } catch (e: HttpException) {
            val msg = e.response()?.errorBody()?.string()?.let { parseMessage(it) } ?: "Login failed (${e.code()})"
            Resource.Error(msg, e.code())
        } catch (e: Exception) {
            Resource.Error(e.message ?: "Connection error")
        }
    }

    suspend fun logout() {
        try {
            if (session.userType == SessionManager.TYPE_CLIENT) {
                api.customerLogout()
            } else {
                api.staffLogout()
            }
        } catch (_: Exception) {
        } finally {
            session.clear()
        }
    }

    private fun parseMessage(body: String): String {
        return try {
            gson.fromJson(body, Map::class.java)["message"]?.toString() ?: body
        } catch (_: Exception) {
            body
        }
    }
}
