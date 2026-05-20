package com.ispplatform.app.ui.client

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import androidx.lifecycle.lifecycleScope
import com.ispplatform.app.databinding.FragmentClientPingBinding
import com.ispplatform.app.ui.common.BaseFragment
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.net.InetAddress

class ClientPingFragment : BaseFragment() {
    private var _binding: FragmentClientPingBinding? = null
    private val binding get() = _binding!!

    override fun onCreateView(inflater: LayoutInflater, container: ViewGroup?, savedInstanceState: Bundle?): View {
        _binding = FragmentClientPingBinding.inflate(inflater, container, false)
        return binding.root
    }

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        super.onViewCreated(view, savedInstanceState)
        binding.inputHost.setText("8.8.8.8")
        binding.btnPing.setOnClickListener { runPing() }
    }

    private fun runPing() {
        val host = binding.inputHost.text?.toString()?.trim().orEmpty()
        if (host.isEmpty()) return
        binding.listResults.text = "Pinging $host...\n"
        viewLifecycleOwner.lifecycleScope.launch {
            val lines = withContext(Dispatchers.IO) {
                val out = StringBuilder()
                repeat(4) { i ->
                    val start = System.currentTimeMillis()
                    val ok = try {
                        InetAddress.getByName(host).isReachable(2000)
                    } catch (_: Exception) {
                        false
                    }
                    val ms = System.currentTimeMillis() - start
                    out.append("Reply ${i + 1}: ${if (ok) "${ms}ms" else "timeout"}\n")
                }
                out.toString()
            }
            binding.listResults.append(lines)
        }
    }

    override fun onDestroyView() {
        super.onDestroyView()
        _binding = null
    }
}
