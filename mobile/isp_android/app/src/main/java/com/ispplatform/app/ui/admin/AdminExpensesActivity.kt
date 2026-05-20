package com.ispplatform.app.ui.admin

import android.os.Bundle
import android.widget.LinearLayout
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.google.android.material.button.MaterialButton
import com.google.android.material.textfield.TextInputEditText
import com.ispplatform.app.data.repository.AdminRepository
import com.ispplatform.app.util.Resource
import kotlinx.coroutines.launch

class AdminExpensesActivity : AppCompatActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        title = "Expense"
        val amount = TextInputEditText(this).apply { hint = "Amount" }
        val note = TextInputEditText(this).apply { hint = "Note" }
        val btn = MaterialButton(this).apply { text = "Add expense" }
        setContentView(LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(32, 32, 32, 32)
            addView(amount)
            addView(note)
            addView(btn)
        })
        btn.setOnClickListener {
            lifecycleScope.launch {
                when (val res = AdminRepository().addExpense(amount.text?.toString()?.toDoubleOrNull() ?: 0.0, note.text?.toString().orEmpty())) {
                    is Resource.Success -> {
                        toast("Expense recorded")
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
