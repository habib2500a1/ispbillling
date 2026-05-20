package com.ispplatform.app.ui.client

import android.content.Intent
import android.os.Bundle
import androidx.appcompat.app.AppCompatActivity
import androidx.navigation.fragment.NavHostFragment
import androidx.navigation.ui.setupWithNavController
import com.ispplatform.app.IspApplication
import com.ispplatform.app.R
import com.ispplatform.app.databinding.ActivityMainBinding
import com.ispplatform.app.ui.auth.LoginActivity
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch

class ClientMainActivity : AppCompatActivity() {
    private lateinit var binding: ActivityMainBinding

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        val navHost = supportFragmentManager.findFragmentById(R.id.nav_host) as NavHostFragment
        val navController = navHost.navController
        navController.setGraph(R.navigation.nav_client)
        binding.bottomNav.menu.clear()
        binding.bottomNav.inflateMenu(R.menu.menu_client_nav)
        binding.bottomNav.setupWithNavController(navController)
    }

    fun logout() {
        CoroutineScope(Dispatchers.Main).launch {
            com.ispplatform.app.data.repository.AuthRepository().logout()
            LoginActivity.startFresh(this@ClientMainActivity)
        }
    }
}
