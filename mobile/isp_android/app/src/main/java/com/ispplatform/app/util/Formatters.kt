package com.ispplatform.app.util

import java.text.DecimalFormat
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

object Formatters {
    private val money = DecimalFormat("#,##0.00")
    private val dateOut = SimpleDateFormat("dd-MMM-yyyy", Locale.ENGLISH)

    fun money(value: Double): String = money.format(value)

    fun date(iso: String?): String {
        if (iso.isNullOrBlank()) return "—"
        return try {
            val parsed = SimpleDateFormat("yyyy-MM-dd", Locale.US).parse(iso.take(10))
            if (parsed != null) dateOut.format(parsed) else iso
        } catch (_: Exception) {
            iso
        }
    }

    fun uptime(seconds: Long): String {
        val h = seconds / 3600
        val m = (seconds % 3600) / 60
        val s = seconds % 60
        return String.format(Locale.US, "%02d:%02d:%02d", h, m, s)
    }
}
