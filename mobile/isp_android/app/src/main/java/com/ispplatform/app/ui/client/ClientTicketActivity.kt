package com.ispplatform.app.ui.client

import android.os.Bundle
import android.widget.LinearLayout
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.google.android.material.button.MaterialButton
import com.google.android.material.textfield.TextInputEditText
import com.ispplatform.app.data.repository.ClientRepository
import com.ispplatform.app.util.Resource
import kotlinx.coroutines.launch

class ClientTicketActivity : AppCompatActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        title = "Create Ticket"
        val subject = TextInputEditText(this).apply { hint = "Subject" }
        val desc = TextInputEditText(this).apply {
            hint = "Description"
            minLines = 4
        }
        val btn = MaterialButton(this).apply { text = "Submit ticket" }
        setContentView(LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(32, 32, 32, 32)
            addView(subject)
            addView(desc)
            addView(btn)
        })
        btn.setOnClickListener {
            lifecycleScope.launch {
                when (val res = ClientRepository().createTicket(subject.text?.toString().orEmpty(), desc.text?.toString().orEmpty())) {
                    is Resource.Success -> {
                        toast("Ticket created")
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
