import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../theme/app_theme.dart';

/// Gradient profile strip under blue app bar (reference Bill & collect screen).
class CustomerProfileHero extends StatelessWidget {
  const CustomerProfileHero({
    super.key,
    required this.name,
    required this.customerCode,
    required this.phone,
    required this.packageName,
    required this.balanceDue,
    required this.isOnline,
  });

  final String name;
  final String customerCode;
  final String phone;
  final String packageName;
  final double balanceDue;
  final bool isOnline;

  @override
  Widget build(BuildContext context) {
    final fmt = NumberFormat('#,##0.00');
    final initial = name.isNotEmpty ? name[0].toUpperCase() : '?';
    final due = balanceDue;
    final hasDue = due > 0.009;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.centerLeft,
          end: Alignment.centerRight,
          colors: [AppTheme.primary, AppTheme.purple, AppTheme.pink],
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CircleAvatar(
            radius: 28,
            backgroundColor: Colors.white.withValues(alpha: 0.25),
            child: Text(initial, style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.bold)),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '$customerCode · $phone',
                  style: const TextStyle(color: Colors.white70, fontSize: 12),
                ),
                const SizedBox(height: 4),
                Text(
                  packageName,
                  style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 6,
                  children: [
                    _pill(isOnline ? 'Online' : 'Offline', isOnline ? AppTheme.success : Colors.white38),
                    _pill('Due ${fmt.format(due)}', hasDue ? AppTheme.warning : AppTheme.success),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _pill(String label, Color bg) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: bg.withValues(alpha: 0.9),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(label, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600)),
    );
  }
}
