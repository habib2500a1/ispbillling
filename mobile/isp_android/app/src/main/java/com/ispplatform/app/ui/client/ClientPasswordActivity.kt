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

class ClientPasswordActivity : AppCompatActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        title = "Change Password"
        val current = TextInputEditText(this).apply { hint = "Current password" }
        val newPass = TextInputEditText(this).apply { hint = "New password" }
        val confirm = TextInputEditText(this).apply { hint = "Confirm password" }
        val btn = MaterialButton(this).apply { text = "Update" }
        val layout = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(32, 32, 32, 32)
            addView(current)
            addView(newPass)
            addView(confirm)
            addView(btn)
        }
        setContentView(layout)
        btn.setOnClickListener {
            val n = newPass.text?.toString().orEmpty()
            if (n != confirm.text?.toString()) {
                toast("Passwords do not match")
                return@setOnClickListener
            }
            lifecycleScope.launch {
                when (val res = ClientRepository().changePassword(current.text?.toString().orEmpty(), n)) {
                    is Resource.Success -> {
                        toast("Password updated")
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
