package com.ispplatform.app.ui.admin

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.TextView
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.google.gson.JsonArray
import com.ispplatform.app.data.repository.AdminRepository
import com.ispplatform.app.databinding.FragmentListRefreshBinding
import com.ispplatform.app.ui.common.BaseFragment
import com.ispplatform.app.util.Resource
import kotlinx.coroutines.launch

class AdminSupportFragment : BaseFragment() {
    private var _binding: FragmentListRefreshBinding? = null
    private val binding get() = _binding!!
    private val adapter = TicketAdapter()

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
        viewLifecycleOwner.lifecycleScope.launch {
            when (val res = AdminRepository().tickets()) {
                is Resource.Success -> adapter.submit(res.data.getAsJsonArray("data") ?: JsonArray())
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

    private class TicketAdapter : RecyclerView.Adapter<TicketAdapter.VH>() {
        private var items = JsonArray()
        fun submit(data: JsonArray) {
            items = data
            notifyDataSetChanged()
        }
        override fun onCreateViewHolder(parent: ViewGroup, viewType: Int) =
            VH(TextView(parent.context).apply { setPadding(32, 20, 32, 20) })
        override fun onBindViewHolder(holder: VH, position: Int) {
            val t = items[position].asJsonObject
            holder.text.text = "${t.get("ticket_number")?.asString} · ${t.get("subject")?.asString} · ${t.get("status")?.asString}"
        }
        override fun getItemCount() = items.size()
        class VH(val text: TextView) : RecyclerView.ViewHolder(text)
    }
}
