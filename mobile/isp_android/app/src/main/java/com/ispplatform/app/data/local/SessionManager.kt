package com.ispplatform.app.data.local

import android.content.Context

class SessionManager(context: Context) {
    private val prefs = context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

    var token: String?
        get() = prefs.getString(KEY_TOKEN, null)
        set(value) = prefs.edit().putString(KEY_TOKEN, value).apply()

    var userType: String?
        get() = prefs.getString(KEY_USER_TYPE, null)
        set(value) = prefs.edit().putString(KEY_USER_TYPE, value).apply()

    var userJson: String?
        get() = prefs.getString(KEY_USER_JSON, null)
        set(value) = prefs.edit().putString(KEY_USER_JSON, value).apply()

    fun isLoggedIn(): Boolean = !token.isNullOrBlank() && !userType.isNullOrBlank()

    fun clear() {
        prefs.edit().clear().apply()
    }

    companion object {
        private const val PREFS = "isp_session"
        private const val KEY_TOKEN = "token"
        private const val KEY_USER_TYPE = "user_type"
        private const val KEY_USER_JSON = "user_json"
        const val TYPE_CLIENT = "client"
        const val TYPE_ADMIN = "admin"
    }
}
