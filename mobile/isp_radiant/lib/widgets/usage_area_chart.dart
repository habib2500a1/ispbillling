import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

class UsageAreaChart extends StatelessWidget {
  const UsageAreaChart({super.key, required this.chart, this.title = 'Mbps per second'});

  final Map<String, dynamic>? chart;
  final String title;

  @override
  Widget build(BuildContext context) {
    final labels = (chart?['labels'] as List<dynamic>?) ?? [];
    final download = (chart?['download_mbps'] as List<dynamic>?)?.map((e) => (e as num).toDouble()).toList() ?? [];
    final upload = (chart?['upload_mbps'] as List<dynamic>?)?.map((e) => (e as num).toDouble()).toList() ?? [];

    if (labels.isEmpty || download.isEmpty) {
      return SizedBox(
        height: 140,
        child: Center(
          child: Text(
            'Live graph (per second) — connect to see traffic',
            style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
            textAlign: TextAlign.center,
          ),
        ),
      );
    }

    final downSpots = List.generate(download.length, (i) => FlSpot(i.toDouble(), download[i]));
    final upSpots = upload.length == download.length
        ? List.generate(upload.length, (i) => FlSpot(i.toDouble(), upload[i]))
        : <FlSpot>[];

    final maxY = [...download, ...upload].fold<double>(0, (a, b) => a > b ? a : b) + 0.5;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text(title, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
            const Spacer(),
            if (upSpots.isNotEmpty) ...[
              _legend('↓', AppTheme.accent),
              const SizedBox(width: 8),
              _legend('↑', AppTheme.primary),
            ],
          ],
        ),
        const SizedBox(height: 6),
        SizedBox(
          height: 160,
          child: LineChart(
            LineChartData(
              minY: 0,
              maxY: maxY < 0.1 ? 1 : maxY,
              gridData: const FlGridData(show: true, drawVerticalLine: false),
              titlesData: const FlTitlesData(show: false),
              borderData: FlBorderData(show: false),
              lineBarsData: [
                LineChartBarData(
                  spots: downSpots,
                  isCurved: true,
                  color: AppTheme.accent,
                  barWidth: 2.5,
                  belowBarData: BarAreaData(show: true, color: AppTheme.accent.withValues(alpha: 0.2)),
                  dotData: const FlDotData(show: false),
                ),
                if (upSpots.isNotEmpty)
                  LineChartBarData(
                    spots: upSpots,
                    isCurved: true,
                    color: AppTheme.primary,
                    barWidth: 2,
                    dotData: const FlDotData(show: false),
                  ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _legend(String label, Color color) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(width: 10, height: 10, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
        const SizedBox(width: 4),
        Text(label, style: TextStyle(fontSize: 11, color: color, fontWeight: FontWeight.bold)),
      ],
    );
  }
}
