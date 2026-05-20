package com.ispplatform.app.ui.admin

import android.content.Intent
import android.graphics.Color
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.Button
import androidx.lifecycle.lifecycleScope
import com.github.mikephil.charting.components.XAxis
import com.github.mikephil.charting.data.BarData
import com.github.mikephil.charting.data.BarDataSet
import com.github.mikephil.charting.data.BarEntry
import com.google.gson.JsonArray
import com.ispplatform.app.data.repository.AdminRepository
import com.ispplatform.app.databinding.FragmentAdminHomeBinding
import com.ispplatform.app.ui.common.BaseFragment
import com.ispplatform.app.util.Formatters
import com.ispplatform.app.util.Resource
import kotlinx.coroutines.launch

class AdminHomeFragment : BaseFragment() {
    private var _binding: FragmentAdminHomeBinding? = null
    private val binding get() = _binding!!
    private val repo = AdminRepository()

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentAdminHomeBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
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
            when (val res = repo.dashboard()) {
                is Resource.Success -> bind(res.data)
                is Resource.Error -> {
                    handleUnauthorized(res.code)
                    toast(res.message)
                }
                else -> Unit
            }
            binding.swipe.isRefreshing = false
        }
    }

    private fun bind(data: com.google.gson.JsonObject) {
        val user = data.getAsJsonObject("user")
        binding.txtAdminName.text = user?.get("name")?.asString ?: "Admin"
        binding.txtUserType.text = "User Type: ${user?.get("user_type")?.asString ?: "Admin"}"
        val billing = data.getAsJsonObject("billing")
        binding.txtMonthly.text = "Monthly Bill: ${Formatters.money(billing?.get("monthly_bill")?.asDouble ?: 0.0)}"
        binding.txtCollected.text = "Collected: ${Formatters.money(billing?.get("collected_bill")?.asDouble ?: 0.0)}"
        binding.txtDue.text = "Due: ${Formatters.money(billing?.get("due")?.asDouble ?: 0.0)}"
        binding.txtDiscount.text = "Discount: ${Formatters.money(billing?.get("discount")?.asDouble ?: 0.0)}"

        binding.quickActions.removeAllViews()
        val actions = listOf(
            "Bill Receive" to AdminBillReceiveActivity::class.java,
            "Monitoring" to AdminMonitoringActivity::class.java,
            "Clients" to AdminClientsActivity::class.java,
            "Expense" to AdminExpensesActivity::class.java,
        )
        actions.forEach { (label, cls) ->
            val btn = Button(requireContext()).apply {
                text = label
                setOnClickListener { startActivity(Intent(requireContext(), cls)) }
            }
            binding.quickActions.addView(btn)
        }

        val chart = data.getAsJsonArray("zone_collection_chart") ?: JsonArray()
        val paidEntries = mutableListOf<BarEntry>()
        val unpaidEntries = mutableListOf<BarEntry>()
        val labels = mutableListOf<String>()
        for (i in 0 until chart.size()) {
            val row = chart[i].asJsonObject
            labels.add(row.get("zone")?.asString ?: "")
            paidEntries.add(BarEntry(i.toFloat(), row.get("paid")?.asFloat ?: 0f))
            unpaidEntries.add(BarEntry(i.toFloat(), row.get("unpaid")?.asFloat ?: 0f))
        }
        val paidSet = BarDataSet(paidEntries, "Paid").apply { color = Color.parseColor("#22D3EE") }
        val unpaidSet = BarDataSet(unpaidEntries, "Unpaid").apply { color = Color.parseColor("#F472B6") }
        binding.zoneChart.data = BarData(paidSet, unpaidSet)
        binding.zoneChart.xAxis.position = XAxis.XAxisPosition.BOTTOM
        binding.zoneChart.description.isEnabled = false
        binding.zoneChart.invalidate()
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
