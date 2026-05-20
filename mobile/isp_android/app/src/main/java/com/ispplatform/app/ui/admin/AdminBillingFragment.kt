package com.ispplatform.app.ui.admin

import android.content.Intent
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import androidx.lifecycle.lifecycleScope
import com.ispplatform.app.data.repository.AdminRepository
import com.ispplatform.app.databinding.FragmentListRefreshBinding
import com.ispplatform.app.ui.common.BaseFragment
import com.ispplatform.app.util.Formatters
import com.ispplatform.app.util.Resource
import kotlinx.coroutines.launch

class AdminBillingFragment : BaseFragment() {
    private var _binding: FragmentListRefreshBinding? = null
    private val binding get() = _binding!!

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentListRefreshBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        binding.swipe.setOnRefreshListener { load() }
        load()
    }

    private fun load() {
        viewLifecycleOwner.lifecycleScope.launch {
            when (val res = AdminRepository().dashboard()) {
                is Resource.Success -> {
                    val b = res.data.getAsJsonObject("billing")
                    toast("Monthly ${Formatters.money(b?.get("monthly_bill")?.asDouble ?: 0.0)} · Due ${Formatters.money(b?.get("due")?.asDouble ?: 0.0)}")
                }
                is Resource.Error -> handleUnauthorized(res.code)
                else -> Unit
            }
            binding.swipe.isRefreshing = false
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
