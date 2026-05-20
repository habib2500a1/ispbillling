package com.ispplatform.app.ui.admin

import android.content.Intent
import android.os.Bundle
import android.widget.AdapterView
import android.widget.ArrayAdapter
import android.widget.ListView
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.ispplatform.app.data.repository.AdminRepository
import com.ispplatform.app.util.Formatters
import com.ispplatform.app.util.Resource
import kotlinx.coroutines.launch

class AdminClientsActivity : AppCompatActivity() {
    private var ids = listOf<Int>()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        title = "Client List"
        val list = ListView(this)
        setContentView(list)
        lifecycleScope.launch {
            when (val res = AdminRepository().customers()) {
                is Resource.Success -> {
                    val data = res.data.getAsJsonArray("data") ?: return@launch
                    val labels = mutableListOf<String>()
                    ids = mutableListOf<Int>().also { idList ->
                        for (i in 0 until data.size()) {
                            val c = data[i].asJsonObject
                            idList.add(c.get("id").asInt)
                            labels.add("${c.get("name")?.asString} · ${c.get("customer_code")?.asString} · Due ${Formatters.money(c.get("balance_due")?.asDouble ?: 0.0)}")
                        }
                    }
                    list.adapter = ArrayAdapter(this@AdminClientsActivity, android.R.layout.simple_list_item_1, labels)
                    list.onItemClickListener = AdapterView.OnItemClickListener { _, _, pos, _ ->
                        startActivity(Intent(this@AdminClientsActivity, AdminClientDetailActivity::class.java).putExtra("id", ids[pos]))
                    }
                }
                is Resource.Error -> toast(res.message)
                else -> Unit
            }
        }
    }

    private fun toast(msg: String) = android.widget.Toast.makeText(this, msg, android.widget.Toast.LENGTH_SHORT).show()
}
