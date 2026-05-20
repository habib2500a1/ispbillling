package com.ispplatform.app.ui.client

import android.os.Bundle
import android.widget.ArrayAdapter
import android.widget.LinearLayout
import android.widget.Spinner
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.google.android.material.button.MaterialButton
import com.google.android.material.textfield.TextInputEditText
import com.ispplatform.app.data.repository.ClientRepository
import com.ispplatform.app.util.Resource
import kotlinx.coroutines.launch

class ClientPackageActivity : AppCompatActivity() {
    private val repo = ClientRepository()
    private var packageIds = listOf<Int>()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        title = "Change Package"
        val layout = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(32, 32, 32, 32)
        }
        val spinner = Spinner(this)
        val note = TextInputEditText(this).apply { hint = "Note (optional)" }
        val btn = MaterialButton(this).apply { text = "Request change" }
        layout.addView(spinner)
        layout.addView(note)
        layout.addView(btn)
        setContentView(layout)

        lifecycleScope.launch {
            when (val res = repo.packages()) {
                is Resource.Success -> {
                    val data = res.data.getAsJsonArray("data") ?: return@launch
                    val names = mutableListOf<String>()
                    packageIds = mutableListOf<Int>().also { ids ->
                        for (i in 0 until data.size()) {
                            val p = data[i].asJsonObject
                            ids.add(p.get("id").asInt)
                            names.add("${p.get("name").asString} — ${p.get("download_mbps")} Mbps")
                        }
                    }
                    spinner.adapter = ArrayAdapter(this@ClientPackageActivity, android.R.layout.simple_spinner_dropdown_item, names)
                }
                is Resource.Error -> toast(res.message)
                else -> Unit
            }
        }

        btn.setOnClickListener {
            val id = packageIds.getOrNull(spinner.selectedItemPosition) ?: return@setOnClickListener
            lifecycleScope.launch {
                when (val res = repo.changePackage(id, note.text?.toString())) {
                    is Resource.Success -> toast(res.data.get("message")?.asString ?: "Requested")
                    is Resource.Error -> toast(res.message)
                    else -> Unit
                }
            }
        }
    }

    private fun toast(msg: String) = android.widget.Toast.makeText(this, msg, android.widget.Toast.LENGTH_SHORT).show()
}
