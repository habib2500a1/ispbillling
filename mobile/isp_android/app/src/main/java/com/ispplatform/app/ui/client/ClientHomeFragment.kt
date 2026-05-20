package com.ispplatform.app.ui.client

import android.content.Intent
import android.graphics.Color
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.core.content.ContextCompat
import androidx.lifecycle.lifecycleScope
import com.github.mikephil.charting.data.Entry
import com.github.mikephil.charting.data.LineData
import com.github.mikephil.charting.data.LineDataSet
import com.google.gson.JsonObject
import com.ispplatform.app.R
import com.ispplatform.app.data.repository.ClientRepository
import com.ispplatform.app.databinding.FragmentClientHomeBinding
import com.ispplatform.app.ui.common.BaseFragment
import com.ispplatform.app.util.Formatters
import com.ispplatform.app.util.Resource
import kotlinx.coroutines.launch

class ClientHomeFragment : BaseFragment() {
    private var _binding: FragmentClientHomeBinding? = null
    private val binding get() = _binding!!
    private val repo = ClientRepository()

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentClientHomeBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        binding.btnPay.setOnClickListener { startActivity(Intent(requireContext(), ClientPayActivity::class.java)) }
        binding.btnPackage.setOnClickListener { startActivity(Intent(requireContext(), ClientPackageActivity::class.java)) }
        binding.btnPassword.setOnClickListener { startActivity(Intent(requireContext(), ClientPasswordActivity::class.java)) }
        binding.btnTicket.setOnClickListener { startActivity(Intent(requireContext(), ClientTicketActivity::class.java)) }
        binding.btnLogout.setOnClickListener { (requireActivity() as ClientMainActivity).logout() }
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
            when (val dash = repo.dashboard()) {
                is Resource.Success -> bindDashboard(dash.data)
                is Resource.Error -> {
                    handleUnauthorized(dash.code)
                    toast(dash.message)
                }
                else -> Unit
            }
            when (val usage = repo.usage()) {
                is Resource.Success -> bindUsage(usage.data)
                is Resource.Error -> handleUnauthorized(usage.code)
                else -> Unit
            }
            binding.swipe.isRefreshing = false
        }
    }

    private fun bindDashboard(data: JsonObject) {
        val customer = data.getAsJsonObject("customer")
        val summary = data.getAsJsonObject("summary")
        binding.txtName.text = customer?.get("name")?.asString ?: "Client"
        binding.txtCode.text = "Code: ${customer?.get("customer_code")?.asString ?: "—"}"
        val connected = summary?.get("status")?.asString == "Connected"
        binding.txtStatus.text = "Status: ${summary?.get("status")?.asString ?: "—"}"
        binding.txtStatus.setTextColor(
            ContextCompat.getColor(requireContext(), if (connected) R.color.success else R.color.danger),
        )
        val monthly = summary?.get("monthly_bill")?.asDouble ?: 0.0
        val paid = summary?.get("paid")?.asDouble ?: 0.0
        binding.txtMonthlyBill.text = "Monthly Bill: ${Formatters.money(monthly)} BDT"
        binding.txtPaid.text = "Paid: ${Formatters.money(paid)} BDT"
        binding.txtPackage.text = "Package: ${summary?.get("package_name")?.asString ?: "—"}"
        binding.txtExpire.text = "Expire: ${summary?.get("expire_date")?.asString ?: "—"}"
    }

    private fun bindUsage(data: JsonObject) {
        val usage = data.getAsJsonObject("usage")
        val down = usage?.get("download_human")?.asString ?: "—"
        val up = usage?.get("upload_human")?.asString ?: "—"
        binding.txtSpeeds.text = "Download: $down · Upload: $up"
        val chart = usage?.getAsJsonObject("chart")
        val dl = chart?.getAsJsonArray("download_mbps")
        if (dl != null && dl.size() > 0) {
            val entries = mutableListOf<Entry>()
            for (i in 0 until dl.size()) {
                entries.add(Entry(i.toFloat(), dl[i].asFloat))
            }
            val set = LineDataSet(entries, "Download Mbps").apply {
                color = Color.parseColor("#3A5A8C")
                setCircleColor(Color.parseColor("#3A5A8C"))
                lineWidth = 2f
            }
            binding.usageChart.data = LineData(set)
            binding.usageChart.invalidate()
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
