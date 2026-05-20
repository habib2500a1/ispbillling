package com.ispplatform.app.ui.client

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.google.gson.JsonArray
import com.ispplatform.app.data.repository.ClientRepository
import com.ispplatform.app.databinding.FragmentListRefreshBinding
import com.ispplatform.app.ui.common.BaseFragment
import com.ispplatform.app.util.Formatters
import com.ispplatform.app.util.Resource
import kotlinx.coroutines.launch

class ClientPaymentsFragment : BaseFragment() {
    private var _binding: FragmentListRefreshBinding? = null
    private val binding get() = _binding!!
    private val repo = ClientRepository()
    private val adapter = PaymentAdapter()

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentListRefreshBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        binding.recycler.layoutManager = LinearLayoutManager(requireContext())
        binding.recycler.adapter = adapter
        binding.swipe.setOnRefreshListener { load() }
        load()
    }

    private fun load() {
        if (!checkNetwork()) {
            binding.swipe.isRefreshing = false
            return
        }
        binding.swipe.isRefreshing = true
        viewLifecycleOwner.lifecycleScope.launch {
            when (val res = repo.bills()) {
                is Resource.Success -> {
                    val data = res.data.getAsJsonArray("data") ?: JsonArray()
                    adapter.submit(data)
                }
                is Resource.Error -> {
                    handleUnauthorized(res.code)
                    toast(res.message)
                }
                else -> Unit
            }
            binding.swipe.isRefreshing = false
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }

    private class PaymentAdapter : RecyclerView.Adapter<PaymentAdapter.VH>() {
        private var items = JsonArray()

        fun submit(data: JsonArray) {
            items = data
            notifyDataSetChanged()
        }

        override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): VH {
            val tv = TextView(parent.context)
            tv.setPadding(32, 24, 32, 24)
            return VH(tv)
        }

        override fun onBindViewHolder(holder: VH, position: Int) {
            val item = items[position].asJsonObject
            val num = item.get("invoice_number")?.asString ?: ""
            val due = item.get("balance_due")?.asDouble ?: 0.0
            val status = item.get("status")?.asString ?: ""
            val date = Formatters.date(item.get("issue_date")?.asString)
            holder.text.text = "$num · ${Formatters.money(due)} BDT · $status · $date"
        }

        override fun getItemCount(): Int = items.size()

        class VH(val text: TextView) : RecyclerView.ViewHolder(text)
    }
}
