import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';

import '../theme/app_theme.dart';

/// Staff / NOC live chart — download + upload Mbps per second.
class LiveBandwidthChart extends StatelessWidget {
  const LiveBandwidthChart({super.key, required this.chart, this.height = 140});

  final Map<String, dynamic>? chart;
  final double height;

  @override
  Widget build(BuildContext context) {
    final download = (chart?['download_mbps'] as List<dynamic>?)?.map((e) => (e as num).toDouble()).toList() ?? [];
    final upload = (chart?['upload_mbps'] as List<dynamic>?)?.map((e) => (e as num).toDouble()).toList() ?? [];

    if (download.isEmpty) {
      return SizedBox(
        height: height,
        child: const Center(child: Text('Collecting per-second data…', style: TextStyle(color: Colors.grey))),
      );
    }

    final downSpots = List.generate(download.length, (i) => FlSpot(i.toDouble(), download[i]));
    final upSpots = upload.length == download.length
        ? List.generate(upload.length, (i) => FlSpot(i.toDouble(), upload[i]))
        : <FlSpot>[];
    final maxY = [...download, ...upload].fold<double>(0, (a, b) => a > b ? a : b) + 0.5;

    return SizedBox(
      height: height,
      child: LineChart(
        LineChartData(
          minY: 0,
          maxY: maxY < 0.1 ? 1 : maxY,
          gridData: const FlGridData(show: false),
          titlesData: const FlTitlesData(show: false),
          borderData: FlBorderData(show: false),
          lineBarsData: [
            LineChartBarData(
              spots: downSpots,
              isCurved: true,
              color: AppTheme.accent,
              barWidth: 3,
              dotData: const FlDotData(show: false),
              belowBarData: BarAreaData(show: true, color: AppTheme.accent.withValues(alpha: 0.12)),
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
    );
  }
}
