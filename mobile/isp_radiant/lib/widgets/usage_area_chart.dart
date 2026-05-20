import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

class UsageAreaChart extends StatelessWidget {
  const UsageAreaChart({super.key, required this.chart});

  final Map<String, dynamic>? chart;

  @override
  Widget build(BuildContext context) {
    final labels = (chart?['labels'] as List<dynamic>?) ?? [];
    final download = (chart?['download_mbps'] as List<dynamic>?)?.map((e) => (e as num).toDouble()).toList() ?? [];

    if (labels.isEmpty || download.isEmpty) {
      return const SizedBox(
        height: 140,
        child: Center(child: Text('Usage chart loading…', style: TextStyle(color: Colors.grey))),
      );
    }

    final spots = List.generate(download.length, (i) => FlSpot(i.toDouble(), download[i]));

    return SizedBox(
      height: 160,
      child: LineChart(
        LineChartData(
          gridData: const FlGridData(show: true, drawVerticalLine: true),
          titlesData: const FlTitlesData(show: false),
          borderData: FlBorderData(show: false),
          lineBarsData: [
            LineChartBarData(
              spots: spots,
              isCurved: true,
              color: AppTheme.accent,
              barWidth: 2.5,
              belowBarData: BarAreaData(
                show: true,
                color: AppTheme.accent.withValues(alpha: 0.25),
              ),
              dotData: const FlDotData(show: false),
            ),
          ],
        ),
      ),
    );
  }
}
