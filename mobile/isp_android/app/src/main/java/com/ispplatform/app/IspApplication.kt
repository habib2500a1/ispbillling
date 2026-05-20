package com.ispplatform.app

import android.app.Application
import com.ispplatform.app.data.api.RetrofitClient
import com.ispplatform.app.data.local.SessionManager

class IspApplication : Application() {
    lateinit var sessionManager: SessionManager
        private set

    override fun onCreate() {
        super.onCreate()
        sessionManager = SessionManager(this)
        RetrofitClient.init(sessionManager)
    }
}
