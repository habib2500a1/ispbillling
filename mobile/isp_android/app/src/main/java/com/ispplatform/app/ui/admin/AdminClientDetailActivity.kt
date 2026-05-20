package com.ispplatform.app.ui.admin

import android.os.Bundle
import android.widget.LinearLayout
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.ispplatform.app.data.repository.AdminRepository
import com.ispplatform.app.util.Formatters
import com.ispplatform.app.util.Resource
import kotlinx.coroutines.launch

class AdminClientDetailActivity : AppCompatActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        title = "Client Detail"
        val id = intent.getIntExtra("id", 0)
        val tv = TextView(this).apply { setPadding(32, 32, 32, 32) }
        setContentView(LinearLayout(this).apply { addView(tv) })
        lifecycleScope.launch {
            when (val res = AdminRepository().customerDetail(id)) {
                is Resource.Success -> {
                    val c = res.data.getAsJsonObject("customer") ?: res.data
                    tv.text = buildString {
                        appendLine(c.get("name")?.asString)
                        appendLine("Code: ${c.get("customer_code")?.asString}")
                        appendLine("Phone: ${c.get("phone")?.asString}")
                        appendLine("Due: ${Formatters.money(c.get("balance_due")?.asDouble ?: 0.0)} BDT")
                    }
                }
                is Resource.Error -> tv.text = res.message
                else -> Unit
            }
        }
    }
}
