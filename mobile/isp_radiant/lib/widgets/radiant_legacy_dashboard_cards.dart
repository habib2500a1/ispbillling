import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../features/dashboard_staff/domain/staff_dashboard.dart';

/// White rounded cards for legacy SOFTIFY staff dashboard.
class RadiantLegacyDashboardCards {
  RadiantLegacyDashboardCards._();

  static final _fmt = NumberFormat('#,##0.00');

  static Widget billingSummary(StaffBilling billing) {
    return _card(
      child: Row(
        children: [
          Expanded(child: _moneyTile('Monthly Bill', billing.monthlyBill, const Color(0xFF5C6BC0))),
          Expanded(child: _moneyTile('Collected Bill', billing.collected, const Color(0xFF26A69A))),
          Expanded(child: _moneyTile('Due', billing.due, const Color(0xFFFF7043))),
          Expanded(child: _moneyTile('Discount', billing.discount, const Color(0xFFAB47BC))),
        ],
      ),
    );
  }

  static Widget ticketTaskRow({
    required CountStat tickets,
    required CountStat tasks,
    bool showTickets = true,
  }) {
    if (!showTickets) {
      return _statusCard('Task', tasks);
    }

    return Row(
      children: [
        Expanded(child: _statusCard('Ticket', tickets)),
        const SizedBox(width: 10),
        Expanded(child: _statusCard('Task', tasks)),
      ],
    );
  }

  static Widget zoneChart(List<ZoneRow> rows) {
    if (rows.isEmpty) return const SizedBox.shrink();

    final maxY = rows.fold<double>(0, (a, r) {
      final peak = r.paid > r.unpaid ? r.paid : r.unpaid;
      return peak > a ? peak : a;
    }) * 1.25 + 1;

    return _card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _legend('Unpaid', const Color(0xFFF06292)),
              const SizedBox(width: 14),
              _legend('Paid', const Color(0xFF4DD0E1)),
            ],
          ),
          const SizedBox(height: 12),
          SizedBox(
            height: 210,
            child: BarChart(
              BarChartData(
                maxY: maxY,
                groupsSpace: 18,
                barGroups: List.generate(rows.length, (i) {
                  final row = rows[i];
                  return BarChartGroupData(
                    x: i,
                    barRods: [
                      BarChartRodData(toY: row.unpaid, color: const Color(0xFFF06292), width: 14, borderRadius: BorderRadius.zero),
                      BarChartRodData(toY: row.paid, color: const Color(0xFF4DD0E1), width: 14, borderRadius: BorderRadius.zero),
                    ],
                    showingTooltipIndicators: [0, 1],
                  );
                }),
                barTouchData: BarTouchData(enabled: false),
                titlesData: FlTitlesData(
                  topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                  rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                  leftTitles: AxisTitles(
                    sideTitles: SideTitles(
                      showTitles: true,
                      reservedSize: 28,
                      getTitlesWidget: (v, _) => Text(
                        v.toInt().toString(),
                        style: const TextStyle(fontSize: 10, color: Color(0xFF90A4AE)),
                      ),
                    ),
                  ),
                  bottomTitles: AxisTitles(
                    sideTitles: SideTitles(
                      showTitles: true,
                      getTitlesWidget: (v, _) {
                        final i = v.toInt();
                        if (i < 0 || i >= rows.length) return const SizedBox.shrink();
                        return Padding(
                          padding: const EdgeInsets.only(top: 6),
                          child: Text(
                            rows[i].zone,
                            style: const TextStyle(fontSize: 9, color: Color(0xFF607D8B)),
                          ),
                        );
                      },
                    ),
                  ),
                ),
                gridData: FlGridData(
                  show: true,
                  drawVerticalLine: false,
                  horizontalInterval: maxY / 4,
                  getDrawingHorizontalLine: (_) => const FlLine(color: Color(0xFFECEFF1), strokeWidth: 1),
                ),
                borderData: FlBorderData(show: false),
              ),
            ),
          ),
        ],
      ),
    );
  }

  static Widget resellerFinance(FinanceSummary finance, StaffBilling billing) {
    return Column(
      children: [
        _card(
          child: Column(
            children: [
              Row(
                children: [
                  Expanded(child: _plainTile('MacReseller Bill', finance.resellerSettledMonth)),
                  Expanded(child: _plainTile('Bandwidth Reseller', 0)),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(child: _plainTile('MacReseller Fund', finance.resellerWallet)),
                  Expanded(child: _plainTile('Received amount', billing.collected)),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 10),
        _card(
          child: Column(
            children: [
              Row(
                children: [
                  Expanded(child: _plainTile('Paid Salary', finance.paidSalaryMonth)),
                  Expanded(child: _plainTile('Installation Charge', 0)),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(child: _plainTile('Service Income', finance.collectedMonth)),
                  Expanded(child: _plainTile('Expense', finance.expenseMonth)),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }

  static Widget cashOnHand(double amount) {
    return Container(
      margin: const EdgeInsets.only(top: 4),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(8),
        boxShadow: const [
          BoxShadow(color: Color(0x14000000), blurRadius: 6, offset: Offset(0, 2)),
        ],
      ),
      child: Row(
        children: [
          const Icon(Icons.savings_outlined, color: Color(0xFF5C6BC0), size: 28),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              'Cash On Hand ${_fmt.format(amount)}',
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: Color(0xFF37474F)),
            ),
          ),
        ],
      ),
    );
  }

  static Widget _card({required Widget child}) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(8),
        boxShadow: const [
          BoxShadow(color: Color(0x14000000), blurRadius: 6, offset: Offset(0, 2)),
        ],
      ),
      child: child,
    );
  }

  static Widget _moneyTile(String label, double value, Color color) {
    return Column(
      children: [
        Icon(Icons.account_balance_wallet_outlined, color: color, size: 22),
        const SizedBox(height: 6),
        FittedBox(
          fit: BoxFit.scaleDown,
          child: Text(
            _fmt.format(value),
            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: Color(0xFF37474F)),
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          textAlign: TextAlign.center,
          style: const TextStyle(fontSize: 10, color: Color(0xFF78909C)),
        ),
      ],
    );
  }

  static Widget _plainTile(String label, double value) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(fontSize: 11, color: Color(0xFF78909C))),
        const SizedBox(height: 4),
        Text(
          _fmt.format(value),
          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: Color(0xFF37474F)),
        ),
      ],
    );
  }

  static Widget _statusCard(String title, CountStat stat) {
    final maxVal = stat.total == 0 ? 1 : stat.total;

    return _card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('${stat.total} $title', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
          const SizedBox(height: 10),
          Text('${stat.pending} Pending', style: const TextStyle(fontSize: 11, color: Color(0xFFFF7043))),
          const SizedBox(height: 4),
          _bar(stat.pending / maxVal, const Color(0xFFFF7043)),
          const SizedBox(height: 8),
          Text('${stat.process} Process', style: const TextStyle(fontSize: 11, color: Color(0xFF42A5F5))),
          const SizedBox(height: 4),
          _bar(stat.process / maxVal, const Color(0xFF42A5F5)),
        ],
      ),
    );
  }

  static Widget _bar(double value, Color color) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(3),
      child: LinearProgressIndicator(
        value: value.clamp(0.0, 1.0),
        minHeight: 7,
        backgroundColor: const Color(0xFFECEFF1),
        color: color,
      ),
    );
  }

  static Widget _legend(String label, Color color) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(width: 12, height: 12, color: color),
        const SizedBox(width: 6),
        Text(label, style: const TextStyle(fontSize: 11, color: Color(0xFF607D8B))),
      ],
    );
  }
}
