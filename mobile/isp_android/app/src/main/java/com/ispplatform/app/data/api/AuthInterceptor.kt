package com.ispplatform.app.data.api

import com.ispplatform.app.data.local.SessionManager
import okhttp3.Interceptor
import okhttp3.Response

class AuthInterceptor(private val session: SessionManager) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val request = chain.request().newBuilder()
            .header("Accept", "application/json")
            .header("Content-Type", "application/json")
            .apply {
                session.token?.let { header("Authorization", "Bearer $it") }
            }
            .build()
        return chain.proceed(request)
    }
}
