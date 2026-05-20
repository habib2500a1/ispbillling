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

class AdminCollectionFragment : BaseFragment() {
    private var _binding: FragmentListRefreshBinding? = null
    private val binding get() = _binding!!

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentListRefreshBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        binding.swipe.setOnRefreshListener {
            startActivity(Intent(requireContext(), AdminBillReceiveActivity::class.java))
            binding.swipe.isRefreshing = false
        }
        load()
    }

    private fun load() {
        viewLifecycleOwner.lifecycleScope.launch {
            when (val res = AdminRepository().wallet()) {
                is Resource.Success -> {
                    val bal = res.data.getAsJsonObject("data")?.get("balance")?.asDouble
                        ?: res.data.get("balance")?.asDouble ?: 0.0
                    toast("Wallet: ${Formatters.money(bal)} BDT — pull to open Bill Receive")
                }
                is Resource.Error -> toast(res.message)
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
