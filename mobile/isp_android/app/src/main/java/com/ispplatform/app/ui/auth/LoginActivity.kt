package com.ispplatform.app.ui.auth

import android.content.Context
import android.content.Intent
import android.os.Bundle
import androidx.activity.viewModels
import androidx.appcompat.app.AppCompatActivity
import com.ispplatform.app.IspApplication
import com.ispplatform.app.data.local.SessionManager
import com.ispplatform.app.databinding.ActivityLoginBinding
import com.ispplatform.app.ui.admin.AdminMainActivity
import com.ispplatform.app.ui.client.ClientMainActivity
import com.ispplatform.app.util.NetworkUtils
import com.ispplatform.app.util.Resource

class LoginActivity : AppCompatActivity() {
    private lateinit var binding: ActivityLoginBinding
    private val viewModel: LoginViewModel by viewModels()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        val session = (application as IspApplication).sessionManager
        if (session.isLoggedIn()) {
            route(session.userType!!)
            return
        }
        binding = ActivityLoginBinding.inflate(layoutInflater)
        setContentView(binding.root)

        binding.btnLogin.setOnClickListener { attemptLogin() }

        viewModel.result.observe(this) { res ->
            when (res) {
                is Resource.Loading -> binding.btnLogin.isEnabled = false
                is Resource.Success -> {
                    binding.btnLogin.isEnabled = true
                    route(res.data)
                }
                is Resource.Error -> {
                    binding.btnLogin.isEnabled = true
                    toast(res.message)
                }
            }
        }
    }

    private fun attemptLogin() {
        val id = binding.inputLogin.text?.toString()?.trim().orEmpty()
        val pass = binding.inputPassword.text?.toString().orEmpty()
        if (id.isEmpty() || pass.isEmpty()) {
            toast("Enter login and password")
            return
        }
        if (!NetworkUtils.isOnline(this)) {
            toast(getString(com.ispplatform.app.R.string.no_internet))
            return
        }
        viewModel.login(id, pass)
    }

    private fun route(userType: String) {
        val intent = when (userType) {
            SessionManager.TYPE_ADMIN -> Intent(this, AdminMainActivity::class.java)
            else -> Intent(this, ClientMainActivity::class.java)
        }
        startActivity(intent)
        finish()
    }

    private fun toast(msg: String) {
        android.widget.Toast.makeText(this, msg, android.widget.Toast.LENGTH_SHORT).show()
    }

    companion object {
        fun startFresh(context: Context) {
            val i = Intent(context, LoginActivity::class.java)
            i.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
            context.startActivity(i)
        }
    }
}
