package com.ispplatform.app.ui.admin

import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.widget.LinearLayout
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.lifecycle.lifecycleScope
import com.ispplatform.app.R
import com.ispplatform.app.data.repository.AdminRepository
import com.ispplatform.app.util.Resource
import kotlinx.coroutines.launch

class AdminMonitoringActivity : AppCompatActivity() {
    private val repo = AdminRepository()
    private val handler = Handler(Looper.getMainLooper())
    private lateinit var container: LinearLayout

    private val refreshRunnable = object : Runnable {
        override fun run() {
            load()
            handler.postDelayed(this, 30_000)
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        title = "Monitoring"
        container = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(24, 24, 24, 24)
        }
        setContentView(container)
        load()
        handler.postDelayed(refreshRunnable, 30_000)
    }

    private fun load() {
        lifecycleScope.launch {
            when (val res = repo.onlineClients()) {
                is Resource.Success -> {
                    container.removeAllViews()
                    val data = res.data.getAsJsonArray("data") ?: return@launch
                    for (i in 0 until data.size()) {
                        val c = data[i].asJsonObject
                        val online = c.get("online")?.asBoolean == true
                        val tv = TextView(this@AdminMonitoringActivity).apply {
                            text = "${c.get("name")?.asString} · ${if (online) "Online" else "Offline"}"
                            setTextColor(ContextCompat.getColor(context, if (online) R.color.success else R.color.danger))
                            setPadding(0, 12, 0, 12)
                        }
                        container.addView(tv)
                    }
                }
                is Resource.Error -> toast(res.message)
                else -> Unit
            }
        }
    }

    override fun onDestroy() {
        handler.removeCallbacks(refreshRunnable)
        super.onDestroy()
    }

    private fun toast(msg: String) = android.widget.Toast.makeText(this, msg, android.widget.Toast.LENGTH_SHORT).show()
}
