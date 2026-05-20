package com.ispplatform.app.data.model

import com.google.gson.JsonObject

data class LoginRequest(
    val login: String,
    val password: String,
    val device_name: String = "isp-android-kotlin",
)

data class LoginResponse(
    val token: String?,
    val token_type: String?,
    val user_type: String?,
    val user: JsonObject?,
    val message: String?,
)
