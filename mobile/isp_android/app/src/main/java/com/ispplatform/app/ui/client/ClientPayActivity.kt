package com.ispplatform.app.ui.client

import android.os.Bundle
import android.widget.LinearLayout
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.google.android.material.button.MaterialButton
import com.ispplatform.app.data.repository.ClientRepository
import com.ispplatform.app.util.Formatters
import com.ispplatform.app.util.Resource
import kotlinx.coroutines.launch

class ClientPayActivity : AppCompatActivity() {
    private val repo = ClientRepository()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        title = "Recharge / Pay"
        val container = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(32, 32, 32, 32)
        }
        setContentView(container)
        loadBills(container)
    }

    private fun loadBills(container: LinearLayout) {
        lifecycleScope.launch {
            when (val res = repo.bills()) {
                is Resource.Success -> {
                    val data = res.data.getAsJsonArray("data") ?: return@launch
                    container.removeAllViews()
                    for (i in 0 until data.size()) {
                        val bill = data[i].asJsonObject
                        val due = bill.get("balance_due")?.asDouble ?: 0.0
                        if (due <= 0) continue
                        val id = bill.get("id")?.asInt ?: continue
                        val tv = TextView(this@ClientPayActivity).apply {
                            text = "${bill.get("invoice_number")?.asString} — Due ${Formatters.money(due)} BDT"
                            setPadding(0, 16, 0, 8)
                        }
                        val btn = MaterialButton(this@ClientPayActivity).apply {
                            text = "Pay with bKash"
                            setOnClickListener { pay(id) }
                        }
                        container.addView(tv)
                        container.addView(btn)
                    }
                }
                is Resource.Error -> toast(res.message)
                else -> Unit
            }
        }
    }

    private fun pay(invoiceId: Int) {
        lifecycleScope.launch {
            when (val res = repo.pay(invoiceId)) {
                is Resource.Success -> {
                    val url = res.data.get("payment_url")?.asString
                    toast(url?.let { "Open payment URL in browser: $it" } ?: res.data.get("message")?.asString ?: "Payment initiated")
                }
                is Resource.Error -> toast(res.message)
                else -> Unit
            }
        }
    }

    private fun toast(msg: String) {
        android.widget.Toast.makeText(this, msg, android.widget.Toast.LENGTH_LONG).show()
    }
}
