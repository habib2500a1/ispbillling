package com.ispplatform.app.ui.admin

import android.content.Intent
import android.os.Bundle
import android.widget.LinearLayout
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.google.android.material.button.MaterialButton
import com.google.android.material.textfield.TextInputEditText
import com.ispplatform.app.data.repository.AdminRepository
import com.ispplatform.app.util.Resource
import kotlinx.coroutines.launch

class AdminBillReceiveActivity : AppCompatActivity() {
    private val repo = AdminRepository()
    private var selectedCustomerId: Int? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        title = "Bill Receive"
        val search = TextInputEditText(this).apply { hint = "Search client (name/code)" }
        val amount = TextInputEditText(this).apply { hint = "Amount BDT" }
        val btnSearch = MaterialButton(this).apply { text = "Search" }
        val btnCollect = MaterialButton(this).apply { text = "Record collection (cash)" }
        val results = TextView(this)
        setContentView(LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(32, 32, 32, 32)
            addView(search)
            addView(btnSearch)
            addView(results)
            addView(amount)
            addView(btnCollect)
        })
        btnSearch.setOnClickListener {
            lifecycleScope.launch {
                when (val res = repo.searchCustomers(search.text?.toString().orEmpty())) {
                    is Resource.Success -> {
                        val data = res.data.getAsJsonArray("data")
                        if (data == null || data.size() == 0) {
                            results.text = "No clients found"
                            return@launch
                        }
                        val c = data[0].asJsonObject
                        selectedCustomerId = c.get("id").asInt
                        results.text = "${c.get("name")?.asString} (${c.get("customer_code")?.asString})"
                    }
                    is Resource.Error -> toast(res.message)
                    else -> Unit
                }
            }
        }
        btnCollect.setOnClickListener {
            val id = selectedCustomerId ?: return@setOnClickListener toast("Search client first")
            val amt = amount.text?.toString()?.toDoubleOrNull() ?: return@setOnClickListener toast("Enter amount")
            lifecycleScope.launch {
                when (val res = repo.receivePayment(id, amt, null, "cash")) {
                    is Resource.Success -> {
                        toast("Collected")
                        finish()
                    }
                    is Resource.Error -> toast(res.message)
                    else -> Unit
                }
            }
        }
    }

    private fun toast(msg: String) = android.widget.Toast.makeText(this, msg, android.widget.Toast.LENGTH_SHORT).show()
}
